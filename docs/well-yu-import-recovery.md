# WELL-YU import investigation — November 11, 2026

## Update after the user's manual import

The user successfully imported `complete-missing-tables.sql` in phpMyAdmin.
Subsequent SELECT-only inspection confirms **35/35 application tables**, all
**53 expected foreign keys**, no schema conflicts, and zero users/properties.
The completion file has been regenerated as a no-op with no CREATE statements.
Homepage, investor login, health and private-file protection checks pass.
No further schema import is needed. The next step is separate account creation.

The inspector was corrected to normalize decimal whitespace and MariaDB integer
display widths without losing unsignedness. All 24 offline setup-safety checks
pass. No database modification was made by the inspector or the checker fix.

The observations below record the earlier empty-database investigation, before
the user performed the successful import.

## Observed result

The application configuration connects to `127.0.0.1:3306`, database
`well_yu_db`, on MariaDB `10.4.32`. SELECT-only inspection found:

| Check | Result |
| --- | --- |
| Application tables present | **0 of 35** |
| Other tables/views in `well_yu_db` | 0 |
| Application foreign keys present | 0 of 53 expected |
| Demo accounts currently present | None; the `users` table does not exist |
| Schema complete | No |
| Database workflows ready | No |
| Automatic migration / seeding / database creation | Disabled |
| Private administrator initializer | Absent |

The user also confirmed that phpMyAdmin now shows zero tables in `well_yu_db`.
No SQL modifications, migrations, seed executions, login attempts, record
deletions, password changes, or imports were performed during this investigation.
No query inspected or targeted the original thesis database. Git still has only
the WELL-YU `origin`; nothing was pushed.

## Why error 1062 occurred

The screenshot reports `INSERT INTO users` attempting to insert the public demo
administrator as ID `1`. MySQL rejected that statement because a `users` row with
primary key `1` already existed in the statement's target database **at that
time**. The statement matches the separate legacy demo seed, which contains four
fixed user IDs and does not support repeated imports.

The current WELL-YU `setup.sql` has no INSERT statements; its DDL matched the
previously reviewed file before this investigation. Its latest edits are comments
clarifying safe retries; the table definitions remain unchanged. Therefore the
displayed demo INSERT cannot have come from the current schema-only setup file.
It could have come from `seed.sql`, a different/older combined setup file, or a
pasted query. The existing local original combined SQL contains its own database
selection; never use that file for WELL-YU.

The current database is empty, so the available evidence cannot identify the
previous conflicting row, establish whether a prior demo import completed, or
prove which file/server/database was used for the historical failing request.
The current inspection does **not** confirm that a previous partial import still
exists. No historical row origin is inferred, and no thesis database was queried
to investigate this discrepancy.

## Required tables

All 35 expected tables are currently missing:

| Area | Tables |
| --- | --- |
| Accounts, broker review/mail (7) | `users`, `user_preferences`, `seller_profiles`, `broker_application_reviews`, `email_verification_tokens`, `broker_mail_outbox`, `site_metrics` |
| Property, catalog and voting (7) | `properties`, `property_media`, `property_due_diligence`, `vote_options`, `showcase_items`, `property_votes`, `property_shortlists` |
| Coordination and reporting (8) | `message_threads`, `property_messages`, `property_document_requests`, `visit_logs`, `notifications`, `audit_logs`, `investment_scenarios`, `spatial_overlays` |
| Assessment/scoring (4) | `criteria_config`, `investor_project_profiles`, `v2_scoring_configurations`, `property_assessment_versions` |
| CLUP governance (9) | `clup_datasets`, `clup_zones`, `clup_use_types`, `clup_zone_use_rules`, `clup_site_profiles`, `clup_evidence_documents`, `clup_review_events`, `clup_evaluations`, `clup_reports` |

The schema defines 487 columns and 53 foreign keys across these tables. The
migration runner's optional bookkeeping table is not needed while automatic
migration remains disabled.

## Files prepared

- `scripts/inspect-well-yu-database.php`: SELECT-only inspector. Checks scoped
  table/column/index/foreign-key metadata and counts existing application records.
  It compares known demo IDs/emails and seed password hashes in memory when a
  users table exists; it never prints real-user email addresses or password hashes.
  It never bootstraps the application or runs migrations.
- `database/well-yu/complete-missing-tables.sql`: generated, reviewed completion
  plan. For the current empty database it contains 35 CREATE TABLE statements.
  No INSERT, UPDATE, ALTER, DROP, TRUNCATE, DELETE, REPLACE, or INSERT IGNORE is
  present. It selects only `well_yu_db` and preserves existing tables/records.
- `setup.sql`, `schema.sql`, and their source generator: comments now explain
  inspection before partial-setup retries. Table definitions are unchanged.
- `seed.sql`: comments explicitly warn that fixed-ID demo seeding is optional,
  requires separate approval, and must not be retried on existing records.
- `database/well-yu/README.md`: repeatable procedure and conflict handling.
- `tests/well-yu-setup-safety.test.php`: offline safety tests for an empty schema,
  a partial schema, a completed schema, existing-schema conflicts, foreign-key
  parent order and rejection of unsupported SQL. Tests use no live database.

## Safe exact next steps

1. Keep automatic migration/seeding disabled. From the WELL-YU directory run:

   ```powershell
   C:\xampp\php\php.exe scripts/inspect-well-yu-database.php --plan
   ```

   Current expected result: `0/35`, all missing tables listed, no existing-schema
   conflicts, exit code 2. Exit 2 here is the expected pending-setup result.

2. In phpMyAdmin confirm the same `127.0.0.1:3306` server and select
   **`well_yu_db`**. Review `database/well-yu/complete-missing-tables.sql`.

3. **Only after approval**, manually import
   **`database/well-yu/complete-missing-tables.sql`**. This initializes the schema
   without demonstration records. Use that one schema file; do not additionally
   import the seed or an original thesis SQL file.

4. Rerun the inspector with `--plan`. Expect `35/35`, no schema conflicts, 53
   foreign keys, and zero rows in the new application tables. The newly generated
   completion file should contain no CREATE statements. If interrupted, rerun
   inspection and import the regenerated missing-only file after reviewing it.
   If existing definitions conflict, stop and review; no alteration is automatic.

5. Repeat the existing GET-only HTTP checks if desired:

   ```powershell
   powershell -NoProfile -File scripts/verify-well-yu-http.ps1
   ```

   These check health, rendering, private-file protection and isolated session
   cookies. They do not log in or run frontend write APIs. Health alone does not
   establish schema completeness.

6. Choose account/data initialization separately. For a clean prototype, approve
   creation of your own accounts through the existing flows. If legacy demo data
   is desired, review the seed and obtain seed approval while application tables
   are still empty. The seed is not required to create the schema and must not
   overwrite passwords or be merged silently into existing data.

7. Open `http://localhost/WELL-YU/`. Catalog and login workflows need the schema;
   successful sign-in also needs an account. V2 scenario and CLUP reference data
   are separate initialization choices, so schema-only setup does not populate
   them. Login activity, registration and listing/visit/message tests write data
   and were not executed here.

The completion procedure is idempotent for compatible schemas: existing tables
are skipped, a successful retry creates only missing ones, and a completed schema
produces a no-op plan. It intentionally does not reinterpret incompatible tables
or conceal conflicting demo data.
