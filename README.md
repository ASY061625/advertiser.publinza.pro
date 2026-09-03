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
- Admin — `owner@publinza.test`, TOTP secret `ABCDEFGHIJKLMNOP`

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
| `POST /logout` | Ends the session; the trusted-device token survives, see below |
| `/settings/two-factor` | Turn 2FA on or off, and regenerate recovery codes |

**Throttling.** `LoginThrottle` allows five attempts per email *and* IP per minute, then locks
that pair out for 15 minutes and emails the account owner once. Keyed on the pair
deliberately: email alone lets anyone lock a victim out, IP alone lets a botnet spread a
credential-stuffing run across addresses.

**Hashing.** Argon2id at 64MiB / 4 passes / 2 threads (`config/hashing.php`). Tests drop the
cost — the algorithm under test is unchanged.

**Password strength** is defined once, in `AppServiceProvider` via `Password::defaults()`, so
signup and reset cannot drift apart: ten characters, mixed case, a digit, and a check against
Have I Been Pwned's breach corpus. The breach check is skipped under test — it is a live HTTP
call, and what the tests are pinning is the length and composition rule, not an external
service's uptime.

**Trusted devices survive sign-out.** "Trust this device for 30 days" has to mean 30 days; a
trust voided at the next sign-out would re-challenge on the very next visit and be worth
nothing. Trust is dropped where dropping it is actually meant — a password reset clears every
device, and turning 2FA off clears them too.

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

## Dashboard

`/dashboard` is the advertiser's landing screen. `/` redirects to it, so there is one
canonical address rather than the same page under two route names.

Everything on the page obeys one date range. Changing the range refetches
`GET /dashboard/metrics` — a single aggregated endpoint behind every widget, cached five
minutes per user, range, granularity and project scope. Six endpoints would let the stat
cards and the chart disagree about which window they are describing.

The first payload is inlined into the page, so the dashboard arrives populated rather than
as skeletons that resolve a beat later. Skeletons exist per panel and are only ever shown
for a range change.

### Honest numbers

- **"Previous equivalent period" is the same number of days again**, not the previous
  calendar month. Comparing a 31-day month against a 28-day one would show February as a
  collapse every year. See `DateRange::previous()`.
- **A delta against zero is "New", not "up 100%".** `deltaPct` is null when the previous
  period was empty, and the chip says so. Exactly zero reads neutral ink, not green.
- **Point-in-time figures are reconstructed, not estimated.** The balance a month ago comes
  off the ledger row that recorded it (`balance_after_cents`); "posts in progress a month
  ago" is rebuilt from `post_status_history` rather than read from today's `status` column.

### The chart is deliberately not dual-axis

The brief asked for placements and spend on one plot with two y-axes. Two independent
scales mean the point where the bars and the line appear to cross is decided by the axis
ranges, not by the data — the chart invents a correlation. It is built instead as two plots
sharing one x-axis, one crosshair and one tooltip, which answers the same question without
manufacturing that crossing.

Brand blue and teal were validated as a pair: ΔE 28.7 under deuteranopia, 31.8 for normal
vision. Teal falls below 3:1 against white, so the spend plot carries visible axis labels, a
direct peak label and a `<details>` table view rather than relying on the line's colour.

### Posts by status does not ask colour to carry identity

The segment hues are the product's fixed status colours, chosen for badges read in
isolation. As a categorical palette they fail: `new` and `content_review` separate by ΔE 2.0
under deuteranopia, and `draft` and `frozen` are two greys. Those colours are not negotiable
— a "Posted" chip is the same chip everywhere — so the bar carries the proportion only.
Identity lives in the legend, where every row spells out its status, count and share;
segments are separated by a surface gap; and hovering or focusing a legend row isolates its
segment, mapping row to bar without reference to colour at all.

Rows are named by their own status rather than by their badge: `Completed` and `Posted`
share a colour, so a chip left to its default text would print "Posted" twice with two
different counts beside it.

### Three empty states, three different messages

| Situation | What the page shows |
| --- | --- |
| No projects at all | The whole body is replaced by one instruction: create a project. |
| Projects, nothing ever ordered | Row 1 stays, reading zeros; rows 2–4 become one prompt to browse the catalog. |
| History, but none in this range | A quieter dashed panel: "No activity in this range", with a reset to the last 30 days. |

A brand-new account, an account that has bought nothing, and an account with plenty of
history that picked a quiet fortnight are not the same situation. One generic "nothing here"
would tell the first person nothing about what to do next and make the third think their
data had been lost.

---

## Posts grid

`/posts` is where an advertiser manages every post across every project. Status tabs,
nineteen filters, a sortable column-configurable table, bulk actions and a detail drawer —
all of it server-side.

### The query string is the state

Every filter serialises to the URL and back, losslessly, and the test suite pins the round
trip. That is what makes a filtered view shareable, bookmarkable and refresh-proof, and it
is why a **saved view stores the query string rather than a bespoke filter format**: applying
one and opening a link someone sent you are the same operation, so they cannot drift apart.

Defaults are omitted, so a plain `/posts` stays a plain URL instead of growing twenty empty
keys. Sort order and page size are excluded from "is this filtered?" — they arrange the grid
rather than narrowing it, which is what keeps "you have no posts yet" and "nothing matches
these filters" two different screens with two different things to do about them.

### Tabs are lifecycle phases, not single statuses

There are nine post statuses and eight tabs. Two statuses are the terminal end of a phase
that already has a tab, so they are grouped rather than given tabs of their own:

| Tab | Statuses |
| --- | --- |
| Posted | `posted`, `completed` |
| Cancelled | `cancelled`, `refunded` |

Otherwise the Posted tab would quietly empty out as posts aged past their verification
window, and last month's live link would be findable only under All. Grouping is also what
makes the tab counts sum to the All count — `ListPosts::tabCounts()` derives All from the
sum rather than running a second query that could disagree with the first. A test asserts
every status lands in exactly one tab, so a tenth status cannot be added without deciding
where it belongs.

Tab counts are computed under the current filters **minus the tab itself**, so a number
answers "how many would I see if I clicked here" rather than always reading zero for every
tab you are not standing on.

### Everything happens in SQL

Filtering, sorting and pagination are server-side; the client sends a query string and
renders what comes back. A grid that holds every post an advertiser has ever bought cannot
sort in PHP, and paginating after the fact would mean loading all of them to show 25.

- Sorting by a related column uses a join, not a correlated subquery — the grid pages
  through it, and a subquery per row does not scale.
- Every sort ends with `posts.id desc` as a tiebreak. Without it two posts created in the
  same second can swap places between pages and one of them is never seen.
- `published_at` and `deadline_at` sort nulls last: "not published yet" is not the top of a
  descending list.
- Search covers domain, anchor text, target URL and post ID. An all-digit term also matches
  the id, and `%` and `_` in a term are escaped so a wildcard is text rather than a pattern.

### Two things that would have been quiet bugs

**Article HTML is sanitised before it is sent.** `Article::body_html` is written by
publishers and rendered unescaped in the drawer, inside the advertiser's authenticated
session on app.publinza.pro — the exact shape of a stored XSS. `App\Support\HtmlSanitizer`
reduces it to an allowlist of about twenty formatting tags: an allowlist, because a
blocklist has to anticipate every vector and loses to the next one. Scripts, style, iframes,
every `on*` handler, `javascript:` and `data:` URLs (including schemes smuggled past a naive
check with a tab in the middle) all come out; links keep their href and leave with
`rel="noopener noreferrer nofollow"`.

**CSV cells are defused.** A cell beginning `=`, `+`, `-` or `@` is executed as a formula
when the file opens. Anchor text and target URLs are advertiser-supplied and end up in a
colleague's spreadsheet, so a leading formula character gets a quote in front of it.

### Bulk actions re-resolve everything

A list of ids from a browser is a request, not an authorisation, so every bulk action
re-resolves the selection against the signed-in advertiser before touching anything. Beyond
that:

- **Cancel** skips posts past the cancellable window rather than failing the batch, and says
  how many it skipped and why. Each post is its own transaction, so one refusal does not
  roll back the thirty-nine that worked.
- **Move to folder** only moves a post into a folder of its own project — a folder belongs
  to a project, and moving across would silently reassign the post to another brief.
- **Duplicate** always produces a draft, and carries the brief only: no order, no article,
  no published URL, no history. A copy of a live placement must not claim to be live.
- **Download articles** builds the zip to a temp file and streams it. An archive with
  nothing in it reads as a broken download, so it carries a README explaining why.

### One address per post

`/posts/{id}` redirects to `/posts?post={id}` — the grid with that row's drawer open. The
drawer shows everything a separate detail page would, and keeps the reader's place in a
filtered, sorted, scrolled list of a hundred rows. A second page would be a second place the
same information lives and a second thing to keep in step.

### Columns

Order and visibility persist per account in `users.grid_preferences`, filtered on the way
out against the canonical list in `App\Support\PostGridPreferences` — a column removed from
the product cannot linger in someone's saved order, and one added later is appended so it is
visible rather than invisible to everyone who ever touched their settings. Website and
Status cannot be hidden whatever a stored preference or a hand-edited request says.

Reordering uses up/down buttons rather than drag-and-drop: dragging is nicer with a mouse and
unusable without one, and this is a preference someone sets once.

---

## Projects list

`/projects` is the reporting surface for an advertiser's campaigns: post mix and
spend per project, in a table or as cards.

### Two queries, whatever the project count

The projects, then one grouped aggregate over their posts — conditional `SUM(CASE WHEN …)`
so it means the same thing on MySQL and on the SQLite the tests use. Ten figures per project
computed as a subquery per column per row would be forty queries for four projects; a test
asserts the count stays at three regardless.

Spend is defined here exactly as `GetDashboardMetrics` defines it — the price of posts that
went live in the window. Two screens disagreeing about what an advertiser spent last month
is a support ticket, not a rounding difference.

### The stacked bar sums to the number above it

Four named segments plus a fifth for the remainder, all disjoint by status:

| Segment | Statuses |
| --- | --- |
| New | `new` |
| In progress | `in_progress`, `content_review` |
| Posted | `completed`, and `posted` past its verification window |
| Frozen | `posted` still inside its window — live, money not yet settled |
| Other | `draft`, `rejected`, `cancelled`, `refunded` |

Frozen sits *beside* Posted rather than slicing through it, because what the bar
distinguishes is whether the money has settled. Naming only the first four would leave four
of the nine statuses out and produce widths that do not add up to the total printed above
them, which is a picture of a population that does not exist.

The **Frozen price** column answers a different question and is deliberately wider: all
funds the wallet is still holding for the project, which is every status where
`holdsFrozenFunds()` is true. It matches the wallet; the bar segment matches the link.

### Numbers that decline to lie

- **Average price is over completed posts only.** A post still being written has a quoted
  price, not one anyone has paid.
- **No completed posts shows an em dash, not `$0.00`** — zero would read as "these
  placements are free".
- **A delta against a zero month is "New"**, not "up 100%".
- **The footer totals are summed from the rendered rows**, not recomputed, so they cannot
  disagree with the column above them.

### Four bugs this screen surfaced

All four came from an abandoned draft → new → active project flow that the enum was later
reduced away from, leaving three places comparing against statuses that no longer existed:

- **`CreateProject` wrote `status => 'draft'`** — not a `ProjectStatus` case, so a project
  created through the app threw a `ValueError` the moment anything read it back.
- **`ProjectData` carried `target_url`, `anchor_text` and `brief`** — *post* fields. None is
  a column on `projects`, and `website_url` (NOT NULL) was never written. Creating a project
  could not work.
- **`ProjectPolicy` compared `status === 'draft'`** against an enum-cast attribute, so every
  `update` and `publish` check returned false and every edit was denied.
- **`PublishProject`** transitioned `draft → new`, neither of which exists. Removed, along
  with a stale duplicate `PostStatus` enum under `Domain/Posts/DTOs` whose vocabulary
  (`published`, `frozen`, no `posted`) contradicted the real one two directories away.

`Project` and `User` now carry `@property` annotations for their cast attributes. Those are
load-bearing: without them static analysis reads `status` as a plain string and cannot see
that comparing it against the enum is meaningful, which is exactly how the same mistake got
made three times. They must stay docblocks — a declared property on an Eloquent model
shadows `__get` and the attribute is never read from the attribute bag at all.

### Scope note

`Projects/Show` covers General and Settings so a row click and the Edit action land
somewhere real. The full project screen — targeting, landing pages, competitors, folders —
is its own piece of work and will absorb these two tabs. Its General tab also carries the
one-time "Find a website" card the create wizard flashes on success.

---

## Create-project wizard

`/projects/create` is three steps: the site, targeting, landing pages. Back navigation keeps
everything because there is one state object and the steps render from it — going back
changes which step is visible and nothing else.

### Fetching a URL someone typed

Step 1 reads the site's title, description and favicon so a typo is caught before the
advertiser is three steps deep. That means the server makes an HTTP request to an address a
form supplied, which is a server-side request forgery primitive: the request originates
inside Publinza's network, with whatever that network trusts.

`App\Support\OutboundUrlGuard` is an allowlist of shapes, not a blocklist of hosts:

- http and https only;
- the host must resolve, and **every** address it resolves to must be public unicast —
  loopback, private ranges, link-local (where cloud metadata lives), carrier-grade NAT and
  the IPv6 equivalents are all refused, including IPv4-mapped, 6to4 and NAT64 forms that
  wrap an IPv4 address a naive check would miss;
- the connection is **pinned to the address that was vetted** (`CURLOPT_RESOLVE`). Handing
  curl the hostname after checking DNS leaves a window for the name to resolve again to
  something private. That gap is the one usually left open.

Redirects are followed by hand, three at most, re-vetting every hop, because a public URL
that 302s to `169.254.169.254` is the standard bypass. The body is capped at 512KB and the
transfer time-boxed. Only extracted fields come back — the page body never reaches the
client, so this cannot read a response the browser could not have fetched itself. The
endpoint is throttled to 20 requests a minute per user. Twenty-one attack shapes are pinned
in `tests/Feature/Projects/SitePreviewSecurityTest.php`.

### Drafts are not projects

Autosave writes to `project_drafts`, one row per advertiser, not to a `draft` status on
`projects`. A status would put unfinished rows inside every query that lists, counts or
reports on projects, each of which would have to remember to exclude it — and the last time
this codebase had a phantom 'draft' project status it produced three silent failures. A
draft has no schema to satisfy; the payload is JSON precisely because a partial answer
should not have to satisfy the constraints a finished project does.

Autosave is debounced and fire-and-forget: it never blocks a keystroke and never surfaces a
failure mid-sentence. The draft is discarded only once the project is real.

### Same-site landing pages

A landing page has to be on the promoted site's registrable domain, so `blog.acme.co.uk` and
`shop.acme.co.uk` match but `acme.co.uk` and `other.co.uk` do not. `App\Support\PublicSuffix`
is an approximation of the Public Suffix List covering the multi-label suffixes people
actually use; the real list needs a package and a refresh schedule.

Its failure mode is deliberate. An unknown multi-label suffix makes two different sites look
like one, so the check is *permissive* where it is wrong — this is a data-entry aid, not a
security boundary, and wrongly blocking someone's own URL costs more than letting an unusual
TLD through. The message names both domains, because "must be the same domain" leaves
someone staring at two URLs that look alike to them.

### Everything else

- **URLs are normalised once**, in `ProjectWizardData`, so a draft and a submit store the
  same spelling: https, lower-cased host, punycoded IDN, no default port, no fragment. The
  path keeps its case — hosts are case-insensitive, paths are not.
- **The brief is a contenteditable**, so it arrives as whatever HTML the client sent. Paste
  is forced to plain text and the result goes through `HtmlSanitizer` on the way in. The
  toolbar is a convenience; the sanitiser is the control. Its character limit is measured on
  the text, not the markup — charging someone for `<strong>` tags they cannot see would be
  nonsense.
- **The suggested colour is computed server-side** and returned with the preview, so client
  and server cannot disagree about what a domain suggests. It stops overriding once a swatch
  is clicked.
- **The anchor-text advisory never blocks.** The heuristic behind it is crude enough that it
  should not.
- **The disabled Next button says what it is waiting for.** The tooltip wraps the button
  rather than sitting on it — a disabled button fires no pointer events, which is how
  "disabled with no explanation" usually happens by accident.

### A design-system bug this surfaced

`Input` and `Select` with `hideLabel` returned a bare control and **silently dropped `error`
and `hint`**, leaving `aria-describedby` pointing at an element that did not exist. Every
label-hidden field in the product — the posts search, the projects search, the landing-page
rows — could not show a validation message. Both now route through `Field`, which learned
`hideLabel` when the posts grid needed it.

---

## Project page — `/projects/{id}`

Six tabs over one project. `?tab=` decides which body renders, validated server-side by
`ProjectTab` so an unrecognised value lands on General rather than an empty panel.

```
ProjectLayout.tsx          breadcrumb, identity, actions, tab bar — built once
  Projects/Show.tsx        the body of whichever tab is selected
    general/DealsPanel     four tiles + stacked bar + legend
    general/FinancePanel   spent / frozen / average + split bar
    general/FoldersSection folder rows, add, edit, delete
    general/FirstDealCard  shown until the project has a post
  Projects/Folders/Edit    add or edit one folder
```

### One aggregate, two screens

`ProjectStats` is the per-project post mix and money, in one grouped query. Both the
projects list and a project's own page read it. The list showing 34 posts and the page
showing 31 is a support ticket, and it is exactly what happens when two screens each write
their own version of "how many are in progress". A test asserts the two payloads match.

`GetProjectOverview` adds the folders: three grouped queries for landing pages, posts, and
non-terminal posts — not a count per folder, because a project with twenty folders should
not cost forty queries to render.

The one figure that deliberately differs is spend. The list's is windowed to the month;
the page's is all-time. The labels say which is which.

### Post management is the posts grid

Rather than a second copy of the grid living here, `?tab=posts` redirects to
`/posts?projects[]={id}`. The Deals tiles do the same with a status attached, so
"In progress 9" opens exactly those nine — the tile's two statuses (`in_progress`,
`content_review`) both travel, or the grid would show fewer posts than the tile promised.

### Folders refuse to disappear quietly

`DeleteFolder` blocks three cases and names each one: the project's only folder (every
landing page has to live in one), a folder with non-terminal posts (a publisher would be
left writing against a brief that no longer exists), and a folder still holding landing
pages. The row disables its own Delete with the same reason in a tooltip — that is the
courtesy; the action is the guard. Finished posts do not block it: `folder_id` is nulled
rather than cascaded, so history stays readable.

Folder briefs are sanitised on write, like the project's, and held to the same 3,000
visible characters the editor counts — the limit is on what was typed, not on the markup
wrapped around it.

### Three bugs this screen surfaced

**Every page's `useToast()` threw.** `ToastProvider` lived inside `AppShell`, and every
page renders its own `<AppShell>` — so the provider was a *descendant* of the component
calling the hook. `/posts` rendered a blank white page in the browser while its feature
tests passed, because the tests assert on the Inertia payload and never mount React. The
provider moved to `main.tsx`, above the page.

**The tab bar dragged the page off the right of a phone.** `min-w-max` on the whole `Tabs`
component sized the *panel* to the tab row's intrinsic width. `Tabs` gained `scrollable`,
which puts the overflow on the row alone.

**Arrow keys could not reach the last three tabs.** With WAI-ARIA's automatic activation,
the first ArrowRight landed on Post management and navigated away. `Tabs` gained
`manualActivation` — arrows move focus, Enter commits — which is what the pattern
prescribes when activating a tab costs a page load.

---

## Folder editor — `/projects/{id}/folders/{folderId}/edit`

One centred form: the folder's name, the brief its posts are written against, and the landing
pages inside it. `/projects/{id}/folders/create` is the same page with nothing filled in.

### The landing-page list is one widget, used twice

`Components/projects/LandingPageEditor` is the drag-reorderable list with bulk paste and
per-row validation. The create wizard's third step is now a thin wrapper around it. They were
the same list written twice, and a landing page validated one way in the wizard and another
way here would be two different products — `landingPageErrors` and `anchorHealth` moved to a
`LandingPageContext` (a promoted URL and some rows) so both screens can call them.

The folder editor adds one thing the wizard has no use for: a row can already have posts
pointing at it.

### A page posts point at does not disappear

Usage is matched on the anchor/URL pair, not a foreign key — a post records where it points in
its own `anchor_text` and `target_url` columns, so a placement keeps meaning something after
the landing page it was ordered from is edited. `GetFolderEditor` counts those pairs for the
whole project in one grouped query and hands each row its number.

Above zero, the row's Remove is disabled with the count in a tooltip, and `SaveFolder` refuses
the same removal server-side — the whole save rolls back rather than half-applying. A rule
only enforced in the browser is not a rule: a stale tab, a replayed request or a second window
would all walk past it.

### The submitted list is the whole truth

Rows with an id are updated in place, rows without one are created, rows the browser stopped
sending are deleted, and order comes from the array — so a drag and a rename are one save.
An id that belongs to another folder is treated as a row that no longer exists and created
fresh, rather than silently editing someone else's page.

### Leaving without saving

`beforeunload` covers a closed tab, Inertia's `before` hook covers a client-side visit, and
neither covers the other. The save itself is a visit, so only `get` navigations are
questioned. Cmd/Ctrl+S submits.

### One bug this surfaced

**Nothing rendered the server's flash messages.** Every redirect in the app carries
`->with('success', …)` or `->with('error', …)` — archiving a project, deleting one, refusing a
folder deletion — and none of them appeared anywhere; the screen just changed silently.
`useFlashToasts` in the shell raises them as toasts, which is also how the editor's "Saved"
arrives on the General tab.

Two smaller ones: four breadcrumbs squeezed each other to nothing on a phone, because every
crumb is `truncate` inside one flex row (the middle ones are now dropped below `sm`), and the
side columns of a landing-page row were padded to clear a label that only the first row has.

---

## Post management — `/projects/{id}?tab=posts`

The `/posts` grid, locked to one project. Not a copy of it: `Components/posts/PostsGrid` is one
component, and `/posts` is now a thin page around the same thing. A fix to the filter round
trip or the bulk bar lands on both surfaces at once, which is the whole reason to have done it.

What the props change:

| | `/posts` | Post management |
| --- | --- | --- |
| `path` | `/posts` | `/projects/{id}` |
| `fixedQuery` | — | `{ tab: 'posts' }` |
| `tabKey` | `tab` | `posts_tab` |
| `only` | `posts`, `tabCounts`, … | `grid` |
| `scope` | — | the project and its folders |
| Saved views | yes | no |

### `tab` was already taken

A project's page spends `tab` on which of its six tabs is open, and the grid's status tabs want
`tab` too. The grid's travels as `posts_tab`: `PostFilters::fromRequest()` takes the key as a
parameter, `toQuery()` always emits `tab` because that is the shape the client holds state in,
and the client renames it on the way out. `/posts` is byte-identical to before.

### The scope is not a filter

`PostFilters::projectScope` never comes from the request, never round-trips through the query
string, and never counts towards `isFiltering()` — a project's own tab reporting "no posts
match these filters" because it is scoped to that project would be nonsense. A crafted
`projects[]=99` cannot widen it, and is dropped from the echoed filters so no chip appears for
a filter that changes nothing.

### Board mode

Five columns — New, In progress, Content review, Posted, Rejected. Posted covers `posted` and
`completed`, exactly as the Posted tab above it does; a column named Posted showing fewer posts
than the tab named Posted is a bug on screen.

Nothing drags. An advertiser cannot move a post between statuses: a post is `in_progress`
because a writer is writing it and `posted` because a link is live. A board that accepted the
gesture and snapped back would be worse than one that never offered it, so a line above the
board says so and the cards open the drawer.

Switching to Board raises the page size to 100 and switching back restores what was there. A
board of twenty-five rows is not a board — whole columns come up empty while the tab above them
says six.

### Two bugs this surfaced

**Every filter change on the tab refreshed nothing.** `usePostFilters` re-fetched
`['posts', 'tabCounts', 'filters', 'isFiltering']` — prop names `/posts` has and this page does
not, since it nests the lot under `grid`. Inertia returned none of them, so the URL changed and
the grid kept showing the previous result. The prop list is a prop now, and a feature test
sends the partial-reload headers to pin it.

**`bg-subtle` painted nothing.** `subtle` is registered as a `borderColor` only, so the summary
strip's vertical rules were invisible. They use `bg-ink-300`, which is what `border-subtle`
resolves to.

---

## Project settings — `/projects/{id}?tab=settings`

Five sections, one form, one save: Basics, Targeting, Publisher brief, Landing pages, Danger
zone. Anchors down the left rather than tabs — the sections submit together, and hiding four of
them behind tabs would let someone save changes they cannot see. The footer bar only exists
while something is unsaved, so the page is quiet until there is a decision to make.

Most of it is components that already existed: the wizard's colour picker, rich brief editor
and site preview, the folder editor's landing-page list, the shared `Combobox` and
`MultiSelect`. What is new is the save, the live count and the Danger zone.

### One transaction, one history entry per field

`UpdateProjectSettings` takes a before/after snapshot around the write and hands the difference
to `ProjectAudit`, which writes one `audit_logs` row per field that actually moved. Per field,
because the History tab reads as a list of things that happened: "Category changed from Finance
to Technology" is a sentence, and one row holding a diff of eleven columns is a blob somebody
has to decode before they can read their own history. Values are stored as the label a person
would recognise, never the id.

Lists compare as sets, so reordering countries records nothing, and resaving an unchanged form
records nothing at all.

### The live match count

`CountMatchingSites` answers what the targeting *on screen* would show, debounced at 350ms,
with out-of-order responses dropped. Sensitive topics narrow rather than widen — a site matches
only if it accepts every topic ticked, because an advertiser writing about crypto and gambling
needs a site that takes both. "View them" carries the same unsaved targeting into the catalog,
or the link would show a different set from the number above it.

### Danger zone

Archive is a status you flip back this afternoon; delete keeps the row for 30 days and then
does not. Delete is refused while any post is unfinished, and the refusal is on screen before
anything is typed, naming the posts with links — the difference between a rule and a dead end.

### Two bugs this surfaced

**`UrlNormalizer::hostOf()` and `normalize()` disagreed about what a URL is.** `normalize()`
assumes `https` for a scheme-less input, so it accepts `example.com`; `hostOf()` called
`parse_url` directly, so the same string had no host and every landing-page rule measured
against it silently passed. In the other direction `parse_url` happily returned
`"not a url at all"` as a host. `hostOf()` now routes through `normalize()`, so the two agree
by construction.

**The audit's "after" snapshot read names off an id-only relation.** `handle()` reloaded the
pivots with `:id` selects and `snapshot()` then used `loadMissing`, which left them loaded and
nameless — every targeting change recorded `[null, null]`. The snapshot uses `load()`.

### One thing worth revisiting

`PostStatus::Cancelled` is not terminal (it can still become `Refunded`), so a cancelled post
blocks deleting its project. That is what "not in a terminal state" means and it is what this
implements, but it means a project with one unrefunded cancellation cannot be deleted by its
owner. The copy no longer claims money is held against those posts, because for a cancelled one
it is not.

---

## Statistics — `/projects/{id}?tab=statistics`

A control bar, four cards, five charts and a sortable table, all reading one payload — so
nothing on the tab can disagree with anything else on it. Two queries carry the lot: the posts
published inside the range and the posts ordered inside it, bucketed in PHP.

"Published" is the event everything financial hangs off. A placement's price is spent when its
link goes live, not when it is ordered, which is why spend and placements share the
`published_at` bucket while "ordered" has its own — and why the two charts rarely peak in the
same period.

### No dual axis, anywhere

The Links chart was specified as a stacked bar with "a running total of live links as a line on
a secondary axis". It is built instead as two plots on one shared x-axis and one crosshair.
Links built per period runs to single digits and links live runs to hundreds, so a second
y-scale means choosing two axis ranges — and where the bars and the line then appear to cross
is decided by that choice rather than by the data. The chart would invent a relationship. The
dashboard's combo chart made the same call for the same reason.

### The palettes are validated, not chosen

Run through the `dataviz` validator before any chart code was written:

| Chart | Pair | CVD ΔE | Normal ΔE |
| --- | --- | --- | --- |
| Guest posts | teal / gold | 14.1 (protan) | 24.9 |
| Links | brand blue / teal | 28.7 (deutan) | 31.8 |

A tint of teal for "ordered" — the obvious first choice — **failed**: ΔE 12.4 for normal vision,
under the hard floor of 15, plus a chroma failure. Gold is both further away and already this
product's token for pending. Teal and gold both sit under 3:1 against white, which obliges
visible relief: every chart carries axis labels, direct labels on peaks, and a table view.

Budget and the two breakdowns are single-series, so they get one hue and no legend — the title
names what is plotted, and each breakdown row is directly labelled with its own amount. The
cumulative line is the same hue dashed, because it is the same measure accumulated; a second
colour would say otherwise.

### Exports without a spreadsheet library

Composer cannot authenticate for PhpSpreadsheet or dompdf in this environment, so both writers
are here. `XlsxWriter` builds the OOXML parts and zips them with `ZipArchive` — about a hundred
lines, and numbers are written as numbers, because a money column that arrives in Excel as text
is a column nobody can sum. `PdfTableWriter` emits PDF 1.4 with Helvetica and absolute text
positions, paginating the table; it is deliberately limited (no images, no wrapping, WinAnsi
only) and the limits are why it is safe.

All three formats build from one set of rows. A CSV that says one thing and a PDF that says
another is the classic export bug, and it happens the moment each format builds its own query.

Exports are queued, the row tracks the job, and the download link checks ownership and a 24-hour
window at request time rather than being baked into a signed URL that would outlive both.

### Two bugs this surfaced

**Every export arrived empty.** The job built into `storage/app/exports` and stored to
`exports/…` on the local disk — whose root is `storage/app`, so those are the same file. It
wrote it, copied it onto itself, and the cleanup deleted it. The test missed it because
`Storage::fake` moves the disk root somewhere the builder was not writing; it now reads the
stored bytes back.

**A mail outage failed a finished export.** The notification was sent inline inside the job, so
an unreachable mail server threw, the `catch` marked the row `failed`, and an export whose file
was already on disk reported failure. The notification is queued now, and its send has its own
`catch` — a build that succeeded is not undone by an announcement that could not go out.

---

## History — `/projects/{id}?tab=history`

Everything that has happened to one project, newest first, grouped by day. Nothing on this tab
is editable or deletable — not because a flag says so, but because `GetProjectHistory` has no
write path at all. The log is the records themselves.

### Unioned at read time, not copied into an events table

Four append-only tables already hold this history: `audit_logs`, `post_status_history`,
`transactions`, and `messages`. They are unioned into nine common columns per request rather
than duplicated into an events table, for two reasons. A second copy is a second thing that can
drift from the record it describes — and a events table added today would have nothing to say
about anything that happened before it, while this reads the whole past on the day it ships.

The sentences are written at read time too, in `HistoryNarrator`. The record is the fact; the
sentence is a rendering of it, so "Category changed from Finance to Technology" can be improved
without rewriting history.

No SQL string-building anywhere in the union. `||` concatenates on SQLite and means OR on
MySQL, `CONCAT()` is the other way round, and `PIPES_AS_CONCAT` is not set — so every column is
selected raw and anything composite is assembled in PHP, where it means one thing on both.

### The scroll is a cursor, not a page number

`(occurred_at, source, source_id)` — the sort spelled out as a predicate. An offset is not a
position in an append-only log read newest-first: anything written between two requests pushes
every row down one, and page two repeats the last row of page one.

The first version pinned a `before` timestamp to stop exactly that, and it was not enough. Rows
from four tables routinely share a timestamp to the second, so a second-granularity ceiling
cannot say which of them the reader has already seen — sixty audit rows written in the same
second reproduced the repeat the ceiling existed to prevent. The tiebreak is not decoration;
it is what makes the order total, and the cursor names a row rather than an instant.

"Jump to date" rides the same parameter. A cursor of `2026-04-18` carries no tiebreak and reads
inclusively from the last instant of that day, which is what jumping to a date means — a
reading position, not a filter. The entry count above stays the whole log, because it is
counted without the cursor: a number that fell as you scrolled would be describing the scroll.

### Actors, and who is never named

An advertiser is named. Publinza staff are always "Publinza team" and everything else is
"System". `HistoryNarrator` does not look an admin up at all — putting a staff member's name on
a permanent log is not a decision to make by accident, so there is nothing to fetch.

### Two bugs this surfaced

**Money events would have returned nothing, silently.** The join was written against
`reference_type = 'post'`. There is no morph map configured, so the wallet stores the full class
name from `getMorphClass()` — the join matched no rows, and a timeline missing its money is a
timeline that looks complete. It is `Post::class` now.

**A word-level diff that struck through words nobody edited.** Tokenising on whitespace glues
punctuation to the word in front of it, so adding a comma after "plain" rendered as deleting
"plain" and inserting "plain," — the word printed twice, once struck through. Punctuation is
its own token now, and the diff shows only what changed.

### One thing the tests could not have caught

`AuthenticateSession` is in the advertiser middleware stack, which ties a session to the
password hash on it. A test that signs in as one person, makes a request, then signs in as
another and reuses the same session is logged out before any policy runs — the second request
redirects to login and an authorisation assertion fails for a reason that has nothing to do
with authorisation. The middleware is doing its job; the test now uses one outsider for both
requests.

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

### Two tests skip without MySQL

`tests/Feature/Billing/WalletConcurrencyTest.php` proves that concurrent freezes cannot
overdraw a wallet. SQLite ignores `SELECT ... FOR UPDATE`, so a pass there would prove
nothing and the tests skip loudly. Run them with `DB_CONNECTION=mysql`.

### PHPStan is not yet clean at level 6

`make stan` currently reports around 290 findings. None is a defect — the ones that were have
been fixed — but the gate does not pass, so it is worth knowing what is in there before you
run it:

| Roughly | What | Why |
| --- | --- | --- |
| 140 | `Undefined variable: $this` in `tests/` | Pest rebinds `$this` inside test closures at runtime. Needs the Pest PHPStan plugin, which is not in the lock file. |
| 28 | `uses generic trait HasFactory but does not specify its types` | Model-level generic annotations, never written. |
| 12 | `Cannot call method ... on string` on model attributes | Datetime columns that *are* cast, but have no `@property` docblock for PHPStan to read (`checkModelProperties` is off). |
| ~20 | Array-shape and collection template annotations | Ordinary level-6 annotation debt. |

Closing it is annotation work across the whole codebase rather than a bug hunt. Do not add a
baseline to make the number go away — it would hide the next real finding, which is exactly
how the ones fixed here survived so long.

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
