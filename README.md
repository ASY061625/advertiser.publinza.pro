# Publinza

Guest-post marketplace. Three surfaces served from one Laravel 11 codebase:

| Surface | Host | Guard | Inertia root | Vite entry |
|---|---|---|---|---|
| Marketing site | `publinza.pro` | — (public) | Blade (`resources/views/marketing/`) | `resources/js/marketing/main.ts` |
| Advertiser app | `app.publinza.pro` | `web` | `resources/views/app.blade.php` | `resources/js/advertiser/main.tsx` |
| Admin panel | `publinza.pro/asylogin` | `admin` + 2FA | `resources/views/admin.blade.php` | `resources/js/admin/main.tsx` |

Stack: Laravel 11 · Inertia · React 18 · TypeScript · Tailwind · MySQL 8 · Redis · Horizon · Meilisearch · S3.

---

## Local setup

Requires Docker, Docker Compose, Node 22 and Make.

```bash
git clone git@github.com:ASY061625/advertiser.publinza.pro.git publinza
cd publinza
make setup
```

`make setup` copies `.env.example` to `.env`, builds and starts the containers, installs
PHP and JS dependencies, generates an app key, migrates, seeds a demo dataset, pushes the
Meilisearch index settings and builds the front-end bundles.

Then point the two local hostnames at your machine:

```bash
echo "127.0.0.1  publinza.localhost app.publinza.localhost" | sudo tee -a /etc/hosts
```

Start the dev server:

```bash
make dev     # containers + Vite with HMR
```

| What | Where |
|---|---|
| Marketing site | http://publinza.localhost |
| Advertiser app | http://app.publinza.localhost |
| Admin panel | http://publinza.localhost/asylogin |
| Horizon | http://publinza.localhost/asylogin/horizon |
| Mailpit | http://localhost:8025 |
| Meilisearch | http://localhost:7700 |

Seeded accounts (local only, password `password`):

- Advertiser — `advertiser@publinza.test`
- Admin — `admin@publinza.test`, TOTP secret `ABCDEFGHIJKLMNOP`

### Make targets

| Target | What it does |
|---|---|
| `make setup` | First-run setup, end to end |
| `make dev` | Containers plus the Vite dev server |
| `make test` | Pest, PHPStan, Pint, ESLint and `tsc` |
| `make fix` | Auto-fix PHP and JS formatting |
| `make fresh` | Drop, migrate, reseed and reindex |
| `make search-index` | Sync Scout settings and reimport the catalog |
| `make deploy ENV=production` | Build assets, migrate, cache config, restart Horizon |

Run `make` on its own for the full list.

---

## How the three surfaces stay apart

This is the part of the architecture worth understanding before changing anything.

**Routing.** `bootstrap/app.php` registers each route file in its own group — the
marketing and advertiser files are bound to hostnames, and the admin file gets the apex
domain plus the `/asylogin` prefix and the `admin` + `2fa` + `throttle:10,1` middleware
stack. The admin login and two-factor routes drop the guard they exist to satisfy (via
`withoutMiddleware`), and keep the throttle.

**Rendering.** The marketing site is server-rendered Blade — it is content, it has to
arrive as HTML for crawlers and for LCP, and it ships a 2KB progressive-enhancement island
rather than a framework. The advertiser app and admin panel are Inertia/React, where the
interactivity earns the runtime.

**Bundles.** Each surface has exactly one Vite entry point. The two Inertia entries resolve
their pages with an `import.meta.glob` rooted in their own directory, so the admin page graph
is unreachable from the advertiser entry and cannot end up in its bundle. Only React, Inertia
and `resources/js/shared` are hoisted into shared chunks. An ESLint rule blocks cross-surface
imports, and CI asserts the separation against the built output.

**Auth.** Advertisers are `App\Models\User` on the `web` guard; staff are
`App\Domain\Admin\Models\Admin` in a separate table on the `admin` guard. An advertiser
session cannot reach `/asylogin` — there is no shared record to escalate through.

**Hardening.** `/asylogin` responses carry `X-Frame-Options: DENY`, `nosniff`,
`no-referrer`, `no-store` and HSTS, set both by nginx and by `SecureAdminHeaders` so the
guarantee survives a misconfigured proxy. The path is rate limited in nginx as well as in
the application, and excluded in `robots.txt`.

---

## Marketing site

Server-rendered Blade at `publinza.pro`, sharing the design system's tokens but not its React
components — `resources/views/components/ui/` holds Blade twins of Button and QuantBar. Keep
the twins in step; they are the one place the system is duplicated.

| Page | Route |
|---|---|
| Home | `/` |
| Catalog preview | `/catalog` |
| How it works | `/how-it-works` |
| Pricing | `/pricing` |
| About | `/about` |
| Contact | `/contact` |
| Blog | `/blog`, `/blog/{slug}` |
| Legal | `/terms`, `/privacy`, `/refund-policy` |
| Sitemap | `/sitemap.xml` |

**Real data, not fixtures.** The hero rows, the catalog preview, the network size, the
category chips and the pricing bands all come from the database, cached for 30–60 minutes.
The pricing page cannot quote a price the app no longer charges.

**Performance.** One CSS file and 2KB of JavaScript. No framework, no hydration. The island
handles inline search, category chips, the cookie banner and the mobile menu, and every one of
them degrades: the FAQ is `<details>`, the chips are links, and the search is a real form that
submits to `/catalog`.

**Consent.** The analytics script is not in the HTML. Its URL sits in a data attribute and is
only turned into a `<script>` tag after someone accepts, so a visitor who declines — or never
answers — never fetches it. Set `ANALYTICS_SCRIPT_URL` to enable it at all.

**SEO.** JSON-LD `Organization` and `WebSite` on every page, `FAQPage` on the home page,
`BlogPosting` and `BreadcrumbList` on articles, built in `app/Support/Seo.php` so the company
details cannot drift between pages. `sitemap.xml` is generated and cached; `robots.txt`
disallows `/asylogin` and search-result URLs.

**Images.** Open Graph and blog covers are WebP with a PNG fallback and explicit `width`/
`height`, generated by rendering a card in Chromium. Regenerate them on a machine that can
reach Google Fonts — the committed versions fell back to a system sans for the headline.

---

## Advertiser authentication

Advertisers only. There is no publisher role and no publisher signup anywhere in this
product — every account buys placements, and the sites being bought are ours.

| Route | Purpose |
|---|---|
| `/signup` | Create an account; also creates a zero-balance wallet and a cart |
| `/login` | Password, then a two-factor challenge if it is on |
| `/forgot-password` | Emails a reset link; the response never reveals whether the account exists |
| `/reset-password/{token}` | Single use, 60-minute life |
| `/verify-email` | Where an unverified account lands; resend limited to once a minute |
| `/two-factor-challenge` | Second step; not signed in until it passes |
| `POST /logout` | Also drops this browser's trusted-device token |
| `/settings/two-factor` | Turn 2FA on or off, and regenerate recovery codes |

**Throttling.** `LoginThrottle` allows five attempts per email *and* IP per minute, then locks
that pair out for 15 minutes and emails the account owner once. Keyed on the pair
deliberately: email alone lets anyone lock a victim out, IP alone lets a botnet spread a
credential-stuffing run across addresses.

**Hashing.** Argon2id at 64MiB / 4 passes / 2 threads (`config/hashing.php`). Tests drop the
cost — the algorithm under test is unchanged.

**Sessions.** Regenerated on sign-in. Cookies are httpOnly, SameSite=Lax, Secure wherever
HTTPS is served, and encrypted. `AuthenticateSession` ties each session to the password hash
it was created under, so a reset signs every other session out — this works with the Redis
session driver, which deleting rows from `sessions` would not.

**Two-factor.** Optional TOTP. The secret is encrypted at rest, and 2FA is not enforced until
a first code is confirmed, so a half-finished setup cannot lock anyone out. Eight recovery
codes are shown once and stored hashed — they cannot be shown again. "Trust this device for
30 days" stores only the token's hash; the plaintext lives in an httpOnly cookie.

**Enumeration.** Forgot-password always answers the same way. Login gives one message for a
wrong password and a missing account, and hashes a throwaway value when the account does not
exist so the two take the same time.

**Audit.** Every attempt writes to `login_attempts` with IP, user agent and outcome —
including failed two-factor and lockout attempts, not just the paths someone remembered.

### Two things worth knowing

The first-ever sign-in routes to `/projects/create` rather than `/dashboard`, decided by
`last_login_at` being null. `CompleteLogin` reads it before stamping it.

No QR code is rendered: there is no QR backend installed, so the setup screen shows the
`otpauth://` link (tappable straight into an authenticator on a phone) and the secret grouped
in fours. Adding `bacon/bacon-qr-code` would let `google2fa-qrcode` draw a real QR.

---

## App shell

Every authenticated route renders inside `resources/js/advertiser/Layouts/AppShell.tsx`.

**Sidebar** — 248px expanded, 68px collapsed, measured in a browser rather than assumed. The
collapsed state is read from `localStorage` first so it paints correctly on the first frame,
then persisted to `users.sidebar_collapsed` so it follows the advertiser to a new browser. The
collapse toggle sits on the sidebar's bottom edge. Collapsed labels become tooltips reachable
by hover and by focus.

**Project scope** — the switcher writes `?project={id}` into the URL, and the Catalog and Posts
nav links carry it, so the buying context follows the advertiser instead of being re-picked on
every screen. Project dots are derived from the project id against a fixed palette, so a
project keeps its colour across sessions without a column to store it.

**Header** — 60px, sticky. What's new (drawer), Favorites (a link, not a menu), Conversations,
Cart, Balance, Profile. Below 1024px the first four collapse into one overflow menu; balance
and profile stay.

**Counts** — `useShellCounts` listens on a private Echo channel and falls back to a 60-second
poll. The poll is a working mode, not a degraded one: with `BROADCAST_CONNECTION=null`
(the default) it is the whole mechanism. Events carry no numbers, only which scopes moved, so
two tabs cannot disagree because events arrived out of order. Echo and Pusher are dynamically
imported, so an installation without a broadcaster never downloads them.

**Overlays** — `useDismiss` keeps a shared stack so Escape closes only the topmost overlay. A
modal opened from inside a drawer no longer takes the drawer down with it.

### Reverb is not wired yet

`config/broadcasting.php` and `routes/channels.php` are in place and `ShellCountsChanged` is a
real broadcast event, but running Reverb needs two Composer packages this repository's lock
file does not have — `laravel/reverb` and a Pusher-protocol client. Add them, set
`BROADCAST_CONNECTION=reverb` and the `REVERB_*` keys, and the shell switches over on its own.
Until then the poll carries it.

---

## Layout

```
app/
├── Domain/                 # Business logic. One folder per bounded context.
│   ├── Catalog/            # Sites, search, the quant-bar ranges
│   ├── Projects/           # Campaigns
│   ├── Posts/              # Placements and their status machine
│   ├── Billing/            # Wallets, frozen funds, ledger, orders
│   ├── Messaging/          # Threads between advertisers and staff
│   └── Admin/              # Staff accounts, site review, payouts
│       ├── Actions/        # One public `handle()` per use case
│       ├── DTOs/           # Readonly inputs and outputs
│       ├── Models/
│       └── Policies/
├── Http/
│   ├── Controllers/{Marketing,Advertiser,Admin}/
│   ├── Middleware/         # Per-surface Inertia handlers, admin guards, headers
│   └── Requests/
├── Models/                 # User only; everything else lives in its domain
└── Providers/

resources/js/
├── marketing/main.ts       # The only script the marketing site ships
├── advertiser/{Pages,Layouts,Components}/
├── admin/{Pages,Layouts,Components}/
└── shared/                 # The only code all three surfaces may import
    ├── components/         # Button, StatusBadge, Table, QuantBar, EmptyState
    ├── lib/                # cn, formatters, the status colour map
    └── types/

resources/views/
├── components/
│   ├── layouts/marketing.blade.php   # Head, JSON-LD, landmarks, consent banner
│   ├── ui/                           # Blade twins of Button and QuantBar
│   └── marketing/                    # Header, footer, catalog table, FAQ
└── marketing/pages/                  # Home, catalog, pricing, blog, legal…

routes/
├── marketing.php           # publinza.pro
├── app.php                 # app.publinza.pro
└── admin.php               # publinza.pro/asylogin

docker/
├── php/                    # php-fpm 8.3 image and php.ini
├── nginx/
│   ├── nginx.conf
│   ├── conf.d/publinza.conf    # Local development
│   └── production.conf         # TLS, HSTS, redirects — deploy to the web tier
└── mysql/my.cnf
```

**Controllers stay thin.** A controller resolves input into a DTO, calls one action, and
returns an Inertia response or a redirect. Anything longer than that belongs in
`app/Domain/<Context>/Actions`.

---

## Design system

The component library lives in `resources/js/shared/ui` and is imported from the barrel:

```tsx
import { Button, Table, QuantBar, useToast } from '@shared/ui';
```

Tokens are declared once as CSS custom properties in `resources/css/globals.css` and
aliased in `tailwind.config.ts` under semantic names — `bg-canvas`, `text-ink-700`,
`border-subtle`, `bg-status-posted-bg`. **Components never contain a raw hex value.**

Because the tokens are hex, Tailwind's opacity modifiers (`bg-card/60`) cannot apply to
them. Where a translucent surface is genuinely needed there is a dedicated token instead
(`bg-overlay`, `bg-row-hover`).

### Preview route

`/design-system` on the advertiser surface renders every component in every state —
default, hover, focus-visible, active, disabled, loading and error. Light theme only.

```bash
make dev   # then open http://app.publinza.localhost/design-system
```

It sits outside every auth guard so it opens without an account, and it is not registered
at all when `APP_ENV=production` (see the guard in `routes/app.php`).

### Rules the system enforces

- **Type** — Sora 600/500 for headings and UI, Inter for body and tables. The `.num`
  utility carries `font-variant-numeric: tabular-nums` and goes on every numeric cell, so
  price columns line up. Scale: 12/13/14/16/20/26/34/44. Sentence case throughout — no
  all-caps labels, no eyebrow labels above headings, no arrow characters glued to button
  text.
- **Layout** — sidebar 248px (68px collapsed), 60px sticky header, 1440px content
  max-width, 24px gutters. Radius 8px cards and inputs, 6px buttons, 999px pills. One
  shadow token, `shadow-card`.
- **Tables** — 48px rows everywhere; the catalog uses 56px to make room for the
  quant-bars. Sticky header, optional sticky first column, sortable columns, row
  selection.
- **Focus** — a 2px `--brand-blue` ring at 2px offset on every interactive element, set
  once globally on `:focus-visible` rather than per component.
- **Motion** — only in response to a user action: row expand 150ms, drawer 180ms, toast
  200ms. `prefers-reduced-motion` flattens every duration via one global rule. No
  scroll-triggered entrance animations.
- **Voice** — plain verbs, active voice. Buttons name the outcome ("Add to cart", "Top up
  balance") and the resulting toast reports it in the past tense ("Published"). Empty
  states give one line of direction and one button, never an apology. Errors say what
  happened and what to do next.

### Components

Button, IconButton, Input, NumberInput, Textarea, Select, MultiSelect, Combobox,
RangeSlider, Checkbox, RadioGroup, Switch, DatePicker, DateRangePicker, Badge, Avatar,
Card, StatCard, Tabs, Table, DataGridToolbar, Pagination, Drawer, Modal, Dropdown, Tooltip,
Toast, Skeleton, EmptyState, Alert, Breadcrumb, ProgressBar and QuantBar.

Status colours are fixed product-wide and owned by `Badge` — the `StatusKey` union and its
colours live in one file so the vocabulary and its palette cannot drift apart.

Dates move through the system as `YYYY-MM-DD` strings, never `Date` objects: a calendar day
has no time and no zone, and parsing one into a `Date` invites an off-by-one for anyone
west of UTC.

### The signature element

In the catalog, every quantitative cell — traffic, DR, DA, spam score — renders as tabular
digits with a 3px proportional bar beneath (`shared/ui/QuantBar.tsx`), so a buyer scanning
200 rows reads shape before digits. The bar is `--brand-blue`, turning `--teal` once the
value lands in the top quartile of the range.

The bars scale against the whole approved catalog's min/max, not the visible page:
`GetCatalogRanges` computes and caches those ranges, and `ReviewSite` busts the cache when a
site is approved or rejected. Scaling per page would rescale every bar on each pagination
click and make two pages incomparable.

Spam score passes `inverted` because low is good.

**Nothing else in the product uses this treatment.** That is enforced, not just documented:
an ESLint `no-restricted-syntax` rule fails the build on a `<QuantBar>` outside the catalog,
the component's own file, and the gallery that documents it.

---

## Testing and quality

```bash
make test     # Pest, PHPStan level 6, Pint, ESLint, Prettier, tsc, bundle isolation
make pest     # PHP tests only
make stan     # Static analysis only
make fix      # Auto-fix formatting
```

Feature tests hit a specific surface through the `marketingUrl()`, `advertiserUrl()` and
`adminUrl()` helpers in `tests/Pest.php` — a request without the right hostname will not
match the route group it is aimed at.

CI runs the same checks and additionally asserts that no admin chunk is reachable from the
advertiser build.

---

## Configuration

Every key is documented and grouped by service in `.env.example`. The ones worth knowing:

| Key | Why it matters |
|---|---|
| `MARKETING_DOMAIN`, `APP_DOMAIN` | Bind the route groups to hostnames |
| `ADMIN_PATH_PREFIX` | The admin path, rotatable without a code change |
| `APP_SUBDOMAIN_URL` | Where the marketing site's log in and sign up links point |
| `ANALYTICS_SCRIPT_URL` | Marketing analytics; loaded only after consent, empty means none |
| `SESSION_DOMAIN` | `.publinza.pro` in production so apex and app share a session |
| `REDIS_CACHE_DB`, `REDIS_HORIZON_DB` | Kept apart so a cache flush never wipes queue history |
| `SCOUT_QUEUE` | Index writes go through Horizon rather than blocking requests |
| `FILESYSTEM_DISK` | `s3` by default; `local` is fine for development |

## Deploying

```bash
make deploy ENV=production
```

Copy `docker/nginx/production.conf` to the web tier's `/etc/nginx/conf.d/`, issue
certificates for `publinza.pro` and `app.publinza.pro`, and run Horizon under a process
supervisor. Set `APP_DEBUG=false`, `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true` and
`LOG_LEVEL=warning`, and rotate `MEILISEARCH_KEY` away from the development default.
