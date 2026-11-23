# Vellum

**A small studio for considered images.**

Vellum is a Laravel 13 + Vue 3 + Inertia + TypeScript + Tailwind 4 application that turns a prompt into an archived image through the [Pixelforge](https://pixelforge.promptedit.dev/docs) API. It is built around one promise: **every number is exact, and a failed image is never charged.**

- Live demo: <https://charleston-wealth-pie-ideal.trycloudflare.com>
- Source: <https://github.com/Jasowills/vellum-studio>
- Pixelforge API reference: <https://pixelforge.promptedit.dev/docs>

> The live demo is served through a Cloudflare quick tunnel from the development host. Quick tunnels are public but ephemeral; the URL can change when the host or tunnel restarts. See `ENGINEERING_REPORT.md` for deployment details.

---

## What it does

- Compose an image with prompt, size (`1024x1024`, `1536x1024`, `1024x1536`), quality (`low`, `medium`, `high`), and up to four public reference-image URLs.
- See a **live estimate range** before submitting, derived from Pixelforge's published pricing and token rules.
- Submit through a stable, per-user idempotency key; retries reuse the same key and cannot create or charge twice.
- Track `pending → queued → processing → succeeded/failed` through signed webhooks, with polling as a safety net.
- Archive every succeeded image to private storage and serve it through an authenticated Vellum route, so it survives expiry of the provider's signed URL.
- Keep immutable provider cost, customer charge, and settlement records in integer **nanodollars**.
- Reconcile local charges against Pixelforge's per-day usage report, in the UI and from the CLI.

---

## Stack

| Layer         | Choice                                                                    |
| ------------- | ------------------------------------------------------------------------- |
| Backend       | Laravel 13, PHP 8.4                                                       |
| Frontend      | Vue 3, Inertia 3, TypeScript, Vite Plus, Tailwind CSS 4                   |
| Money         | `brick/math` decimal arithmetic, stored as integer nanodollars            |
| Auth          | Laravel Fortify (email/password, passkeys, 2FA, verification)             |
| Queue / cache | Redis (configurable)                                                      |
| Database      | MySQL in development/demo; SQLite in tests                                |
| Design        | Warm-paper editorial system — Fraunces, Instrument Sans, Spline Sans Mono |

---

## Quick start

### Requirements

- PHP 8.4+ with `bcmath`/`gmp` as needed by `brick/math`
- Composer 2
- Node 20+ and npm
- MySQL 8+ (or SQLite for a lightweight local run)
- Redis (recommended for queue/cache)
- A Pixelforge API key with a webhook URL and signing secret

### Install

```bash
git clone https://github.com/Jasowills/vellum-studio.git
cd vellum-studio

composer install
cp .env.example .env
php artisan key:generate

# Fill PIXELFORGE_API_KEY and PIXELFORGE_WEBHOOK_SECRET in .env.
# In Pixelforge's dashboard, set the key's webhook URL to:
#   https://your-public-host/webhooks/pixelforge

npm install
npm run build

php artisan migrate --force
php artisan db:seed --force
```

### Run

Use three terminals:

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work --tries=3 --stop-on-failure
php artisan schedule:work
```

The scheduler runs:

- `vellum:reconcile-generations` every minute
- `vellum:retry-webhooks` every minute
- `pixelforge:reconcile-usage --days=7` hourly

### Demo accounts

Seeded by `php artisan db:seed`:

| Role     | Email                  | Password            |
| -------- | ---------------------- | ------------------- |
| Studio   | `studio@vellum.studio` | `VellumStudio!2026` |
| Operator | `admin@vellum.studio`  | `VellumAdmin!2026`  |

The studio account is used by the live demo. The operator account can open `/admin/report`.

---

## Money: exact by construction

Pixelforge reports token counts and rates, not prices. Vellum computes the price itself.

1. **Provider cost** — for each token class:

    ```text
    rate (USD per 1M tokens) × tokens × 1,000 = nanodollars
    ```

    The result is rounded **once** to an integer nanodollar using `brick/math` with half-up rounding. No float ever enters the calculation.

2. **Customer charge** — `provider cost × 125 / 100`, rounded half-up to the nearest nanodollar. This is a transparent flat 25% studio fee.

3. **Failed generations** — no usage is settled and no `generation_charges` row is created.

4. **Immutability** — charge rows are written once when the provider reports final usage. Later webhooks or reconciliation runs only read/verify them; they do not mutate them.

5. **Precision** — API/UI use exact 9-decimal strings such as `$0.264787500` alongside a shorter display form such as `$0.2647875`.

`app/Support/Money.php` is the single source of truth for these conversions.

---

## Lifecycle and reliability

- `CreateGeneration` writes a pending row with a deterministic key: `vellum_u{userId}_{token}`.
- `SubmitGenerationJob` sends the request with `Idempotency-Key`; Pixelforge replays safely on retries.
- `WebhookController` verifies `Pixelforge-Signature` over the **raw body** (`t.rawBody`, HMAC-SHA256, constant-time compare, 300-second replay window) before decoding JSON.
- Verified events are stored in `webhook_events` with a unique `provider_event_id`, then processed by `ProcessPixelforgeWebhookJob`. Duplicate deliveries return `204` without a second charge.
- State transitions are monotonic: a late `processing` event cannot move a `succeeded` generation backwards.
- `ReconcileGenerationJob` polls the provider as a fallback, including when webhooks are missed or arrive before the create response.
- Bounded retries respect `Retry-After` for `429`/`503` and cap submission and reconciliation attempts.

---

## Tests

```bash
php artisan test --compact
```

Current result: **81 passed, 342 assertions**.

Tests use HTTP fakes for Pixelforge; no test makes an external API call. Coverage includes:

- money/rounding and markup math
- estimate ranges and pricing snapshots
- state machine and out-of-order webhooks
- signature verification, replay rejection, duplicate events
- idempotent submission and bounded retries
- authorization, CSRF, rate limiting, and admin gating
- usage reconciliation and provider-day token totals
- library pagination/resource shape (regression test for nested `data` payloads)

Frontend checks:

```bash
npm run check
npm run types:check
npm run build
```

---

## Reconciliation

From the UI: sign in as the operator and open `/admin/report`.

From the CLI:

```bash
php artisan pixelforge:reconcile-usage --days=14
php artisan pixelforge:reconcile-usage --days=14 --fail-on-mismatch
```

Live reconciliation at the time of writing:

```text
Range ............................................. 2026-11-10 to 2026-11-23
Provider generations ..................................................... 6
Local generations ........................................................ 6
Provider cost ................................................. $1.462355000
Local cost .................................................... $1.462355000
Difference .................................................... $0.000000000

INFO  Provider usage and the local ledger match exactly.
```

---

## Security notes

- Webhooks are outside CSRF middleware and verified by HMAC over the raw body.
- All generation and image routes require authentication and ownership; `/admin/report` requires the `admin` gate.
- Provider image URLs are fetched only from an allowlisted HTTPS host (`PIXELFORGE_IMAGE_HOSTS`) and archived privately.
- Security headers are applied by `App\Http\Middleware\SecurityHeaders`.
- Generation, estimate, image, webhook, and admin-report endpoints are rate limited.
- `.env`, API keys, webhook secrets, generated build output, and local vendor files are gitignored.

---

## Repository layout

```text
app/
  Support/Money.php                 # nanodollar math
  Services/Pixelforge/              # client, DTOs, pricing, estimates, usage, signatures
  Services/Generations/             # creation, state machine, sync, archival, webhook processor
  Jobs/                             # submit, reconcile, archive, process webhook
  Http/Controllers/                 # studio, library, generation, usage, admin, webhook
  Http/Resources/GenerationResource.php
resources/js/
  pages/                            # Welcome, Studio, Library, Generation, Usage, Admin/Report
  components/                       # GenerationCard, StatusBadge, BrandMark, AppLayout
  lib/vellum.ts                     # client-side money/date helpers
tests/                              # Feature + Unit, faked provider only
```

---

## Deployment notes

The application expects `APP_URL` to match its public origin so signed routes and image URLs are correct. Set the Pixelforge key's webhook URL to `https://<public-host>/webhooks/pixelforge` and copy the matching signing secret into `PIXELFORGE_WEBHOOK_SECRET`.

The current public demo is tunnel-backed. For a durable deployment, use the same three processes (`web`, `queue:work`, `schedule:work`) behind a persistent host with a managed MySQL/Redis service, and run `php artisan pixelforge:reconcile-usage --days=14 --fail-on-mismatch` as a deploy gate.

See `ENGINEERING_REPORT.md` for evidence, design rationale, and the exact live reconciliation/webhook checks.
