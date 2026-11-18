# Available property import — November 18, 2026

The live local app is `C:\xampp\htdocs\WELL-YU`, served at <http://localhost/WELL-YU/> and connected to `well_yu_db`. The nested `sfcelerate-bizstart/WELL-YU` folder is a different copy.

The seven publicly available properties from `sfcelerate_bizstart` were appended to the live WELL-YU catalog, retaining property IDs `56`, `125`, `126`, `127`, `148`, `216`, and `217`. Their ten media records, seven due-diligence records, property fields, uploaded files, approval states, and availability were copied. The source creator account `1` was copied as WELL-YU account `11` to preserve attribution without replacing an existing account.

The existing demo properties `101`–`103`, accounts, wellness proposals, assessments, and other readable tables were preserved. The resulting public catalog contains **10 properties**. The source database was not changed. This is an independent snapshot; future edits do not synchronize automatically.

The backup before this import is `database/backups/backup_before_bizstart_properties_20261118_075117.sql`. It contains the 45 readable tables. The pre-existing `clup_review_events` table was unreadable in the storage engine, so it was excluded from that backup and left untouched.

Validation checked all 455 imported property fields, allowing the creator ID remap, and verified preservation of existing rows and wellness data. Both `api/properties.php` and `api/bootstrap.php` return all ten properties. A signed-in investor browser check confirmed ten Priority board cards with no JavaScript errors.
