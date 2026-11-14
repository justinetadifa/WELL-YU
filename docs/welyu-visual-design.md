# wELYU interface redesign

This pass implements the user's visual reference across the existing local PHP
application. The user confirmed **visual redesign throughout first**; the attached
wellness feature brief supplies product direction, not authorization to implement
or migrate those new workflows during this pass.

## Visual language

| Use | Color |
| --- | --- |
| Warm white canvas | `#FCFDF9` |
| White surfaces | `#FFFFFF` |
| Primary ink | `#293C32` |
| Secondary text | `#687562` |
| Forest accent | `#3E5F4C` |
| Sage | `#DCE7DC` |
| Pale lime action | `#E5FBB9` |
| Quiet borders | `#E0E7DC` |

Inter remains locally bundled. Lighter headings, rounded cards and controls,
subtle borders, restrained shadows and more space replace the previous dominant
red/navy presentation. Error/rejection/high-risk states retain contrasting
terracotta treatments. The photographic hero uses an existing local image of San
Fernando, not a fabricated service directory or fictional property inventory.

## Implementation

- `assets/css/welyu-theme.css` is shared through `CityShell.php`. Its first cascade
  layer overrides legacy component styling without changing DOM hooks, control
  names or application behavior. It covers cards, filters, tables, inputs,
  maps/controls, reports, pipeline, simulator, staff/broker surfaces, property
  editor, dialogs, account pages and print presentation.
- `app/Views/welyu-home-hero.php` supplies native PHP/HTML hero markup. The existing
  actual listing count, login/catalogue destinations, homepage anchors and section
  navigation remain connected. The previous React hero's source/assets are
  retained but its hydration script is no longer loaded on this homepage.
- `assets/images/welyu-mark.svg` is a code-authored four-leaf mark. The shared
  header, footer, favicon, help and account surfaces use current wELYU branding.
  Historical LOCUS-SF methodology names and research acknowledgments remain.
- `assets/js/welyu-interface.js` progressively adds a small, reduced-motion-aware
  entrance treatment. It makes no network requests or persistent writes.
- `account-view.php` and `auth-interface.js` use calmer copy and matching poster
  states. The signup heading is preserved instead of being overwritten by the
  sign-in welcome initializer. Role switching, validation, password visibility
  and request targets remain connected.
- Application name and mail display name are `wELYU` in the shared config, local
  example and ignored local config. Database names, isolated cookies/session
  storage, credentials and disabled automatic migrations/seeding remain intact.

## Verification

`tests/welyu-visual.browser.cjs` uses headless Chromium on localhost and tests seven
public routes at 1440px, 768px and 390px. It verifies page loading, current brand,
consistent action color, horizontal overflow, keyboard focus, homepage anchors,
mobile navigation, privacy/help dialogs, signup heading and password visibility.
Screenshots and the results JSON are saved under ignored `.ui-audit/`.

All API requests are intercepted inside the browser test because some existing
GET APIs record visit metrics. External requests and non-GET/HEAD requests are
blocked. No registrations, login attempts, seeded records or database changes are
performed by this test. It does not bypass permissions or create test accounts.
Authenticated business workflows have not been tested end to end; no account
exists. Shared component styling applies to their existing layouts.

Run with a locally available Playwright installation, for example:

```powershell
$env:PLAYWRIGHT_MODULE = 'C:\xampp\htdocs\WELL-YU\.ui-audit\tooling\node_modules\playwright-core'
node tests/welyu-visual.browser.cjs
```

PHP/JavaScript syntax, the 101 isolation checks, and existing pure classification,
pricing-computation, draft-media and sound checks also pass. Read-only inspection
confirms the existing 35-table/53-foreign-key schema remains complete. No imports,
migrations, seeding, Git pushes or thesis-project edits were performed.

Preview at `http://localhost/WELL-YU/`. Refresh an already-open page to load the
new shared stylesheet. No npm build or deployment is needed to view the redesign.
