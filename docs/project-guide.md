# NKKakeist Public Project Guide

This guide describes NKKakeist's current product behavior, financial model, import safety rules, and public roadmap. It intentionally excludes personal account names, balances, transaction records, site credentials, and operational notes tied to a specific user.

## 1. Product principles

NKKakeist is designed for personal financial records collected from services that expose incompatible exports and account models.

The application follows four principles:

1. **Explain before committing.** Imported rows remain visible until account resolution, validation, and duplicate checks are complete.
2. **Separate spending from movement.** Transfers affect balances but are not counted as new income or spending.
3. **Do not invent missing values.** Asset and net-worth charts use stored snapshots only; missing dates are not interpolated.
4. **Keep the user in control.** External acquisition is explicit and human-triggered, and financial transactions are not committed unattended.

The complete application interface is available in Japanese and English. The selected locale is stored in the session and applies to client-side labels as well as server-side authentication, validation, import, and domain messages.

## 2. Account and transaction model

### Account roles

Accounts can represent:

- cash and bank assets;
- credit-card liabilities;
- e-money and prepaid balances;
- payment-clearing accounts;
- securities and pension valuations;
- reward points; and
- other user-defined financial containers.

Balance behavior is explicit:

- `ledger` accounts derive their balance from an opening balance and balance-affecting transactions.
- `snapshot` accounts use the latest valuation snapshot as a base and then apply later balance-affecting transactions.

Accounts also have a net-worth role such as `asset`, `liability`, or `clearing`. Clearing accounts can represent payment reallocation without inflating assets or liabilities.

### Transaction types

NKKakeist distinguishes three transaction types:

- `income`: money received and included in income aggregation.
- `expense`: actual consumption or spending included in expense aggregation.
- `transfer`: movement between accounts, excluded from income and expense aggregation while still affecting balances.

For transfers, `account_id` is the source and `transfer_account_id` is the destination.

Two flags keep reporting and balances independent:

- `is_calculation_target` controls income and expense aggregation.
- `affects_account_balance` controls whether a transaction changes an account balance.

This separation supports records such as point acquisition that may affect an asset balance without appearing as ordinary income.

### Common patterns

- A card purchase is an `expense` on the credit-card account.
- A credit-card payment is a `transfer` from the bank account to the card account.
- An e-money charge is a `transfer` from the funding account to the e-money account.
- A payment-service charge billed to a card is a `transfer` from the card to the payment-clearing account.
- Funding an investment account is a `transfer`; the later market valuation is stored as a snapshot.

These patterns prevent the same economic event from being counted once at purchase time and again at settlement time.

## 3. Import lifecycle

All sources reuse the same staged lifecycle:

1. Upload
2. Source-specific parsing
3. Normalization with traceable raw fields
4. Account, category, and transfer resolution
5. Classification-rule application
6. Validation and deterministic duplicate detection
7. Row-level preview
8. Explicit commit

Uploaded content is treated as untrusted. Parsers validate file structure, dates, required fields, and source-specific invariants.

Duplicate candidates stay visible in preview and are skipped safely at commit. Unsafe rows remain unresolved rather than being silently guessed.

### Same-day snapshot conflicts

Official balances and valuations are unique per account, purpose, and date. When a new file contains a different value for an already stored date, preview marks it as a conflict. The user must explicitly choose replacement before commit.

Instrument details can enrich an otherwise duplicate account snapshot without silently changing its account-level value.

Official-balance exports also preserve acquisition-tool and recognized-page-structure metadata. Preview warns when optional summaries are unavailable and rejects files when required account tables can no longer be recognized, making upstream website changes visible before commit.

## 4. Supported sources

### Money Forward transaction CSV

- Accepts common Japanese encodings including UTF-8 and CP932/SJIS variants.
- Resolves accounts from names and user-configured import aliases.
- Normalizes source-side "uncategorized" values to empty category relationships.
- Resolves transfer direction from account roles and signed source data.
- Preserves source identifiers when available and uses deterministic hashes otherwise.

### Mobile Suica statement PDF

- Extracts text from supported statement PDFs.
- Verifies running-balance continuity before accepting the file.
- Imports spending rows while using charge and carry-over rows for validation.
- Generates stable identifiers so overlapping statement periods can be imported safely.
- Requires an explicitly selected user-owned e-money account.

### JRE POINT JSON

- Uses data exported explicitly from an authenticated browser session.
- Separates point acquisition, expiration, usage, and transfers to e-money.
- Reconciles the imported ledger against the captured official balance.
- Does not store the user's password, session cookie, or second-factor credentials.

### Official balance and valuation JSON

- Imports bank balances, card outstanding amounts, securities valuations, and pension valuations.
- Resolves source account names through user-owned aliases or manual preview selection.
- Normalizes card outstanding amounts as liabilities.
- Stores account-level snapshots separately from transaction-ledger reconciliation.
- Stores investment position details such as quantity, price, valuation, acquisition data, and unrealized gain when supplied.

### Money Forward asset-history CSV

- Imports historical total assets and source-provided asset-class breakdowns.
- Preserves the source summary instead of redistributing it into account balances.
- Detects duplicate dates and supports long-range asset-history charts.

## 5. Classification and review

Classification rules can match import fields such as merchant, description, account, or transaction type. Rules can set a category, subcategory, and calculation-target behavior.

The category-review screen provides:

- high-confidence suggestions based on rules and prior classified transactions;
- a separate manual-review queue;
- the reason and reference history behind a suggestion;
- category creation without leaving the review workflow; and
- optional creation of a reusable classification rule when a category is confirmed.

Rules and suggestions never use another user's records.

## 6. Balances, assets, and liabilities

### Ledger balances

Ledger balances start from an opening balance and opening date. Only later transactions with `affects_account_balance=true` are applied.

### Official balance reconciliation

Official bank and card balances are read-only observations. Importing an official balance does not rewrite the ledger automatically.

The reconciliation workflow compares the ledger with the latest official value. Any opening-balance correction requires explicit confirmation and creates an auditable reconciliation snapshot.

### Valuation snapshots

Securities and other market-valued accounts use `valuation` snapshots. A daily account snapshot stores the total account value, while investment-position snapshots store the source-provided instrument breakdown.

Daily net worth uses:

- valuation snapshots for snapshot-based asset accounts;
- official balances for banks and cards when recorded for that date; and
- ledger calculations when an official value is unavailable.

This display preference does not mutate transactions or opening balances.

## 7. Dashboards and drill-downs

### Monthly dashboard

The monthly view includes:

- income, expenses, and balance by currency;
- comparison with the previous month and the same month of the previous year;
- transaction count, expense count, average expense, and largest expense;
- top merchants;
- uncategorized, unconfirmed, and pending-import counts;
- account balances and category totals;
- recent monthly trends;
- daily assets, liabilities, and net worth; and
- imported long-range asset history.

Summary cards, accounts, categories, merchants, and trend months link to filtered transaction or dashboard views.

Monthly net-worth change compares the first and last snapshots actually stored within the month. If only one date exists, the UI reports that comparison is unavailable.

### Yearly dashboard

The yearly view includes annual income, expenses, balance, monthly trends, category totals, and drill-down links to the relevant transaction range.

### Monthly closing

Past months can move through `open`, `reviewed`, and `closed` states. Accounts that may receive delayed card charges, phone bills, bank updates, or investment valuations can require explicit account-level confirmation before closing. The UI links each confirmation to the relevant account records and hides the closing panel for the current month. If source data changes after review or closing, the stored review fingerprint exposes that change instead of silently treating the month as final.

### Securities detail

The securities overview supports 30-day, 90-day, one-year, and all-history periods. Account cards and chart legends link to an account detail page.

The detail page shows:

- latest valuation and period change;
- valuation acquisition count and source;
- account-level daily history;
- latest instrument composition and allocation percentage;
- quantity, current price, acquisition cost, valuation, and unrealized gain when available;
- instrument-level daily history; and
- the import that created each account snapshot.

Values that a source supplies as zero to represent missing price or acquisition data are displayed as unavailable rather than as a real zero price.

## 8. Semi-automatic acquisition

NKKakeist prefers an official API when one is available and appropriate. When a site has no suitable API, acquisition remains explicit and user-triggered:

1. The user signs in on the institution's own website.
2. A local bookmarklet or export helper reads the visible account data.
3. The helper downloads JSON or CSV to the user's machine.
4. The user uploads that file to NKKakeist.
5. NKKakeist parses, validates, previews, and explicitly commits it.

Daily Money Forward snapshots and weekly JRE POINT/Mobile Suica status cards make missing updates visible without storing external credentials.

This is not unattended screen scraping. Authentication, second-factor confirmation, and export remain under the user's control.

## 9. Security and privacy

- Every user-owned query and mutation is scoped to the authenticated user.
- Related account, category, import, and snapshot identifiers are validated for ownership.
- Development one-click login is restricted to `local` and `testing` environments.
- Financial exports, uploaded files, environment files, logs, database volumes, backups, screenshots, and generated media are excluded from version control.
- The repository's seeders and automated tests use fictional data only.
- Public demonstrations should use a separate fictional database.

NKKakeist does not send personal financial data to an AI service at runtime.

## 10. Diagnostics and testing

Read-only diagnostic commands identify suspicious transaction patterns and propose review actions without changing data. Corrections are intentionally separate from diagnosis.

The automated suite prioritizes:

- authentication and user isolation;
- CRUD validation and authorization;
- import parsing, preview, replacement, and commit;
- duplicate and transfer resolution;
- dashboard aggregation;
- balance reconciliation; and
- securities and snapshot history.

Run the full suite with:

```bash
docker compose exec app php artisan test
```

## 11. Current roadmap

Near-term work focuses on:

1. accumulating reliable daily net-worth, account, and instrument history;
2. validating acquisition helpers against upstream website changes;
3. operating the monthly-closing workflow against delayed real-world updates;
4. improving report annotations as recurring review needs emerge; and
5. evaluating source-specific adapters only when they preserve the same preview-first safety model.

Potential future work includes budgets and carefully constrained automation for sources with proven duplicate detection and reconciliation rules.

## 12. Intentional non-goals

The current project does not aim to:

- replicate a commercial financial aggregator;
- store financial-site credentials or browser cookies;
- automatically commit externally acquired financial records;
- interpolate missing market values;
- provide tax, investment, accounting, or financial advice; or
- replace institution-provided statements.
