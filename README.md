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
    posts/PostsGrid        the /posts grid, scoped to this project
    settings/…             five anchored sections and a dirty-only footer
    statistics/…           control bar, four cards, five charts, a table
    history/…              the immutable timeline
    competitors/…          add row, your-site card, table, three charts
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

## Competitors — `/projects/{id}?tab=competitors`

Ten rival domains benchmarked against the project's own site. A comparison, so every
figure on the tab is a difference — which is what dictates most of the design below.

### One vendor answers for every domain, and the row says which

Four providers implement `MetricsProvider`, selected by `publinza.competitors.provider`.
Nothing above the interface learns who answered; swapping vendor is a line of config.

The seam matters more here than in most integrations. Ahrefs and Moz disagree about the
same domain by a wide margin, so a delta between one vendor's figure for your site and
another's for a rival is not a measurement of anything. One provider answers for every
domain in a project, and `competitor_metrics.provider` records which — on the row, not in
config, because config says who answers *now* and a cached row has to keep saying who
answered *then*. The tab prints it: "Data from Ahrefs, updated 3 April".

`dr` and `da` are separate nullable columns for the same reason. No vendor sells both, and
the one it does not sell stays null and renders as an em dash. Defaulting it to zero would
print a real score — the worst one on the scale — for a site nobody measured.

With no vendor credentials the registry falls back to a sample provider, which labels
itself "Sample data" in the same line that would name Ahrefs. Without it the tab is
unreachable in development, in CI and in any review app, and a feature nobody can open is
a feature nobody reviews.

### Your own site is a row in the same table

`competitors.is_self`. It carries the same columns, filled by the same provider on the
same schedule, and is excluded from the ten slots. A separate table would have been the
same six columns and a second code path to keep in step — and the first time the two
disagreed, every delta on the page would have been wrong in a way nobody could see.

The unique key on `(project_id, domain)` then also means an advertiser cannot add their
own site as a rival to itself. And because the promoted URL is editable on the settings
tab, `EnsureProjectSiteTracked` repoints and refetches the row when it changes — otherwise
every comparison would silently be against the site they used to promote.

### Failure keeps the numbers

A provider failure marks the row and stops. It never writes a row of zeros and never
deletes the last good one, so the tab can show last week's figures under an amber
"Showing cached data from …" notice. Both alternatives lose something true: zeros redraw
every chart around a site that supposedly lost all its traffic overnight, and an error
screen throws away figures that are still accurate, just not current.

Manual refresh is one a day per competitor, enforced in the action rather than only on the
button, and the remaining wait is printed on the control it gates. Vendor calls are
metered and billed per row; these numbers move on a scale of weeks.

### The recommendation strip is derived, not written

"They have 34 links from Technology sites you don't" is that competitor's referring domains
in a category minus yours. The categories come from intersecting the provider's referring-
domain list with **Publinza's own catalog** — so a category only appears if this company
has sites in it, and the button always lands on a catalog with something in it. A
suggestion nobody can act on is an advertisement for a gap in the inventory.

### Chart colours were computed, not chosen

Run through the `dataviz` validator against white, all pairs:

| Check | Result |
| --- | --- |
| Lightness band | PASS — all inside L 0.43–0.77 |
| Chroma floor | PASS |
| CVD separation | WARN — worst pair `#a21caf`×`#1d4ed8`, ΔE 7.4 (protan) |
| Normal vision | PASS — worst pair ΔE 22.2 |
| Contrast on white | PASS — all ≥ 3:1 |

Brand blue is your site and is never assigned to a rival. Three competitor hues
(`#0d9488`, `#c2410c`, `#a21caf`) crossed with four stroke patterns give the ten stable
identities the per-project limit needs; cycling a hue alone would give the fourth
competitor the first one's colour. The slot is fixed when a competitor is added, so
sorting the table or hiding a line never repaints the others.

That CVD WARN sits in the 6–8 band, legal only with a second encoding. There are three:
the stroke pattern, a legend that draws each series exactly as the plot does, and a table
view under every chart. Earlier attempts at five and six distinct hues both failed — with
brand blue occupying the blue-violet region, the warm-to-green arc is exactly where
deuteranopia collapses, and every candidate fifth hue failed either CVD or the
normal-vision floor.

### Two bugs this surfaced

**A lazy load that only throws sometimes.** `FetchCompetitorMetrics` read
`$competitor->project`, and this app turns lazy loading into an exception outside
production. Whether it throws depends on where the row came from: Eloquent arms the guard
only on models hydrated from a query that returned *more than one row*
(`Builder::hydrate`). So a caller holding one row got a silent extra query, a caller
iterating a collection got an exception, and a test using a freshly-created model — exempt
from the guard entirely — proved nothing either way. The regression test fetches two rows
as a collection, which is the only shape that fails without the fix.

**A cooldown that threw on every render.** `cooldownSeconds()` returned Carbon's float
from `diffInSeconds` through an `int` return type. The tab calls it once per row.

### One thing deliberately not built

No favicons. Fetching one means asking a third party for an icon and telling them, on
every page load, which rivals this advertiser is tracking — some of the most commercially
sensitive information in the product. The dashboard already made this call for the weaker
case of the sites an advertiser buys on. The 20px slot holds a monogram instead, so
nothing shifts if Publinza ever serves its own marks.

---

## Catalog — `/catalog`

The commercial heart of the product, and the one place QuantBar is allowed to appear. A 280px
sticky filter rail, fourteen filter groups, two layouts, and two modes told apart by one query
parameter.

### Two modes, one page

`/catalog` browses; `/catalog?project={id}` buys. A separate route for buying would mean a
shared link stops working the moment the recipient does not own the project, and would leave
two page components to keep in step. Browse mode disables "Add to cart" rather than hiding it
— a missing button reads as "this cannot be bought", a disabled one that says **Choose a
project first** reads as "not yet, and here is the step".

A project id belonging to somebody else drops the page quietly back to browse mode. That
parameter survives in bookmarks long after access to a project has gone, and a 403 on the
catalog is a confusing way to learn it.

### The URL is the whole state

Fourteen filter groups, the sort, the layout and the page size all live in the query string
and nowhere else, so a view is a link. `CatalogFilters` is the one place that reads and writes
that format, and it round-trips: what it parses is what it re-emits.

Ranges are nullable pairs rather than defaulted to the catalog's own min and max. "No price
filter" and "a price filter spanning the whole catalog" look identical on screen and behave
differently — a stored link with an explicit ceiling should keep it, and one with no filter
should widen as inventory grows. The blacklist toggle is deliberately excluded from
`isFiltering()`: it is on by default, so counting it would mean the catalog is never
unfiltered, and the "no filters and no results" state — the one that means something is broken
— could never be reached.

### Meilisearch answers words; the database answers everything else

Scout's builder is not an Eloquent builder, so it cannot carry the metrics join, the price
join and three per-user `exists()` clauses. And three of the filters are about *this
advertiser* — their blacklist, their favourites, what they have already bought for this
project — which cannot live in a shared index without indexing every user against every site.
So the engine returns ids and the database narrows them.

### Facets are counted against every filter except their own

With Finance ticked, the Technology row still says how many sites ticking it too would add.
Counting a dimension against itself is the classic faceted-search bug: every unselected option
reads zero and the list becomes a dead end. Zero counts are shown, quieter — an option missing
from the list is indistinguishable from one that has not loaded.

The price histogram behind the slider is drawn with the price filter removed, because the
histogram is what the handles are aimed *at*.

### Cursor pagination over a moving sort

The catalog is ordered by figures that change. A site's traffic moving between two clicks
shifts every offset after it, and the buyer sees a row twice or not at all. Cursors name a
row.

Two details make it work over a joined column. The sort columns are selected as plain
`table.column as alias` strings, because the cursor paginator maps an ordering alias back to
its column by string-matching `" as "` in the select list — a raw `Expression` there is not a
string, and the bare alias would reach the `WHERE` clause, which MySQL rejects. And every sort
ends in `websites.id`, so ties cannot swap places between pages.

### Relaxations are aimed, not guessed

"Raise your maximum price to $250 to see 34 more sites" is only worth printing if both numbers
are real. The boundary is the cheapest site *above* the buyer's ceiling, read off the filtered
inventory and rounded to something a person would say; the count comes from running the
relaxed filter. The first version multiplied the ceiling by 1.5, which landed short of the
next site and produced a card promising nothing.

One filter is relaxed at a time. Two filters that exclude each other produce no suggestions at
all, which is correct — no single change helps — and the "Clear all filters" button is the
honest fallback.

### Compatibility warnings inform, they do not decide

A site whose language is outside the project's, or that does not accept one of its topics,
gets an amber flag naming the mismatch. The row is not hidden and the button is not disabled:
that publisher may still be right for a different article, and the catalog is not the place to
decide it on the buyer's behalf.

### Three bugs this surfaced

**Every cart, favourite and blacklist button 404ed.** `Website::getRouteKeyName()` is `slug`,
and the frontend addressed sites by numeric id. Route model binding found nothing and returned
404 from controls that looked like they had worked. Every site route takes a slug now.

**The row menu was trapped in a stacking context.** Pinning the action column to the right edge
— so the buy button stays reachable when nine dense columns overflow — made that cell
`position: sticky`, which creates a stacking context. The dropdown inside it could not paint
over the next row at any z-index, and the container's `overflow-x: auto` clipped whatever hung
below the last row. `Dropdown` now portals its menu to the body and positions it from the
trigger's box, which fixes the same latent problem in every other scrollable table. `useDismiss`
grew an `alsoInside` ref for exactly this: with the panel portalled, the trigger is outside it
in the DOM, so clicking to close would close and immediately reopen.

**"In cart" wrapped, and the wrap cost the price column.** The pinned column widened enough to
overlay the price beside it. Nothing in that column ever benefits from wrapping.

### One number that is in the design system now

`screens.rail` — 1100px, where a 280px rail stops fitting beside the results. The catalog is
the only thing that uses it, but naming it beats three `min-[1100px]:` literals across three
components.

---

## Website detail — `/catalog/website/{slug}`

One website, at 620px. It opens as a right-hand drawer from any catalog row and renders as a
full page when the URL is visited directly.

### One address, two answers

`GET /catalog/website/{slug}` is a single route that reads `wantsJson()`. Asked for as JSON —
by the drawer, from a row — it returns the payload. Visited by a browser it returns
`Catalog/Website`. `GetWebsiteDetail` builds that payload once for both, so the drawer is
deep-linkable without a second implementation of it, and a link pasted into Slack opens the
same thing the person who sent it was looking at.

The payload starts as `CatalogPresenter`'s row and adds to it (`$row + [...]`), so the header
and the buy button cannot disagree with the row that opened them. The drawer then merges the
live row back over the fetched payload: the payload is fetched once when the drawer opens, but
the row is re-fetched by Inertia after every favourite, blacklist and cart change on the page
behind it. Without the merge the footer keeps offering "Add to cart" for something already in
the cart.

### Every metric carries its own date

Nine tiles, three across, and each one names its source and when it was fetched. One date at
the foot of the grid would imply the nine were measured together, and they are not — a domain
rating is one vendor's crawl and a traffic figure is another vendor's estimate, often weeks
apart. A buyer comparing two sites on DR needs to know both scores came from the same place on
roughly the same day.

A measure nobody has recorded still gets a tile, with a dash and "Not measured". Dropping it
would make the grid a different shape per site and lose the fact that nobody has looked. Domain
age is the one tile with no vendor and no sparkline: it is arithmetic on a registration date,
and saying so is more useful than implying a crawl.

Sparklines are twelve monthly points, one per month — `website_metrics` accumulates rather than
updates, so the newest row in each month wins. A series with fewer than two points is drawn as
nothing: one point is not a trend, and a flat line reads as one. There is no hover tooltip on a
68×20 plot; what replaces it is an accessible summary in words ("Monthly traffic, up 34% over
12 months"), which serves a screen reader, a keyboard and a touch screen equally.

Country shares are percentages of all measured traffic, not of the eight shown — so eight bars
adding to 96% can say "the remaining 4% comes from elsewhere" instead of implying the world is
eight countries.

### Things that are answers, not gaps

`link_guarantee_months = 0` is "no guarantee", printed as those words. A dash there would read
as though nobody asked. The topic chips show both halves — accepted in teal, refused struck
through — because "refuses gambling" and "nobody asked about gambling" are opposite answers for
anyone shopping on it, and a list of only what is accepted cannot tell them apart.

### The buy popover configures before it adds

"Add to cart" opens a 340px popover: service, content mode, folder, landing page, express. Five
choices, and every one of them changes either the price or what the publisher receives. The
running total is recomputed in the popover's own footer, because the two fees are optional and a
buyer choosing between them is choosing between prices. The writing fee is printed on the radio
option that incurs it, not in a footnote — that is the moment the choice is made.

Choosing a folder narrows the landing pages to that folder's. A folder chosen and then offered
another folder's URLs is how an order ends up pointing somewhere nobody meant.

### Keyboard

Escape closes, Tab is trapped, and **J** and **K** step to the next and previous website in the
current filtered result set without closing the drawer. Comparing four candidates is the actual
job here, and doing it through open-read-close costs three clicks a site and loses your place in
the list every time. Both keys are ignored while focus is in a field — they are letters before
they are shortcuts, and typing "jk" into the report box should not navigate away mid-sentence.

### Two bugs this surfaced

**A portalled menu closed the drawer under it.** Clicking any item in the overflow menu — which
`Dropdown` portals to the body — dismissed the drawer, because the menu is outside the drawer's
own DOM and so counted as an outside press. `useDismiss` now answers an outside press only when
it is the topmost overlay, which is the rule it already applied to Escape. Nested overlays close
one at a time, in order.

**A language has no flag.** The header rendered `flagFor(site.language.code)`, and the
regional-indicator pair for "en" is not a country — it draws an empty box. Languages get their
code in a chip, the way the catalog table already showed them; countries, which are countries,
keep their flag.

### One table rather than one column

`website_sample_posts` is a table, not a JSON array on `websites`. A JSON array would make "add
one more sample" a read-modify-write of the whole set, and there is no way to order or expire an
element of a blob.

---

## Cart — `/cart` and checkout — `/checkout`

Eight columns of lines, four of money, and a three-step checkout that ends in one
database transaction.

### The live price wins, and the cart says when it moved

`cart_items.unit_price_cents` is what a line was quoted when it was added. It is
deliberately **not** what gets charged. A cart left open for a month would otherwise
buy at last month's price, and a price that came down would still be billed at the
old one — so the screen, the summary card and the order all read the publisher's
current price, and the snapshot's only job is to let the line say "was $90.00 when
you added it" instead of quietly changing the number.

`CartPricer` is the single place either figure becomes money. The cart page, the
header's cart preview and `PlaceOrder` all go through it, which is what stops the
header quietly disagreeing with the cart about what the same lines cost. The fees
follow the same rule: they are read off the live price row rather than frozen onto
the line, so there is one figure to be right about per site instead of three copies
that can drift apart.

`PlaceOrder` re-reads prices *inside* its transaction, under `lockForUpdate`. That
is the moment the money moves, and the current price is the only figure anybody has
agreed to.

### Grouped by project, because that is the unit people think in

"Am I finished buying for the spring launch" is a question about a group, and a flat
list of nineteen lines across four campaigns cannot answer it. The subtotal sits in
the group header so a collapsed group still answers it.

Lines with no project are not dropped — they are still money in the cart, and a
buyer who cannot see them cannot fix them. They sort last and the header offers the
fix.

### Warnings are advisory, and dismissal sticks

Two kinds. Compatibility (a refused topic, a language the project does not target)
shares `CompatibilityWarnings` with the catalog row, so the two surfaces cannot word
the same problem differently — which is how a buyer ends up thinking they are two
problems. Duplication ("you already placed a post here 6 days ago") is one query for
the whole cart, capped at 90 days: outside that window a repeat placement is an
ordinary thing to want, and a strip that fires on every line is a strip nobody reads.

Every warning offers **both** dismiss and remove, because only the buyer knows which
applies — "does not accept crypto" might be irrelevant to this particular article,
or it might be the reason to drop the line. Dismissal is stored on the line rather
than in component state: a warning that reappears on every load is one people learn
to scroll past, and by then the strip has stopped working for the ones that matter.

The one warning with no dismiss is "the publisher withdrew this service". That line
cannot be bought, so hiding the reason would leave somebody stuck at checkout with
no explanation.

### The shortfall is arithmetic the page can do

When the balance does not cover the order the wallet panel names the exact gap —
"You need $180.00 more" — and the top-up modal opens over the cart with that figure
already in the field, rounded up to whole dollars. "Insufficient funds" at the end of
a three-step checkout is where advertisers abandon orders; sending them to the
billing page to work the number out themselves is how a cart becomes an abandoned
cart.

### Checkout is three steps over one URL

The step is `?step=` rather than component state, so a refresh, the back button and a
link pasted between two of the buyer's own devices all land where they were. Nothing
is written outside the cart until the last step — the articles staged on step two
live on the cart line, so abandoning here leaves a cart with the work already in it.

**Review** is read-only on purpose. It exists to be checked, and a screen where every
field is also an input invites re-configuring instead of reading. Its Article column
is the buyer's first sight of how many of these they are on the hook to write.

**Content** counts words against each publisher's own minimum, which is a real
acceptance criterion — a 400-word draft against a 1,200-word minimum comes back
rejected days later, and catching it here costs nothing. `.docx` is a zip of XML, so
`ArticleText` reads `word/document.xml` with PHP's own zip extension rather than
adding a document library for one field. There are three states, not two: "too short"
is not "not started", and a buyer who pasted 400 words needs to be told which they
are in. A draft that saves short keeps its editor open — closing it would be closing
the one editor with work left in it.

Lines the publisher writes are complete on arrival, which is what turns "twelve
items" into "three to write".

**Confirm** puts *what happens next* directly above the button: frozen not spent,
released per verified link, refunded in full if a placement falls through. A
marketplace that takes several hundred dollars and says nothing about when it leaves
the account generates a support ticket per order.

### One transaction, or none of it

`PlaceOrder` does six things together: the order row, a post per line, the freeze in
the wallet, the promo redemption, the invoice, and emptying the cart. Any subset is a
state somebody unpicks by hand — money frozen against an order that does not exist,
or an empty cart and no posts.

A failure rolls all of it back and **the cart is untouched**. That matters more than
the error message: an advertiser whose payment failed and whose cart also vanished
has lost an hour of work to a problem that was not theirs.

The notification is deliberately outside the transaction. Sent inside it, a mail
server having a bad minute would roll back an order that was otherwise fine, and the
mail could describe a transaction that later aborts.

A post still waiting on the buyer's own article stays in `draft`. That is the whole
of the rule — it does not consult what was chosen on the content step, because "I
said I would do it later" and "there is no article here" are the same fact, and
deriving the state from the article itself is the version that cannot disagree with
what is stored.

### Two bugs this surfaced

**`preserveState: false` was eating the validation errors.** The promo field, the line
editor and the article editor all render server-side errors, and remounting the
component on the response threw them away before anything could show them — so a
rejected promo code silently did nothing. Inertia replaces the page props either way,
so the totals still refresh with the component preserved.

**The header disagreed with the cart.** `ShellData` still summed
`unit_price_cents`, so the cart dropdown could show a different total from the cart
page for the same lines. It goes through `CartPricer` now, like everything else.

### One thing deliberately not built

Card and PayPal are presented for the shortfall but not wired to a processor. The
wallet is the real payment path in this codebase and it is complete — freeze, charge,
refund, all under a row lock — and stubbing a gateway would have added a second,
fake money path beside a working one.

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
