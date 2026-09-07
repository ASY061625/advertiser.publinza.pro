# Installing Publinza

This archive is the complete application source plus the compiled front-end
assets. It does **not** contain `vendor/`, `node_modules/` or a `.env` — the
first two are installed by their package managers, and the third holds your
secrets and has to be written per environment.

## What you need

| | Version | Why |
|---|---|---|
| PHP | 8.3+ | with `bcmath`, `intl`, `pdo_mysql`, `redis`, `zip`, `gd` |
| Composer | 2.x | installs `vendor/` |
| MySQL | 8.0+ | the application database |
| Redis | 6+ | sessions, cache and the queue |
| Node | 22+ | **only if you change front-end code** — see below |
| Meilisearch | 1.x | optional; search falls back to the database without it |

## Install

```bash
unzip publinza-<version>.zip -d /var/www/publinza
cd /var/www/publinza

composer install --no-dev --optimize-autoloader

cp .env.example .env
php artisan key:generate          # writes APP_KEY — do this once, never again
```

Edit `.env`: database credentials, `APP_URL`, `PUBLINZA_APP_DOMAIN`, mail, and
Redis. Then:

```bash
php artisan migrate --force
php artisan db:seed --force       # reference data, and a demo account
php artisan storage:link

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Point the web server's document root at **`public/`**, not at the archive root.
Everything above `public/` must not be reachable over HTTP.

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache
```

## You do not need Node to install this

`public/build/` is already compiled and included, and `manifest.json` is in it.
Node is only needed if you change anything under `resources/js` or
`resources/css`, in which case:

```bash
npm ci
npm run build
```

`npm` walks *up* from wherever it is run to find `package.json`, so any
directory inside the archive works. If npm reports `package.json` missing, it is
running outside the archive entirely — its error prints the path it looked in,
and that path is the answer.

## Search

The global palette and the catalog filter read Meilisearch when it is
configured. Without it, set `SCOUT_DRIVER=database` and both fall back to SQL —
no typo tolerance, but a working product.

With Meilisearch:

```bash
php artisan scout:sync-index-settings
php artisan scout:import "App\Domain\Catalog\Models\Website"
php artisan scout:import "App\Domain\Projects\Models\Project"
php artisan scout:import "App\Domain\Posts\Models\Post"
php artisan scout:import "App\Domain\Messaging\Models\Conversation"
```

All four back the palette. Importing only the catalog leaves three of its
groups silently empty — a search against an index nothing filled returns
nothing rather than erroring.

## Background work

The queue runs scheduled notifications, exports and metric fetches:

```bash
php artisan horizon          # under systemd, supervisor or your panel
```

And the scheduler, once a minute, from cron:

```
* * * * * cd /var/www/publinza && php artisan schedule:run >> /dev/null 2>&1
```

Without these, notification digests, weekly summaries and deadline warnings
never go out. Everything else works.

## Before you go live

Set `APP_DEBUG=false`, `APP_ENV=production`, `FORCE_HTTPS=true`,
`SESSION_SECURE_COOKIE=true` and `LOG_LEVEL=warning`, and change
`MEILISEARCH_KEY` from the development default in `.env.example`.

The seeded demo advertiser (`advertiser@publinza.test`) and the seeded admin
exist for evaluation. Delete them, or skip `db:seed` and run
`php artisan db:seed --class=ReferenceDataSeeder` instead, which loads the
categories, countries and languages without the demo data.

## Checking a server

```bash
make preflight
```

Prints the resolved application root, the Node and npm versions, and whether
artisan will run through Docker or on the host. Compare the root it prints with
any path an error message gave you.

`README.md` has the architecture, the reasoning behind each surface, and a
longer deployment section.
