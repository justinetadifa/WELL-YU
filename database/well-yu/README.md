# WELL-YU schema and optional data

Use only `well_yu_db`. Database changes require explicit approval; none of the
commands below imports or applies SQL automatically.

1. Inspect the current database and prepare a completion file:

   ```powershell
   C:\xampp\php\php.exe scripts/inspect-well-yu-database.php --plan
   ```

   This executes only SELECT statements against `well_yu_db` and its scoped
   metadata. It does not bootstrap the application, migrate, seed, or log in.
   Exit 0 means the schema passes the checks; exit 2 means missing tables or
   incompatible existing schema; exit 1 means inspection failed. `--plan` writes
   only the local `complete-missing-tables.sql` file.

2. Read `missing_tables`, `schema_issues`, `table_row_counts` and `demo_accounts`.
   Confirm phpMyAdmin is connected to the same server, port and database.

3. Review `complete-missing-tables.sql`. When existing tables match the expected
   schema, this file contains only `USE well_yu_db` and `CREATE TABLE IF NOT
   EXISTS` for missing tables, in parent-first order. If all tables are present,
   it contains no CREATE statements. If existing columns, indexes, keys or
   engines conflict, it is marked BLOCKED and contains no repair DDL.

4. **After approval**, manually import only that completion file in phpMyAdmin.
   Rerun inspection immediately before import if anything has changed. Never
   delete/recreate existing tables or users to make an import succeed.

5. Rerun inspection with `--plan`. After successful completion, expect 35/35
   application tables, no missing tables or schema issues, 53 foreign keys,
   and a completion file with no CREATE statements. Previously existing data
   and password hashes are not changed by this process.

`setup.sql` and `schema.sql` are equivalent full schema definitions, with no data
inserts. They also use `CREATE TABLE IF NOT EXISTS`; they do not upgrade an
incompatible existing table. The inspected completion file is preferable for
retrying a partial import because it omits tables already present.

`seed.sql` is **optional, separate, and not idempotent**. It inserts public legacy
demo accounts using fixed IDs, plus sample city/property records. Do not import
it into a populated database or retry it after a duplicate-key error. It does
not use REPLACE, INSERT IGNORE, or password-updating conflict handlers. Existing
data needs inspection and a separately approved, explicit initialization choice.
This workflow intentionally does not attempt to merge partially seeded datasets.

For the current incident and exact next steps, see
[the import investigation](../../docs/well-yu-import-recovery.md).

## Optional wellness extension

The new wellness workflows use **ten additional tables**, separately from the
35-table base schema. The local extension has been applied after explicit
approval: all 45 tables and 79 foreign keys pass inspection, and the current
completion file contains no CREATE statements. No seed or account changes were
applied. For later read-only verification or a separately approved installation,
run this inspection to prepare a missing-only extension file:

```powershell
C:\xampp\php\php.exe scripts/inspect-welyu-wellness.php --plan
```

Review `wellness-completion.sql` and import it only after explicit approval.
`01-wellness.sql` is its canonical source. Both contain schema only: no seed
rows, account changes or changes to existing tables. A complete installation
of this extension has 45 total tables and 79 total foreign keys. Leave automatic
migrations/seeding disabled. See [the wellness implementation and activation
guide](../../docs/welyu-wellness.md) for permissions, calculation rules and testing.
