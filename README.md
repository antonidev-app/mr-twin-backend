# Mr. Twin Backend

REST API backend for **Mr. Twin** — a portfolio marketplace project that mirrors real Accurate Online inventory (from a real IT/CCTV reseller) into a local database, then serves a storefront (catalog, customer auth, checkout) entirely on top of that local mirror. Accurate itself is **only ever read**, never written to — every customer transaction is a local order, never a real Accurate Sales Order. See [`PLANNING.md`](../PLANNING.md) for the full project rationale and architecture.

This repo (`mr-twin-backend`) is the single source of truth: it owns the Accurate OAuth connection, the sync jobs, and all REST endpoints consumed by `mr-twin-web`, `mr-twin-backoffice`, and (later) `mr-twin-mobile`.

## Tech stack

- Laravel 12 (PHP 8.2+)
- PostgreSQL
- Laravel Sanctum (customer + admin token auth)
- Laravel Scheduler + Queue (sync jobs)

## Requirements

- PHP 8.2+ with `pdo_pgsql`/`pgsql` extensions
- Composer
- PostgreSQL running locally (or reachable)
- Real Accurate Online OAuth credentials (client ID/secret) with access to at least one Accurate database — this project talks to a **real** Accurate account, not a sandbox

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
- `DB_*` — point at your Postgres instance (a database matching `DB_DATABASE` must already exist, e.g. `createdb mr_twin_backend`)
- `ACCURATE_CLIENT_ID` / `ACCURATE_CLIENT_SECRET` — from your Accurate developer app
- `ACCURATE_REDIRECT_URI` — must exactly match the OAuth callback URL registered in Accurate's developer dashboard (protocol, host, and port all have to match — e.g. `http://localhost:8000/accurate/callback` if you run `php artisan serve` on the default port)
- `ACCURATE_SCOPES` — space-separated. At minimum `item_view item_category_view` (the project's two sync jobs need both; `item_category_view` is a separate scope from `item_view` even though it's still item-related — this isn't obvious from Accurate's public docs, only from the scope list in your own Accurate developer app dashboard)

Then:

```bash
php artisan migrate
```

## Connecting to Accurate

The OAuth authorize step is an interactive browser login — it can't be automated or run from an API client. Order:

1. Start the server: `php artisan serve`
2. Open `http://localhost:8000/accurate/connect` **in a real browser** (not curl/Bruno) — log in and approve. Accurate redirects back to `/accurate/callback`, which exchanges the code for a token and shows a JSON confirmation.
3. `GET /accurate/databases` — lists the Accurate databases (companies) available to your account.
4. `POST /accurate/databases/select` with `id_db` (and optionally `db_alias`) — picks the database and opens an API session against it.
5. `GET /accurate/status` — confirms `connected: true` and shows token/session health.

These four endpoints are documented as requests in the [Bruno collection](#api-documentation-bruno) (steps 2–4 are runnable from Bruno; step 1 has to be opened in a browser).

Token refresh is on-demand and locked (not a blind `everyMinute()` cron) — see `app/Services/Accurate/AccurateClient.php` and PLANNING.md section 8 for why.

## Syncing data from Accurate

Once connected, pull categories and items into the local mirror tables:

```bash
php artisan accurate:sync-categories --sync   # ~150 rows, fast
php artisan accurate:sync-items --sync        # can be tens of thousands of rows — slow
```

Drop `--sync` to dispatch to the queue instead of running inline (needs `php artisan queue:work`). Add `--dry-run` to fetch and print just the first page without writing anything — useful for sanity-checking field names against Accurate's real response shape before trusting a full sync.

Both jobs are also scheduled every 30 minutes (`routes/console.php`) once `php artisan schedule:work` (or a real cron calling `schedule:run`) is running.

Synced data lands in `synced_items` / `synced_categories` — raw mirrors, untouched by curation. Nothing is customer-visible until an admin publishes it via `product_display` (currently only doable manually through `php artisan tinker`; a proper curation UI is `mr-twin-backoffice`, not yet built).

## API documentation (Bruno)

The [`bruno/`](./bruno) folder is a full [Bruno](https://www.usebruno.com) collection covering every endpoint in this API — open it directly in the Bruno app (`Open Collection` → select the `bruno` folder). It's plain text and lives in git, so it stays in sync with the code instead of drifting like a wiki page would.

- Select the **Local** environment (base URL `http://localhost:8000`).
- Folders are numbered in the order you'd actually use them: **Accurate Connection** → **Catalog** → **Customer Auth** → **Orders**.
- Running **Customer Auth › Register** or **Login** auto-saves the returned token into the `customer_token` environment variable via a post-response script — every request under **Orders** already reads `{{customer_token}}`, so there's no manual copy-pasting.
- Every request has a `docs` block explaining what it does and any non-obvious behavior (why a 404 instead of 403, why `product_id` isn't an Accurate item ID, etc).

### Endpoint summary

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/accurate/connect` | none | browser-only, starts OAuth |
| GET | `/accurate/callback` | none | OAuth redirect target |
| GET | `/accurate/databases` | none | list Accurate databases |
| POST | `/accurate/databases/select` | none | pick database + open session |
| GET | `/accurate/status` | none | connection health |
| GET | `/api/catalog/products` | none | filter: `display_category`, `brand`, `min_price`, `max_price`, `q` |
| GET | `/api/catalog/products/{id}` | none | 404 if unpublished |
| GET | `/api/catalog/categories` | none | distinct `display_category` in use |
| POST | `/api/auth/register` | none | returns Sanctum token |
| POST | `/api/auth/login` | none | returns Sanctum token |
| POST | `/api/auth/logout` | customer token | revokes current token |
| GET | `/api/orders` | customer token | own orders only |
| GET | `/api/orders/{id}` | customer token | 404 if not the owner |
| POST | `/api/orders` | customer token | checkout — live Accurate stock check, server-side pricing |

"Admin-only" endpoints (`/accurate/*`) are currently unauthenticated — there's no backoffice/admin auth yet (Fase 2). Fine for local/manual use, but must be gated before any public deploy. "customer token" means a Sanctum bearer token issued to the `customers` table specifically — an admin `users` token is explicitly rejected (403) by a dedicated `customer` middleware, since Sanctum's `auth:sanctum` guard alone can't tell the two apart.

## Architecture notes

- **`synced_items` / `synced_categories`** — raw, unfiltered mirrors of Accurate's `item/list.do` / `item-category/list.do`, rewritten on every sync. Never written back to Accurate.
- **`product_display`** — the curation layer (1:1 with `synced_items`). `is_published` gates storefront visibility; `display_category`/`brand` are admin-curated overrides, deliberately decoupled from Accurate's raw (deeply nested, ERP-messy) category tree.
- **`local_orders` / `local_order_items`** — 100% local. Checkout never calls any Accurate write endpoint (`save.do`/`delete.do`); it only reads live stock via `item/detail.do` before creating the order, and snapshots item name/SKU/price at order time so historical orders don't shift if the synced item changes later.
- **`customers` vs `users`** — intentionally separate tables/models. `users` is the admin/backoffice identity; `customers` is the storefront identity. Both can hold Sanctum tokens; a custom `EnsureCustomer` middleware is what actually keeps the two from being usable interchangeably.
- **JSON error responses are always `{"message": "..."}`**, regardless of `APP_DEBUG` — `bootstrap/app.php`'s `withExceptions` strips Laravel's default `exception`/`file`/`trace` keys before they reach any API client, since leaking internal file paths and stack traces to a public API is a real issue, not just a cosmetic one. Full traces still land in `storage/logs/laravel.log` as usual.

Full rationale for all of the above lives in [`PLANNING.md`](../PLANNING.md).

## Useful artisan commands

| Command | Purpose |
|---|---|
| `php artisan accurate:sync-categories --sync\|--dry-run` | sync `item-category/list.do` → `synced_categories` |
| `php artisan accurate:sync-items --sync\|--dry-run` | sync `item/list.do` → `synced_items` |
| `php artisan route:list` | full route table |
| `php artisan migrate` | run pending migrations |
| `php artisan tinker` | manual product curation, ad-hoc queries |
