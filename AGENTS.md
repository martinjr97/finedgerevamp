# Agent instructions for FineEdge Revamp

## CRITICAL — Data safety (read first)

**Never delete, wipe, truncate, or reset the user's data.**

This applies to all agents, subagents, and automated tasks working in this repository.

### Protected databases

- `finedgerevamp` — primary local/dev application database
- `finedge` — legacy database (do not modify)
- Any database named in the user's `.env` `DB_DATABASE`

Tests must use **`finedgerevamp_testing` only**. See `phpunit.xml` and `tests/TestCase.php`.

### Forbidden without explicit written user approval

- `migrate:fresh`, `migrate:refresh`, `migrate:reset`
- `db:wipe`
- `DELETE`, `TRUNCATE`, `DROP` SQL
- `Model::query()->delete()`, `::truncate()`, mass `destroy()`
- Tinker/scripts that bulk-delete or reset schema on the app database
- Changing `.env` to point at a different database
- Running tests when `DB_DATABASE` is a protected database

### Default behaviour

- **Read** and **add/update** data only when fixing or seeding.
- Prefer `updateOrCreate`, `firstOrCreate`, and additive seeders.
- To restore dev fixtures: `php artisan dev:resume-repayment-testing` (never deletes).
- If data recovery is needed, use additive seeders — do not wipe first.

### If destruction seems necessary

1. **Stop.**
2. Explain what you want to do, which database, and why.
3. Wait for the user to explicitly approve that exact command.
4. Offer a non-destructive alternative first.

See also: `.cursor/rules/data-safety.mdc`
