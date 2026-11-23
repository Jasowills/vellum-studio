# Vellum — engineering report

**Date:** 23 November 2026
**Repository:** <https://github.com/Jasowills/vellum-studio>
**Live demo:** <https://charleston-wealth-pie-ideal.trycloudflare.com>

---

## 1. Executive summary

Vellum is a complete Laravel 13 / Vue 3 / Inertia / TypeScript / Tailwind application integrated with the Pixelforge image API. It includes a public landing page, an authenticated studio with live pricing estimates, a library of archived plates, a per-user ledger, and an operator reconciliation report.

The main engineering constraint was **exact financial accounting**. All money is represented as integer nanodollars and all arithmetic uses `brick/math`; no float is used for pricing. Provider cost is computed from the rates and token counts Pixelforge actually reports, and the customer charge is that cost plus a flat, transparent 25% studio fee. Failed generations never create a charge.

The live ledger contains **six real Pixelforge generations**. Reconciliation against the Pixelforge usage report matches exactly:

```text
Provider generations ..................................................... 6
Local generations ........................................................ 6
Provider cost ................................................. $1.462355000
Local cost .................................................... $1.462355000
Difference .................................................... $0.000000000
```

The automated suite has **81 passing tests / 342 assertions**, with Pixelforge faked in every test. Pint, PHPStan level 7, Vue/TS checks, type checking, and the production build all pass; the GitHub Actions `tests` workflow is green on `main`. The product was then rebuilt from the brand outward as **SIGNAL**, a strict black-and-white system with Geist, Instrument Serif, Geist Mono, an original V mark, and a lazy Three.js signal object. Browser QA covered every main page at 1440, 1280, 768, and 390 px; the earlier axe-core pass found no WCAG A/AA violations, and the redesign pass verified monochrome rendering, exact fonts, no horizontal overflow, no broken images, no console errors, reduced-motion behavior, and WebGL lifecycle cleanup.

---

## 2. Source of truth and scope

The [Pixelforge API reference](https://pixelforge.promptedit.dev/docs) was treated as authoritative. No pricing fields, token rules, statuses, headers, or webhook semantics were invented.

| Pixelforge fact                                               | Vellum implementation                                                      |
| ------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Money is an exact decimal string; never a float               | Integer nanodollars + `brick/math`, 9-decimal output                       |
| Rates are USD per 1M tokens                                   | `rate × tokens × 1,000` nanodollars                                        |
| Rates are fixed per generation                                | `pricing_snapshot` and `estimate_snapshot` stored on the row               |
| Real cost is known only after completion                      | Charge created by `GenerationSynchronizer` on final usage                  |
| Failed generations are not billed                             | No charge row; UI states “failed work is free”                             |
| Webhooks are at-least-once, may be duplicated or out of order | Unique `provider_event_id`, monotonic state machine, idempotent settlement |
| Webhooks can arrive before the create response                | Local row is created first; sync uses the provider object as truth         |
| Delivery may fail                                             | Polling reconciler + bounded retry commands                                |
| `429`/`503` include `Retry-After`                             | Client parses and honours it with capped retries                           |
| Signed image URLs expire after 24h                            | Images archived to private disk and served through an authenticated route  |

---

## 3. Architecture

```text
Browser (Inertia/Vue)
  ├─ /                    Landing
  ├─ /studio              Composer + live estimate + recent plates
  ├─ /library             Searchable/filterable archive
  ├─ /generations/{id}    Plate detail, ledger, timeline
  ├─ /usage               User ledger + 30-day activity
  └─ /admin/report        Operator reconciliation (admin only)

Laravel
  ├─ Controllers           Request validation, authorization, Inertia props
  ├─ Policies/Middleware   Ownership, admin gate, security headers, rate limits
  ├─ Services/Pixelforge   HTTP client, DTO, pricing cache, estimator, usage, HMAC
  ├─ Services/Generations  Creation, state machine, synchronizer, archiver, webhook processor
  ├─ Jobs                  Submit, reconcile, archive, process webhook
  └─ Models                Generation, GenerationCharge, WebhookEvent

External
  └─ Pixelforge API        Generations, pricing, usage, signed webhooks, image URLs
```

### Key design decision: resolve API resources before passing them as Inertia props

During browser QA the library reported six plates but rendered no cards. The cause was not the query — it was Laravel JSON resource wrapping. `GenerationResource` passed directly as an Inertia prop is serialized as `{ "data": { ... } }`, so Vue received `generations.data[0].data` instead of `generations.data[0]`.

The fix was to call `->resolve()` when passing resources as props:

- `LibraryController`: `->through(fn ($g) => (new GenerationResource($g))->resolve())`
- `GenerationController`: `'generation' => (new GenerationResource($g))->resolve()`
- `StudioController`: map `recent` through `->resolve()`

A regression test now asserts the flat shape (`tests/Feature/LibraryTest.php`).

---

## 4. Money model and proof

### 4.1 Provider cost

For each token category:

```text
rate (USD per 1M tokens) × tokens × 1,000 = nanodollars
```

Each category contribution is computed as a decimal, summed, then rounded **once** to an integer. This avoids per-category rounding drift. Input validation rejects non-integer token counts, negative counts, and non-decimal-string rates.

### 4.2 Customer charge

```text
customer charge = round_half_up(provider cost × 125 / 100)
```

This is displayed everywhere as a 25% studio fee. The operator report additionally shows margin as a percentage of provider cost (20% of cost = 25% markup).

### 4.3 Live figures

| Measure                        |         Exact value |
| ------------------------------ | ------------------: |
| Provider cost                  |      `$1.462355000` |
| Customer charges               |      `$1.827943750` |
| Studio margin                  |      `$0.365588750` |
| Difference vs Pixelforge usage |      `$0.000000000` |
| Tokens billed                  |            `50,258` |
| Input text / image             |         `176` / `0` |
| Output / reasoning             | `30,380` / `19,702` |

Individual settled charges:

| Public ID                    |         Charge |
| ---------------------------- | -------------: |
| `01M84M979D3MYC5BRPHFK9P8X5` | `$0.018084375` |
| `01M84MAZQKJJBTF7RJDFY0C7W2` | `$0.349184375` |
| `01M84MAZS5VA0656T8R2DD44Y4` | `$0.399315625` |
| `01M84MAZSH1PSMQ3842RMQ33F4` | `$0.389215625` |
| `01M84MAZSVK1B5VVYWGJNWYSY0` | `$0.264787500` |
| `01M84MAZT385988N2C2NNDC59P` | `$0.407356250` |

Charge rows are immutable once written. Duplicate webhooks, repeated reconciliation, and late events never create a second charge.

---

## 5. Lifecycle, webhooks, and bounded retries

### 5.1 Local state machine

```text
pending → queued → processing → succeeded
                             ↘ failed
```

`GenerationStateMachine` rejects backward transitions. A late `generation.processing` webhook cannot undo a `succeeded` or `failed` generation.

### 5.2 Idempotent submission

- Local idempotency key: `vellum_u{userId}_{token}`.
- The same key is reused for retries.
- Pixelforge returns the original generation for the same key and parameters; Vellum resumes it instead of creating a duplicate.

### 5.3 Signed webhook verification

`WebhookController` verifies `Pixelforge-Signature` exactly as documented:

- Parse `t=<unix>,v1=<hex>`.
- Compute HMAC-SHA256 over `"{$t}.{$rawBody}"` with `PIXELFORGE_WEBHOOK_SECRET`.
- Compare with `hash_equals`.
- Reject timestamps outside a 300-second window.
- Only then decode JSON.

Verified events are stored with a unique `provider_event_id`, which makes repeated delivery harmless. Slow work is dispatched to `ProcessPixelforgeWebhookJob` so the endpoint responds quickly.

### 5.4 Live webhook evidence

A signed `generation.succeeded` event was sent to the public endpoint through the live tunnel:

```text
event id      evt_vellum_e2e_1795459063
first delivery  HTTP 204
webhook row     processed
charges before  6
charges after   6

duplicate delivery (same event id)  HTTP 204, still 1 event / 6 charges
invalid HMAC                        HTTP 400 "Invalid Pixelforge signature."
```

This demonstrated signature verification, queue processing, deduplication, and no double charge against the public URL.

### 5.5 Recovery paths

- `vellum:reconcile-generations` polls active generations every minute.
- `vellum:retry-webhooks` retries failed webhook jobs every minute.
- `pixelforge:reconcile-usage --days=7` compares provider and local ledgers hourly.
- `--fail-on-mismatch` is available as a deployment gate.
- Submission retries cap at 12 attempts / 30 minutes; reconciliation retries cap at 60 minutes with backoff.

---

## 6. Security and authorization

- All studio/library/generation/usage routes require `auth` and `verified` users.
- `GenerationPolicy` enforces ownership; the admin report requires the `admin` middleware.
- Webhook route is exempt from CSRF by design and protected by HMAC instead.
- Estimate, generation, image, webhook, and admin-report routes have dedicated throttle limits.
- Provider image download host is allowlisted via `PIXELFORGE_IMAGE_HOSTS`; only HTTPS URLs are accepted.
- Archived images live on the private local disk and are served only through `generations.image` after ownership is checked.
- `SecurityHeaders` middleware adds standard response headers.
- `.env`, API keys, webhook secrets, built assets, and vendor directories are excluded from git.

---

## 7. Frontend and accessibility

The product now uses the **SIGNAL** design system: near-black and white only, zero-radius geometry, Geist Sans for UI, Instrument Serif for display, Geist Mono for technical metadata, and a single white signal accent against black fields. The visual language is dimensional and generative rather than decorative: a custom geometric V glyph, a separate wordmark treatment, a lazy-loaded Three.js object with a static SVG fallback, and real artwork used as evidence rather than staged mockups.

The landing page follows a six-section narrative — ARRIVAL, FROM SIGNAL, THE WORK, THE STUDIO, TRANSPARENT CREATION, and ENTER — with a functional prompt. Unauthenticated submissions persist the prompt in short-lived `sessionStorage` through Fortify login; authenticated submissions go straight to `/studio` with the composer prefilled. No backend route or schema was changed for this behavior.

Responsive and state coverage:

- Landing: signal hero, working prompt, transformation sequence, five real Pixelforge plates, real studio screenshot, exact reconciliation figures, final CTA, minimal footer.
- Auth: login, register, forgot-password, 2FA challenge/verify, passkeys, and verification surfaces use the same monochrome field rhythm without changing Fortify behavior.
- Studio: minimal nav rail, image-first canvas, floating composer, progressive advanced settings, live estimate, honest pending/processing states, and reference-token invalidation on prefill/edit.
- Library and generation detail: status-label filters, image-first archive, active-only visibility-aware polling, terminal polling cleanup, robust image fallback, and admin-only provider gating.
- Usage and admin report: accessible per-day chart data, responsive stacked tables, honest empty states, reconciliation cockpit, and margin denominator corrected to `lifetime.provider_cost`.
- Settings: monochrome profile, password, 2FA, passkeys, and appearance surfaces; shared destructive/error/success components were also converted to grayscale rather than left with red/green leakage.

Browser QA was rerun after the redesign at **1440, 1280, 768, and 390 px** on the landing page, studio, library, generation detail, usage, admin report, profile settings, and appearance settings, plus auth pages at 1440/768/390. For every page/breakpoint pair checked:

| Check                               | Result                                    |
| ----------------------------------- | ----------------------------------------- |
| Horizontal document overflow        | none                                      |
| Broken images                       | none                                      |
| Console errors / 4xx responses      | none for the correct role and route       |
| Computed chromatic CSS colors       | 0                                         |
| Body / display / mono font families | Geist / Instrument Serif / Geist Mono     |
| Unlabelled visible form fields      | 0                                         |
| WebGL canvas on landing             | 1; removed on route change to `/studio`   |
| `prefers-reduced-motion: reduce`    | no armed reveals, `scroll-behavior: auto` |

Screenshot pixel histograms were also checked: all captured pages rendered at **100% grayscale**, with only neutral channel values. The earlier axe-core pass on the pre-redesign UI found no WCAG A/AA violations; keyboard focus, ARIA labels, skip links, and reduced-motion behavior were rechecked manually through DOM and browser automation after the redesign.

---

## 8. Automated verification

```bash
# Backend
docker run --rm --network vellum-net -v "$PWD":/app -w /app vellum-dev php artisan test --compact
# => Tests: 81 passed (342 assertions)

docker run --rm --network vellum-net -v "$PWD":/app -w /app vellum-dev ./vendor/bin/pint --test
# => PASS 121 files

docker run --rm --network vellum-net -v "$PWD":/app -w /app vellum-dev ./vendor/bin/phpstan analyse --no-progress --memory-limit=1G
# => [OK] No errors

# Frontend
npm run check          # formatting + lint: 86 formatted files, 78 linted files; all pass
npm run types:check    # vue-tsc --noEmit
npm run build          # production build
```

GitHub Actions runs the same checks through `composer ci:check` on every push. The `tests` workflow has been verified green on `main`, including run `45244875780`.

Tests deliberately use `https://pixelforge.test/v1` and HTTP fakes. `phpunit.xml` forces `APP_ENV=testing`, SQLite `:memory:`, array cache/session, queue `sync`, and a fixed `APP_KEY` so a developer's `.env` cannot redirect tests to a real database.

### Incident note

Early in the build, running tests with `--env-file .env` allowed `APP_ENV=local` to override the test environment, which pointed `RefreshDatabase` at the development MySQL database and wiped it. Two fixes were applied:

1. Every test environment variable in `phpunit.xml` now uses `force="true"`, including a fixed test `APP_KEY`.
2. The six real generations and charges were restored from Pixelforge using their `metadata.vellum_generation_id` values and archived PNGs; reconciliation then returned to zero difference.

The incident is recorded here because the fix is part of the reliability story: test configuration can no longer target production-like data.

---

## 9. Deployment

### Live demo

- URL: <https://charleston-wealth-pie-ideal.trycloudflare.com>
- Mechanism: Cloudflare quick tunnel forwarding to `http://localhost:8000`
- Processes: Laravel web server, `queue:work`, `schedule:work`, MySQL, Redis

The tunnel is public and currently healthy. A signed webhook sent to the public `/webhooks/pixelforge` endpoint returned `204` and was processed, as documented above.

### Durable deployment status

A durable managed deployment was attempted but not completed:

- GCP project `klyp-504415` has billing disabled, which blocks App Run/Cloud SQL provisioning.
- No authenticated Railway, Render, or Fly.io account/credentials were available.
- The available Vercel CLI is not a good fit for this stateful Laravel app without external MySQL/Redis services.

The repository is deployment-ready: set `APP_URL`, `PIXELFORGE_API_KEY`, `PIXELFORGE_WEBHOOK_SECRET`, MySQL, and Redis; run migrations/seed/build; and start `web`, `queue:work`, and `schedule:work`. For a persistent host, configure the Pixelforge key's webhook URL to `https://<host>/webhooks/pixelforge` and use `pixelforge:reconcile-usage --fail-on-mismatch` as a deploy gate.

### Known limitation

The Pixelforge API key's webhook URL registration lives in the Pixelforge dashboard and could not be changed without dashboard credentials for that key. Live webhook verification was therefore performed by sending a correctly signed event to the public endpoint. Normal generation settlement continues to work through the polling reconciler, so no charge is missed.

---

## 10. Reproduction checklist

```bash
# 1. Install and configure
composer install
cp .env.example .env
php artisan key:generate
# set Pixelforge credentials in .env
npm install && npm run build
php artisan migrate --force && php artisan db:seed --force

# 2. Run services
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work --tries=3 --stop-on-failure
php artisan schedule:work

# 3. Verify
php artisan test --compact
php artisan pixelforge:reconcile-usage --days=14 --fail-on-mismatch
```

Sign in as `studio@vellum.studio` / `VellumStudio!2026` for the studio experience, or `admin@vellum.studio` / `VellumAdmin!2026` for `/admin/report`.

---

## 11. SIGNAL redesign closeout

### Brand

The warm-paper identity was replaced by **SIGNAL**, a strict black-and-white visual system. It uses an original geometric V mark, a separate wordmark treatment, a regenerated SVG/ICO/Apple-touch favicon family, Geist Sans for interface copy, Instrument Serif for display moments, and Geist Mono for technical metadata. The mark and wordmark were checked at favicon, nav, and large-display scales. Tailwind radius tokens were remapped to `0px`; shared error, success, destructive, and badge components were de-colored instead of leaving red/green leakage inside the monochrome shell.

### Experience

The landing page now tells a six-part story — ARRIVAL, FROM SIGNAL, THE WORK, THE STUDIO, TRANSPARENT CREATION, ENTER — using five tracked real Pixelforge plates, a real Studio screenshot, and the exact reconciliation figures already present in the ledger. The landing prompt is functional: unauthenticated users keep it through Fortify login; authenticated users are routed directly into a prefilled composer. Studio, library, generation detail, usage, admin report, auth, and settings were rebuilt as one coherent instrument rather than separate themed pages. Backend contracts, financial behavior, Inertia component names, resource shapes, authorization, tests, and database schema were left intact.

### Motion

Three.js is used only for the landing signal object. The heavy module is lazy-loaded, mounted on intersection, paused when off-screen or when the document is hidden, sized with `ResizeObserver`, and disposed on unmount. Route-change QA confirmed the landing canvas exists and the studio canvas count is zero. `prefers-reduced-motion` disables reveal animation and smooth scrolling, with a static geometric fallback in the component. The generated lazy chunk is isolated from the initial app bundle.

### Verification

Final checks all passed:

- `php artisan test --compact` — **81 passed, 342 assertions**
- `./vendor/bin/pint --test` — **PASS, 121 files**
- `./vendor/bin/phpstan analyse --no-progress --memory-limit=1G` — **[OK] No errors**
- `pixelforge:reconcile-usage --days=14` — 6 provider / 6 local generations, `$1.462355000` on both sides, `$0.000000000` difference
- `npm run check` — 86 formatted files, 78 linted files, no issues
- `npm run types:check` — no errors
- `npm run build` — production build succeeded with the Three.js chunk lazy-loaded

Browser QA was rerun at 1440, 1280, 768, and 390 px after the final edits. Screenshots were retained for each surface and breakpoint. The checks found no horizontal overflow, broken images, console errors, wrong-role 4xx responses, chromatic CSS, or unlabelled visible fields. First Tab focus on both landing and studio is the visible “Skip to content” link with a solid 2px outline. Pixel histograms of all captured screenshots were 100% grayscale.

The in-app browser was then used for a final live inspection of the landing page, login, studio, library, generation detail, usage, admin report, and settings surfaces, plus the public Cloudflare build. Lighthouse scored accessibility, best practices, and SEO at 1.00 on those surfaces after fixing two real findings: low-contrast metadata on the landing page and admin report, and two accessible names that did not include their visible text. The signed-in prompt handoff was exercised both ways in the browser: unauthenticated submission survived login into a prefilled studio composer, and authenticated submission navigated directly to a prefilled composer with the short-lived storage key cleared.

### Git and deployment

The redesign is committed as one focused frontend/documentation change on `main`, with no secrets, build output, local `.env`, or private generation images added. The public repository remains <https://github.com/Jasowills/vellum-studio>. The live demo is still the ephemeral Cloudflare quick tunnel noted above; durable hosting remains blocked by the same external credential/billing constraints, not by application code.

### Genuine remaining work

1. Provision a durable Laravel host with MySQL and Redis, then set `APP_URL`, Pixelforge credentials, and the webhook secret.
2. With Pixelforge dashboard credentials, register the production webhook URL and verify a signed live event against the permanent host.
3. If a human visual sign-off is required before a permanent launch, review the retained breakpoint screenshots; this session's screenshot inspection was automated through DOM geometry and pixel histograms rather than human vision.

No known product defect, test failure, reconciliation mismatch, or required backend change remains from this redesign.
