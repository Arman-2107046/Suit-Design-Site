<h1 align="center">Custom Tailor</h1>

<p align="center">
  A made-to-measure suit storefront: design a suit fabric by fabric and detail by detail, watch it rendered live, estimate your measurements, and order — with a full admin behind it.
</p>

<p align="center">
  <img alt="Laravel 12" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white">
  <img alt="React 18" src="https://img.shields.io/badge/React-18-61DAFB?logo=react&logoColor=black">
  <img alt="Inertia 2" src="https://img.shields.io/badge/Inertia-2-9553E9">
  <img alt="Filament 4" src="https://img.shields.io/badge/Filament-4-F59E0B">
  <img alt="Tailwind CSS 3" src="https://img.shields.io/badge/Tailwind-3-06B6D4?logo=tailwindcss&logoColor=white">
  <img alt="Cloudinary" src="https://img.shields.io/badge/Media-Cloudinary-3448C5?logo=cloudinary&logoColor=white">
</p>

---

## Contents

- [What it does](#what-it-does)
- [How it is built](#how-it-is-built)
- [Getting started](#getting-started)
- [Environment](#environment)
- [Admin panel](#admin-panel)
- [Bulk uploading images](#bulk-uploading-images)
- [Fabric info cards](#fabric-info-cards)
- [Orders](#orders)
- [Project layout](#project-layout)
- [Testing](#testing)
- [Deploying](#deploying)

---

## What it does

### Storefront

| Area | Highlights |
|---|---|
| **Homepage** `/` | Editorial hero with parallax, a real-life photo carousel of the fabrics, a details showcase generated from the live catalogue (lapel styles, shoulders, pockets, buttons, linings), a designer video/screenshot frame, process steps, a photo collage, and a footer with payment and shipping logos. Every photo, video, logo and social link is managed from the admin. |
| **Designer** `/design` | Pick a fabric, then shape the body, lapel style and width, shoulder construction, side and chest pockets, buttons and lining. Each choice is rendered on the suit as layered PNGs on a canvas, colour-managed from Adobe RGB to sRGB in Web Workers, with aggressive prefetching so switches feel instant. Fabric swatches carry the name in script; hover for "more info", which opens a full-height preview with pictures, badges, and a details card. |
| **Bag** `/cart` | Suits with their chosen details, quantities, totals. Lives in the browser so guests can shop. |
| **Body profile** `/checkout/body-profile` | Height, weight and age sliders estimate twelve garment measurements server-side; the customer reviews and edits each one, then names and saves the profile (to the account, or to the browser for guests). |
| **Checkout** `/checkout` | Email for guests, then delivery → details → payment (pay on delivery or bank transfer). Prices always come from the catalogue, never the client. |
| **Orders** `/orders/{number}` | Receipt with a six-step status timeline, items, delivery, payment and body profile. Guests can open the receipt from the same browser session; registering with the same email adopts their orders. A confirmation email is sent on placement. |
| **Account** `/dashboard` | Greeting, the suit in progress (read from the designer's saved state), quick actions, the fabric collection, orders with progress bars, and the saved body profile. |

### Admin (`/admin`, Filament)

- **Homepage** — hero image, designer video (uploaded straight from the browser to Cloudinary, so it sidesteps PHP upload limits) and poster, section photos, payment and shipping logos (reorderable), social links.
- **Catalogue** — every configurator table (fabrics, bodies, lapels, sleeves, pockets, buttons, linings and their types) with drag-to-reorder that the storefront respects.
- **Fabrics** — swatch, price, "New" badge, default/active flags, pictures (preview and real-life, with captions) and a bulk picture uploader.
- **Fabric info** — the "more info" card per fabric: title, up to ten icon badges (name auto-filled from the icon's filename), and four columns of `Label: value(note)` details.
- **Fabric pictures** — all preview and real-life pictures grouped by fabric.
- **Orders** — list with status badges, filters and bulk status change; a full order view with items, design lines, delivery and all twelve measurements; an Update-status action (Pending → Confirmed → In production → Quality check → Shipped → Delivered / Cancelled) with payment status and internal notes. Shipped/delivered dates stamp themselves.
- **Bulk upload** — one drop-zone for the whole catalogue; the filename prefix decides where an image goes (see below).

## How it is built

```
Browser ──▶ Laravel 12 (Inertia) ──▶ React 18 pages (Tailwind)
   │                │
   │                ├─▶ /api/configurator  → cached JSON of the entire catalogue
   │                ├─▶ Filament 4 admin   → /admin
   │                └─▶ MySQL
   │
   └─▶ Cloudinary  (all renders, swatches, pictures, icons, logos, video; unsigned uploads from the browser for bulk images and video)
```

A few decisions worth knowing:

- **One payload, cached.** `/api/configurator` returns every fabric with all of its options. It is cached and invalidated by the `BustsConfiguratorCache` trait on every catalogue model, plus explicit busts where Filament writes bypass model events (table reordering, pivot syncs).
- **Colour pipeline.** The suit renders were authored in Adobe RGB but only some carry the profile. The designer ignores embedded profiles, treats every layer as Adobe RGB, and converts to sRGB in Web Workers, so all layers match. Cloudinary is asked only for resizing and format (`f_auto,q_auto,w_…`) — never for colour conversion.
- **Client-side bag and body profile.** Both live in `localStorage` (`resources/js/lib/store.js`) so guests can go from design to receipt without an account. The server re-validates and re-prices everything at checkout.
- **Media never round-trips through PHP where it doesn't have to.** Bulk images and the homepage video go browser → Cloudinary with an unsigned preset; the server only receives the resulting URLs. Single admin uploads use Filament's `FileUpload` on the `cloudinary` Flysystem disk.

## Getting started

Requirements: PHP 8.2+ (8.4 recommended), Composer, Node 18+, MySQL, a Cloudinary account.

```bash
git clone https://github.com/Arman-2107046/Suit-Design-Site.git custom-tailor
cd custom-tailor

composer install
npm install

cp .env.example .env
php artisan key:generate
# edit .env — see Environment below

php artisan migrate
npm run build        # or: npm run dev
php artisan serve    # or use Laravel Herd / Valet
```

Create an admin user with `php artisan make:filament-user`, then open `/admin`.

> With [Laravel Herd](https://herd.laravel.com) the site is served automatically at `http://<folder-name>.test`.

## Environment

| Key | Purpose |
|---|---|
| `APP_NAME` | Shown in the browser tab. Quote it if it has spaces: `APP_NAME="Custom Tailor"` |
| `DB_*` | MySQL connection |
| `CLOUDINARY_URL` | `cloudinary://<key>:<secret>@<cloud>` — used by the `cloudinary` disk, admin uploads and cleanup |
| `CLOUDINARY_UPLOAD_PRESET` | An **unsigned** upload preset; used for browser-side bulk image and video uploads |
| `MAIL_*` | Order confirmations. `MAIL_MAILER=log` writes them to `storage/logs` in development |

## Admin panel

Filament lives at `/admin`. Groups you'll see in the sidebar:

- **Management** — Homepage, Bulk Upload
- **Sales** — Orders (with a badge for pending ones)
- **Catalogue resources** — Fabrics, Fabric info, Fabric pictures, Bodies, Body types, Body buttons, Button images, Lapels, Lapel categories, Lapel subcategories, Sleeves, Sleeve types, Side pockets, Side pocket types, Chest pockets, Chest pocket types, Lining types, Default linings, Custom linings, Custom lining fabrics

Every catalogue table has a **Reorder records** button; the order you drag into is the order the storefront shows.

> ⚠️ `app/Providers/Filament/AdminPanelProvider.php` must list `Authenticate::class` under `authMiddleware` in production. Don't deploy with it commented out.

## Bulk uploading images

**Admin → Bulk Upload** (or the upload buttons on individual resources). Files go straight to Cloudinary; the filename decides what each one becomes. Fabric names must match exactly and must not contain underscores.

| Prefix | Creates / updates | Example |
|---|---|---|
| `FAB` | Fabric (price, name, `_1` = default) | `FAB_120_Blue Stripe_1.png` |
| `FPI` | Fabric preview picture (`_N` = slot) | `FPI_Blue Stripe_2.png` |
| `RL` | Fabric real-life picture (`_N` = slot, `_Caption` = caption) | `RL_Blue Stripe_2 Piece Suit.png` · `RL_Blue Stripe_2_Waistcoat.png` |
| `LT` | Lining type | `LT_Satin Lining.png` |
| `CF` | Custom lining fabric | `CF_Blue Silk.png` |
| `CL` | Custom lining render (offered on every fabric) | `CL_Full Lining_Blue Silk.png` |
| `BT` | Body type | `BT__Single_Breasted_SB1.png` |
| `BD` | Body render | `BD_SB1_Black_1.png` |
| `DL` | Default lining render | `DL_SB1_Default_Black Wool.png` |
| `BI` | Button image | `BI_Button1.png` |
| `BB` | Body button render | `BB_SB1_Button1_1.png` |
| `SLT` | Sleeve (shoulder) type | `SLT_English_Shoulder_ES.png` |
| `SL` | Sleeve render | `SL_ES_Black Stripe_1.png` |
| `CPT` | Chest pocket type | `CPT_Welt_WELT.png` |
| `CP` | Chest pocket render | `CP_NP_Blue Stripe_1.png` |
| `SPT` | Side pocket type | `SPT_Welt_WELT.png` |
| `SP` | Side pocket render | `SP_WELT_Black_1.png` |
| `LPC` | Lapel category | `LPC_Notch.png` |
| `LPS` | Lapel subcategory (width) | `LPS_Classic.png` |
| `LP` | Lapel render | `LP_SB1_Notch_Large_Blue Stripe_1.png` |

Uploads are processed in dependency order (fabrics and types before the renders that reference them), and every failure is reported with the reason. Up to ten preview and ten real-life pictures per fabric.

Fabrics can also be imported from CSV (**Fabrics → Import Fabrics**).

## Fabric info cards

**Admin → Fabric info.** One record per fabric (the most recently edited one is shown):

1. **Fabric** and a **title** (shown large on the card), plus an optional description.
2. **Badges** — up to ten icon + name pairs. Upload an icon and the name fills from the filename (`Pure wool.png` → "Pure wool").
3. **Four textboxes**, one per column, each a comma-separated list of `Label: value` entries:

   ```
   Tone: Black, Pattern: Checked, Weave: Serge(A twill weave with a pronounced diagonal.), Brand: Loro Piana
   ```

   Text in brackets becomes an ⓘ that opens the note large. Commas inside brackets are safe, and a chunk without a colon continues the previous value, so `Suggested occasion: Business, Casual` stays one entry. Any bracket becomes a note — write `Weight: Medium 280 gr/m²` to keep text inline.

On the storefront the card shows the title and badges; **More info** reveals the description and columns, **Real life pictures** switches to a captioned photo slider.

## Orders

Statuses, in the order customers see them: `pending` → `confirmed` → `in_production` → `quality_check` → `shipped` → `delivered`, plus `cancelled`. Order numbers look like `CT-2026-A7K3QZ`. Payment methods today are `cash_on_delivery` and `bank_transfer`; the `payment_method` / `payment_status` fields are ready for a card gateway.

## Project layout

```
app/
  Enums/OrderStatus.php                 statuses, labels, colours, customer copy
  Filament/                             admin: Pages (Homepage, BulkUpload), Resources, Forms/Components
  Http/Controllers/
    Api/SuitConfiguratorController.php  the cached catalogue payload
    CheckoutController.php              bag, body profile, estimate, checkout
    OrderController.php                 receipts
  Mail/OrderPlaced.php                  confirmation email (Markdown)
  Models/                               Fabric, FabricImage, FabricInfo, Order, OrderItem, BodyProfile, HomepageSetting, …
  Services/BulkUpload/                  one uploader per filename prefix
  Support/BodyEstimator.php             measurements from height / weight / age
resources/js/
  Pages/Home.jsx                        homepage
  Pages/Welcome.jsx                     the designer (canvas, colour pipeline, caches, overlay)
  Pages/Cart.jsx, Checkout/, Orders/    commerce flow
  Pages/Dashboard.jsx, Auth/, Profile/  account
  Layouts/                              Store, Guest, Authenticated shells
  lib/store.js                          bag + body profile in localStorage
tests/                                  feature tests for checkout, uploads and orders; unit tests for the info-card parser
```

## Testing

```bash
php artisan test
```

Runs against an in-memory SQLite database. Coverage includes guest and account checkout, catalogue-side pricing, receipt privacy, order adoption on registration, body-profile saving, status timestamps, the bulk picture uploader, the configurator payload, and the `Label: value(note)` parser.

## Deploying

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build        # writes public/build; needs Node ^20.19 || >=22.12
php artisan migrate --force
php artisan optimize           # config, route and view cache
```

- **`APP_ENV=production` and `APP_DEBUG=false`** in the server's `.env`, with `APP_URL` set to the real domain. Debug mode on a public host leaks stack traces containing your credentials.
- **Front-end assets** come from `public/build`, which is gitignored — so either run `npm run build` on the server or upload the folder. There is no dev-server fallback in production: `npm run dev` writes its marker to `storage/vite.hot`, outside the web root, and any environment other than `local` ignores that file outright (see `AppServiceProvider::boot()`). If a live site ever requests `localhost:5173`, an old `public/hot` is still sitting on the server — delete it.
- Every page component needs its own manifest entry, so **pages must not import other pages** — put anything shared in `resources/js/Components`. `php artisan test` catches a violation, because the suite renders against the real manifest.
- Raise `upload_max_filesize` / `post_max_size` only if you need larger *single* admin uploads; bulk images and the video never pass through PHP.

---

<p align="center">Made to measure.</p>
