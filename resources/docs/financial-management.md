<!-- @guards:admin @any-permissions:banks.view,wallets.view,creditors.view,financial-transactions.view,transfers.view,financial-statements.view -->
# Financial Management Module

This section covers the Financial Management menu and related workflows.

## Where to Find It

Menu path:

- `Financial Management`

## Core Modules and URLs

- Banks: [`/admin/banks`](/admin/banks)
- Wallets (mobile wallets/cash wallets): [`/admin/wallets`](/admin/wallets)
- Creditors: [`/admin/creditors`](/admin/creditors)
- Transactions: [`/admin/financial-transactions`](/admin/financial-transactions)
- Transfers: [`/admin/transfers`](/admin/transfers)
- Financial Statements:
  - [`/admin/financial-statements/balance-sheet`](/admin/financial-statements/balance-sheet)
  - [`/admin/financial-statements/cash-flow`](/admin/financial-statements/cash-flow)
  - [`/admin/financial-statements/income-statement`](/admin/financial-statements/income-statement)

## Typical Tasks

### Create Bank

1. Open [`/admin/banks`](/admin/banks).
2. Click create action.
3. Fill bank details.
4. Save.

### Create Wallet

1. Open [`/admin/wallets`](/admin/wallets).
2. Click create action.
3. Enter wallet type and account identifiers.
4. Save.

### Create Transfer

1. Open [`/admin/transfers/create`](/admin/transfers/create).
2. Choose source and destination (bank/wallet).
3. Enter amount and reference.
4. Submit.
5. If approval is required, review from transfer or approvals pages.

### Record Transactions

Use [`/admin/financial-transactions`](/admin/financial-transactions) for income/expense entries and traceability.

## Permissions Reminder

If a menu item is missing, your role may have only partial financial permissions.
