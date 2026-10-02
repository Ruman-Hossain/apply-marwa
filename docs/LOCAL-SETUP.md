# Local setup and deployment

How apply.marwaoverseas.com runs on Ruman's machine, and how changes get to the
server. Written 2 October 2026, against plugin 1.2.0.

## Why bother

Every change so far has been tested in production. Eleven schema migrations have
run against live data with no way back. A local copy ends that, and it is the
first half of `pf-staging` in the roadmap.

It also gives two things the server cannot: **a mail catcher**, which is how the
SMTP fault will actually be diagnosed, and **a debug log you can leave on**.

## The shape of it

```
D:\Foresightbyte\Projects\marwa-apply\      ← the repo. Source of truth.
  plugin\marwa-apply\                        │
  theme\astra-child\                         │  junction, not a copy
  docs\  reference\  tools\  dist\           │
  local\                                     │  git-ignored entirely
    app\public\wp-content\                   ▼
      plugins\marwa-apply   ──────────── junction to ..\..\..\..\plugin\marwa-apply
      themes\astra-child    ──────────── junction to ..\..\..\..\theme\astra-child
    marwa-private\                       document vault, above the web root
```

Three ideas carry it:

1. **The repo is the only place code is edited.** Not the server, not the
   `local\` tree.
2. **The site holds no copy of the plugin** — it holds a junction. Edit in VS
   Code, refresh the browser. Nothing to sync, nothing to drift.
3. **`local\` is git-ignored.** WordPress core, third-party plugins, uploads and
   the MySQL data directory all live there and none of it belongs in the repo.

## Why the site is *inside* the repo but not *in* it

Local needs to own a folder outright: `app\`, `conf\`, `logs\`, and a running
MySQL data directory. Keeping that under the project folder is convenient — one
place for everything — but it must never be versioned. `/local/` in
`.gitignore` is what makes both true at once.

## The stack, and why

| Setting | Value | Reason |
|---|---|---|
| PHP | 8.2.29 | Matches typical shared hosting; already installed, so no download |
| Web server | **Apache 2.4.43** | Not nginx. The plugin writes a protective `.htaccess` when documents fall back inside the web root, and permalinks use one. nginx would silently ignore both, hiding a bug that would appear only in production |
| Database | MariaDB 10.6.25 | What cPanel hosts usually run |
| Site URL | `https://marwa-apply.local` | Local's certificate must be trusted once, or Chrome refuses the site |

## What is already configured

`local\app\public\wp-config.php` carries four local-only settings:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );      // wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false ); // printing breaks AJAX and redirects
define( 'MA_DEMO_PASSWORD', '...' ); // the demo tool's account password
define( 'MA_STORAGE_DIR', dirname( ABSPATH ) . '/marwa-private' );
```

`MA_DEMO_PASSWORD` is deliberately in no shipped file — it is set per
environment, and the local value differs from the server's. `MA_STORAGE_DIR`
puts the document vault above the web root, which is how the server should be
configured too.

**Table prefix is `wp_`,** not the server's `wpim_`. That is correct *as long as
the local database is built from demo data*. If you ever import the live
database, change it to `wpim_` in the same file — otherwise the portal loads
with every screen empty, which is the single most common cause of
"I imported it and nothing is there".

## Running it the first time

1. **Trust the certificate.** Local → the site → **Trust** next to SSL. Windows
   asks for administrator rights; approve it. Without this Chrome shows a privacy
   error and nothing loads.
2. **Link the repo in.** Double-click `tools\link-into-local.bat`. It creates the
   two junctions. Safe to re-run; it refuses to delete a real folder, so it can
   never eat your source.
3. **Install the two dependencies** from wp-admin: the **Astra** theme and the
   **WP Job Manager** plugin, both free, both from the WordPress.org directory.
4. **Activate** Marwa Apply, and the Marwa Apply child theme.
5. **Marwa Apply → Demo data → Create.** This builds employers, candidates,
   vacancies, applications and blog posts. The site is now fully populated.

Local's **one-click admin** is on, so the **WP Admin** button signs you in
without a password.

## No live data, on purpose

We are **not** copying the production database down, and that is the right
default, not a workaround for the FTP trouble:

- The demo seeder produces a fuller, more varied dataset than the live site
  currently holds.
- No candidate's passport number ends up on a laptop.
- Nothing has to be sanitised, because nothing real was ever copied.

If you later need the live content exactly — to reproduce a specific bug, say —
pull it then, and only then. Over SSH that is one command:

```bash
ssh user@server "cd ~/public_html && wp db export - --single-transaction" > live.sql
```

Import it into Local's site shell with `wp db import live.sql`, then:

```bash
wp search-replace 'https://apply.marwaoverseas.com' 'https://marwa-apply.local' \
  --all-tables --skip-columns=guid
wp cache flush
```

`--all-tables` matters: the plugin's thirteen tables are not standard WordPress
tables, so the default `--all-tables-with-prefix` would skip them. And set
`$table_prefix = 'wpim_';` before loading the page.

**Once real candidates exist, do not do this at all.** Export, strip or scramble
the candidate rows, then import. The document vault never travels — it lives
above the web root, so it is not in any file transfer in the first place.

## Deployment: SSH, not FTP

FTP was unreliable and is not needed. SSH does the same job better, and the
difference matters most on the one thing FTP is worst at: partial uploads.

**Set up key authentication first.** Password SSH cannot be automated safely —
no password should ever pass through a chat or a script.

```bash
ssh-keygen -t ed25519 -C "marwa-apply deploy"
# then paste ~/.ssh/id_ed25519.pub into the host's SSH Keys page,
# or append it to ~/.ssh/authorized_keys on the server
ssh user@server "echo ok"
```

**Deploy the plugin:**

```bash
rsync -az --delete \
  plugin/marwa-apply/ \
  user@server:~/public_html/wp-content/plugins/marwa-apply/
```

`--delete` removes files you deleted locally — which a zip upload never does,
leaving orphaned classes behind to be loaded by an autoloader that no longer
expects them.

**Then load `/wp-admin` once.** `Install::maybe_upgrade()` runs on `admin_init`,
so a schema change applies on the first admin page view. Do **not** reactivate
the plugin — that re-runs the whole activation routine.

**Order of environments:** local → staging → live. Never straight to live for
anything touching the database.

## The day-to-day loop

```
edit in the repo  →  refresh marwa-apply.local  →  commit
                  →  rsync to staging, load wp-admin, check
                  →  rsync to live, load wp-admin, check
```

`bash tools/build.sh` still cuts a versioned zip into `dist/` when you want a
release artefact — for the WordPress.org-style upload path, or to hand someone a
copy.

## Gotchas, in the order you will hit them

| Symptom | Cause |
|---|---|
| Chrome shows a privacy error | Local's certificate is not trusted yet. Local → Trust, then approve the Windows prompt |
| Plugins list is empty | `link-into-local.bat` has not run, or it reported SKIP because a real folder is in the way |
| Portal loads, every screen empty | Table prefix mismatch — `wp_` against `wpim_` data |
| Links still point at the live domain | `wp search-replace` was run without `--all-tables` |
| Demo tools say the password is not defined | `MA_DEMO_PASSWORD` missing from this environment's `wp-config.php` |
| Email "works" locally but not on the server | Local catches all mail. **Never conclude the SMTP fault is fixed from local results** — the catcher proves the plugin sends, not that Brevo delivers |
| A PHP error only on the server | PHP version mismatch, or nginx-versus-Apache behaviour around `.htaccess` |
| Status screen warns about the storage directory | `MA_STORAGE_DIR` is unset or unwritable, so it fell back inside `uploads` |

## What this unlocks in the roadmap

- **`pf-staging` (0.3)** — the local copy is half of it; a staging subdomain is the other half, set up the same way.
- **`in-smtp` (0.4)** — the mail catcher separates "the plugin didn't send" from "Brevo didn't deliver".
- **`pf-tests` (8.1)** — tests need somewhere to run that is not production.
- **`pf-ci` (8.2)** — needs the git repo and the SSH key, both of which now exist.
