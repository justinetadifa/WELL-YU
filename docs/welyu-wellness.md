# wELYU wellness workflows

Open **http://localhost/WELL-YU/wellness.php**. The shared navigation now includes
Wellness. Property details, community voting and the city pipeline link into the
new workflow. Existing property calculations and historical records remain in
their original tables and calculation versions.

## Current activation status

The ten-table migration has been **applied after explicit user approval** to
`well_yu_db` only. Post-migration checks confirm **45 tables and 79 foreign keys**:
the original 35-table baseline plus 10 wellness tables (96 columns, 26 foreign
keys), with no schema issues. A re-generated completion plan has no CREATE
statements. Before/after schema and full-record fingerprints for every original
table match. All ten new tables are empty. Guest previews work; saving,
reviewing, uploads, monitoring and private exports now have their required
schema and still require authorized signed-in accounts. Saved scored assessments
also require a reviewer-created scoring configuration. This work has not
inserted accounts, changed passwords, or
inserted service records/scoring configurations. The latest read-only check
finds **one existing investor account and its preferences**, which are preserved.
It is not a legacy demo identity. In particular, do not import the legacy seed:
its fixed user ID 1 conflicts with that existing investor account.

The migration approval covered only the ten new tables. No demonstration data,
scoring configurations or account initialization was included. Account
provisioning and source-data imports remain separate, explicitly authorized
changes. Automatic migration, seeding and database creation remain disabled.
The local execution record is `.ui-audit/wellness-migration-audit.json` (ignored
by Git); it contains fingerprints and counts, not account or password contents.

## Implemented capabilities

- Service directory and community need reports: category, user groups, barangay,
  coordinates, affordability/travel/hours/capacity/accessibility barriers, source,
  collection date, verification date and scoped review status. Owner revisions
  reset review and use revision checks. Duplicate fingerprints reject conflicts.
- Leaflet map and context radar: recorded same-category services, specified
  complements, free public alternatives and expressed partnership interest.
  Known points within 10 km straight-line are shown; unknown distances remain
  unknown. Health-service categories and source-backed accessibility information
  are visible. Routes and walking/travel times are not fabricated.
- Proposal planning from a need, existing visible property or standalone concept;
  opening, improving and partnering modes. Improving requires ownership of the
  linked service and documented barriers, observations and a proposed response.
- A separate six-criterion wellness methodology, saved immutable configurations,
  assessment input/result snapshots, missing-evidence handling and readiness
  gates. Existing property MCE/IAI scores are never updated by this feature.
- Baseline and alternative scenarios for hours, capacity, price, costs, quoted
  improvements and explicit customer assumptions. Loading a scenario as a draft
  creates a new saved version; it never overwrites the prior baseline. Optional
  documented population units deduplicate IDs and exclude known existing coverage.
- Private comparisons of 2–5 assessment versions, frozen sources/scenarios and
  downloadable PDFs that reproduce the comparison snapshot. Different scoring
  configurations are visibly identified. No automatic investment recommendation
  is supplied.
- Submitted → under review → evidence required / reviewed workflow with explicit
  scope, notes and a dated history. Reviewers cannot review their own submissions.
- Pipeline, confirmed inquiries, site visits, decisions, implementation evidence,
  responsibilities and next actions. Operator follow-up observations remain
  separate from projected outputs. Outdated records and unresolved correction
  reports are available in the review desk. Needs are opportunity signals;
  proposals and reported implementation are distinct states.
- Private PDF/JPG/PNG evidence uploads (10 MB maximum), permission-checked
  downloads, and source-backed JSON directory/need import preview with an explicit
  separate commit. Import commits never replace existing rows.

## Safe local activation

From `C:\xampp\htdocs\WELL-YU`, with MySQL running, execute these **read-only** checks:

```powershell
C:\xampp\php\php.exe scripts/inspect-well-yu-database.php
C:\xampp\php\php.exe scripts/inspect-welyu-wellness.php --plan
```

The extension checker exits 2 when tables are missing, 1 on inspection failure,
and 0 when complete. `--plan` only writes the local SQL completion file. It
checks table engines, columns/types/nullability, primary and named indexes,
foreign-key targets and RESTRICT deletion rules. It never repairs an
incompatible table. A schema conflict produces a BLOCKED completion file.

The local migration is already complete; **do not import it again or import a
seed**. The following procedure is for a separately approved new installation or
compatible partial initialization. After explicit approval, review and import only
`database/well-yu/wellness-completion.sql` in phpMyAdmin, with `well_yu_db`
selected on the configured server/port. The file contains one `USE well_yu_db`
and missing-table `CREATE TABLE IF NOT EXISTS` statements in dependency order.
It has no inserts, account changes, updates, deletes, ALTERs, table drops,
database creation, credentials or fixtures. `01-wellness.sql` is the canonical
definition; the generated completion file is preferred for a partial retry.
MariaDB DDL commits statements individually: if an import fails, inspect again
and regenerate the missing-only file instead of deleting or recreating tables.

Rerun both inspections after import. With this extension alone, expect 45 total
tables: the 35 original tables with 53 original foreign keys, plus 10 new tables
with 96 columns and 26 additional foreign keys. A successful replan contains no
CREATE statements. Keep auto_migrate, auto_seed and auto_create disabled.

The existing investor account can contribute after extension activation. Through
a separately approved account setup, use the existing staff
administration/activation flows to establish an authorized city reviewer. An
administrator is needed for reviewed scoring
configuration and record review; browsing and manual previews do not require an
administrator. Do not import the legacy seed to obtain an account. Never enable
the private automatic administrator initializer casually: it can change account
records. Use WELL-YU-specific settings and credentials only.

An active admin in CICTO/ICT, ASSESSOR/CAO or LEBDO can save a new scoring
configuration under **Scoring & imports**. Enter all six weights totaling 100%,
direction, units and normalization bounds. Start provisional unless a suitable
validation reference and scope actually exist. No validated weights are bundled.

## Calculation and evidence rules

Methodology: `welyu-wellness-1.0`.

| Criterion | Scope |
| --- | --- |
| access_affordability | Community access and affordability |
| health_access | Relevant health facility access |
| active_mobility | Walking, wheeling and entrance accessibility |
| operating_readiness | Infrastructure and operating readiness |
| cost_feasibility | Property and implementation costs |
| market_context | Services, competition and demand evidence |

For maximize rules, normalization is
`clamp(100 × (raw − minimum) / (maximum − minimum), 0, 100)`.
Minimize rules use `100 − normalized`. MCE is the sum of normalized criteria
times their weights divided by 100. All six criteria require a raw value,
source and valid nonfuture observation date. Zero is a value; missing is not.
An incomplete criterion withholds the composite without redistributing weights.

Wellness IAI uses the same weighted score only after zoning, permit, sanitation,
environment and hazards each have reviewed, dated evidence. Otherwise it is
withheld. Any reported critical restriction withholds both composites. Source
review is distinct from validated weights; complete inputs may remain
provisional. There are no validated investment pass/fail thresholds or automatic
rank recommendations. This wellness IAI definition is separate from legacy
property IAI. A favorable score never overrides a recorded restriction or
constitutes official zoning, sanitation, hazard or water-quality clearance.

Break-even visits/month is `ceil(fixed monthly cost / positive per-visit margin)`;
missing inputs or a nonpositive margin withhold it. Integer cent arithmetic
avoids floating-point rounding at the ceiling. Money supports two decimals.
Expected customers are an explicit assumption, never derived from votes or
population reach. Startup cost is recorded separately from recurring break-even.
Coverage requires source-backed disjoint units; overlapping polygons and
unrecorded existing coverage cannot be resolved automatically.

## Permissions, persistence and files

Reviewed directory/need profiles are public. Unreviewed records are visible to
their active owner and authorized reviewer. Proposals, assessments, scenarios,
comparisons, activity history and supporting documents are private to authorized
owners/reviewers. Downloading a public service profile does not authorize its
private documents. Comparison/PDF access rechecks every assessment's current
proposal and linked-property permission. No self-review or owner-supplied
`reviewed` evidence flag can bypass the assessment review checks.

| Files | Purpose |
| --- | --- |
| `wellness.php`, `assets/js/wellness.js`, `assets/css/wellness.css` | Connected soft-theme workspace |
| `api/wellness.php` | Existing-session/CSRF API, stateless previews, persistence and downloads |
| `app/Repositories/WellnessRepository.php` | DB target guard, permissions, versioned records, transactions |
| `app/Support/Wellness{Engine,Records,Policy,Brief}.php` | Pure calculations, validation, access policy, deterministic PDF |
| `app/Support/CityShell.php` | Shared navigation and map assets |
| `property-details.php`, `voting-dashboard.php`, `city-pipeline.php` | Entry points from existing workflows |
| `tests/property-wizard-classification.test.cjs` | Browser environment stub repair for the existing regression test |
| `database/well-yu/01-wellness.sql`, `wellness-completion.sql` | Reviewed additive schema and missing-only plan |
| `scripts/inspect-welyu-wellness.php` | SELECT-only extension inspection and local plan generation |
| `tests/wellness-*.test.php`, `tests/wellness-*.browser.cjs` | Offline reference/permission/save-plan checks and browser workflows |
| `data/wellness-documents/` | Gitignored, web-blocked private upload storage |

New tables: `welyu_configurations`, `welyu_services`, `welyu_needs`,
`welyu_proposals`, `welyu_assessments`, `welyu_scenarios`, `welyu_comparisons`,
`welyu_comparison_items`, `welyu_events`, `welyu_documents`. Existing `users`
and `properties` are referenced inside `well_yu_db` only. All new foreign keys
use ON DELETE RESTRICT. No existing table definitions are changed.

## Verification and an investment comparison walkthrough

Safe offline checks (no MySQL connection):

```powershell
C:\xampp\php\php.exe tests/wellness-engine.test.php
C:\xampp\php\php.exe tests/wellness-permissions.test.php
C:\xampp\php\php.exe tests/wellness-persistence-plan.test.php
C:\xampp\php\php.exe tests/well-yu-setup-safety.test.php
C:\xampp\php\php.exe scripts/verify-well-yu.php
```

The calculation checks use independently calculated references for weighted
scores, normalization direction, missing sources, zero values, restrictions,
financial arithmetic and population deduplication. SELECT-only PHP doubles
test private-document and export permissions without creating users or records.
PDF checks verify deterministic bytes and the PDF cross-reference offset.
Save-plan checks exercise real repository assessment/comparison/document methods
with PDO doubles that capture prepared statements and bound parameters in PHP
only. They also verify snapshot fingerprints survive JSON serialization. One
legacy wizard classification test now supplies the browser `window` stub its
extracted code requires; its assertions and application behavior are unchanged.

Browser checks use the existing local Apache URL and locally installed Chrome.
Set `PLAYWRIGHT_MODULE` to an installed `playwright-core` module and `PHP_BINARY`
to `C:\xampp\php\php.exe`, then run:

```powershell
node tests/wellness-preview.browser.cjs
node tests/wellness-workflow.browser.cjs
node tests/wellness-review.browser.cjs
node tests/welyu-visual.browser.cjs
```

The preview test uses real read-only bootstrap metadata and stateless server
calculations. The workflow test intercepts every API call into synthetic memory,
calls the real pure PHP engine/PDF generator, and writes only ignored artifacts
under `.ui-audit/`. No accounts or MySQL fixtures are created. The public visual
regression test intercepts all APIs, including legacy GET visit counters.
The reviewer test checks correction responses, review states, rejected unsupported
validation, provisional configuration, import-preview invalidation and commit,
and selecting a need to prefill a proposal, using synthetic memory only.

The desktop/phone workflow exercises this **illustrative** comparison:

1. Add a sourced movement service and a community travel-barrier report.
2. Save opening proposals for sites A and B with the same provisional fixture
   configuration. Six observations at 50 for A and 70 for B produce MCE 50
   and 70. IAI remains withheld because readiness is unverified.
3. Save a baseline: PHP100/visit, PHP40 variable cost, PHP12,000 monthly fixed
   cost and an explicitly assumed 300 monthly customers. Break-even is 200
   visits/month. With no coverage data, population reach remains unknown.
4. Save an alternative tied to that baseline at PHP120/visit. Break-even is 150;
   the baseline stays unchanged. These are arithmetic scenarios, not forecasts.
5. Save a comparison of A and B, inspect frozen inputs/sources/scenarios and
   download the PDF. Re-exporting that snapshot reproduces the same PDF bytes.
6. In a separately authorized live acceptance test, use another reviewer account
   for scoped review, attach evidence, record a visit/decision/implementation,
   then enter observed availability/usage and respond to correction reports.

The approved migration succeeded against the real local database. Metadata checks
and before/after fingerprints confirm that the existing schema and records
survived unchanged. Live guest bootstrap and stateless preview checks pass with
the extension installed. Authenticated data writes have not been exercised: the
migration approval did not include test inserts, accounts or scoring setup.
A live acceptance test must use explicitly authorized records. Synthetic tests
do not prove successful live INSERTs; the applied DDL was verified separately.
Do not run the whole legacy test suite: some older
tests create/drop databases or submit real mutations.

Remaining dependencies: an authorized reviewer account and scoring/validation
process; real dated service, community, cost and readiness
evidence; map-tile connectivity and any separately authorized route/official
data integrations. Manual entry and reviewed imports cover missing integrations.
The current bounded prototype lists up to 500 directory/proposal records and
100 assessment/comparison versions per query; larger deployments need paging.
PDF text uses Windows-1252 transliteration, so arbitrary Unicode names require
a future embedded-font renderer.
