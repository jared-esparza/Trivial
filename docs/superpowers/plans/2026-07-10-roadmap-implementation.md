# Rueda Quiz Roadmap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Incorporar cuentas opcionales, packs temáticos versionados, participación segura, estadísticas e historial sin cambiar la geometría ni la experiencia anónima actual.

**Architecture:** Mantener un monolito modular PHP/PDO con `public/api.php` como adaptador HTTP delgado. Persistir usuarios, contenido, participantes y eventos de forma relacional; conservar JSON solo para el estado transitorio del motor y el snapshot inmutable de categorías de cada sala.

**Tech Stack:** PHP 8.2+, PDO SQLite/MySQL, JavaScript vanilla sin build, CSS propio y test runner PHP del repositorio.

## Global Constraints

- Mantener juego local y online anónimo.
- Mantener exactamente seis categorías y cuatro opciones por pregunta.
- No modificar geometría SVG, grafo, overlays, fullscreen, marcador ni preferencias salvo para consumir categorías dinámicas.
- No añadir framework, Composer ni proceso de build.
- Usar `mail()` en producción y outbox local fuera de `public/`.
- Implementar cada comportamiento con ciclo RED→GREEN→REFACTOR.

---

### Task 1: Migraciones y composición modular

**Files:**
- Create: `src/Database/MigrationRunner.php`
- Create: `database/migrations/001_initial.php`
- Create: `database/migrations/002_identity.php`
- Create: `database/migrations/003_packs.php`
- Create: `database/migrations/004_participants_stats.php`
- Modify: `src/Database.php`
- Modify: `src/bootstrap.php`
- Test: `tests/run.php`

**Interfaces:**
- Produces: `MigrationRunner::__construct(PDO $pdo, string $directory)` and `MigrationRunner::migrate(): void`.
- Produces: `app_pdo(): PDO` executing pending migrations exactly once.

- [ ] Add a test that creates an in-memory SQLite database, runs migrations twice, and asserts one row per migration in `schema_migrations` plus all required tables.
- [ ] Run `C:\xampp\php\php.exe tests\run.php` and confirm the new test fails because `MigrationRunner` does not exist.
- [ ] Implement transactional numbered migrations and replace runtime `CREATE TABLE IF NOT EXISTS` orchestration with the runner.
- [ ] Run the full suite and PHP lint; keep all existing tests green.
- [ ] Commit `chore: add versioned database migrations`.

### Task 2: Cuentas, sesiones y correo

**Files:**
- Create: `src/Auth/UserRepository.php`
- Create: `src/Auth/SessionRepository.php`
- Create: `src/Auth/AccountTokenRepository.php`
- Create: `src/Auth/AuthService.php`
- Create: `src/Auth/Authorization.php`
- Create: `src/Mail/Mailer.php`
- Create: `src/Mail/NativeMailer.php`
- Create: `src/Mail/LocalOutboxMailer.php`
- Create: `bin/create-admin.php`
- Modify: `src/bootstrap.php`
- Test: `tests/run.php`

**Interfaces:**
- Produces: `AuthService::register(string $email, string $password): array`, `verify(string $token): void`, `login(string $email, string $password): array`, `requestPasswordReset(string $email): void`, `resetPassword(string $token, string $password): void` and `logout(string $sessionToken): void`.
- Produces: `Authorization::requireVerifiedUser(?array $user): array` and `requireAdmin(?array $user): array`.

- [ ] Add failing tests for normalized unique email, password hashing, verification token expiry, login before/after verification, password reset revoking sessions, disabled users and last-admin protection.
- [ ] Run the focused suite and confirm failures correspond to missing auth classes.
- [ ] Implement repositories and service with opaque random tokens stored only as SHA-256 hashes, 24-hour verification, 1-hour reset and 30-day idle sessions.
- [ ] Implement `Mailer` transports and the idempotent CLI admin creator/promoter.
- [ ] Run all tests and lint.
- [ ] Commit `feat: add account and session services`.

### Task 3: API y pantallas de autenticación

**Files:**
- Create: `src/Http/ApiRequest.php`
- Create: `src/Http/ApiResponse.php`
- Create: `src/Http/ApiRouter.php`
- Create: `src/Http/AuthController.php`
- Create: `public/account.php`
- Create: `public/assets/account.js`
- Modify: `public/api.php`
- Modify: `public/index.php`
- Modify: `public/admin.php`
- Modify: `public/assets/styles.css`
- Test: `tests/run.php`

**Interfaces:**
- Produces routes `/auth/register`, `/auth/verify`, `/auth/login`, `/auth/logout`, `/auth/password/forgot`, `/auth/password/reset` and `/auth/me`.
- Produces JSON errors `{ "error": { "code": string, "message": string, "fields"?: object } }`.

- [ ] Add failing integration tests for cookies, CSRF, unauthenticated/admin responses and uniform status codes.
- [ ] Implement router, request context, session cookie and CSRF middleware, then move auth endpoints out of `public/api.php`.
- [ ] Add account UI and replace the shared admin key with an authenticated admin session.
- [ ] Verify anonymous home/game flows still render and run the full suite.
- [ ] Commit `feat: expose optional account authentication`.

### Task 4: Packs, revisiones e intercambio

**Files:**
- Create: `src/Packs/PackRepository.php`
- Create: `src/Packs/PackService.php`
- Create: `src/Packs/PackImporter.php`
- Create: `src/Packs/PackExporter.php`
- Create: `src/Http/PackController.php`
- Create: `public/packs.php`
- Create: `public/assets/packs.js`
- Modify: `src/bootstrap.php`
- Modify: `public/api.php`
- Test: `tests/run.php`

**Interfaces:**
- Produces immutable active revisions with six slots `0..5`; editing an active pack creates a draft revision.
- Produces CSV columns `pack_name,category_slot,category_key,category_name,category_color,question,option_a,option_b,option_c,option_d,correct`.
- Produces JSON `{format_version:1, pack:{name,categories:[...]}}`.

- [ ] Add failing tests for ownership, admin override, draft completeness, revision immutability, soft deletion and system color schemes.
- [ ] Add failing round-trip tests for CSV and JSON, including rejection of imported ownership/visibility metadata and duplicate-slot conflicts.
- [ ] Implement pack repositories/services and import/export; every import creates a new private draft.
- [ ] Seed idempotently the Classic pack, demo questions, and current classic/alternative public color schemes.
- [ ] Add pack management UI and admin controls; run tests/lint.
- [ ] Commit `feat: add versioned thematic packs`.

### Task 5: Motor por slots y salas con snapshot

**Files:**
- Modify: `src/GameEngine.php`
- Modify: `src/RoomRepository.php`
- Modify: `src/QuestionRepository.php`
- Create: `src/Game/RoomService.php`
- Create: `src/Game/ParticipantTokenService.php`
- Modify: `src/Http/ApiRouter.php`
- Modify: `public/assets/app.js`
- Modify: `public/index.php`
- Test: `tests/run.php`

**Interfaces:**
- Produces room categories as `[{slot:int,key:string,name:string,color:string}]` and internal board category IDs `slot_0..slot_5`.
- `POST /rooms` accepts optional `packId` and `colorSchemeId` and returns `participantToken` once.
- `POST /rooms/{code}/actions` requires `expectedVersion` and `X-Participant-Token`; online tokens map to one participant and local controller tokens may act for all slots.

- [ ] Add failing regression tests proving classic slot distribution retains all existing adjacency, reachability and geometry invariants.
- [ ] Add failing tests for public/private pack selection, frozen revision/snapshot, token hashing, cross-team denial and stale-version `409`.
- [ ] Adapt category logic from semantic slugs to six internal slots without changing coordinates or graph shape.
- [ ] Implement transactional room creation/actions and frontend dynamic categories/token persistence.
- [ ] Run all PHP/JS checks and manually exercise anonymous local/online creation.
- [ ] Commit `feat: bind secure rooms to pack snapshots`.

### Task 6: Eventos, informe e historial

**Files:**
- Create: `src/Stats/AnswerEventRepository.php`
- Create: `src/Stats/StatisticsService.php`
- Create: `src/Http/HistoryController.php`
- Create: `public/history.php`
- Create: `public/assets/history.js`
- Create: `bin/cleanup.php`
- Modify: `src/Game/RoomService.php`
- Modify: `public/assets/app.js`
- Test: `tests/run.php`

**Interfaces:**
- Produces per-team totals, correct/incorrect percentages, category breakdown, longest correct streak, wedges, winner and duration.
- Produces `/rooms/{code}/statistics`, `/me/games` and `/me/games/{id}`.

- [ ] Add failing tests proving answer event and state update are atomic and duplicate/stale actions create no event.
- [ ] Add failing metric tests covering zero answers, category percentages, streak reset, winner/duration, local ownership and online membership.
- [ ] Implement event persistence and report queries; render final overlay and authenticated history.
- [ ] Implement account anonymization and cleanup of fully anonymous finished rooms older than 30 days.
- [ ] Run all checks and commit `feat: add game statistics and account history`.

### Task 7: Consolidación y verificación

**Files:**
- Modify: `public/api.php`
- Modify: `public/assets/app.js`
- Modify: `config.example.php`
- Modify: `database/schema.mysql.sql`
- Modify: `README.md`
- Modify: `PROJECT_CONTEXT.md`
- Test: `tests/run.php`

**Interfaces:**
- Removes shared `admin_key` routes and legacy global-question replacement semantics.
- Preserves existing room URLs and anonymous Classic defaults.

- [ ] Add regression assertions that legacy admin-key access is absent and current board/fullscreen/UI contracts remain present.
- [ ] Remove obsolete compatibility code and split only non-game page scripts; keep board rendering in `app.js`.
- [ ] Verify SQLite fresh install, migration rerun, admin bootstrap, CSV/JSON round trip, PHP lint, JS syntax and the full automated suite.
- [ ] Verify MySQL schema equivalence statically and document production migration, mail, admin creation and cleanup commands.
- [ ] Commit `docs: finalize modular roadmap rollout`.

