# NKKakeist Project Guide

This public guide describes NKKakeist's product behavior and domain model without including personal financial records.

## Product goal

NKKakeist helps individuals manage accounts, transactions, income, expenses, transfers, and asset balances across financial services that export incompatible formats.

The application favors operational confidence over automatic bookkeeping. Imported data remains visible and explainable before it changes the ledger.

## Transaction model

NKKakeist distinguishes three transaction types:

- `income`: money received and included in income aggregation.
- `expense`: actual consumption or spending included in expense aggregation.
- `transfer`: movement between accounts, excluded from income and expense aggregation while still affecting both account balances.

`account_id` is the source account and `transfer_account_id` is the destination account for transfers. This representation prevents credit card payments, wallet charges, and other internal movements from being counted twice.

Income and expense aggregation is controlled separately from account-balance effects. This allows reward-point acquisition and other special records to affect an asset balance without being presented as ordinary income.

## Supported imports

### Money Forward CSV

- Accepts common Japanese text encodings.
- Resolves accounts from configured names and import aliases.
- Normalizes uncategorized records to empty category relationships.
- Resolves transfer direction independently from the displayed amount sign.

### Mobile Suica PDF

- Extracts structured text from supported statement PDFs.
- Verifies running-balance continuity before accepting rows.
- Imports spending rows while using charge and carry-over rows for validation.
- Produces deterministic identifiers so overlapping statement periods are safe.

### JRE POINT JSON

- Uses data captured explicitly from an already authenticated browser session.
- Does not store credentials or browser cookies in NKKakeist.
- Separates point acquisition, usage, expiration, and transfers to e-money.
- Reconciles the imported ledger against the official captured balance.

### Balance snapshot JSON

- Imports official balances and asset valuations through the same staged workflow.
- Requires each row to resolve to a compatible user-owned account.
- Keeps valuation snapshots separate from transaction-ledger reconciliation.

## Import lifecycle

1. Upload
2. Source-specific parsing
3. Normalization with traceable raw fields
4. Account, category, and transfer resolution
5. Validation and duplicate detection
6. Row-level preview
7. Explicit commit

Duplicate candidates remain visible in preview and are skipped safely during commit. Imports containing unresolved or unsafe rows can be rejected as a whole when the source requires exact reconciliation.

## User data isolation

Every user-owned read and write is scoped to the authenticated user, including accounts, categories, subcategories, transactions, imports, import rows, classification rules, and account snapshots. Related identifiers are validated for ownership before they can be attached.

## Current capabilities

- Account, category, transaction, and classification-rule management
- Monthly and yearly dashboards
- Category-review workflow with explainable suggestions
- Staged file imports with validation and duplicate detection
- Account balance calculation and official-balance reconciliation
- Read-only transaction diagnostics and safe correction support

## Roadmap

- Improve asset-valuation workflows and net-worth trends.
- Add explicit, user-triggered acquisition helpers for supported financial sites.
- Reuse the existing validate and preview lifecycle for every new source.
- Consider limited automatic commits only after source-specific safety conditions are proven.

Automatic external bank integration and unattended financial-data commits are intentionally outside the current scope.
