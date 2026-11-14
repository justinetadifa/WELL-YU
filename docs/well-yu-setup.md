# WELL-YU independent baseline

For the duplicate-key import incident and the current read-only database findings,
use [the import investigation and recovery procedure](well-yu-import-recovery.md).
It adds a missing-tables-only plan for safely retrying compatible partial setups.
The user has since completed the manual schema import: read-only verification
now passes for 35 tables and 53 foreign keys, with no accounts or demo records.
The initial preparation snapshot below describes the earlier empty database.

Prepared and checked on November 11, 2026, exclusively in `C:\xampp\htdocs\WELL-YU`.
No SQL import, migration, seed, record deletion, commit or push was performed. The
read-only connection confirmed `well_yu_db` exists and has **zero tables**. Full
database workflows remain pending approval and schema initialization.

## Repository and files

`origin` fetch/push is `https://github.com/justinetadifa/WELL-YU.git`. The local
`thesis` remote pointing to BIZSTART was removed to prevent an accidental push;
this changed only this checkout's Git metadata. No remote repository was contacted.
The BIZSTART directory and database were neither inspected nor changed.

Inspected configuration, bootstrap, authentication/session and staff activation,
web URL generation, API initialization/health, upload and external-service code,
all four `database/migrate-*.php` entry points, all four application schema
managers, the three original SQL files, browser persistence, ignore/access rules,
tracked files and local Git history for recognizable credential patterns.

Changes:

| Files | Purpose |
| --- | --- |
| `app/config.php`, `app/config.local.php.example` | WELL-YU defaults, manual initialization, separate URL/environment/storage configuration |
| `app/config.local.php` (ignored) | Local WELL-YU credentials and blank optional service credentials |
| `app/Core/Database.php` | Reject every database name except `well_yu_db` before connection; reject DSN delimiters in host/charset |
| `app/Support/auth.php`, `app/Support/account-view.php` | Separate session name, cookie path/files and returning-user cookie reader |
| `app/Support/staff-onboarding.php` | Read WELL-YU-prefixed staff environment settings |
| `app/Support/ExternalServices.php` | Separate Cloudinary fallback folder |
| `assets/js/{utils,portal,auth-interface,session-guard,sound-manager,home-discovery,broker-workspace,city-workspace,property-wizard,admin-mce-weights}.js` | Isolate shared storage keys, draft photos, returning-user cookie and cross-tab updates |
| `tests/sound-system.test.cjs` | Update existing sound tests for the isolated storage names |
| `.gitignore`, `.htaccess`, `router.php` | Exclude new runtime/private files; deny private metadata, settings and SQL downloads |
| `database/well-yu/{setup,schema,seed}.sql` | Separate WELL-YU-only schema and optional demo seed |
| `scripts/prepare-well-yu-sql.cjs` | Reproducibly generate SQL from source without connecting to MySQL or executing PHP |
| `scripts/verify-well-yu.php`, `scripts/verify-well-yu-http.ps1` | Repeatable static, SELECT-only and HTTP checks |
| `README.md`, this document | Replace local setup guidance and record findings |

The original `database/setup.sql`, `schema.sql`, `seed.sql`, migrations and schema
manager implementations are unchanged. The originals still name the thesis
database: **never import those three original SQL files for WELL-YU**.

## Effective local configuration

| Setting | Value |
| --- | --- |
| Application | `wELYU` (current visual branding) |
| URL | `http://localhost/WELL-YU/` |
| Database host/port | `127.0.0.1:3306` |
| Database | `well_yu_db` only |
| Local XAMPP user/password | `root` / empty password, verified to connect |
| `app.auto_migrate` / `app.auto_seed` / `db.auto_create` | All `false` |
| Session cookie | `WELL_YU_SESSION`, path `/WELL-YU/`, HttpOnly, SameSite=Lax |
| Session files | `WELL-YU/data/sessions/` (ignored, denied HTTP access) |
| Draft IndexedDB | `well_yu_property_drafts` |
| Cloudinary folder | `well-yu/properties`, with credentials blank |
| SMTP, AI and other keyed services | Unconfigured |

All environment settings consumed by WELL-YU now require the `WELL_YU_` prefix.
Examples: `WELL_YU_DB_PASS`, `WELL_YU_APP_URL`, `WELL_YU_SMTP_HOST`,
`WELL_YU_CLOUDINARY_CLOUD_NAME`, `WELL_YU_SFC_CITY_STAFF_PASSKEY`.
Generic `DB_*`, `APP_*`, `SMTP_*`, `SFC_*` and service variables used by the thesis
are ignored. Existing precedence is preserved: private configuration overrides
database/service defaults; explicit WELL-YU app-flag, URL, mail and transport
environment settings override the private file. Leave all automatic-write flags
disabled. Existing integration code and fallback behavior remain available.

Uploads, evidence, broker documents, assessment files, caches and rate limits
resolve within this checkout. No filesystem junctions/symlinks were found.
Existing browser keys already containing the application's base path remain
separate through `/WELL-YU`; formerly unscoped keys now use WELL-YU names. Old
thesis drafts/cookies are neither read, migrated nor cleared.

## SQL preparation and approval boundary

The legacy SQL schema lacks current runtime tables and intake fields. The prepared
schema includes **35 tables** from the original schema plus current criteria,
broker, V2 and CLUP managers, including `subname`, `proposed_activity` and four V2
property fields. Static checks compare manager DDL table/column names and validate
foreign-key creation order. This is a source compatibility check; an actual MySQL
DDL import and authenticated workflows have not been exercised.

Choose exactly one schema file after explicit approval:

- `database/well-yu/setup.sql`: schema only, **no seed**.
- `database/well-yu/schema.sql`: the same schema, alternate entry point.
- `database/well-yu/seed.sql`: optional legacy demo data, requires **separate seed approval**.

Every prepared file begins with `USE well_yu_db`; none names the thesis database,
creates a database, drops tables or clears records. Setup/schema contain only
table creation, with InnoDB and utf8mb4. They are for a fresh empty database, not
upgrades; `IF NOT EXISTS` does not repair existing tables. DDL commits implicitly,
so inspect any failed import before retrying. Do not import setup and schema both.

Demo seeds contain public development passwords, legacy `@sfcelerate.local`
identities and city/property samples. They do not populate the newer operational
`criteria_config`, V2 scenario or CLUP reference tables. Those remain empty with
schema-only setup; some application code uses static defaults, while configured
scenarios/reference catalogs require a separately approved initialization decision.

Migration review: city-workspaces delegates to `SchemaManager`, which performs
data normalization and inserts criteria/V2 defaults; listing Batch A changes
listing DDL; broker Batch B adds verification/mail DDL; V2 adds columns/tables and
inserts draft scenario configurations. CLUP initialization also inserts reference
uses and zoning rules. Therefore enabling auto-migration is **not schema-only**.
No migration or dry-run migration was executed during preparation.

## Verification completed

- PHP syntax: 266 project PHP files, excluding bundled PHPMailer; all passed.
- JavaScript syntax: all 10 changed application JS files; passed using UTF-8 input.
- `php scripts/verify-well-yu.php`: 101 isolation/schema checks passed, including
  ignoring conflicting generic host environment settings and rejecting other DB names.
- `php scripts/verify-well-yu.php --database`: connected to `well_yu_db`, zero
  tables; expected exit code **2** means schema initialization is pending.
- Existing pure checks: property classification (170), wizard computation parity
  (153), draft photo persistence with mocked IndexedDB, and sound behavior; passed.
  No MySQL fixture creation, migration or seed tests were run.
- HTTP: health, homepage and investor login returned 200; homepage/login issued
  isolated session cookies and used the `/WELL-YU/` base URL. Private config, SQL,
  sessions and `.env` returned 403; `.git/config` returned 404.
- GET-only checks did not execute frontend JavaScript or submit forms. Health
  confirms connectivity only, not schema readiness. Database remains empty.

## Remaining risks and dependencies

- Apache, PHP, MySQL and localhost are shared infrastructure. No global XAMPP or
  server settings were changed. XAMPP `root` can access other databases; the
  application guard prevents accidental targeting but is not a MySQL permission
  boundary. After approval, prefer a dedicated account granted access only to
  `well_yu_db`. Account creation/grants were not performed.
- Browser storage is same-origin on localhost. Namespaces avoid routine
  interference; they are not a security boundary against malicious same-origin
  scripts. A separate local hostname can provide stronger isolation later.
- Git tracks 102 paths under `assets/uploads` (101 images plus a profile access
  rule). New uploads are ignored; existing tracked images remain tracked. Review
  ownership, sensitive content and competition redistribution rights before any
  push. No images were deleted or untracked.
- A heuristic scan of tracked text and 94 local commits found no recognizable API
  tokens/private keys; private config files were not found tracked or in their
  path history. This is not a comprehensive secret-history certification. Demo
  passwords and local staff activation passkeys remain public in legacy code/docs.
  Review staged files and credentials before publishing. No push is recommended yet.
- A synthetic assessment authentication debug JSON is tracked; it labels itself
  synthetic, not real agency approval. Legacy geographic datasets, reports,
  consent text, logos, titles, `sfcelerate.local` demo identities, `LOCUS-SF`
  methodology labels, and package metadata are preserved for later customization.
- Keyed integrations are blank. Public OpenStreetMap/Overpass/geocoding, remote
  map artwork/services and external source documents can still need internet;
  this baseline is not an offline build. Use separate WELL-YU accounts/keys before
  enabling SMTP, Cloudinary, AI, news, market or weather integrations.
- `app/admin-access.local.php`, if later supplied, creates/updates administrator
  records at bootstrap independently of auto-seeding. It is currently absent;
  do not copy it from the thesis or add it without approval.
- Full catalog, authentication, uploads and workflow writes cannot be verified
  end to end until schema/account initialization is approved. Do not run the full
  legacy test suite blindly: several tests create/drop databases or submit writes.

## Exact next steps

1. Review this document, the Git diff and the three files in `database/well-yu/`.
   Private credentials are in ignored `app/config.local.php`; do not stage them.
2. Keep Apache/MySQL running. From `C:\xampp\htdocs\WELL-YU`, repeat safe checks:

   ```powershell
   C:\xampp\php\php.exe scripts/verify-well-yu.php
   C:\xampp\php\php.exe scripts/verify-well-yu.php --database
   powershell -NoProfile -File scripts/verify-well-yu-http.ps1
   git check-ignore app/config.local.php
   git remote -v
   ```

   Database verification exits 2 until tables are imported. HTTP health can still
   return 200 while the database is empty.
3. **Only after explicit schema approval:** in phpMyAdmin select `well_yu_db`,
   verify it is still empty, then import **only** `database/well-yu/setup.sql`.
   This creates tables without accounts or demo records. Never select/import
   against the thesis database. If tables already exist, stop for inspection.
4. Rerun the PHP verifier with `--database`; it should pass and report zero rows
   in the representative application tables. Rerun the HTTP checks. Keep both
   auto flags disabled.
5. **Only after separate data/account approval:** decide between clean prototype
   accounts and the legacy demo seed. For the demo choice, while application
   tables are still empty, review/import `database/well-yu/seed.sql` in
   `well_yu_db`. Account provisioning and operational scoring/CLUP initialization
   need an explicit decision; no default account currently exists.
6. Open `http://localhost/WELL-YU/`. After approved account provisioning, test
   role-specific login/logout, catalog, property editor, profile and uploads.
   Registration, login activity, listing changes, visits, messages and browser
   APIs such as `api/bootstrap.php`/`api/property.php` may write records or metrics;
   these functional checks require approval for test writes. Verify by inspection
   that all API/upload URLs stay below `/WELL-YU/` and logout expires only the
   WELL-YU cookie.
7. Before any future push, inspect `git status`, `git diff` and the exact staged
   diff, review the tracked upload images and any new credentials, and confirm
   the only remote is WELL-YU. Obtain push approval separately.

For this baseline use the Apache URL above. If later using PHP's development
server at the URL root, explicitly adjust the private application URL and session
cookie path for that server and use `router.php`; the `/WELL-YU/` cookie is
intentionally scoped for the current Apache setup.
