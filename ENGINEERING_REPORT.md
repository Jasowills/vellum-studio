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

The automated suite has **81 passing tests / 342 assertions**, with Pixelforge faked in every test. Pint, PHPStan level 7, Vue/TS checks, type checking, and the production build all pass; the GitHub Actions `tests` workflow is green on `main`. Browser QA covered every main page at desktop and mobile widths with axe-core; no WCAG A/AA violations were found.

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

The design uses a warm bone/paper palette, near-black ink, a vermilion accent, Fraunces display type, Instrument Sans body type, and Spline Sans Mono for ledger data. It is intentionally editorial rather than a generic AI dashboard.

Responsive and state coverage:

- Landing: hero, process, plate gallery, ledger, footer.
- Studio: prompt suggestions, reference URLs, size/quality controls, live estimate, loading/error/estimate-ready states, recent plates.
- Library: search, filters, active filter chips, empty state, pagination, six real cards.
- Generation: image, ledger, timeline, failure state support, copy prompt, variation prefill, refresh.
- Usage: summary cards, 30-day chart with data-table fallback, settled charge table.
- Admin: range switcher (7/14/30/92 days), totals comparison, differences, lifetime margin, settlements.

Browser QA results:

| Page                | axe violations | Notes                                                                                                       |
| ------------------- | -------------: | ----------------------------------------------------------------------------------------------------------- |
| `/`                 |              0 | One `incomplete` contrast item on an absolute caption with manually verified `#211d17` on `#fbf7ee` (~13:1) |
| `/studio`           |              0 | Estimate and six recent plates render                                                                       |
| `/library`          |              0 | Six cards and six images render after resource-shape fix                                                    |
| `/generations/{id}` |              0 | Ledger, timeline, and image render                                                                          |
| `/usage`            |              0 | Summary, chart, table, and charges render                                                                   |
| `/admin/report`     |              0 | Reconciliation and settlements render                                                                       |

Mobile viewport `390 × 844` showed `scrollWidth === clientWidth` on the landing, studio, library, and usage pages. Keyboard focus was visible on the first tab stop with a solid outline.

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
npm run check          # formatting + lint: 80 formatted files, 72 linted files; all pass
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
