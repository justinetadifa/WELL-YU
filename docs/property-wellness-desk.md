# Property & wellness desk

Open `http://localhost/WELL-YU/admin-properties.php` while signed in with a city department account.

The desk combines the existing property portfolio with three discovery views:

- **Nearby investments:** the first three approved, available properties nearest a recorded care anchor within 2 km. Cards show asking prices, approximate straight-line distance, and the number of nearby facilities. The expandable methodology explains selection. This is a proximity shortlist, not a financial rating or a site approval.
- **Health & wellness facilities:** 32 entries from the existing San Fernando inventory, searchable by facility, address, or barangay and filterable by category. Five entries link to official identity/address sources. The inventory date and the need to confirm current operations are visible.
- **Business ideas:** six complementary wellness concepts with descriptions, location considerations, and validation steps in accessible dialogs. Planning links open the existing wellness workspace.

The GET-only `api/property-wellness.php` requires the same city department access as the page. The discovery helper does not persist records, update assessments, or make network calls. Property saves and lifecycle changes refresh the shortlist. Existing listing search, filters, reviews, and the property wizard remain available.

The facility snapshot is dated April 12, 2026; linked sources were checked November 18, 2026. LUMED's operator address differs from its inventory point, so its address is corrected for display and its coordinates are excluded from proximity calculations pending verification.

Validation: `C:/xampp/php/php.exe tests/property-wellness-discovery.test.php` covers eligibility, distances, stable ordering, missing coordinates, pricing units, source labels, and empty states. Browser QA at 1440, 768, and 390 pixels covers tabs, keyboard navigation, facility filters, idea dialogs, retry, original listing controls, and the property wizard.
