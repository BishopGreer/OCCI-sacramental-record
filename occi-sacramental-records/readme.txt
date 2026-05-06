=== OCCI Sacramental Records ===
Contributors: Old Catholic Churches International
Tags: sacramental records, church, catholic, database, baptism, marriage, ordination
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later

National sacramental record database for Old Catholic Churches International.

== Description ==

This plugin provides a complete, secure, and canonical sacramental records management system for Old Catholic Churches International (OCCI) and its constituent parishes. It stores and manages all six principal sacramental registers within WordPress using MariaDB/MySQL.

= Registers Included =

* Baptism Register
* Confirmation Register (with event and per-recipient records)
* Marriage Register
* Death Register
* First Holy Communion Register
* Ordination Register
* Parish Registry (shared lookup)

= Key Features =

* Full CRUD for all six registers
* Chronological listing with date-range search
* Surname index search per register
* Parish lookup with city and state
* Alternate location field for off-site sacraments
* Notations column on every register
* Confidential flag on baptism records
* Printable certificate view with signature lines
* Co-consecrator fields on bishop ordinations (shown/hidden by rank)
* Cremation handling per canon law (fact noted; date/place of cremation not recorded)
* Dashboard with record counts for all registers
* Two access roles: occi_manage_records (full CRUD) and occi_view_records (read-only)
* All queries use $wpdb->prepare() for SQL injection prevention
* All forms protected with WordPress nonces
* Date formatting prints month name per handbook guidelines (e.g. "May 5, 2026")

= Canonical Compliance =

Designed in alignment with canon law (cc. 535, 874-878, 892-896, 1121-1123, 1182) and the Diocese of Little Rock Handbook for Sacramental Records as a reference standard, adapted for OCCI's Old Catholic tradition.

= Access Control =

On activation, both `occi_manage_records` and `occi_view_records` capabilities are added to the Administrator role. Additional roles or users may be granted these capabilities through any WordPress role management plugin.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/
2. Activate the plugin through the Plugins menu in WordPress
3. Navigate to Sacramental Records in the admin menu
4. Add your parishes first under the Parishes submenu
5. Begin entering records in each register

== Database Tables Created ==

* {prefix}occi_parishes
* {prefix}occi_baptisms
* {prefix}occi_confirmation_events
* {prefix}occi_confirmation_recipients
* {prefix}occi_marriages
* {prefix}occi_deaths
* {prefix}occi_communions
* {prefix}occi_ordinations

Tables are created on activation using dbDelta() and are compatible with MariaDB and MySQL.

== Frequently Asked Questions ==

= Will deactivating the plugin delete my records? =

No. Deactivation does not drop any tables or remove any data. Records persist unless you manually drop the tables.

= Can I grant a secretary access to enter records without full admin access? =

Yes. Use a role management plugin to create a role with the `occi_manage_records` capability, or `occi_view_records` for read-only access.

= Does this replace the physical register? =

No. Per canon law and best practices, physical registers remain the authoritative record. This system provides a searchable, backed-up digital complement. Physical registers must never be destroyed.

== Changelog ==

= 1.0.0 =
* Initial release
* All six sacramental registers
* Parish management
* Dashboard overview
* Print/certificate views

== Notes ==

Pax et Bonum.
OCCI — Old Catholic Churches International
https://myocci.org
