=== OCCI Sacramental Records ===
Contributors: Old Catholic Churches International
Tags: sacramental records, church, old catholic, database, baptism, marriage, ordination
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.9
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

National sacramental record database for Old Catholic Churches International (OCCI).

== Description ==

OCCI Sacramental Records provides a complete, secure, and canonically structured sacramental records management system for Old Catholic Churches International and its constituent parishes. All six principal sacramental registers are stored in MariaDB/MySQL within WordPress.

= Registers Included =

* Baptism Register
* Confirmation Register (per-person, flat model)
* Marriage Register
* Death Register
* First Holy Communion Register
* Ordination Register
* Parish Registry (shared lookup with per-parish certificate templates)

= Key Features =

* Full CRUD for all six registers, searchable by name and date range
* Surname index search per register; chronological default ordering
* Parish lookup with city and state; alternate location field for off-site sacraments
* Notations column on every register; confidential flag on baptism records
* Certificate printing using a full-page background image template (OCCI default included; per-parish overrides supported)
* Person Sacramental Report: search all registers simultaneously for a single individual
* Import / Export: JSON-based exchange format for inter-parish data sharing with intelligent duplicate detection by name and date of birth
* Automatic update checker supporting self-hosted JSON or GitHub Releases (configured via wp-config.php constants; no WordPress.org required)
* Two access roles: occi_manage_records (full CRUD) and occi_view_records (read-only); both granted to Contributor role and above on activation
* Per-parish certificate template images via WordPress Media Library
* Fixed admin footer bar displaying organization name and version on all plugin pages
* All queries use $wpdb->prepare() for SQL injection prevention; all forms protected with WordPress nonces
* Date formatting prints month name per canonical handbook guidelines (e.g., "May 5, 2026")
* Print-optimized CSS for certificates and reports; signature lines included

= Canonical Compliance =

Designed in alignment with canon law (cc. 535, 874-878, 892-896, 1121-1123, 1182) and informed by the Diocese of Little Rock Handbook for Sacramental Records as a reference standard, adapted for OCCI's Old Catholic tradition independent of Rome.

= Automatic Updates =

To enable automatic update checking without WordPress.org, add one of the following to wp-config.php:

**Self-hosted (recommended):**
  define( 'OCCI_UPDATE_URL', 'https://myocci.org/updates/occi-sacramental-records.json' );

**GitHub Releases:**
  define( 'OCCI_UPDATE_URL',    'https://github.com/YOUR-ORG/OCCI-sacramental-record' );
  define( 'OCCI_UPDATE_SOURCE', 'github' );

Full configuration instructions and the required JSON format are shown in Certificate Settings once the plugin is installed.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install via the ZIP upload in Plugins > Add New.
2. Activate the plugin through the Plugins menu in WordPress.
3. Navigate to Sacramental Records in the admin menu.
4. Add your parishes first under the Parishes submenu.
5. Begin entering records in each register.

To enable automatic updates, add the appropriate constants to wp-config.php before or after installation (see Description above).

== Database Tables ==

The following tables are created on activation using dbDelta() and are compatible with MariaDB and MySQL:

* {prefix}occi_parishes
* {prefix}occi_baptisms
* {prefix}occi_confirmations
* {prefix}occi_marriages
* {prefix}occi_deaths
* {prefix}occi_communions
* {prefix}occi_ordinations

Tables are updated automatically when a new plugin version is installed; no manual migration is required.

== Frequently Asked Questions ==

= Will deactivating the plugin delete my records? =

No. Deactivation does not drop any tables or remove any data. Records persist until you manually remove the plugin's database tables.

= Can I grant a parish secretary access without full admin access? =

Yes. Both occi_manage_records and occi_view_records are granted to the Contributor role and above. Use a role management plugin to assign these capabilities to custom roles as needed.

= Does this replace the physical register? =

No. Per canon law and best practices, physical registers remain the authoritative record. This system provides a searchable, backed-up digital complement. Physical registers must never be destroyed.

= How do I set up automatic updates? =

See the Description section above and the Certificate Settings page within the plugin after installation.

= Can each parish use its own certificate background image? =

Yes. Edit any parish record and use the Media Library button to upload a parish-specific certificate image. The plugin cascades: parish image → global OCCI setting → bundled default.

= How does the import handle duplicate records? =

Records are matched by name plus sacrament date. Baptisms additionally use date of birth when present, so two people with the same name but different birth dates are never treated as the same individual. Existing records are skipped; new records for known individuals are added normally. Parishes are matched by name, city, and state and created automatically if not found.

== Changelog ==

= 1.0.9 =
* Fixed plugin ZIP structure — files are now wrapped in an occi-sacramental-records/ folder as WordPress requires, so uploads install as updates rather than new plugins
* Fixed GitHub Actions workflow to produce correctly-structured ZIPs going forward

= 1.0.8 =
* Fixed "Force Update Check Now" button redirecting to the Parish Register instead of the national register
* Added missing admin_init hook so the national register handles its own cache-clear request
* Renamed cache-clear GET parameter and nonce to occi_sr_clear_update_cache to prevent collision when both plugins are installed on the same site

= 1.0.7 =
* Added plugin banner and icon images for the WordPress update screen
* Banner (772x250 and 1544x500) and icon (128x128 and 256x256) now display when an update is available

= 1.0.6 =
* Fixed GitHub Actions workflow error caused by invalid secrets context reference in conditional step
* Removed obsolete self-hosted update JSON step (no longer needed since v1.0.5 hardcoded GitHub as update source)

= 1.0.5 =
* Automatic updates now work without any wp-config.php configuration
* GitHub repository URL is hardcoded in the updater; no OCCI_UPDATE_URL or OCCI_UPDATE_SOURCE constants needed
* Certificate Settings update section simplified to show live status (installed version, latest available, update source link)

= 1.0.4 =
* Added automatic update checker supporting self-hosted JSON endpoint and GitHub Releases
* Moved version/organization subtitle from page header to a fixed footer bar on all plugin admin pages
* Update source configuration instructions and "Force Update Check Now" button added to Certificate Settings

= 1.0.3 =
* Added Import / Export system (JSON format) with intelligent duplicate detection
* Export: filter by parish and record type; downloads as a named JSON file
* Import: matches individuals by name + date of birth; creates parishes automatically if not found; full per-register results report after import
* Marriage duplicate check handles both party orderings to prevent double-insertion
* Added Import / Export submenu item

= 1.0.2 =
* Added per-parish certificate template images via WordPress Media Library picker on the parish edit form
* Certificate template now cascades: parish-specific → global OCCI setting → bundled default
* Access control updated: both occi_manage_records and occi_view_records now granted to Contributor, Author, Editor, and Administrator roles; explicitly revoked from Subscriber
* Capabilities update runs automatically on plugin version change without requiring reactivation
* WordPress Media Library enqueued on Parishes page

= 1.0.1 =
* Added certificate printing using OCCI blank certificate image as full-page background (792x1056px, letter portrait)
* Six certificate types: Baptism, Confirmation, Marriage, Christian Burial, First Holy Communion, Ordination
* Added Certificate Settings page: upload custom template via Media Library; select certificate font
* Added Person Sacramental Report: search all six registers simultaneously by name; print full report as standalone document
* "Print Certificate" button added to every register's View Record page
* Bundled OCCI_Certificate_blank_v1.png as default template

= 1.0.0 =
* Initial release
* Baptism Register: date, baptismal name, parents (mother's maiden name required), sponsors with proxy support, minister, parish, alternate location, notations, confidential flag, record book and page number
* Confirmation Register: per-person flat model with date, confirming bishop/delegate, name, saint's name chosen, parish, alternate location, notations
* Marriage Register: both parties with names, maiden names, birth dates, two witnesses, minister, parish, alternate location, notations
* Death Register: date of death, name, burial location, funeral details, graveside flag, cemetery, cremation flag with ashes interment (date/place of cremation not recorded per canon law)
* First Holy Communion Register: communicant name, baptism date and church, presider, parish, notations
* Ordination Register: date, ordinand name, rank (Deacon/Priest/Bishop), presiding bishop, three co-consecrator fields, parish, alternate location, notations
* Parish Registry with name, city, state
* Dashboard with record counts and quick links
* Sortable, searchable list views for all registers
* Print-ready record view with signature lines for each register
* Custom capabilities: occi_manage_records and occi_view_records granted to Administrator on activation
* Date formatting prints month name (e.g., "May 5, 2026") per canonical handbook guidelines

== Upgrade Notice ==

= 1.0.4 =
Adds automatic update checking. No database changes. Safe to install over 1.0.3.

= 1.0.3 =
Adds Import/Export. No database changes. Safe to install over 1.0.2.

= 1.0.2 =
Adds per-parish certificate templates and expanded role access. Runs a safe database migration (adds cert_template_url column to parishes table) on first load after upgrade.

= 1.0.1 =
Adds certificate printing and person report. No database changes. Safe to install over 1.0.0.

== Notes ==

Pax et Bonum.
Old Catholic Churches International
https://myocci.org
