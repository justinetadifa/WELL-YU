# wELYU (`WELL-YU`) — Pre-Verified Demo Accounts & 1-Click Role Switcher

This document provides the ready-to-use, pre-verified demo accounts for pitching and testing **wELYU: Wellness & Land Intelligence Platform** at the **La Union SIKLAB Conference & Sprint 2026**.

---

## 1. Instant 1-Click Role Switching (Zero Typing Required)

You can switch between all 4 roles from **any page** in a single click using:
1. **Top Navigation Bar ("Demo Role Switcher")**:
   - `[👤 Investor]` — Signs in immediately as **Juan Dela Cruz (Wellness Investor)**
   - `[🏢 PRC Broker]` — Signs in immediately as **Maria Santos, REB (PRC-Verified Broker)**
   - `[🛡️ Admin / LEBDO]` — Signs in immediately as **Justine Tadifa (CICTO Admin & LEBDO/CAO Reviewer)**
   - `[👀 Guest]` — Clears the active session to preview public / unauthenticated views
2. **Sign-In Pages (`investor-login.php`, `seller-login.php`, `admin-login.php`)**:
   - Includes a prominent **1-Click Instant Demo Sign-In** banner at the top of the login card.
3. **Direct Endpoint (`api/demo-switch.php`)**:
   - `api/demo-switch.php?role=investor`
   - `api/demo-switch.php?role=broker`
   - `api/demo-switch.php?role=admin`
   - `api/demo-switch.php?role=guest`
   - Supports optional `&redirect=wellness.php` to stay on your current workspace after switching.

---

## 2. Plain-Text Fallback Credentials

If you wish to test the manual sign-in form, use the following pre-verified credentials:

| Persona | Name | Email | Password | Role & Department | Default Workspace |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **👤 Investor** | **Juan Dela Cruz (Wellness Investor)** | `investor@welyu.local` | `Investor2026!` | `investor` (Verified & Active) | `investor-dashboard.php` / `wellness.php` |
| **🏢 PRC Broker** | **Maria Santos, REB (PRC-Verified Broker)** | `broker@welyu.local` | `Broker2026!` | `seller` (`PRC REB #0028491`, CAO/LEBDO Approved) | `seller-dashboard.php` |
| **🛡️ Admin / LEBDO** | **Justine Tadifa (CICTO Admin & LEBDO/CAO Reviewer)** | `admin@welyu.local` | `Admin2026!` | `admin` (`CICTO` + `CAO` + `LEBDO` All-Access Reviewer) | `admin-dashboard.php` / `wellness.php` |

---

## 3. Pre-Populated Linked Data by Role

### 👤 1. Investor Account (`investor@welyu.local`)
- **Saved Shortlist (`property_shortlists`)**:
  - **#101**: *Biday Preventive Rehab & Senior Mobility Parcel* (1,200 sqm, C-2 Commercial, PHP 14.8M)
  - **#102**: *Catbangen Civic & After-Hours Therapy Annex* (850 sqm, C-2 Commercial, PHP 11.9M)
  - **#103**: *Poro Point Coastal Recovery & Active Mobility Studio Lot* (1,800 sqm, C-2 Mixed-Use, PHP 24.3M)
- **3 Side-by-Side Wellness Proposals (`welyu_proposals`, `welyu_assessments`, `welyu_scenarios`, `welyu_comparisons`)**:
  - **Mode A (`Open`)**: *Biday Preventive Rehab & Senior Mobility Hub* — All 6 `welyu-wellness-1.0` criteria & all 5 municipal readiness gates (`zoning`, `permit`, `sanitation`, `environment`, `hazard`) verified; **IAI 88.88/100**; break-even **136 visits/mo**.
  - **Mode B (`Improve`)**: *Catbangen Clinic Ramp & Evening Hours Upgrade* — Adding a ground-floor PWD ramp + extending operating hours to 8:00 PM; **IAI 86.25/100**; break-even **63 visits/mo**.
  - **Mode C (`Partner`)**: *Poro Point Shared After-Hours Therapy & Fitness Co-Location* — Low-CapEx co-location partnership; **IAI 85.40/100**; break-even **44 visits/mo**.
- **Active Inquiries & Site Visits**:
  - Open message thread with PRC Broker Maria Santos on Parcel #101.
  - Shared engineering & zoning document packet on Parcel #101 + pending document request on Parcel #102.
  - Scheduled joint technical site inspection on Parcel #101 (`visit_logs`).

### 🏢 2. PRC-Verified Broker Account (`broker@welyu.local`)
- **Zero Waiting State**:
  - Email verification (`email_verified_at`) and CAO/LEBDO identity & PRC verification (`identity_verification_status = 'verified'`, `seller_profiles.application_status = 'verified'`) are pre-approved.
  - PRC Registration **`#0028491`** (valid until **`2029-12-31`**) with front and back credential images stored in `data/broker-documents/`.
  - The **"+ Add New Listing"** action in `seller-dashboard.php` is immediately unlocked.
- **Managed Portfolio**:
  - 3 active, city-approved listings in San Fernando City (Brgy. Biday #101, Brgy. Catbangen #102, Brgy. Poro #103) with `authority_to_sell` validated and 100% completed LOCUS-SF MCE/IAI assessments.
  - Incoming investor inquiry thread, document access requests, and scheduled site visit ready to manage.

### 🛡️ 3. Admin & All-Access City Reviewer Account (`admin@welyu.local`)
- **Unified Municipal Governance**:
  - Combines **CICTO System Administrator**, **CAO / City Assessor**, and **LEBDO Wellness & Broker Reviewer** permissions in a single demo account.
  - Can configure MCE weights and `welyu-wellness-1.0` wellness criteria weights/thresholds (`wellness.php` & `admin-dashboard.php`).
  - Can review and verify Barangay community needs (`welyu_needs`), wellness services (`welyu_services`), proposals (`welyu_proposals`), and all 5 readiness clearance gates.
  - Can review PRC broker accreditations, property listings, authority-to-sell documents, and municipal ground-truth field audits.

---

## 4. Re-Seeding via CLI

To reset or re-verify the demo accounts and their linked showcase records at any time from the terminal:

```powershell
C:\xampp\php\php.exe scripts/seed-demo-accounts.php
```
