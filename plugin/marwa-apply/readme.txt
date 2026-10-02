=== Marwa Apply ===
Contributors: rumanhossain
Tags: recruitment, applications, candidates, job board
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Candidate application platform for Marwa Overseas. Profiles, applications, pipeline and protected document storage.

== Description ==

Everything candidate-side for apply.marwaoverseas.com. Job posts are handled by
WP Job Manager (free core); this plugin owns candidates, applications, the
status pipeline, document storage and the generated CV.

**Version 0.1.0 is the foundation only.** It creates the schema, the roles and
the protected document directory. There is no front end yet.

= What this version does =

* Creates seven custom tables, each carrying `agency_id` so multi-agency can be
  added later without a migration
* Creates four roles — candidate, staff, manager, employer — with the
  capability names settled up front
* Finds a directory for candidate documents outside the web root, hardens it,
  and shows an admin warning if it could only manage a directory inside
* Registers a shared `ma_sector` taxonomy and seeds the eleven Marwa sectors
  with their Japanese labels

= Why custom tables =

The admin list filters on sector, trade, years of experience, district and
passport status together. Held in post meta that is one JOIN per field, and it
stops responding at a few thousand candidates.

= Why documents live outside wp-content =

Everything under wp-content/uploads is reachable by anyone who guesses the URL.
Acceptable for a photo of a building site; not for a passport scan.

== Installation ==

1. Upload and activate.
2. Check Dashboard for a storage warning. If one appears, create a directory
   above the web root on the server and add to wp-config.php:
   `define( 'MA_STORAGE_DIR', '/home/USER/marwa-apply-data' );`
3. Install WP Job Manager (free) for job posts. No paid add-ons are needed.

== Changelog ==

= 0.1.0 =
* Schema, roles, capabilities, protected storage, sector taxonomy.
