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

The automated suite has **81 passing tests / 342 assertions**, with Pixelforge faked in every test. Pint, PHPStan level 7, Vue/TS checks, type checking, and the production build all pass; the GitHub Actions `tests` workflow is green on `main`. The product was then rebuilt from the brand outward as **MIDNIGHT / SIGNAL / CREATIVE TECHNOLOGY**: a black room, geometric V light-beam identity, Sora display type, Geist UI type, Geist Mono metadata, and a lazy Three.js sculpture. Browser QA covered every main page at 1440 and 390 px; the earlier axe-core pass found no WCAG A/AA violations, and the final pass verified monochrome rendering, exact fonts, no horizontal overflow, no broken images, no console errors, reduced-motion behavior, and WebGL lifecycle cleanup.

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

The product now uses the **MIDNIGHT / SIGNAL** design system documented in `DESIGN.md`: a closed monochrome palette, square geometry, Sora for display, Geist Sans for UI and body copy, and Geist Mono for technical metadata only. Instrument Serif is gone. The identity is a geometric V cut from converging light planes, reproduced in the wordmark, favicon family, inline fallback, and lazy Three.js hero sculpture. Real artwork is used as evidence, not as staged mockup filler.

The landing page is a full-viewport ARRIVAL followed by the exact required narrative: “Imagine it. Then see it.”, “Everything begins with an idea.”, “Make room for the unexpected.”, “Know what it costs. Keep creating.”, and “Let’s see what you imagine.” Both composers are functional. Unauthenticated submissions persist the prompt in short-lived `sessionStorage` through Fortify login; authenticated submissions go straight to `/studio` with the composer prefilled. No backend route or schema was changed for this behavior.

Responsive and state coverage:

- Landing: full-viewport V sculpture, working prompt, five tracked Pixelforge plates, real Studio screenshot, the documented rate-card cost example, final CTA, and compact footer.
- Auth: login, register, forgot-password, 2FA challenge/verify, passkeys, and verification surfaces use the same monochrome field rhythm without changing Fortify behavior.
- Studio: canvas-first instrument, naturally anchored composer, progressive settings, collapsible history, live 9-decimal estimate, and only real states (idle, entered, submitting, queued, processing, completed, failed).
- Library and generation detail: image-first archive, exact charge values, active-only visibility-aware polling, terminal polling cleanup, robust image fallback, authenticated private image delivery, and admin-only provider gating.
- Usage and admin report: accessible per-day chart data, internally scrollable wide chart/table containers, honest empty states, reconciliation cockpit, and margin denominator based on `lifetime.provider_cost`.
- Settings: monochrome profile, password, 2FA, passkeys, and appearance surfaces; shared destructive/error/success components remain grayscale.

Final browser QA was run at **1440 and 390 px** on the landing page, studio, library, generation detail, usage, admin report, all three settings surfaces, and public auth pages. For every page/breakpoint pair checked:

| Check                               | Result                                  |
| ----------------------------------- | --------------------------------------- |
| Horizontal document scroll          | none                                    |
| Broken or unloaded lazy images      | none after scroll-through               |
| Console errors / warnings           | 0                                       |
| Computed chromatic CSS colors       | 0                                       |
| Body / display / mono font families | Geist / Sora / Geist Mono               |
| Unlabelled visible form fields      | 0                                       |
| WebGL canvas on landing             | 1; removed on route change to `/studio` |
| `prefers-reduced-motion: reduce`    | static hero, `scroll-behavior: auto`    |

The five landing plates and the Studio screenshot were scrolled into view and confirmed loaded at full intrinsic resolution. Authenticated image delivery returned `200 image/png`. The earlier axe-core pass on the pre-redesign UI found no WCAG A/AA violations; keyboard focus, ARIA labels, skip links, and reduced-motion behavior were rechecked through DOM and browser automation after the redesign.

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

# Reconciliation
docker run --rm --network vellum-net -v "$PWD":/app -w /app --env-file .env vellum-dev php artisan pixelforge:reconcile-usage --days=14
# => 6 provider / 6 local, $1.462355000 on both sides, $0.000000000 difference

# Frontend
npm run check          # 92 formatted files, 78 linted files; all pass
npm run types:check    # vue-tsc --noEmit
npm run build          # production build; Three.js remains in a lazy chunk
```

GitHub Actions runs the same checks through `composer ci:check` on every push. The `tests` workflow was verified green on `main` before this redesign (run `45259645135`); the final redesign commit's CI result is recorded in the closeout below.

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

## 11. MIDNIGHT redesign closeout

### Brand

The prior SIGNAL identity was superseded by the pinned **MIDNIGHT / SIGNAL / CREATIVE TECHNOLOGY** direction. The system is fully specified in `DESIGN.md`: the exact 13-colour black/grey/white palette, Sora for display and brand headlines, Geist Sans for UI and body copy, Geist Mono for technical metadata, zero radius, and hairline structure. Instrument Serif was removed from the font pipeline. The geometric V glyph, wordmark, SVG favicon, ICO, and Apple-touch icon were all regenerated as monochrome assets; the Apple-touch and ICO files were rebuilt from the final SVG after a pixel audit showed the earlier PNG was white-dominant. Shared status, badge, error, success, and destructive components remain grayscale.

### Experience

The landing page is now a working front door rather than a brochure. Its full-viewport ARRIVAL uses a custom Three.js V sculpture, the exact required headline sequence, two real prompt composers, five tracked Pixelforge plates, a real Studio screenshot, the documented rate-card cost example, a final CTA, and a compact footer. There are no numbered section titles, generic feature cards, fake metrics, testimonials, or invented progress.

Studio is canvas-first with minimal chrome, a naturally anchored composer, progressive settings, collapsible history, and only real states. Library, generation detail, usage, admin, auth, and settings were rebuilt as one coherent instrument. Backend contracts, Pixelforge behavior, financial calculations, Inertia component names, resource shapes, authorization, tests, and database schema were preserved.

### Motion

Three.js is used only for the landing V. The heavy module is lazy-loaded, mounted on intersection, paused when off-screen or when the document is hidden, sized with `ResizeObserver`, and disposed on unmount. Pointer response is restrained and the sweep is slow. Route-change QA confirmed one canvas on landing and zero on Studio. With `prefers-reduced-motion: reduce`, the hero keeps a static geometric rendering and `scroll-behavior` is `auto`. No percentage progress or synthetic success animation was added.

### Verification

Final checks all passed:

- `php artisan test --compact` — **81 passed, 342 assertions**
- `./vendor/bin/pint --test` — **PASS, 121 files**
- `./vendor/bin/phpstan analyse --no-progress --memory-limit=1G` — **[OK] No errors**
- `pixelforge:reconcile-usage --days=14` — 6 provider / 6 local generations, `$1.462355000` on both sides, `$0.000000000` difference, `$1.827943750` customer charges
- `npm run check` — 92 formatted files, 78 linted files, no issues
- `npm run types:check` — no errors
- `npm run build` — production build succeeded; Three.js remains in an isolated lazy chunk

Browser QA was rerun at 1440 and 390 px after the final edits. All landing images were scrolled into view and confirmed loaded at full intrinsic resolution, including `studio-interface.png`. No horizontal document scroll, broken images, console errors/warnings, chromatic computed CSS, or unlabelled visible fields were found. Studio's live estimate renders exact 9-decimal values from nanos; library, detail, usage, and admin show the same exact money format. The authenticated private image route returned `200 image/png`. Wide admin/usage containers scroll internally rather than moving the document.

Prompt handoff was exercised through the real UI on both paths:

1. Unauthenticated: hero prompt stored under `vellum:pending-prompt`, routed to login, then restored into the Studio composer after Fortify login.
2. Authenticated: closing prompt routed straight to Studio, restored into the composer, and consumed from storage.

### Git and deployment

The redesign is committed on `main` as `1bad8ee` — “Rebuild Vellum as MIDNIGHT creative instrument” — with no secrets, `.env`, API/webhook credentials, generated build output, or private generation images added. The push-triggered `tests` workflow `45275710397` completed successfully in 51s. The public repository remains <https://github.com/Jasowills/vellum-studio>. The live demo is still the ephemeral Cloudflare quick tunnel noted above; durable hosting remains blocked by the same external credential/billing constraints, not by application code.

### Genuine remaining work

1. Provision a durable Laravel host with MySQL and Redis, then set `APP_URL`, Pixelforge credentials, and the webhook secret.
2. With Pixelforge dashboard credentials, register the production webhook URL and verify a signed live event against the permanent host.
3. If a human visual sign-off is required before a permanent launch, review the retained screenshots; this session's inspection was automated through DOM geometry, lazy-load checks, grayscale/chromatic sampling, and captured pixels rather than human vision.

No known product defect, test failure, reconciliation mismatch, or required backend change remains from this redesign.
