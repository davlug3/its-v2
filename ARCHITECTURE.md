# Architecture

This document defines the technical architecture for the application.

## Core Working Rule (from agents.md)

**Do not invent your own scaffolds, boilerplates, or project structures.**

Always look up the official getting-started / installation guide for the library, language, or framework on the web and follow it. Prefer the recommended project layout, tooling, and conventions from the maintainers (Laravel, Vue, Vite, Tailwind, shadcn-vue, Reka UI, Docker, jwt-auth, etc.). Only deviate when this architecture document explicitly requires it (e.g. strict REST layering, additive-only migrations, legacy MD5 handling).

**Stack summary**

| Layer              | Choice                                      | Notes |
|--------------------|---------------------------------------------|-------|
| Backend            | PHP 8.3+ / Laravel 13.x                     | Strict REST API |
| Frontend           | Vue 3.5+ (SPA) + Vite                       | Consumes REST API |
| Language (FE)      | TypeScript (strict)                         | Checked with `vue-tsc` |
| UI Components      | shadcn-vue 2.x + Reka UI 2.x                 | Copied source; follow `DESIGN.md` |
| Styling            | Tailwind CSS 4.x (latest)                   | Utility-first |
| Validation (FE)    | Zod 4.x + vee-validate                      | Schema-first |
| Database           | MySQL / MariaDB                             | — |
| Auth               | JWT (`php-open-source-saver/jwt-auth`)      | Stateless JWT + legacy MD5 support |
| Containerization   | Docker + Docker Compose                     | Full local / CI / prod parity |
| Optional bridge    | Inertia.js                                  | Not recommended for pure REST (see below) |

---

## 1. High-level Architecture

```
┌─────────────────────┐         HTTPS / JSON          ┌──────────────────────────┐
│   Vue 3 SPA         │  ◄──────────────────────────► │  Laravel 13 REST API     │
│   (Vite + shadcn)   │                               │  (Strict, versioned)     │
│                     │                               │                          │
│  - Zod schemas      │                               │  Controllers (thin)      │
│  - Pinia stores     │                               │  Form Requests           │
│  - Vue Router       │                               │  Policies / Gates        │
│  - Composition API  │                               │  Services / Actions      │
└─────────────────────┘                               │  Repositories            │
                                                      │  DTOs                    │
                                                      │  API Resources           │
                                                      │  Models (Eloquent)       │
                                                      └────────────┬─────────────┘
                                                                   │
                                                                   ▼
                                                      ┌──────────────────────────┐
                                                      │  MySQL / MariaDB         │
                                                      └──────────────────────────┘
```

**Decision: Pure SPA + REST API (preferred over Nuxt / Inertia)**

- Laravel exposes a strict, versioned REST API only.
- Vue SPA is a fully independent client.
- This keeps clear separation of concerns, enables future mobile clients, and matches “strict REST based API”.
- **Nuxt** adds SSR/SSG complexity that is unnecessary for a classic authenticated SPA.
- **Inertia.js** is excellent for tightly-coupled Laravel + Vue apps, but it blurs the REST boundary and is less ideal when the API must remain a first-class, reusable contract. It can be revisited later if a hybrid approach is desired.

---

## 2. Backend (Laravel 13)

### 2.1 Principles

- Controllers stay thin — orchestration only.
- Business logic lives in Services or single-purpose Actions.
- Data access is isolated behind Repositories (or query objects when the query is complex).
- **Prefer Eloquent abstractions over raw SQL** whenever possible (Eloquent models, relationships, local/global scopes, query builder, accessors/mutators, attribute casting, etc.). Use `DB::raw()`, `DB::select()`, or other raw expressions only when Eloquent cannot express the requirement cleanly or when there is a measured performance need. Any raw SQL must be documented and justified.
- Input is validated with Form Requests.
- Authorization is enforced via Policies (and Gates where appropriate).
- Responses are always shaped by API Resources (never raw models).
- DTOs are used at boundaries (request → use-case, use-case → external systems, complex responses).
- API is versioned from day one (`/api/v1/...`).
- Global, consistent pagination.
- Contracts (interfaces) are used for major dependencies so implementations can be swapped or mocked.

### 2.2 Recommended Directory Structure

```
app/
├── Actions/                  # Optional single-purpose use-case classes
├── Contracts/                # Interfaces (RepositoryInterface, etc.)
├── DTOs/                     # Data Transfer Objects
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Middleware/
│   ├── Requests/             # Form Requests
│   └── Resources/Api/V1/     # API Resources + ResourceCollections
├── Models/
├── Policies/
├── Repositories/
├── Services/
└── Support/                  # Helpers, Enums, Exceptions, Pagination, etc.
```

### 2.3 Layer Responsibilities

| Layer            | Responsibility |
|------------------|----------------|
| **Route**        | Map HTTP verb + URI → Controller method. Versioned under `api/v1`. |
| **Controller**   | Validate via Form Request, authorize via Policy, call Service/Action, return Resource. |
| **Form Request** | Input validation + authorization (or delegate to Policy). |
| **Policy**       | Authorization rules for models / actions. |
| **DTO**          | Immutable, typed data bags crossing layer boundaries. |
| **Service / Action** | Business rules, orchestration, transactions. |
| **Repository**   | All data-access logic. Prefer Eloquent (models, relationships, scopes, query builder). Returns Models or collections of Models (or DTOs when needed). Raw SQL only as a last resort. |
| **Model**        | Eloquent model, relationships, casts, scopes. Keep domain logic light. |
| **API Resource** | Transform Model/DTO → public JSON contract. |

### 2.4 REST Conventions

- Resource-oriented URLs (`/api/v1/users`, `/api/v1/users/{user}`).
- Proper HTTP verbs and status codes (`201`, `204`, `422`, `403`, `404`, etc.).
- Consistent error envelope.
- JSON:API-inspired or simple `{ data, meta, links }` style (choose one and stick to it).
- Sparse fieldsets / includes only when explicitly needed.

### 2.5 Pagination (Global Support)

- All list endpoints must support pagination.
- Prefer Laravel’s `LengthAwarePaginator` (or cursor pagination for very large datasets).
- Standard query parameters: `page`, `per_page` (capped, e.g. max 100).
- Response shape (example):

```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 12,
    "per_page": 15,
    "total": 178
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

- A shared base ResourceCollection or a helper trait enforces the shape across the API.

### 2.6 Authentication & Authorization

See **§8 Authentication Layer** for the full design (JWT + legacy MD5 password support).

- **JWT** via `php-open-source-saver/jwt-auth` (Laravel 13 compatible fork of tymon/jwt-auth).
- Stateless Bearer tokens for the Vue SPA and any future clients.
- Policies for model-level authorization.
- Middleware + Form Request `authorize()` methods for route-level checks.
- Rate limiting on all public and authenticated endpoints (stricter on login).

### 2.7 Database & Migrations (Critical Rules)

**Database**: MySQL / MariaDB.

**Migration policy (non-negotiable)** — the database contains live (development) data:

- **Allowed**: Adding new columns, adding new tables, adding indexes, adding foreign keys (when safe).
- **Forbidden**:
  - Modifying existing columns (type, nullability, default, length, etc.).
  - Renaming columns.
  - Dropping / deleting columns.
  - Renaming tables.
  - Dropping / deleting tables.
  - Any destructive change that can cause data loss or break running code.

All schema evolution must be additive only. If a column must change semantically, introduce a new column, migrate data in a safe job/command, and phase out the old column later (never in the same migration that the application still depends on).

### 2.8 Other Backend Practices

- Prefer constructor / method dependency injection.
- Use PHP 8.3+ features (typed properties, enums, attributes, readonly, etc.).
- API Resources for every response.
- Form Requests for every write endpoint.
- Eager loading to avoid N+1.
- Queues for long-running work.
- Feature tests + unit tests for Services/Actions/Repositories.
- OpenAPI documentation (e.g. Scramble or Scribe) generated from the code.

---

## 3. Frontend (Vue 3 SPA)

### 3.1 Principles

- Composition API + `<script setup>` exclusively.
- Follow `design.md` for visual language, spacing, typography, and component usage.
- **Tailwind CSS 4.x** as the primary styling solution (utility-first).
- **shadcn-vue 2.x** as the component source. Components are copied into `resources/js/components/ui/` and owned by this project.
- **Reka UI 2.x** as the headless primitive layer for accessibility and keyboard behavior.
- **vee-validate** with Zod for form state and field-level errors.
- Schema validation with **Zod** on all forms and API payloads.
- **TypeScript is required.** All frontend source files use `.ts`, or `.vue` with `<script setup lang="ts">`. Do not add plain JavaScript files to `src/`.
- Type checking with `vue-tsc --noEmit` must pass in CI.
- Use `strict` mode in `tsconfig.json`. Do not use `any` to bypass a check. Use a precise type or an explained alternative.
- Pinia for global/client state.
- Vue Router for client-side routing.
- Axios or ofetch / ky for HTTP (with interceptors for auth, errors, pagination).
- Strict separation: UI components stay presentational; business logic lives in composables / stores / services.

### 3.2 Recommended Structure

```
resources/js/
├── assets/
├── components/          # Shared components
│   └── ui/              # shadcn-vue components (copied, owned by this project)
├── composables/
├── layouts/
├── lib/
│   └── utils.ts         # cn() helper for class merging
├── pages/               # or views/
├── router/
├── services/            # API client modules
├── stores/              # Pinia
├── schemas/             # Zod schemas
├── types/               # Shared types and API contracts
├── utils/
├── app.ts
└── env.d.ts             # Vite and environment typings
```

Every file in this tree is TypeScript.
Component files use `<script setup lang="ts">`.

### 3.3 Validation

- Every form and every important API payload has a corresponding Zod schema.
- Schemas are the single source of truth for client-side validation and TypeScript types (`z.infer<typeof schema>`).
- Forms use `vee-validate` with `@vee-validate/zod`. The shadcn-vue `Form` components bind fields and errors.
- Server-side validation errors (422) are mapped back onto the form fields.

### 3.4 UI & Design System

- **Tailwind CSS 4.x** for all layout, spacing, typography, and custom styling.
- **shadcn-vue** components are copied into `resources/js/components/ui/`. Edit them directly when the design needs it.
- **Reka UI** provides the headless behavior: focus, keyboard, ARIA, and portals.
- Design tokens follow `DESIGN.md` and live as CSS variables in `resources/css/app.css`, mapped to Tailwind theme tokens.
- Do not add a second component library. Use the shadcn-vue collection and extend it.
- Consistent loading, empty, and error states.
- Accessibility (ARIA, keyboard) is required for interactive components.

### 3.5 Data Fetching & State

- API calls live in dedicated service modules.
- Server state can be managed with Pinia or a lightweight data-fetching layer.
- Pagination, filtering, and sorting parameters are always explicit and typed.
- Optimistic updates only when the UX benefit is clear and rollback is handled.

---

## 4. Cross-cutting Concerns

| Concern              | Approach |
|----------------------|----------|
| API Versioning       | `/api/v1` prefix; future versions live side-by-side |
| Error Format         | Consistent JSON envelope for validation, auth, and server errors |
| CORS                 | Locked down to the SPA origin(s) |
| Security             | JWT (Bearer), rate limiting, Policies, prepared statements (Eloquent), password rehashing |
| Logging / Observability | Structured logs, Laravel Telescope (dev), proper exception reporting |
| Testing              | Feature tests (API), unit tests (Services/Actions), component tests (Vue) |
| CI/CD                | Lint, static analysis (PHPStan/Larastan, ESLint), tests, build |

---

## 5. Technology Versions (Latest Stable at Time of Writing)

| Package / Runtime     | Target |
|-----------------------|--------|
| PHP                   | 8.3 – 8.5 |
| Laravel               | 13.x |
| Vue                   | 3.5+ |
| TypeScript            | 5.x or 7.x (latest stable that passes `vue-tsc`) |
| Vite                  | Latest stable |
| Tailwind CSS          | 4.x (latest) |
| shadcn-vue            | 2.x (component source, not a runtime library) |
| Reka UI               | 2.x |
| vee-validate          | 4.x with `@vee-validate/zod` |
| Zod                   | 4.x |
| Pinia                 | Latest |
| Vue Router            | 5.x |
| php-open-source-saver/jwt-auth | Latest (v2.9+ for Laravel 13) |
| MySQL / MariaDB       | Current LTS |
| Docker / Compose      | Latest stable |

Always pin exact versions in `composer.lock` / `package-lock.json` (or `pnpm-lock.yaml`) and upgrade deliberately.

---

## 6. What This Architecture Explicitly Avoids

- Fat controllers
- Returning Eloquent models directly from the API
- Business logic inside Form Requests or Resources
- Direct DB queries scattered across the codebase
- Raw SQL when an Eloquent equivalent exists
- Destructive migrations on a database that already contains data
- Mixing SSR concerns into a pure SPA unless requirements change
- Ad-hoc validation without schemas on the frontend
- Custom scaffolds or project layouts that ignore the official getting-started guides of the chosen tools

---

## 7. Evolution Path

1. Start with the structure above.
2. Keep the REST contract stable and well-documented.
3. If a future need arises for tighter Laravel–Vue coupling (e.g. rapid internal admin tools), Inertia can be introduced for specific routes without discarding the public REST API.
4. Schema changes remain additive only until a formal data-migration strategy and maintenance window are defined.

---

## 8. Authentication Layer (JWT + Legacy MD5)

### 8.1 Goals

- Use **proper JWT** authentication for the REST API (stateless Bearer tokens).
- Support the **existing database** where passwords are stored as **MD5** hashes.
- Transparently and safely upgrade passwords to modern hashing (bcrypt / Argon2id) on successful login.
- Never break existing users.
- Keep the password column additive-only compliant (we only *update the value* of the existing `password` column — no schema change required).

### 8.2 Package Choice

**`php-open-source-saver/jwt-auth`** (actively maintained fork of `tymon/jwt-auth`, supports Laravel 13).

```bash
composer require php-open-source-saver/jwt-auth
php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

Configure the `api` guard in `config/auth.php` to use the `jwt` driver.

### 8.3 Password Strategy

| Situation                        | Behaviour |
|----------------------------------|-----------|
| Stored password is MD5 (32-char hex) | Verify with `md5($plain) === $stored`. On success → immediately re-hash with `Hash::make($plain)` (bcrypt/Argon2) and persist. |
| Stored password is modern (bcrypt/Argon2) | Standard `Hash::check()`. |
| New registrations / password resets | Always use `Hash::make()` (modern). |

**Detection heuristic** (simple and reliable):

```php
// 32-character hexadecimal string → treat as legacy MD5
protected function isLegacyMd5(string $hash): bool
{
    return (bool) preg_match('/^[a-f0-9]{32}$/i', $hash);
}
```

### 8.4 Recommended Implementation

#### 8.4.1 Custom User Provider (cleanest)

Extend Laravel’s `EloquentUserProvider` and override `validateCredentials`:

```php
// app/Auth/LegacyEloquentUserProvider.php
namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class LegacyEloquentUserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? '';
        $hashed = $user->getAuthPassword();

        if ($this->isLegacyMd5($hashed)) {
            if (md5($plain) === strtolower($hashed)) {
                // Upgrade in place (allowed – we only change the value)
                $user->forceFill([
                    'password' => Hash::make($plain),
                ])->save();

                return true;
            }

            return false;
        }

        // Modern hash
        return Hash::check($plain, $hashed);
    }

    protected function isLegacyMd5(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{32}$/i', $hash);
    }
}
```

Register it in `AuthServiceProvider` (or `AppServiceProvider`):

```php
Auth::provider('legacy-eloquent', function ($app, array $config) {
    return new \App\Auth\LegacyEloquentUserProvider(
        $app['hash'],
        $config['model']
    );
});
```

Then in `config/auth.php`:

```php
'providers' => [
    'users' => [
        'driver' => 'legacy-eloquent',
        'model'  => App\Models\User::class,
    ],
],
```

#### 8.4.2 Auth Endpoints (API)

| Method | Endpoint              | Description |
|--------|-----------------------|-------------|
| POST   | `/api/v1/auth/login`  | Email + password → JWT access token (+ optional refresh token) |
| POST   | `/api/v1/auth/refresh`| Refresh the access token |
| POST   | `/api/v1/auth/logout` | Invalidate token (blacklist) |
| GET    | `/api/v1/auth/me`     | Current authenticated user |

Login flow (controller / action):

1. Validate credentials with Form Request.
2. Attempt authentication via the custom provider (handles MD5 → bcrypt upgrade automatically).
3. On success, issue JWT with `auth('api')->login($user)` or `JWTAuth::fromUser($user)`.
4. Return token + basic user resource.

### 8.5 JWT Configuration Recommendations

- Algorithm: `HS256` (or `RS256` if you prefer asymmetric keys).
- Access token TTL: 15–60 minutes (configurable).
- Refresh token TTL: longer (e.g. 7–14 days) if using refresh tokens.
- Enable blacklist for logout / forced invalidation.
- Put the secret in `.env` (`JWT_SECRET`) and never commit it.
- Always send as `Authorization: Bearer <token>`.

### 8.6 Frontend Integration (Vue)

- Store the access token securely (memory + `httpOnly` cookie is ideal; if pure SPA, use memory + silent refresh, or secure storage with short TTL).
- Axios / fetch interceptor adds the `Authorization` header.
- On 401 → attempt refresh once; if refresh fails → redirect to login.
- Login form validated with Zod schema.
- After login, store token and user profile in a Pinia auth store.

### 8.7 Security Notes

- MD5 is cryptographically broken for passwords. The upgrade-on-login strategy minimises the window of exposure.
- Rate-limit the login endpoint aggressively (e.g. 5–10 attempts per minute per IP / email).
- Consider adding a `password_upgraded_at` timestamp (new column – allowed) later for monitoring migration progress.
- After a reasonable period, remaining MD5 users can be forced through a password-reset flow.
- Never log plain-text passwords.
- Use HTTPS only in all environments that handle real credentials.

### 8.8 What We Explicitly Do *Not* Do

- Do **not** change the column type or length of the existing `password` column.
- Do **not** rename or drop the password column.
- Do **not** leave MD5 as the permanent hashing algorithm.
- Do **not** use Sanctum session/cookie auth as the primary mechanism (JWT is required).

This gives a clean, modern JWT-based authentication layer while remaining fully compatible with the current MD5-hashed passwords and the strict additive-only migration policy.

---

## 9. Containerization (Docker + Docker Compose)

Everything runs in containers for consistent local development, CI, and deployment.

### 9.1 Goals

- One-command local environment (`docker compose up`).
- Parity between local, CI, and production where practical.
- Isolated services: PHP-FPM / Laravel, Nginx (or Caddy), MySQL/MariaDB, Node (for Vite build / dev server), optional Redis / queue worker.
- Frontend can run in dev mode (Vite HMR) or be built into static assets served by Nginx.

### 9.2 Recommended Service Layout

```yaml
# docker-compose.yml (conceptual)
services:
  app:          # Laravel (PHP-FPM)
  nginx:        # Web server / reverse proxy
  db:           # MySQL or MariaDB
  node:         # Vite dev server / asset builder (profile: dev)
  redis:        # Optional – cache, queues, JWT blacklist
  queue:        # Optional – Laravel queue worker
```

### 9.3 Key Practices

- **Multi-stage Dockerfiles** for the Laravel app (build stage with Composer, runtime stage with only production dependencies).
- Separate `Dockerfile` for the frontend build when producing production assets.
- Use `.env` + `docker-compose.override.yml` (git-ignored) for local overrides.
- Persist database data with a named volume.
- Mount source code as volumes in development for hot reload; copy into the image for production.
- Healthchecks on `db`, `app`, and `nginx`.
- Non-root users inside containers where feasible.
- Keep secrets out of the image (use env files or secrets).

### 9.4 Typical Developer Workflow

```bash
docker compose up -d          # start the stack
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed   # if needed
# Frontend (dev)
docker compose exec node npm install
docker compose exec node npm run dev
```

Or expose the Vite dev server and Laravel API on different ports and point the SPA at the API URL via env.

### 9.5 Production Considerations

- Build optimized images (no dev dependencies, OPcache, etc.).
- Frontend assets pre-built and served as static files (or via CDN).
- Use a production-grade Compose file or migrate to Kubernetes / a PaaS later; the same Docker images should remain usable.
- Run migrations as a one-off job / init container, never automatically on every container start in production without care.

### 9.6 What We Avoid

- Running the database without a volume (data loss risk).
- Baking secrets or `.env` into images.
- Using the same Compose file for local and production without clear separation of concerns (use override files or distinct compose files).

Docker Compose is the default local and CI orchestration tool. The same images feed higher environments.

---

## 10. API Response & Error Contract

All API responses follow a consistent envelope so the Vue SPA (and any future clients) can handle them uniformly.

### 10.1 Success Responses

**Single resource**
```json
{
  "data": { ... }
}
```

**Collection (with pagination)**
```json
{
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 12,
    "per_page": 15,
    "total": 178
  },
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  }
}
```

Use API Resources / ResourceCollections exclusively. Never return raw Eloquent models.

### 10.2 Error Responses

| HTTP Status | Typical Cause              | Shape |
|-------------|----------------------------|-------|
| 401         | Unauthenticated            | `{ "message": "..." }` |
| 403         | Unauthorized (Policy)      | `{ "message": "..." }` |
| 404         | Resource not found         | `{ "message": "..." }` |
| 422         | Validation failure         | `{ "message": "...", "errors": { "field": ["..."] } }` |
| 429         | Rate limited               | `{ "message": "..." }` |
| 500         | Server error               | `{ "message": "Server Error" }` (no sensitive details in production) |

- Validation errors always use Laravel’s standard 422 format (`message` + `errors` object).
- Frontend maps 422 `errors` onto form fields (Zod / form library).
- Do not leak stack traces, SQL, or internal paths in production responses.

### 10.3 Implementation Notes

- Prefer a shared base controller or response helper/trait for consistent wrapping when needed.
- API Resources remain the primary transformation layer.
- Document the contract in OpenAPI (Scramble / Scribe) so it stays the source of truth.

---

## 11. Testing Strategy

### 11.1 Backend

| Type | Scope | Tools |
|------|--------|-------|
| Feature / API tests | Full HTTP request → response (auth, validation, status codes, JSON structure) | PHPUnit / Pest |
| Unit tests | Services, Actions, Repositories, pure logic | PHPUnit / Pest |
| Policy tests | Authorization rules | PHPUnit / Pest |

- Prefer Pest if the team likes its style; otherwise PHPUnit is fine. Follow the official Laravel testing docs.
- Use `RefreshDatabase` or targeted migrations carefully (respect additive-only rules; prefer factories + seeders).
- Factories for all models that need test data.
- Test the MD5 → modern hash upgrade path explicitly.
- JWT: test login, refresh, protected routes, and invalid/expired tokens.

### 11.2 Frontend

| Type | Scope | Tools |
|------|--------|-------|
| Component tests | UI behaviour, props, emits, basic interactions | Vue Test Utils + Vitest |
| Composable / store tests | Business logic in isolation | Vitest |
| Type checks | Strict TypeScript across `src/` and Vue SFCs | `vue-tsc --noEmit` |
| E2E | Critical user flows and full cross-surface propagation of every create/edit/delete (see §11.4) | Playwright or Cypress |

- Colocate or mirror source structure for tests.
- Mock API calls in unit/component tests; use real API only in E2E.
- Write tests in TypeScript.
- A failing type check fails the build. Do not silence it with `any` or `@ts-ignore`.

### 11.3 Running Tests

```bash
# Backend (inside container)
docker compose exec app php artisan test
# or
docker compose exec app ./vendor/bin/pest

# Frontend
docker compose exec node npm run test
docker compose exec node npm run typecheck
```

Tests must pass in CI before merge.

### 11.4 End-to-End Testing Requirements

When creating end-to-end tests, make sure to cover all scenarios. Be very thorough.

If something is created or edited (or deleted, archived, restored, status-changed, etc.), verify that the change is reflected in **every window, page, and response that displays, prints, or consumes that data**. A test that only checks the screen where the change was made is incomplete.

For every mutation, cover at minimum:

- **The originating form/page**: success state, validation errors (422 mapped to fields), and post-save redirect or refresh.
- **List and index pages**: the new or changed record appears with correct values, including after pagination, sorting, search, and filter changes.
- **Detail/show pages**: all fields display the updated values.
- **Related and dependent pages**: dashboards, summaries, counts, totals, reports, dropdowns/selects, lookups, and any page that embeds or references the record.
- **Printable and exportable output**: print views, PDFs, and CSV/Excel exports show the updated data.
- **API responses**: every endpoint that returns or consumes the data (list, show, nested includes, related resources) returns the updated values in the correct `data` / `meta` / `links` shape.
- **Cached and derived data**: caches are invalidated, and computed or aggregated values (counts, totals, statuses) are recalculated.
- **Other sessions and roles**: users with different roles/permissions see (or do not see) the change as the Policies dictate, including from a second browser session.
- **Persistence**: values survive a full page reload and re-login.
- **Negative paths**: unauthorized (403), not found (404), rate-limited (429), expired token (401 → refresh → retry), and failed saves leave all surfaces unchanged.

Before considering a feature's E2E coverage complete, list every place the data is displayed, printed, or consumed, and confirm each one has an assertion.

---

## 12. Queues & Background Jobs

- Use Laravel queues for any work that is slow, external, or can be deferred (emails, notifications, heavy imports, image processing, etc.).
- Default driver in production: **Redis** (or database driver only for very simple setups).
- Run a dedicated `queue` service / worker container in Docker Compose.
- Always implement `failed()` / failed-job handling and monitor the `failed_jobs` table.
- Prefer small, focused Job classes; put orchestration in Actions/Services if needed.
- Never perform long-running work inside a web request.

---

## 13. Caching Strategy

| What | Where | Notes |
|------|--------|-------|
| Config, routes, views | `php artisan optimize` / file or Redis | Standard Laravel production practice |
| Query results / computed data | Redis (preferred) or file | Use `Cache::remember` with sensible TTLs |
| API responses | Only for truly public, cacheable endpoints | Respect auth; prefer short TTLs + cache tags when available |
| Frontend | HTTP caching headers + Vite build hashing | — |

- Prefer Redis when the `redis` service is present.
- Invalidate explicitly on writes that affect cached data.
- Avoid caching personalized or sensitive data without proper keys and TTLs.

---

## 14. Logging & Observability

- Use Laravel’s structured logging (JSON in production when possible).
- Log context (user id, request id, relevant IDs) on important actions and failures.
- **Laravel Telescope** — development / staging only; never expose it in production.
- Exception reporting: use a proper service (Sentry, Flare, Bugsnag, etc.) or at least robust log aggregation in production.
- Do not log passwords, tokens, or full payment payloads.
- Correlate frontend errors with backend request IDs when feasible.

---

## 15. Environment & Configuration

- All environment-specific values live in `.env` (never committed).
- Use Laravel’s `config/*.php` files; read via `config('...')`, never `env()` outside config files.
- Provide a committed `.env.example` with every required key and sensible defaults/comments.
- Docker Compose uses `env_file` or `environment` entries; keep secrets out of images.
- Distinct environments: `local`, `testing`, `staging`, `production`.
- `APP_DEBUG=false` and proper `APP_KEY` / `JWT_SECRET` in any shared or production environment.

---

## 16. Security Checklist (Beyond Auth)

- CORS locked to the SPA origin(s) only.
- Rate limiting on all routes; stricter limits on auth endpoints.
- Mass-assignment protection (`$fillable` / `$guarded`) on every model.
- Policies / Gates for every non-public action.
- HTTPS only in staging and production.
- Security headers (via Nginx or middleware): at least `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`; consider a CSP.
- Validation on every write endpoint (Form Requests).
- Prepared statements via Eloquent / query builder (no raw concatenated SQL).
- Dependencies kept up to date; run `composer audit` / `npm audit` in CI.

---

## 17. CI/CD Outline

Typical pipeline stages (GitHub Actions, GitLab CI, etc.):

1. **Install** — Composer & npm dependencies (cached).
2. **Static analysis** — PHPStan / Larastan, ESLint, Prettier check, TypeScript check.
3. **Tests** — Backend feature + unit tests; frontend unit/component tests.
4. **Build** — Frontend production assets; Docker images (multi-stage).
5. **Push images** — To container registry (on main / tags).
6. **Deploy** — Staging on merge to main; production on tag / manual approval.

- Run the same Docker images locally and in CI where practical.
- Migrations are applied as a controlled release step, not silently on every deploy.
- Block merges on failing tests or static analysis.

---

## 18. Optional / Later Concerns

These are intentionally out of scope until requirements appear:

| Topic | Notes |
|-------|-------|
| File / media storage | Local disk for dev; S3-compatible for production when needed |
| Internationalization (i18n) | Vue I18n + Laravel localization when multi-language is required |
| Advanced roles & permissions | Spatie Permission or equivalent; start with Policies |
| OpenAPI docs | Scramble or Scribe generated from code |
| Search | Scout + Meilisearch / Algolia / Typesense when full-text search is needed |
| Multi-tenancy | Only if product requirements demand it |

---

## 19. Approved Rebuild Decisions (2026-11-04)

These decisions override earlier assumptions in this document where they disagree.
Each one is recorded with reasons and evidence in [`DECISIONS.md`](./DECISIONS.md).

### 19.1 Project location and scope

- The new application lives in `/home/dave/its-v2`.
- `/home/dave/its-helpdesk` is ignored. No file is copied from it.
- The application is built from scratch with the official Laravel 13 installer.

### 19.2 Stack confirmations

The stack in sections 2 and 3 stands.

- Laravel 13 with a strict REST API at `/api/v1`.
- Vue 3.5 single-page application with Vite.
- TypeScript in strict mode for all frontend code. Checked with `vue-tsc`.
- shadcn-vue 2.x with Reka UI, Tailwind CSS 4, and vee-validate with Zod.
- JWT authentication with `php-open-source-saver/jwt-auth`.
- Docker and Docker Compose.

Still excluded: Inertia, Sanctum as the primary auth system, and Breeze.

### 19.3 Second database connection: payroll

The PIS sync needs a second connection.

| Item | Value |
|---|---|
| Connection name | `payroll` |
| Host | Same host as the main database |
| Database | `jblmrhpayroll` |
| Table | `payroll` |
| Join key | `its.users.id = payroll.id` |
| Department mapping | `payroll.department` maps to `its.locations.id` |
| Access | Read-only |

Rules:

- The sync is one explicit service. It is not a middleware side effect.
- The sync validates that the mapped `locations` row exists.
- The sync fails closed when `department = 0`.
- The sync never recurses.

### 19.4 Schema additions required by the decisions

Beyond the columns in `PROMPT.md` §9.7, the schema adds:

| Column | Table | Purpose |
|---|---|---|
| `units_count` | `tickets` | Holds the units value. `assigned_gid` stays a legacy group ID. |
| `password_v2` | `users` | Holds the modern password hash during the dual run. Pending approval of `DECISIONS.md` P1. |

`users.group_id` and `tickets.assigned_gid` stay readable.
No new query filters or groups by `users.group_id`.

### 19.5 Retired features

Teams are retired.
The following modules return a "This feature is retired" page:

- `groups`
- `add_group`
- `edit_group`
- `process_add_group`
- `process_edit_group`
- `process_delete_group`
- `assigned_to_group`

The `groups` table, its rows, the `users.group_id` column, and the affected `mods` rows all stay.
No `mods` row is deleted.

### 19.6 Module registry

- The live `mods` table has 69 rows. All 69 are kept.
- `parent = ''` and `parent = 'none'` both mean top-level.
- The registry is cached and invalidated when `mods` changes.
- Every legacy path maps to a controller route or a "not migrated" page.

### 19.7 Module-specific deviations

| Module | Deviation | Reason |
|---|---|---|
| `change_password` | Alias redirects to `?p=user_options` | The row is mis-registered. `DECISIONS.md` C7. |
| `assigned_to_group` | Retired page | Teams retired. `DECISIONS.md` T1. |
| Dashboard group card | Removed, no replacement | Teams retired. `DECISIONS.md` T1. |
| User add/edit | No group field. Insert writes `group_id = 1`. | Teams retired. `DECISIONS.md` T2. |
| Ticket create (staff) | Writes `assigned_gid = 0`. No group log. | `DECISIONS.md` D27. |

### 19.8 Administrator protection

- The protected administrator set is the 8 users with `acl_id = 9`.
- `users.id = 1` is not protected. Production shows `acl_id = 1`.
- The user list still hides `users.id = 1` from display.
- Hiding and protecting are separate rules.

### 19.9 Notifications

- Notifications are in-app through a bell in the top menu.
- The list is scoped to the session user.
- `state` keeps its legacy meaning: 1 = new, 2 = read.
- No password, token, or secret is stored in a notification.
- The 5 legacy rows with plaintext passwords are redacted during migration.

---

This architecture prioritizes clarity, testability, long-term maintainability, and safety with an existing development database that already holds live data.

