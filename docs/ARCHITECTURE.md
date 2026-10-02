# Architecture

How apply.marwaoverseas.com is put together, at the level someone picking the
project up would need before changing anything. Accurate as of plugin 1.2.0,
schema version 11, 2 October 2026.

## The short version

One WordPress site, one custom plugin, one child theme. The plugin owns the
data model, the screens and the permissions. WordPress provides authentication,
the media library, the editor for guides, and nothing else of consequence. WP
Job Manager free core provides the vacancy post type and is the one dependency
scheduled for removal (`pf-wpjm`).

The portal has **no runtime dependency on marwaoverseas.com**. They share a
brand and nothing else. The main site can be rebuilt, moved or broken without
affecting the portal.

## Stack

| Layer | Choice | Why |
| --- | --- | --- |
| CMS | WordPress 7.1.2 | Already in use; the team can administer it |
| Theme | Astra (free) | Free, fast, and the design system is already synced to it |
| Child theme | `astra-child`, titled "Marwa Apply" | All overrides live here so Astra can update |
| Plugin | `marwa-apply` 1.2.0 | Everything specific to the portal |
| Vacancies | WP Job Manager free core | Post type and templates only. To be replaced |
| Mail | GoSMTP → Brevo | Configured, not delivering. See `DECISIONS.md` |
| Security | Wordfence | Also the intended home for staff two-factor (`id-2fa`) |
| Snippets | WPCode Lite | For anything that must survive a plugin reinstall |

No paid themes or plugins anywhere, by standing instruction.

## Plugin layout

```
marwa-apply/
  marwa-apply.php        bootstrap, MA_VERSION, autoloader
  includes/
    class-plugin.php     wires everything together
    class-install.php    schema, migrations, DB_VERSION
    class-roles.php      roles and capabilities
    ... 29 more classes
    demo.php             demo data create and remove
```

**Autoloading convention.** `MarwaApply\Some_Class` loads from
`includes/class-some-class.php` — lowercased, underscores become hyphens. A
class named `EmployersAdmin` therefore lives in `class-employersadmin.php`, not
`class-employers-admin.php`. This has bitten once. A verification script checks
every class filename against the rule.

`demo.php` is the exception: it is `require_once`'d explicitly from the
bootstrap because it defines functions, not a class.

## Data model

Thirteen custom tables, all prefixed `<wp_prefix>ma_` — so `wpim_ma_candidates`
on the live site. Created and migrated by `Install::create_tables()` through
`dbDelta`, with the current schema recorded in the `ma_db_version` option.
Schema version is **11**.

| Table | Holds |
| --- | --- |
| `ma_agencies` | Tenancy. One row today; the field exists so a second agency is never a migration |
| `ma_employers` | Company record, compliance fields, `name_public`, logo, demand-letter reference |
| `ma_employer_docs` | Vetting documents filed against an employer, with review state |
| `ma_candidates` | The candidate file — roughly sixty fields: identity, body, addresses, emergency contact, passport, availability, visibility, completeness |
| `ma_education` | Repeatable: the Bangladeshi certificate ladder |
| `ma_training` | Repeatable: TTC and private courses |
| `ma_certificates` | Repeatable: eighteen named exams including JFT-Basic, JLPT, the SSW skills tests, BMET, BNMC, Prometric, Dataflow |
| `ma_experience` | Repeatable: work history with country and dates |
| `ma_skills` | Repeatable: skill, level, years |
| `ma_referees` | Repeatable |
| `ma_documents` | Vault index. The files themselves are not in here |
| `ma_applications` | One row per candidate per vacancy, with stage and interview details |
| `ma_status_log` | Append-only audit trail of every stage change |

### Why custom tables and not post meta

A candidate file is sixty-odd fields plus seven repeatable sections. As post
meta that is hundreds of rows per candidate, and the query Marwa actually needs
— *welders with a valid passport and JFT-Basic who can travel within sixty
days* — becomes a join nobody wants to maintain. The cost of custom tables is
that WordPress gives no admin screens for free, so every screen in the portal is
built. That cost was accepted deliberately.

Vacancies are the exception: they stay WordPress posts, because WP Job Manager's
post type is what the public job pages are built on.

## Roles and capabilities

Four roles added at activation, each with named capabilities checked in code.
Hidden menus are not treated as access control.

| Role | Broadly can |
| --- | --- |
| `ma_candidate` | Edit own profile, apply, view own documents |
| `ma_employer` | View own vacancies, edit own employer record, see forwarded candidates |
| `ma_staff` | View and edit candidates, change status, add notes, build shortlists |
| `ma_manager` | All of staff, plus manage employers, jobs, agencies, settings, export packs, view sensitive fields |

Capabilities in use: `ma_edit_own_profile`, `ma_apply_to_jobs`,
`ma_view_candidates`, `ma_edit_candidates`, `ma_delete_candidates`,
`ma_view_documents`, `ma_view_sensitive`, `ma_change_status`, `ma_add_notes`,
`ma_build_shortlists`, `ma_export_pack`, `ma_manage_jobs`,
`ma_manage_employers`, `ma_manage_agencies`, `ma_manage_settings`,
`ma_view_own_vacancies`, `ma_edit_own_employer`.

The employer role was found holding only `read` on 2 October — its screens had
been gating on user meta rather than capabilities. Fixed in 1.0.1. Worth
remembering as the shape of mistake to look for.

## Screens

Ten WordPress pages, each a shortcode, created at activation by `Pages`:

| Page | Shortcode | Notes |
| --- | --- | --- |
| `welcome` | `[ma_home]` | Hero, stats, browse grid, latest, closing soon, destinations, guides |
| `register` | `[ma_register]` | Honeypot and rate limiting |
| `sign-in` | `[ma_sign_in]` | Routes employers to the employer area |
| `verify` | `[ma_verify]` | Six-digit code, 30-minute expiry, 8-failure lockout |
| `account` | `[ma_account]` | Rail / content / rail |
| `profile` | `[ma_profile]` | Seven sections |
| `documents` | `[ma_documents]` | Vault |
| `guides` | `[ma_guides]` | Index for the `ma_guide` post type |
| `employer` | `[ma_employer]` | Five sections |
| `employer-signup` | `[ma_employer_signup]` | Creates a pending employer |

Plus `[ma_jobs]` for the vacancy list and `[ma_trust]` for the anti-fraud block.

### Layout frame

`Shell` renders the rail / content / rail frame used by account, profile and
documents: left rail for navigation, centre for content, right rail for
next-step prompts and the licence block. The grid is
`272px minmax(0,1fr) 288px`.

Two CSS ceilings have had to be fought and are worth knowing about before
anyone wonders why a width "isn't applying":

- The synced design system sets `.mo .ap-shell{max-width:760px}`, which
  out-specifies `.ma-wrap`. The fix is `.mo .ap-shell.ma-wrap`.
- Astra's `.ast-container` caps at 1240px. `body.ma-wide` breaks out of it.
- The synced header stylesheet colours every `nav a` for a dark bar at a
  specificity that cannot be beaten by nesting. The header's own colours are
  therefore explicit hex with `!important`, deliberately.

### Header

`Header` buffers the `astra_header` output — `ob_start` at priority 1, closed at
99 — and rewrites the `<nav>` through `DOMDocument`. That is how Home / Jobs ▾ /
Guides ▾ / For employers ▾ / the main site link / the avatar menu get in without
touching Astra's templates. It also emits the profile-completeness strip.

## Request flow

A candidate applying:

1. `[ma_jobs]` renders the list. Every filter is a link and every state is in
   the URL, so the page works with JavaScript off. Faceting runs in PHP over
   the whole matching set — acceptable because one licensed agency posts
   hundreds of vacancies, not hundreds of thousands.
2. `Detail` hooks `single_job_listing_start` at priority 20 to add the sticky
   action bar, facts strip, benefits and the eligibility panel. `Trust` hooks
   `single_job_listing_end` at 20 for the fraud notice.
3. `Eligibility::check()` compares the candidate against
   `Vacancy::spec()` and reports gaps. It never blocks an application.
4. Applying writes one `ma_applications` row. Uniqueness is enforced in the
   database, not only in the form.
5. Every stage change appends to `ma_status_log`. Nothing is updated in place.

## Application pipeline

Ten stages, each with separate staff-facing and candidate-facing wording:
`new`, `screened`, `shortlisted`, `interview`, `selected`, `medical`, `visa`,
`deployed`, `rejected`, `withdrawn`. An employer sees a candidate only from
`shortlisted` onward (`Employer::visible_stages()`).

## Employer vetting

`Vetting` defines ten document types, six required by default, and the required
set is editable by an admin in settings — so Marwa decides what is compulsory
rather than the code deciding. `EmployersAdmin` refuses to activate an employer
whose required documents are not all accepted: the status silently reverts to
`pending` and a transient explains why.

## Document storage

Files live outside the web root under random filenames. Every read goes through
`Vetting::maybe_serve()` or the candidate vault equivalent, which checks
ownership or staff permission first. A passport scan with a guessable URL is a
breach waiting for a crawler.

## Known sharp edges

- **Mail.** Broken, and it has silently broken four features. Anything that
  sends must assume failure. The demo seeder short-circuits `pre_wp_mail`,
  raises the time limit to 300 seconds and is resumable, because
  `wp_insert_user` fires mail hooks that each blocked until timeout.
- **Tab-safe saving.** `Profile::save()` carries a `$fallback` array so saving
  one section cannot wipe another section's fields. Removing it would cause
  silent data loss.
- **Namespace-sensitive string references.** Dynamic class checks must use
  `__NAMESPACE__ . '\\Guides'`, not a literal. A fold-in script once rewrote one
  to `'\Guides'` and the guides builder stopped running.
- **Only one demo surface.** `ma_demo_standalone_present()` guards against the
  old standalone demo plugin being active alongside the folded-in copy, which
  produced a duplicate admin menu.
- **No staging, no backups, no error alerting.** Phase 0 of the roadmap exists
  entirely to fix this. Today every change is tested in production.
