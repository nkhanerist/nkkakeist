# NKKakeist

NKKakeist is a personal finance and asset management application that turns fragmented financial exports into reviewable, explainable records.

Banks, credit cards, mobile payments, e-money, investment accounts, and reward programs all describe money differently. A naive import can count the same purchase twice, confuse a card payment with new spending, or leave balances that cannot be reconciled. NKKakeist makes those decisions explicit and keeps every import inspectable before it changes the ledger.

This project was created for **OpenAI Build Week** in the **Apps for Your Life** category.

## Highlights

- Manage cash, bank, credit card, e-money, payment, securities, pension, and point accounts.
- Track `income`, `expense`, and `transfer` records without double-counting internal money movements.
- Import Money Forward CSV, Mobile Suica PDF, JRE POINT JSON, official balance JSON, and Money Forward asset-history CSV files.
- Use a staged upload, parse, validate, preview, and explicit commit workflow.
- Detect duplicate, unresolved, conflicting, and replacement candidates with row-level explanations.
- Review uncategorized transactions and create reusable classification rules.
- Compare calculated ledger balances with official bank and card balances.
- Store daily account valuations and per-instrument investment positions.
- Explore monthly reports, net-worth history, account trends, and security-level valuation history.
- Use the complete interface in Japanese or English, with the selected language kept across navigation.
- Keep every user-owned resource isolated to its authenticated user.

The core safety rule is simple: **if an imported financial record cannot be explained, it is not silently committed.**

## Screens and workflows

### Explainable imports

Every source uses the same safety-oriented lifecycle:

1. Upload an untrusted file.
2. Parse the source-specific format.
3. Normalize records while preserving useful raw fields.
4. Resolve accounts, categories, and transfer direction.
5. Validate values and detect deterministic duplicate candidates.
6. Preview every row with its status and explanation.
7. Commit only confirmed, valid, non-duplicate rows.

Balance snapshots support an explicit same-day replacement flow. A different value for an existing account and date is never overwritten without review.

### Monthly reporting

The monthly dashboard includes:

- income, expenses, and monthly balance by currency;
- previous-month and previous-year comparisons;
- transaction count, average expense, and largest expense;
- top merchants;
- uncategorized, unconfirmed, and pending-import indicators;
- account and category drill-down links; and
- net-worth change between actually stored snapshot dates.

No missing valuation dates are interpolated.

Past months can move through open, reviewed, and closed states. Account-level confirmations make delayed card, bank, and investment updates explicit before closing, while later data changes are detected and surfaced for review.

### Product interface

The application provides a bilingual Japanese/English interface across authentication, accounts, transactions, imports, dashboards, categories, and securities. The welcome and login experience uses the NKKakeist visual identity, including browser and home-screen icons.

### Assets and securities

NKKakeist stores account-level valuations and instrument-level daily snapshots. The securities area provides:

- account valuation history;
- latest portfolio composition;
- instrument valuation, quantity, current price, acquisition cost, and unrealized gain;
- period changes and daily history; and
- links back to the import that created each snapshot.

### Human-in-the-loop acquisition

For sites without a suitable public API, NKKakeist uses an explicit semi-automatic approach: the user signs in on the financial institution's own site, exports structured data from that authenticated browser session, and uploads it through the same preview workflow.

NKKakeist does not store financial-site passwords, authentication cookies, or second-factor credentials.

## Technology

- Laravel 13 and PHP 8.3
- Inertia.js 2, React 18, and TypeScript
- Tailwind CSS and Vite 8
- MySQL 8.4
- Docker Compose
- PHPUnit 12

## Quick start

Prerequisites:

- Docker with Docker Compose
- ports `8000`, `5190`, and `13306` available locally

Start the application from the repository root:

```bash
docker compose up --build
```

The container installs Composer and npm dependencies, creates `src/.env`, generates an application key, runs migrations, and starts Laravel and Vite.

Seed fictional demo data:

```bash
docker compose exec app php artisan db:seed
```

Open [http://localhost:8000](http://localhost:8000). In the local Docker environment, the login screen provides a development-only one-click login. The seeded user is `developer@example.test`; this shortcut is unavailable outside `local` and `testing` environments.

## Verification

Run the test suite:

```bash
docker compose exec app php artisan test
```

Build the frontend:

```bash
docker compose exec app npm run build
```

Format PHP code:

```bash
docker compose exec app ./vendor/bin/pint
```

Repository wrappers provide the same container-based workflow:

```bash
scripts/dev artisan test
scripts/dev npm run build
scripts/dev pint
```

## Architecture

- **Controllers** handle HTTP concerns, authorization entry points, and Inertia responses.
- **Form Requests** validate input and related-resource ownership.
- **Actions** define use cases and transaction boundaries.
- **Services** contain parsing, aggregation, classification, duplicate detection, diagnostics, and reconciliation logic.
- **Feature tests** cover authentication, user isolation, imports, dashboards, and correction workflows.
- **Unit tests** cover parsers, normalization, and balance calculations.

See [docs/project-guide.md](docs/project-guide.md) for the public product model, supported sources, safety rules, and roadmap. Development conventions are documented in [AGENTS.md](AGENTS.md).

## How Codex was used

### How GPT-5.6 was used

GPT-5.6 was selected inside Codex for long-context reasoning across financial edge cases. It helped distinguish transfers from actual spending, preserve auditability, and turn ambiguous cases into explicit domain rules and regression tests.

Codex was used throughout development as a collaborative engineering agent to:

- turn real-world financial inconsistencies into explicit domain rules;
- design the staged import, reconciliation, and snapshot architecture;
- implement parsers and deterministic duplicate detection;
- keep controllers small by separating requests, actions, and services;
- diagnose data inconsistencies and design auditable corrections;
- write and refine feature and unit tests;
- identify and remove an N+1 query in category review; and
- verify user-facing flows in the browser.

Codex is a development tool for this repository, not a runtime dependency. NKKakeist does not send personal financial data to an AI service.

## Data and privacy

This repository contains only fictional seed and test data. Real exports, uploaded statements, environment files, database volumes, logs, screenshots, generated media, and backups are excluded from version control.

Do not expose a development instance containing personal data to the internet. Use a separate database with fictional data for public demonstrations.

## Scope

NKKakeist is a personal project, not a bank, broker, accounting service, or financial adviser. External website formats may change, and users must review imported data before relying on it.

Unattended bank aggregation and automatic commit of externally acquired financial records are intentionally outside the current scope.

## License

NKKakeist is available under the [MIT License](LICENSE).
