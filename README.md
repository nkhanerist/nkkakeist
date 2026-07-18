# NKKakeist

NKKakeist is a personal finance and asset management web application that turns fragmented financial exports into trustworthy, explainable records.

It is built for a common problem: banks, credit cards, mobile payments, e-money, investments, and reward points all describe money differently. Naive imports can count the same spending twice or produce balances that cannot be reconciled. NKKakeist keeps every import reviewable and makes the accounting decisions explicit.

This project is an **OpenAI Build Week** submission in the **Apps for Your Life** category.

## What it does

- Manages cash, bank, credit card, e-money, payment, investment, and point accounts.
- Tracks income, expenses, and transfers without double-counting internal money movements.
- Imports Money Forward CSV, Mobile Suica PDF, JRE POINT JSON, and balance snapshot JSON files.
- Uses a staged upload, parse, validate, preview, and commit workflow.
- Detects duplicate and unresolved rows with row-level explanations.
- Supports category review and reusable classification rules.
- Reconciles calculated ledger balances against official balances.
- Displays monthly and yearly income, expenses, trends, and account balances.
- Keeps every user-owned resource isolated to its authenticated user.

The core safety rule is simple: when an imported financial record cannot be explained, it is not silently committed.

## Built with

- Laravel 13 and PHP 8.3
- Inertia.js, React, and TypeScript
- Tailwind CSS
- MySQL 8.4
- Docker Compose and Vite
- PHPUnit
- Codex with GPT-5.5 and GPT-5.6

## Quick start

Prerequisites:

- Docker with Docker Compose
- Ports `8000`, `5190`, and `13306` available locally

Start the application from the repository root:

```bash
docker compose up --build
```

The container installs Composer and npm dependencies, creates `src/.env`, generates an application key, runs migrations, and starts Laravel and Vite.

Seed the included fictional demo data:

```bash
docker compose exec app php artisan db:seed
```

Open [http://localhost:8000](http://localhost:8000). In the local Docker environment, the login screen provides a development-only one-click login. The seeded account is `developer@example.test`; it is not used outside local or test environments.

## Verification

Run the automated test suite:

```bash
docker compose exec app php artisan test
```

Run the frontend production build:

```bash
docker compose exec app npm run build
```

Run the PHP formatter:

```bash
docker compose exec app ./vendor/bin/pint
```

Repository wrappers under `scripts/` provide the same container-based workflow:

```bash
scripts/dev artisan test
scripts/dev npm run build
scripts/dev pint
```

## Import safety model

Every supported source follows the same lifecycle:

1. Upload an untrusted file.
2. Parse the source-specific format.
3. Normalize records while preserving useful raw fields.
4. Resolve accounts, categories, and transfers.
5. Validate values and detect deterministic duplicate candidates.
6. Preview every row with an explanation.
7. Commit only confirmed, valid, non-duplicate rows.

Transfers are deliberately separated from income and expenses. They affect account balances but are excluded from income and expense aggregation, preventing card payments and wallet charges from being counted as new spending.

## How Codex and GPT-5.6 were used

The initial foundation was developed with Codex using GPT-5.5. From July 2026 onward, Codex with GPT-5.6 was used throughout the project to:

- turn real-world financial inconsistencies into explicit domain rules;
- design the staged import and reconciliation architecture;
- implement source-specific parsers and deterministic duplicate detection;
- keep controllers small by separating validation, actions, and services;
- diagnose data inconsistencies and design safe, auditable corrections;
- write and refine feature and unit tests;
- identify and remove an N+1 query in category review; and
- verify user-facing flows in the browser.

Codex was used as a development collaborator, not as a runtime dependency. NKKakeist does not send personal financial data to an AI service.

## Architecture

- Controllers handle HTTP concerns, authorization entry points, and Inertia responses.
- Form Requests validate input and related-resource ownership.
- Actions define use cases and transaction boundaries.
- Services contain parsing, aggregation, classification, duplicate detection, diagnostics, and reconciliation logic.
- Feature tests cover authentication, user isolation, CRUD, imports, dashboards, and correction workflows.
- Unit tests cover parsers and balance calculations.

See [docs/project-guide.md](docs/project-guide.md) for the public product and domain overview.

## Data and privacy

The repository contains only fictional seed and test data. Real exports, uploaded statements, environment files, database volumes, logs, and backups are excluded from version control.

Do not expose a development instance containing personal data to the internet. Use a separate database with fictional demo data for public demonstrations.

## License

NKKakeist is available under the [MIT License](LICENSE).
