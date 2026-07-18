# AGENTS.md

## 1. Purpose and precedence

This repository contains a personal finance and asset management web application.

The application manages:

- accounts such as cash, banks, credit cards, e-money, code payments, securities, and points
- income, expense, and transfer transactions
- categories, subcategories, and classification rules
- dashboards and account balances
- file-based imports with preview, validation, duplicate detection, and commit
- diagnostics and safe correction of existing personal finance data

Use the following sources in this order:

1. The user's current request
2. This `AGENTS.md` for durable development rules
3. `docs/project-guide.md` for current behavior, confirmed domain decisions, data findings, and roadmap
4. Existing code and tests

Do not duplicate changing product decisions in this file. Update `docs/project-guide.md` when the application's current behavior, operating rules, real-data findings, or roadmap changes.

---

## 2. Working principles

- Communicate with the user in Japanese.
- Lead with the result and keep explanations concise and concrete.
- Inspect the existing implementation and tests before designing a change.
- Prefer a focused change that fits the current architecture over a broad redesign.
- Make reasonable, reversible assumptions when the answer is discoverable or low risk.
- State assumptions when they materially affect behavior, data, or scope.
- Ask before making a decision that would significantly expand scope or irreversibly change data.
- Preserve unrelated user changes in a dirty worktree.
- Do not commit, push, or modify real application data unless the user explicitly requests it or it is clearly part of the requested operation.

Interpret requests as follows:

- Explain, review, diagnose, or report: inspect and report; do not implement or mutate data unless asked.
- Implement, fix, or build: make the change and verify it in proportion to risk.
- Correct or migrate real data: inspect exact targets, back up first, apply the smallest auditable change, and verify the result.

For multi-step or risky work, keep a short working plan and update it as facts change. Trivial changes do not need ceremonial planning.

---

## 3. Technical stack and environment

Core stack:

- Laravel
- Inertia.js
- React and TypeScript
- Tailwind CSS
- MySQL
- Vite
- PHPUnit
- Docker Compose

The application lives under `src/`. Docker Compose is the standard development environment.

- Prefer `docker compose` commands from the repository root.
- Run PHP, Composer, Artisan, Node.js, and npm workflows in the `app` container.
- Do not assume host-installed PHP, Composer, Node.js, npm, or MySQL.
- Host tools may be used for read-only inspection or when a repository workflow explicitly requires them.

Common commands:

```bash
docker compose up --build
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse
docker compose exec app npm run build
docker compose exec app npm run lint
```

Run only configured quality tools. Repository wrappers under `scripts/` may be used when they express the same container-based workflow more clearly.

Useful local endpoints when available:

- Application: `http://localhost:8000`
- Browser automation: `http://localhost:8080/browser-automation`
- DB compare: `http://localhost:8080/db-compare`

---

## 4. Repository structure

Follow Laravel's standard structure and existing project conventions.

Backend:

- `src/app/Http/Controllers/` — HTTP entry points and Inertia responses
- `src/app/Http/Requests/` — validation and request authorization
- `src/app/Actions/` — use-case orchestration
- `src/app/Services/` — reusable domain, import, aggregation, and diagnostic logic
- `src/app/Models/` — Eloquent models and relationships
- `src/app/Policies/` — resource authorization
- `src/routes/web.php` — web routes
- `src/database/` — migrations, factories, and seeders

Frontend:

- `src/resources/js/Pages/` — Inertia pages
- `src/resources/js/Components/` — reusable components
- `src/resources/js/Layouts/` — shared layouts
- `src/resources/js/types/` — shared TypeScript types
- `src/resources/css/` — styles

Tests:

- `src/tests/Feature/` — HTTP, authorization, integration, and persistence behavior
- `src/tests/Unit/` — isolated domain and parsing behavior

Runtime files under `src/storage/` and browser automation artifacts are not source files and must not be committed.

---

## 5. Architecture guidelines

Keep responsibilities explicit without introducing abstraction for its own sake.

- Controllers: HTTP concerns, authorization entry point, response construction, and orchestration only.
- FormRequests: validation and request-level authorization.
- Actions: meaningful application use cases and transaction boundaries.
- Services: reusable calculations, parsing, classification, duplicate detection, diagnostics, and aggregation.
- Models: relationships, casts, scopes, and small model-specific behavior.
- Policies: authorization for user-owned resources.

Prefer:

- readable Eloquent and query builder code
- constructor injection
- typed properties, parameters, and return values
- small, cohesive methods
- existing patterns already proven by tests

Avoid:

- fat controllers
- large aggregation or parsing logic in controllers
- repository layers without a current need
- speculative frameworks or generalized abstractions
- refactoring unrelated areas during a focused task

Extract logic when it has meaningful domain behavior, reuse, independent test value, or would otherwise make an HTTP entry point difficult to understand.

---

## 6. Domain and data rules

Primary models include:

- User
- Account
- Category and Subcategory
- Transaction
- Import and ImportRow
- ClassificationRule
- future or optional Budget, RecurringTransaction, and AccountSnapshot

Confirmed transaction concepts:

- `income` — income
- `expense` — actual consumption or spending
- `transfer` — movement between accounts, including payment reallocation and charges where appropriate

Use `account_id` as the source account and `transfer_account_id` as the destination for transfers unless the confirmed design changes. Transfers normally have no category and are excluded from income/expense aggregation while still affecting account balances.

Detailed and evolving rules—such as point usage, code-payment reallocation, Suica charges, account balance meaning, and dashboard treatment—belong in `docs/project-guide.md`.

### User data isolation

All user-owned reads and writes must be scoped to the authenticated user. This includes accounts, categories, subcategories, transactions, imports, import rows through their import, classification rules, and future user-owned models.

- Never expose, resolve, attach, or mutate another user's resource.
- Validate ownership of related IDs, not only the primary resource.
- Cover ownership boundaries with Feature Tests when a route or mutation is involved.

### Database changes

- Use English table and column names.
- Add foreign keys and indexes where supported by actual access patterns.
- Prefer evidence from queries or profiling before adding performance indexes.
- Use soft deletes where recovery and auditability matter, especially for transactions.
- Keep migrations forward-only, focused, and safe for existing data.
- Update `.env.example` when introducing configuration; never commit `.env`.

### Real-data changes

Personal finance data is high-value and difficult to reconstruct.

Before material correction, migration, or bulk mutation of the development database:

1. Resolve the exact user and record set with read-only queries.
2. Create a backup with `bash scripts/db-backup.sh` unless the operation is already safely isolated by the test database.
3. Prefer a transaction and an auditable script or command over ad-hoc statements.
4. Verify counts, totals, relationships, and invariants after the change.
5. Record confirmed findings and corrections in `docs/project-guide.md`.

Do not use production-like personal data in automated tests.

---

## 7. Import and duplicate detection

Import is domain-critical. Preserve the staged workflow:

1. upload
2. source-specific parse
3. normalize and preserve useful raw data
4. validate and resolve accounts/categories/transfers
5. detect duplicate candidates
6. preview with row-level explanations
7. commit confirmed rows

Current sources include Money Forward CSV and Mobile Suica PDF. New sources should reuse the shared import lifecycle and isolate source-format interpretation in a parser or service.

Requirements:

- Treat uploaded content as untrusted.
- Validate file type, size, structure, dates, and required fields.
- Preserve `raw_payload` where it supports troubleshooting and traceability.
- Keep duplicate identifiers and hashes deterministic and explainable.
- Keep duplicate candidates visible in preview and skip them safely at commit.
- Keep parsing, duplicate detection, and commit logic out of controllers.
- Ensure reparse and recommit behavior remains explicit and tested.

For future automatic acquisition, reuse the same parse/validate/preview pipeline. Start with acquisition and preview; do not auto-commit financial transactions until safety conditions are explicitly agreed and documented.

---

## 8. Frontend and UX

Implement main application screens with Inertia + React + TypeScript. Blade is limited to framework bootstrapping, authentication scaffolding details, or an explicitly justified minimal page.

- Prefer server-driven Inertia props and local component state.
- Avoid adding a global state library without a demonstrated need.
- Reuse established layouts, form controls, filters, tables, cards, and feedback patterns.
- Keep forms predictable, validation visible, and filter state understandable.
- Use Tailwind CSS and match the existing visual language.
- Favor clarity and operational confidence over decoration.
- Keep TypeScript types explicit at backend/frontend boundaries.

For UI changes, verify at least:

- the relevant desktop flow in the browser
- validation and error states affected by the change
- responsive behavior when layout changes materially
- absence of new browser console errors

Use screenshots when they add review value, not as a substitute for behavioral verification.

---

## 9. Testing and verification

Use PHPUnit through `php artisan test` as the primary automated test framework.

Prioritize Feature Tests for:

- authentication and authorization
- user data isolation
- CRUD and validation flows
- import preview and commit
- duplicate detection
- dashboard visibility and aggregation
- real route-level regressions

Use Unit Tests for parsers, calculations, normalization, matching, and other isolated domain behavior.

Verification should be proportional and progressive:

1. Run syntax or formatting checks for touched files.
2. Run the narrowest relevant tests while iterating.
3. Run the full test suite when shared domain logic, persistence, imports, authorization, or broad behavior changes.
4. Run `npm run build` for TypeScript or frontend changes.
5. Perform browser verification for user-facing changes.

Do not claim a check passed unless it was actually run. If a configured check cannot run, report the reason and the remaining risk.

---

## 10. Standard delivery workflow

Use this as the default workflow, adapting it to task size:

1. Orient
   - inspect `git status`
   - read the relevant code, tests, and `docs/project-guide.md` sections
   - identify existing user changes and current behavior
2. Decide
   - state material assumptions
   - choose the smallest coherent design that fits existing patterns
   - identify data-safety or compatibility risks
3. Implement
   - keep the diff focused
   - preserve authorization, validation, traceability, and user isolation
   - add or update tests with the behavior
4. Verify
   - targeted checks first, then broader checks according to risk
   - use real supplied files read-only when they are necessary to validate parsers
   - do not mutate real data merely to prove code works
5. Document
   - update `docs/project-guide.md` for changed behavior, confirmed rules, real-data findings, or roadmap state
   - avoid creating scattered temporary design notes
6. Hand off
   - summarize the outcome, important files, tests, schema/routes impact, assumptions, and deferred work
7. Commit
   - commit only when requested
   - review the staged scope and use a short, focused, imperative message

---

## 11. Git and review

- Keep commits focused on one logical change where practical.
- Do not mix unrelated cleanup into a feature commit.
- Never commit `.env`, personal exports, database backups, uploaded statements, or automation artifacts.
- Do not discard or overwrite changes that were already in the worktree unless the user explicitly asks.
- Use non-destructive Git operations by default.

Pull request descriptions must be written in Japanese and should include:

- purpose and scope
- important implementation decisions
- migrations, seeders, and route impact
- tests and manual checks performed
- screenshots when useful for UI review
- assumptions and deferred work

---

## 12. Communication and handoff

All user-facing communication, PR descriptions, review comments, and implementation summaries must be in Japanese. Code identifiers and schema names remain in English.

For implementation tasks, report only applicable items:

- outcome and notable behavior
- important changed files
- migration, seeder, configuration, and route impact
- tests and browser checks actually run
- data changes or confirmation that none were made
- assumptions, risks, and deferred items
- whether changes are committed

Avoid repeating unchanged project background or listing every touched file when a smaller grouped summary is clearer.

---

## 13. Anti-goals

Unless explicitly requested and justified by the current roadmap, do not:

- build direct bank or card integrations as an incidental extension of another task
- auto-commit externally acquired financial transactions
- introduce microservices or repository layers
- replace Inertia pages with Blade full-page implementations
- add heavy frontend state management
- over-engineer bookkeeping beyond confirmed domain rules
- perform broad unrelated refactors
- optimize without evidence of a real bottleneck
