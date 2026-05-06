# OCCI Sacramental Records — Claude Code Context

## Project Overview
WordPress plugin for Old Catholic Churches International (OCCI).
Provides a complete canonical sacramental records management system — all six principal sacramental registers stored in MariaDB/MySQL within WordPress.

**Current version:** 1.0.4
**GitHub repository:** https://github.com/BishopGreer/OCCI-sacramental-record
**Working directory:** ~/Projects/occi-sacramental-records

## Versioning Rules
- Every change request bumps the version incrementally: 1.0.5, 1.0.6, etc.
- Version is updated in BOTH the plugin header (`* Version: X.X.X`) AND the `OCCI_SR_VERSION` constant in `occi-sacramental-records.php`.
- ZIP filename always includes the version: `occi-sacramental-records-X.X.X.zip`
- A GitHub release tag (`vX.X.X`) triggers the Actions workflow to build and publish the release ZIP automatically.
- Only use a major version bump (e.g., 1.1.0, 2.0.0) if explicitly requested.

## File Structure
```
occi-sacramental-records/
├── occi-sacramental-records.php   # Main plugin file, constants, activation hooks
├── readme.txt                      # WordPress readme with full changelog
├── CLAUDE.md                       # This file
├── GITHUB_SETUP.md                 # One-time GitHub push instructions
├── .gitignore
├── .github/workflows/release.yml   # Auto-builds ZIP on version tag push
├── admin/
│   ├── css/occi-admin.css
│   └── js/occi-admin.js
├── assets/images/
│   └── certificate-template.png   # Default OCCI blank certificate (792x1056px)
└── includes/
    ├── class-occi-admin.php        # Admin menu, footer bar, settings pages
    ├── class-occi-baptism.php      # Baptism register CRUD
    ├── class-occi-certificates.php # Certificate printing + Person Report
    ├── class-occi-communion.php    # First Communion register CRUD
    ├── class-occi-confirmation.php # Confirmation register CRUD (flat per-person model)
    ├── class-occi-database.php     # Schema creation, all table definitions
    ├── class-occi-death.php        # Death/burial register CRUD
    ├── class-occi-import-export.php# JSON export and import with deduplication
    ├── class-occi-marriage.php     # Marriage register CRUD
    ├── class-occi-ordination.php   # Ordination register CRUD
    ├── class-occi-parishes.php     # Parish registry with per-parish cert templates
    ├── class-occi-report.php       # Person Sacramental Report (cross-register search)
    ├── class-occi-updater.php      # Custom updater (self-hosted JSON or GitHub releases)
    └── functions.php               # Shared helper functions
```

## The Six Registers

### Baptism (`occi_baptisms`)
- date, birth_date, birth_place
- baptismal_first, baptismal_middle, baptismal_last (name at time of baptism)
- father_first, father_middle, father_last
- mother_first, mother_middle, mother_last, mother_maiden (required)
- sponsor1_first, sponsor1_last, sponsor1_gender
- sponsor2_first, sponsor2_last, sponsor2_gender
- proxy flag + proxy_for name
- minister_name, minister_type
- parish_id, alternate_location
- notations, confidential flag, volume, page_number

### Confirmation (`occi_confirmations`) — flat per-person model
- confirmation_date
- confirming_bishop (required)
- first_name, middle_name, last_name (required)
- saints_name (optional)
- parish_id, alternate_location, notations

### Marriage (`occi_marriages`)
- marriage_date
- party1_first, party1_middle, party1_last, party1_maiden, party1_birth_date
- party2_first, party2_middle, party2_last, party2_maiden, party2_birth_date
- witness1_first, witness1_last, witness2_first, witness2_last
- minister_name, parish_id, alternate_location, notations

### Death/Burial (`occi_deaths`)
- death_date, first_name, middle_name, last_name
- burial_location, burial_city, burial_state
- funeral_date, presider, parish_id
- graveside (bool), cemetery_name, cemetery_city, cemetery_state
- cremation (bool), ashes_interment_date, ashes_interment_place
- (cremation date/place deliberately omitted per canon law)

### First Communion (`occi_communions`)
- communion_date
- first_name, middle_name, last_name
- baptism_date, baptism_church, baptism_city, baptism_state
- presider, parish_id, notations

### Ordination (`occi_ordinations`)
- ordination_date
- first_name, middle_name, last_name
- rank (Deacon / Priest / Bishop)
- presiding_bishop
- co_consecrator1, co_consecrator2, co_consecrator3 (always visible; required for Bishop rank)
- parish_id, alternate_location, notations

## Parishes (`occi_parishes`)
- name, city, state
- cert_template_url (optional — overrides global certificate template for this parish)

## Capabilities & Access Control
- `occi_manage_records` — full CRUD
- `occi_view_records` — read-only
- Both granted to: Contributor, Author, Editor, Administrator
- Subscribers get nothing
- Capabilities are applied on activation AND on every version bump (no deactivate/reactivate needed)

## Certificate Printing
- Template image: 792×1056 px (8.5"×11" at 96dpi)
- Template cascade: parish cert_template_url → global OCCI setting → bundled `certificate-template.png`
- Font choices: Palatino Linotype, Georgia, Times New Roman, Garamond, Book Antiqua
- Each register's View Record page has both "Print Record" and "Print Certificate" buttons
- Certificates open in a new browser tab with the image as background and data overlaid
- Always includes two signature lines and "Issued: [current date]"

## Person Sacramental Report
- Menu item: Sacramental Records > Person Report
- Search by first name, last name, or both — searches all six registers simultaneously
- Results grouped by sacrament type with links to full records and Print Certificate buttons
- "Print / Save Report" opens a standalone formatted document with OCCI letterhead, signature line, and footer: "Confidential Canonical Document — Church Use Only — Pax et Bonum"

## Import / Export
- Menu item: Sacramental Records > Import / Export
- Export: JSON file per parish (or all), embeds parish name/city/state in every record
- Import deduplication rules:
  - Baptism: first_name + last_name + baptism_date (+ birth_date when present as name-collision guard)
  - Marriage: checked both party orderings to prevent double-insertion
  - All others: first_name + last_name + sacrament_date
  - Parish matching: name + city + state; created automatically if not found
- Results table shows: imported / skipped / errors per register, parishes created

## Automatic Updates
Dormant until configured. Add to wp-config.php:

Option A — Self-hosted:
```php
define( 'OCCI_UPDATE_URL', 'https://myocci.org/updates/occi-sacramental-records.json' );
```

Option B — GitHub releases:
```php
define( 'OCCI_UPDATE_URL', 'https://github.com/BishopGreer/OCCI-sacramental-record' );
define( 'OCCI_UPDATE_SOURCE', 'github' );
// For private repos: define( 'OCCI_UPDATE_GITHUB_TOKEN', 'your-token' );
```

## Admin Footer
Every OCCI admin page has a fixed bottom bar:
- Left: "Old Catholic Churches International — National Sacramental Database"
- Right: "vX.X.X — Pax et Bonum" (in gold)
- Suppressed on print

## Organizational Preferences
- No em dashes in prose or UI
- Close plugin-related responses with "Pax et Bonum"
- Scripture references use CPDV (Catholic Public Domain Version)
- Music: never suggest or reference David Haas

## Releasing a New Version (after initial GitHub setup)
1. Make changes, bump version in plugin header and OCCI_SR_VERSION constant
2. Update readme.txt changelog
3. Commit and tag:
   ```bash
   git add .
   git commit -m "Version X.X.X: description of changes"
   git push origin main
   git tag vX.X.X
   git push origin vX.X.X
   ```
4. GitHub Actions builds `occi-sacramental-records-X.X.X.zip` and publishes the release automatically.

## Initial GitHub Push (one-time setup, not yet completed)
```bash
cd ~/Projects/occi-sacramental-records
git remote set-url origin https://github.com/BishopGreer/OCCI-sacramental-record.git
git push origin main
git push origin v1.0.4
```
