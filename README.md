<p align="center">
  <img src="assets/images/welyu-logo-clean.png" alt="wELYU Logo" width="130" />
</p>

<h1 align="center">wELYU (WELL-YU)</h1>
<p align="center">
  <strong>Wellness &amp; Land Intelligence Platform · City of San Fernando, La Union</strong><br>
  <em>Connecting community wellness needs, spatial property intelligence, and municipal investment facilitation.</em>
</p>

---

## 1. Executive Overview: What Does This System Do?

**wELYU** (`WELL-YU`) is a web-based **Wellness & Land Intelligence Decision-Support System** tailored for the **City of San Fernando, La Union**. Built upon the **LOCUS-SF / BIZSTART** spatial real-estate foundation, wELYU bridges three critical domains in local economic and health-focused development:

1. **Community Need & Service Gap Intelligence (`wellness.php`)**: Identifies where health, wellness, preventive care, active-living, and community support services already exist across San Fernando’s **59 barangays**—and where residents face documented barriers in affordability, travel distance, operating hours, capacity, or physical accessibility.
2. **Spatial Property & Parcel Evaluation (`property-explorer.php`, `property-ranking.php`)**: Helps capital investors, healthcare operators, and wellness developers discover available land parcels and commercial buildings, inspect 500-meter surroundings and 10-kilometer service catchments, screen against **CLUP zoning eligibility**, and evaluate sites through transparent **Multi-Criteria Evaluation (MCE)** and **Investment Attractiveness Index (IAI)** methodologies.
3. **Statutory Incentives & Municipal Concierge Facilitation (`index.php`, `city-pipeline.php`)**: Connects investors directly to the City of San Fernando’s **Investments and Incentives Code (Ordinance No. 2024-41)**, RTWPB Region I operating cost benchmarks, BPLO unified permitting templates, **PRC-verified property brokers**, and the **Local Economic and Business Development Office (LEBDO)** concierge desk.

---

## 2. Core System Modules & Capabilities

### A. Wellness Investment & Community Need Workspace ([`wellness.php`](wellness.php) · [`api/wellness.php`](api/wellness.php))
Powered by the 10-table additive wellness schema (`welyu_*`) and [`app/Support/WellnessEngine.php`](app/Support/WellnessEngine.php):
- **Recorded Service Directory & 10 km Context Radar**: Maps existing health facilities, clinics, wellness operators, nonprofits, and free public alternatives on an interactive Leaflet map. Distinguishes between *recorded services* and *missing records* (never falsely claiming an unrecorded service does not exist), and computes straight-line distances within a 10 km radius.
- **Barangay Community Needs Registry**: Captures and tracks barangay-specific service gaps and access barriers (affordability, travel, hours, capacity, accessibility) with source attribution, collection dates, and reviewer verification states.
- **Proposal Planning (Open · Improve · Partner)**: Allows investors and operators to model a new venture from an identified community need, a listed property parcel, or a standalone concept across three modes:
  - **Opening** a new wellness/community facility
  - **Improving** an existing owned service with documented barrier responses
  - **Partnering** with existing local providers or public programs
- **Versioned 6-Criterion Wellness Assessment (`welyu-wellness-1.0`)**:
  | Criterion Key | Evaluation Scope |
  | :--- | :--- |
  | `access_affordability` | Community pricing accessibility and affordability alignment |
  | `health_access` | Proximity and complementarity to relevant health facilities |
  | `active_mobility` | Walking, wheeling, transit, and entrance accessibility |
  | `operating_readiness` | Utility, facility, and operational readiness |
  | `cost_feasibility` | Property acquisition/lease and implementation cost feasibility |
  | `market_context` | Existing service density, competition, and demand evidence |
  - **Strict Evidence & Readiness Gates**: All six criteria require a raw value, source, and valid observation date (incomplete criteria withhold the composite score rather than redistributing weights or substituting zero). The **Wellness IAI** composite is unlocked only after **zoning, permit, sanitation, environment, and hazard** clearances each have reviewed, dated evidence and zero critical restrictions.
- **Break-Even Financial Scenarios & Side-by-Side Comparison**:
  - Models baseline and alternative scenarios for operating hours, capacity, pricing, fixed monthly costs, startup CAPEX, and explicit customer assumptions, computing monthly break-even visits via exact integer-cent arithmetic:
    $$\text{Break-Even Visits/Month} = \left\lceil \frac{\text{Fixed Monthly Cost}}{\text{Positive Per-Visit Margin}} \right\rceil$$
  - Compares **2 to 5 frozen assessment versions** side by side and exports a deterministic, downloadable **Private PDF Brief** ([`app/Support/WellnessBrief.php`](app/Support/WellnessBrief.php)).
- **Implementation & Follow-Up Desk**: Tracks post-assessment pipeline stages, site visits, confirmed inquiries, implementation evidence, and operator follow-up observations separately from projected outputs.

---

### B. Curated Property Collection, Spatial Explorer & MCE/IAI Ranking
- **Interactive Spatial Explorer ([`property-explorer.php`](property-explorer.php))**: Split-pane map and catalog supporting 13 commercial and land categories (*Land, Hospitality, Senior Living, Special Purpose, Medical/Office, Retail, Mixed Use, Multifamily, Industrial, Self Storage, Mobile Home Park, Note/Loan, Business for Sale*) with parcel boundary polygons, satellite/street map layers, and barangay filters.
- **Priority Board & Site Comparison ([`property-ranking.php`](property-ranking.php), [`site-comparison.php`](site-comparison.php))**: Ranks fully assessed listings by their composite score and allows side-by-side comparison of parcel metrics, pricing, hazards, and criteria breakdowns.
- **Basic vs. Advanced Investor Lens ([`assets/js/investor-view.js`](assets/js/investor-view.js))**:
  - **Basic View**: Surfaces essential price, area, barangay location, broker contact, and hazard notices first.
  - **Advanced View**: Unlocks full MCE/IAI criterion weights, calculation formulas, 500-meter competitor/complement radar, and supporting evidence attachments.
- **5-Step Property & Evidence Wizard ([`docs/property-wizard.md`](docs/property-wizard.md))**: Used by brokers and city departments (*Basics $\rightarrow$ Parcel Boundary $\rightarrow$ Site Evidence $\rightarrow$ 500m Surroundings Radar $\rightarrow$ Review*), supporting separate survey vs. polygon-calculated areas and private document uploads.

---

### C. Investor Signal Atlas & City Pipeline ([`city-pipeline.php`](city-pipeline.php) · [`voting-dashboard.php`](voting-dashboard.php))
- **Dual-Lens Opportunity Atlas**: Separates **Whitespace Gaps** (validated unmet community/investor demand) from **Projects in Motion** (active commercial and wellness developments already underway) across San Fernando’s primary growth corridors (**Poro Point Special Economic & Freeport Zone** and **Barangay Biday Commercial Growth Center**), preventing duplicate-build saturation.

---

### D. Municipal Investment Concierge & Statutory Framework ([`index.php`](index.php))
- **Ordinance No. 2024-41 (Investments and Incentives Code)**: Interactive showcase of the City of San Fernando’s 7 Priority Investment Sectors (*Agriculture/Fishery, Agro-Support Facilities, Tourism & Transportation, ICT/BPM, Manufacturing/Processing, Infrastructure/Water/Sanitation/Property Development, and Ecological Solid Waste Management*).
- **Catalyst Locator Registry**: Profiles major incentive recipients (**TaskUs La Union**, **Robinsons La Union**, **La Union Cultural Institute / LUCS**, and **SM City La Union**) with approved business tax holiday timelines (4–6 years).
- **Cost of Doing Business Guidance**: Interactive Region I statutory cost reference covering RTWPB Wage Order No. RB1-23 (`₱468.00/day` non-agriculture; `₱435.00/day` agriculture/micro), commercial lease rates, power, water, and telco parameters.
- **BPLO Unified Permitting & Downloadable Forms**: Direct links to 5 official city permitting guides and 10 downloadable statutory forms (Unified Business Permit, Building, Architectural, Civil/Structural, Sanitary/Plumbing, Electrical, Mechanical, Electronics, Demolition, and Individual Work Permits).
- **Direct LEBDO Concierge**: Connects investors to City Investment Officers (**Rizalyn D. Medrano, EnP** and **Irish Dominique D. Halabaso**), the direct trunkline `(072) 619 - 7170`, and the **Permanent Business One Stop Shop (BOSS) Building**.

---

### E. Role-Based Governance, Security & Data Privacy
| Role | Primary Capabilities & Access Scope |
| :--- | :--- |
| **Guest** | Landing discovery, 3-property preview, public wellness directory/needs browsing, pipeline signal overview, and statutory guides |
| **Investor** | Full property catalog & map, Basic/Advanced MCE view, saved sites, comparisons, wellness proposal/scenario modeling, PDF brief export, broker messaging, document requests, and site-visit booking |
| **Broker (`seller`)** | PRC credential submission (front/back ID + registration number), two-stage email + CAO/LEBDO approval workflow, own listing management, and investor inquiry/visit coordination |
| **CICTO (`admin`)** | Technical administration, staff account provisioning & passkey onboarding, reviewer permission grants, and listing approval/decline |
| **CAO / City Assessor** | Departmental property entry, 7-criterion spatial/hazard assessment, wellness scoring configuration, and authorized broker review |
| **LEBDO** | Investment facilitation, priority sector alignment, wellness need/service review, departmental assessment, and authorized broker review |

---

## 3. Current Weaknesses & Roadmap for Refinement into a Full Wellness Investment Website

> [!IMPORTANT]
> This section documents the **current architectural and product limitations** of the prototype so they can be systematically addressed in the next phase of turning **wELYU** into a dedicated, end-to-end **Wellness Investment Platform**.

### 1. Dual-Track Separation Between Property Listings and Wellness Intelligence
- **Current State**: The property catalog ([`property-explorer.php`](property-explorer.php), [`property-ranking.php`](property-ranking.php)) still evaluates parcels using generic commercial real-estate criteria inherited from LOCUS-SF, while [`wellness.php`](wellness.php) operates as a separate workspace tab.
- **Refinement Opportunity**: Deeply unify the two engines so every property card and detail page natively displays a **Wellness Suitability Profile**—such as proximity to hospitals/clinics, barangay unmet health/wellness needs, active-mobility/walkability scores, coastal/green-space environmental quality, and recommended wellness asset classes (e.g., *Preventive Care Clinic, Boutique Recovery Resort, Senior Active-Living, Fitness & Rehab Hub, or Farm-to-Table Wellness Center*).

### 2. Cold-Start / Empty Public Experience on Unseeded Databases
- **Current State**: Because `auto_migrate` and `auto_seed` are strictly disabled for database isolation and `welyu_configurations` starts empty until a city reviewer manually defines weights, a fresh checkout displays `0 Active Listings`, `0 Live Signals`, and `No properties match your search` on the public homepage and pipeline. Furthermore, testing the wellness scoring engine requires manually typing all 6 raw criteria and dates.
- **Refinement Opportunity**: Provide curated, clearly badged **Wellness Investment Benchmark Showcases & Templates** (e.g., pre-built wellness resort, diagnostic center, and senior-living investment packages in Poro Point and Biday) and one-click **"Load Sample Wellness Scenario"** presets so prospective investors immediately see rich data, scored comparisons, and realistic financial models on their first visit.

### 3. Straight-Line Distance vs. Demographic & Catchment Intelligence
- **Current State**: Both the 500-meter property surroundings radar and the 10-kilometer wellness service radar rely on straight-line coordinates and manual evidence uploads, without demographic overlays or road-network travel times.
- **Refinement Opportunity**: Layer barangay-level **population density, age cohorts (e.g., senior population, families, workforce), tourism footfall, and 5/10/15-minute drive/walk catchment rings** onto the map so wellness investors can quantify underserved patient or guest demand per barangay.

### 4. Simplistic Financial Modeling for Capital-Intensive Wellness Real Estate
- **Current State**: The financial calculator in [`app/Support/WellnessEngine.php`](app/Support/WellnessEngine.php) computes a single-tier monthly visit break-even (`Fixed Monthly Cost / Per-Visit Margin`), which suits a small outpatient clinic but does not model multi-stream wellness real estate (room keys/beds, membership tiers, ancillary spa/diagnostics revenue, land lease vs. purchase CAPEX, or multi-year payback).
- **Refinement Opportunity**: Upgrade the scenario simulator into a **Wellness Capital & ROI Modeler** that calculates:
  - **Ordinance No. 2024-41 Tax Holiday Savings** (estimating 4–6 years of local business tax and real property tax exemptions)
  - **CAPEX / OPEX per sqm** using San Fernando’s RTWPB Region I wage and utility rates
  - **5-Year Cash Flow, Net Operating Income (NOI), Cap Rate, and Payback Period** across clinic, hospitality-wellness, and active-living typologies.

### 5. Fragmented Investor Conversion Funnel Across Pages
- **Current State**: An investor must navigate across four separate pages (`city-pipeline.php` $\rightarrow$ `property-explorer.php` $\rightarrow$ `wellness.php` $\rightarrow$ `index.php#investment-concierge`) to move from discovering a demand gap to booking a site consultation.
- **Refinement Opportunity**: Build a guided **"Wellness Investment Concierge Flow"** (*1. Select a Wellness Sector & Barangay Gap $\rightarrow$ 2. Match a Zoning-Cleared Site $\rightarrow$ 3. Simulate ROI & Tax Incentives $\rightarrow$ 4. Export Brief & Schedule a LEBDO/Broker Site Visit*).

---

## 4. Visual Design System & Brand Identity

The interface has been redesigned around the **wELYU** brand crest ([`assets/images/welyu-logo-clean.png`](assets/images/welyu-logo-clean.png) and [`assets/images/welyu-shield.png`](assets/images/welyu-shield.png)) and an architectural **Gallery Wall** aesthetic ([`assets/css/welyu-theme.css`](assets/css/welyu-theme.css)):

- **Typography ([`assets/css/fonts.css`](assets/css/fonts.css))**:
  - **Display Headlines (`h1`, `h2`)**: `Clash Display` (`font-weight: 500`, `letter-spacing: -0.015em`, `word-spacing: 0.1em`)
  - **Editorial Serifs & Accents**: `Zodiak` & `Sentient` italic
  - **UI, Cards, Controls & Body (`h3`–`h6`, `p`, `button`)**: `Cabinet Grotesk`
- **Color Palette**:
  | Token | Hex | Role |
  | :--- | :--- | :--- |
  | `--wy-paper` | `#FBFBF9` | Warm alabaster gallery canvas |
  | `--wy-white` | `#FFFFFF` | Elevated card & modal surfaces |
  | `--wy-navy` | `#011944` | Deep brand navy (shield crest, primary metrics, active toggles) |
  | `--wy-blue` | `#0056FD` | Royal blue brand accent |
  | `--wy-forest` | `#223F31` | Architectural forest green (chapter kickers, tags, verified badges) |
  | `--wy-lime` | `#E5FBB9` | Signature pale-lime primary action pills & highlights |
  | `--wy-ink` | `#111417` | High-contrast primary typography |
  | `--wy-muted` | `#5D6761` | Secondary editorial copy & captions |

---

## 5. System Architecture & Directory Structure

```text
WELL-YU/
├── api/                          # JSON REST endpoints (health, properties, wellness, messages, visits)
│   └── wellness.php              # Wellness directory, needs, proposals, assessments, scenarios & PDF brief API
├── app/
│   ├── Core/                     # Database connection (PDO) & SchemaManager
│   ├── Repositories/             # Data access layer (WellnessRepository.php, property & user queries)
│   ├── Support/                  # Core domain services & shared layout shell
│   │   ├── CityShell.php         # Shared header, footer, navigation, font preloads & asset versioning
│   │   ├── PropertyAssessmentService.php # MCE & IAI property scoring engine
│   │   ├── WellnessEngine.php    # Pure 6-criterion wellness scoring, normalization & break-even engine
│   │   ├── WellnessBrief.php     # Deterministic PDF brief generator for multi-version comparisons
│   │   ├── WellnessPolicy.php    # Role & ownership access-control rules for wellness records
│   │   └── WellnessRecords.php   # Input validation, fingerprinting & normalization
│   ├── Views/
│   │   └── welyu-home-hero.php   # Cinematic chapter 01 hero component with live listing & city stats
│   └── config.php                # Environment-prefixed (WELL_YU_) application & database configuration
├── assets/
│   ├── css/                      # Modular stylesheets (fonts.css, welyu-theme.css, wellness.css, etc.)
│   ├── fonts/                    # Self-hosted WOFF2 Fontshare families (Clash Display, Cabinet Grotesk, Zodiak)
│   ├── images/                   # Brand assets (welyu-shield.png, welyu-logo-clean.png) & San Fernando media
│   └── js/                       # ES module frontend controllers (wellness.js, city-workspace.js, etc.)
├── database/
│   └── well-yu/                  # Isolated WELL-YU SQL schemas (setup.sql, 01-wellness.sql, wellness-completion.sql)
├── docs/                         # Technical, governance, setup & visual design documentation
├── scripts/                      # Read-only DB & schema inspection utilities
├── tests/                        # Offline PHP unit tests & Playwright headless browser QA suites
├── index.php                     # Public landing page (Chapters 01–07)
├── wellness.php                  # Connected Wellness Intelligence Workspace
├── city-pipeline.php             # Investor Signal Atlas & City Pipeline
├── property-explorer.php         # Interactive Leaflet Map & Property Catalogue
├── property-ranking.php          # MCE / IAI Priority Board
└── property-details.php          # Deep property assessment & surroundings radar view
```

---

## 6. Local Setup & Database Isolation (`well_yu_db`)

The application uses **PHP 8+**, **PDO (MySQL/MariaDB)**, and native **ES Modules** (no Composer or npm build step required at runtime).

1. **Place Repository in XAMPP**:
   Clone or place the project inside `C:\xampp\htdocs\WELL-YU` and start **Apache** and **MySQL** in XAMPP.
2. **Database Target (`well_yu_db` only)**:
   - Default local connection: `127.0.0.1:3306`, database `well_yu_db`, user `root`, empty password.
   - Local overrides live in gitignored `app/config.local.php` (copy from [`app/config.local.php.example`](app/config.local.php.example)).
   - Automatic migration (`auto_migrate`) and automatic seeding (`auto_seed`) are **disabled by default** to guarantee data safety.
   - With the base schema (35 tables, 53 foreign keys) and the 10-table wellness extension (`welyu_*`), the complete database contains **45 tables and 79 foreign keys**.
3. **Read-Only Database Verification**:
   ```powershell
   C:\xampp\php\php.exe scripts/verify-well-yu.php --database
   C:\xampp\php\php.exe scripts/inspect-well-yu-database.php
   C:\xampp\php\php.exe scripts/inspect-welyu-wellness.php --plan
   ```
   See [`docs/well-yu-setup.md`](docs/well-yu-setup.md) and [`docs/welyu-wellness.md`](docs/welyu-wellness.md) for full schema management details.
4. **Open in Browser**:
   - Landing Page: [http://localhost/WELL-YU/](http://localhost/WELL-YU/)
   - Wellness Workspace: [http://localhost/WELL-YU/wellness.php](http://localhost/WELL-YU/wellness.php)
   - City Pipeline: [http://localhost/WELL-YU/city-pipeline.php](http://localhost/WELL-YU/city-pipeline.php)
   - Property Explorer: [http://localhost/WELL-YU/property-explorer.php](http://localhost/WELL-YU/property-explorer.php)

---

## 7. Automated Testing & Visual QA

Run the offline PHP verification suites and Playwright visual/responsive checks from the repository root:

```powershell
# Safe offline PHP unit & regression checks (no database writes)
C:\xampp\php\php.exe tests/well-yu-setup-safety.test.php
C:\xampp\php\php.exe tests/wellness-engine.test.php
C:\xampp\php\php.exe tests/wellness-permissions.test.php
C:\xampp\php\php.exe tests/wellness-persistence-plan.test.php
C:\xampp\php\php.exe tests/account-workflow.test.php
C:\xampp\php\php.exe tests/landing-concierge.test.php
C:\xampp\php\php.exe tests/business-opportunities.test.php

# Headless Chromium visual & responsive QA across 1440px, 768px, and 390px viewports
$env:PLAYWRIGHT_MODULE = "C:\xampp\htdocs\WELL-YU\.ui-audit\tooling\node_modules\playwright-core"
node tests/welyu-visual.browser.cjs
```

---

## 8. Documentation Index

- **[Wellness Workflows & Methodology (`welyu-wellness-1.0`)](docs/welyu-wellness.md)**
- **[Visual Design Notes](docs/welyu-visual-design.md)**
- **[Database Setup & Isolation Review](docs/well-yu-setup.md)**
- **[Database Import Recovery Procedure](docs/well-yu-import-recovery.md)**
- **[Property Editor & 5-Step Wizard](docs/property-wizard.md)**
- **[CLUP Zoning Governance](docs/CLUP_GOVERNANCE.md)**
- **[System Architecture & Manuscript Documentation](docs/system-manuscript-documentation.md)**
