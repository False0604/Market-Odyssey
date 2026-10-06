# Project Handoff: Market Odyssey

_A student-run campus marketplace for Woxsen — built for the **Web Technologies** course._
_Last updated: 2026-09-15_

---

## 📋 Executive Summary

**Market Odyssey** is a web app where Woxsen students buy, sell and swap items among
themselves. It replaces the unstructured "Buy n Sell" campus WhatsApp group with a real
marketplace: post a photo + price, an admin approves it, buyers reserve/purchase and
message the seller in-app, both sides confirm the handover, and they review each other.

It is built **only with the topics on the course syllabus** — HTML, CSS, PHP, MySQL and
JavaScript — with **no frameworks, no Composer, no build step**. It runs on **XAMPP**
(Apache + MariaDB + PHP). Everything is plain server-rendered PHP plus one CSS file and
one JS file.

---

## 🏗️ Project Overview

| | |
|---|---|
| **Project name** | Market Odyssey |
| **Type** | Server-rendered PHP web app (no framework) |
| **Runs on** | XAMPP — Apache, MariaDB 10.4.32, PHP 8.2.12 |
| **Location** | `C:\xampp\htdocs\market-odyssey` |
| **URL (local)** | http://localhost/market-odyssey/ |
| **Database** | MySQL/MariaDB schema `market_odyssey` (auto-created on first run) |
| **Version control** | **Not a git repo yet** — consider `git init` (see Onboarding) |
| **Owner** | Course student (mr.dabzme@gmail.com) — Web Technologies, Woxsen |

---

## 🛠️ Technology Stack

- **PHP 8.2.12** (procedural; `mysqli` for the database). No Composer / no PHP packages.
- **MariaDB 10.4.32** (the MySQL bundled with XAMPP).
- **Apache** (XAMPP) serving `htdocs`.
- **HTML5 / CSS3** — one hand-written stylesheet, no CSS framework.
- **Vanilla JavaScript** — one file, no libraries.
- **Google Fonts** (CDN): _Hanken Grotesk_.
- **Microsoft OAuth 2.0** (optional, for "Continue with Microsoft") — code present, off by default.

There is **no package.json, no build tooling, no bundler**. Edit a file → refresh the browser.

> **Cache-busting:** `style.css` and `main.js` are referenced with a `?v=N` query
> (currently **v8**) in `includes/header.php` and `includes/footer.php`. **Bump both
> numbers whenever you change CSS/JS**, or browsers will serve stale cached assets.

---

## 📁 Directory Structure

```
market-odyssey/
├── index.php            Marketplace home (hero, category filter, search, card grid)
├── product.php          Single listing: photo gallery, buy→reserve→confirm→review, freeze hold
├── sell.php             "Sell an item" form (multi-photo upload, UPI QR, "Other" category)
├── messages.php         Buyer/seller chat (system notices rendered as centered pills)
├── login.php            Student login (session + "remember me" cookie; blocks banned users)
├── register.php         Two-step signup: details (+ typed location) → email OTP verify
├── logout.php           Ends the session, clears cookies
├── account.php          Profile: my listings, my purchases, sale requests, request verification
├── admin_login.php      SEPARATE admin login (distinct credentials)
├── admin.php            Admin panel: verify accounts, approve/hide/remove listings, ban users,
│                        view transactions & reviews (full data visibility)
├── ms_login.php         Starts "Continue with Microsoft" (real OAuth if configured, else demo)
├── ms_callback.php      Microsoft OAuth redirect handler (real mode only)
│
├── config/
│   ├── db.php           DB connection + first-run table creation + idempotent auto-migrations
│   ├── seed.php         Demo users + listings (first run only)
│   ├── functions.php    Helpers: sessions, auth guards, categories/locations, money, reviews,
│   │                    verified tick, listing state (reserved/sold/frozen), gallery
│   └── ms_config.php    Microsoft OAuth credentials (blank = demo mode)
│
├── includes/
│   ├── header.php       Two-tier top bar (logo, search, category bar) + <head> asset links
│   └── footer.php       Footer + Admin link + <script> tag
│
├── assets/
│   ├── css/style.css    ALL styling (design tokens, components, responsive, cursor, modals)
│   └── js/main.js       ALL client JS (form validation, previews, custom cursor, gallery)
│
├── uploads/             Uploaded product photos, UPI QR images, and admin_log.txt
│   └── index.php        Redirect guard (stops directory browsing)
│
├── sql/schema.sql       Reference schema (the app auto-creates tables, so this is docs only)
├── README.md            Student-facing run guide + syllabus mapping
└── handoff.md           This document
```

Also relevant (outside the project folder):
- `C:\xampp\Start-MarketOdyssey-MySQL.bat` — reliable one-click MySQL starter (see Gotchas).

---

## ⚙️ Setup & Installation

**Prerequisites:** XAMPP installed at `C:\xampp` (Apache + MySQL/MariaDB + PHP).

1. Place the project at `C:\xampp\htdocs\market-odyssey` (it already is).
2. **Free port 3306 (one-time, this PC only):** a standalone **MySQL 8.0 service ("MySQL80")**
   is installed on this machine and grabs port 3306, which blocks XAMPP's MariaDB. Stop it:
   - Open **Services** (Win → "Services") → right-click **MySQL80** → **Stop**
     (optionally set Startup type to **Manual** so it stays stopped after reboot).
3. **Start the database:**
   - In **XAMPP Control Panel**, click **Start** on **MySQL** — **or**, if it says
     _"MySQL shutdown unexpectedly"_, double-click **`C:\xampp\Start-MarketOdyssey-MySQL.bat`**
     and keep that window open (a known XAMPP Control Panel quirk on this PC).
4. **Start Apache** in the XAMPP Control Panel.
5. Open **http://localhost/market-odyssey/**.

On the **first request**, `config/db.php` automatically creates the `market_odyssey`
database, all tables, the admin account, and demo data. No manual phpMyAdmin steps.

### Database credentials (in `config/db.php`)
```php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';                // stock XAMPP default
$DB_NAME = 'market_odyssey';
$DB_PORT = 3306;
```
> ⚠️ If the machine's MariaDB root account has a password, copy
> `config/db.local.example.php` to `config/db.local.php` and set `$DB_PASS` there.
> `db.local.php` is git-ignored, so the real password never lands in the repo.

---

## 🚀 Build & Run

There is **no build**. To develop: edit a `.php`/`.css`/`.js` file and refresh the browser.

- **Run:** start MySQL + Apache (above), open `http://localhost/market-odyssey/`.
- **Lint a PHP file:** `C:\xampp\php\php.exe -l path\to\file.php`
- **Reset the whole database** (fresh demo data): open phpMyAdmin
  (`http://localhost/phpmyadmin`), **drop** the `market_odyssey` database, then reload the
  site — it rebuilds itself and re-seeds.
- **After editing CSS/JS:** bump `?v=8` → `?v=9` in `includes/header.php` (CSS) and
  `includes/footer.php` (JS) so browsers fetch the new files.

### Test / demo credentials
| Role | Email | Password | Entry point |
|---|---|---|---|
| Student | `jhon@woxsen.edu` | `pass123` | Log in |
| Students | `aarav@woxsen.edu`, `ishita@woxsen.edu`, `riya@outlook.com` | `pass123` | Log in |
| **Admin** | `admin@woxsen.edu` | `admin123` | Footer → **Admin** → admin login |

The 4 seed students are pre-**verified** (blue tick). New signups start unverified and
appear in the admin verification queue.

---

## 🗄️ Database Schema (`market_odyssey`)

Tables are auto-created by `config/db.php`; new columns are added idempotently via the
`ensure_column()` helper, so existing databases upgrade themselves on the next page load.

**users**
| column | notes |
|---|---|
| id, name, email (unique), password (hashed) | `password_hash()` — never plain text |
| hostel | the pickup/campus-spot location (free text allowed) |
| is_admin | 1 = admin account (separate login) |
| banned | 1 = blocked; cannot log in, listings hidden |
| email_verified | 1 = verified via OTP at signup |
| verified | 1 = **admin-approved account** → blue tick |
| verify_requested | 1 = user asked the admin to verify |
| created_at | |

**listings**
| column | notes |
|---|---|
| id, user_id, title, category, custom_category | `custom_category` used when category = "Other" |
| price (0 = Free), item_condition, pickup, description | `item_condition` avoids the reserved word `condition` |
| photo | primary/cover image filename (in `/uploads`) |
| qr | seller's UPI QR image filename |
| pay_cod, pay_upi | payment methods accepted |
| status | `pending` \| `approved` (admin review) |
| avail | `available` \| `reserved` \| `sold` |
| reserved_by, reserved_until | 20-min hold (see functions.php) |
| frozen | 1 = hold never expires (delivery convenience) |
| seller_confirmed, buyer_confirmed | dual handover confirmation |
| hidden | 1 = admin hid it from the marketplace |
| created_at | |

**listing_photos** — extra photos beyond the cover: `id, listing_id, filename`.

**messages** — chat + system notices: `id, listing_id, sender_id, receiver_id, body, created_at`.
System notices (buy/sold/frozen/thanks) begin with an emoji (🛒 ✅ ❄ 🙏) and render as
centered pills in the chat.

**reviews** — mutual post-sale reviews: `id, listing_id, reviewer_id, reviewee_id, role
('buyer'|'seller'), rating (1–5), body, created_at`.

---

## 🧭 Key Flows & Where They Live

- **Listing lifecycle:** `sell.php` (create, `status=pending`) → `admin.php` (approve) →
  visible in `index.php`. Own listings are hidden from your own marketplace view and live
  under `account.php`.
- **Purchase flow (`product.php`):** Buy → **reserve 20 min** (`reserved_until`, notifies
  seller) → optional **Freeze** (hold never expires) → seller "Confirm delivery" + buyer
  "Confirm receipt" (both are **modals/`<dialog>` windows**) → when both confirm, `avail=sold`
  → each gets a **review window** (star rating + note).
- **Reservation time is computed in PHP** (`listing_state()` / `reserve_minutes_left()` in
  `config/functions.php`) — deliberately **not** with MySQL `NOW()`, because PHP and MariaDB
  can be on different timezones on this machine. Keep it that way.
- **Signup + OTP (`register.php`):** step 1 details (+ "Other" location = free text) → a
  6-digit OTP is generated and stored in the session → step 2 verifies it → account created
  with `email_verified=1`, `verified=0`.
- **Account verification (`admin.php`):** admin sees new/unverified accounts (email + name +
  email-verified status), clicks **Verify** → `verified=1` → **blue tick** shows on profile,
  product seller card, and admin lists. Unverified users can **Request verification** in
  `account.php`.

---

## 🔑 Environment Variables & Secrets

There is **no `.env`** — configuration is inline in `config/`.

| Secret / config | File | Notes |
|---|---|---|
| DB root password | `config/db.local.php` (git-ignored) | Machine-specific; template in `config/db.local.example.php` |
| Microsoft OAuth client id/secret | `config/ms_config.php` | **Blank by default = demo mode** |

> **Do not commit real secrets** if you push this to a public repo. For a submission it's
> fine locally, but rotate/blank the DB password and any OAuth secret before sharing.

---

## ⚠️ Known Issues & Gotchas

1. **Port 3306 conflict (MySQL80).** A standalone MySQL 8.0 Windows service ("MySQL80") on
   this PC occupies 3306 and blocks XAMPP's MariaDB. Symptom: XAMPP says _"MySQL shutdown
   unexpectedly"_. Fix: stop MySQL80 (Services), or use `C:\xampp\Start-MarketOdyssey-MySQL.bat`.
   Check with `netstat -ano | findstr ":3306"`. Presence of port **33060** = MySQL80 is back.
2. **DB password is machine-specific.** `config/db.php` defaults to an **empty** password
   (stock XAMPP). If MariaDB root has a password, put it in `config/db.local.php`, or you'll
   get _"Access denied … using password: YES/NO"_.
3. **Asset caching.** If CSS/JS changes don't show, the browser cached the old file. Bump the
   `?v=` number in `header.php` (CSS) and `footer.php` (JS), or hard-refresh (Ctrl+Shift+R).
4. **Email OTP is "demo mode."** Stock XAMPP has no SMTP server, so `register.php` **shows the
   OTP on screen** in a clearly-labelled demo banner instead of emailing it. To send real
   email, uncomment the `mail()` line in `register.php` (`issue_otp()`) and configure SMTP /
   PHPMailer.
5. **Microsoft login is "demo mode."** Without Azure credentials in `config/ms_config.php`,
   "Continue with Microsoft" signs in as the demo account **Riya Kapoor**. To make it real:
   register an app at portal.azure.com, set redirect URI
   `http://localhost/market-odyssey/ms_callback.php`, and fill in `MS_CLIENT_ID` / `MS_CLIENT_SECRET`.
6. **Timezone: reservation timing must stay in PHP.** PHP and MariaDB may report different
   "now" on this machine. All reservation-expiry logic uses PHP `time()`/`strtotime()`. Don't
   switch it to MySQL `NOW()` comparisons or holds will expire wrong.
7. **Services don't auto-start.** After a reboot you must start MySQL (bat/panel) and Apache
   again. If the site shows a DB connection fatal error, MySQL simply isn't running.
8. **`uploads/` is world-writable by the app.** Files are validated (image type, ≤4 MB) and
   given randomized names, but there's no virus scanning — fine for a course demo, harden
   before any real deployment.

---

## 🔒 Security Notes (current state)

- Passwords are stored with `password_hash()` / verified with `password_verify()`. ✅
- Output is escaped with a central `e()` (htmlspecialchars) helper to prevent XSS. ✅
- SQL uses `mysqli_real_escape_string()` (and some prepared statements). ⚠️ For production,
  migrate all queries to **prepared statements** — the current mix is acceptable for the
  course but not ideal.
- No CSRF tokens on POST forms (acceptable for a local course demo; add for production).
- Admin area is gated by `require_admin()` (session `is_admin`), separate from student login.

---

## 🎓 Syllabus Mapping (why each topic is covered)

| Topic | Where |
|---|---|
| HTML (forms, lists, tables, images) | every `.php` page; forms in `sell.php`, `register.php`, `login.php` |
| CSS | `assets/css/style.css` (design tokens, responsive, components) |
| PHP: variables, arrays, strings, control structures, functions | `config/functions.php`, all pages |
| PHP: reading form controls (text, list, radio, checkbox) | `sell.php`, `register.php` |
| PHP: **file uploads** | multi-photo + QR upload in `sell.php` |
| PHP: **MySQL** connect / query / results | `config/db.php` and everywhere |
| PHP: **sessions & cookies** | `login.php`, `logout.php`, `config/functions.php` |
| PHP: **file handling** (open/write/append/close) | admin audit log `uploads/admin_log.txt` (`admin.php`) |
| JavaScript: variables, functions, event handlers, DOM, **form validation** | `assets/js/main.js` |

> The syllabus also lists **XML** (DTD/Schema/DOM/SAX) and **Java Servlets** — these are
> **not yet implemented**. See Future Work.

---

## 🧪 How to Verify It Works (smoke test)

1. Log in as `jhon@woxsen.edu` / `pass123`.
2. Browse the marketplace; open a listing; click **Purchase** → it reserves for 20 min and
   messages the seller.
3. Log in as the seller in another browser/incognito; open `account.php` → **Sale requests**
   → open the item → **Confirm delivery**; as the buyer **Confirm receipt** → item goes
   **Sold** → each gets a **review** window.
4. Register a new account → enter the on-screen **OTP** → land logged in (unverified).
5. As admin (`admin@woxsen.edu` / `admin123`) → **Account verifications** → **Verify** → the
   new account gets the **blue tick**.

---

## ✅ Onboarding Checklist (for whoever takes this over)

- [ ] Install XAMPP; confirm PHP 8.2.x and MariaDB 10.4.x.
- [ ] Resolve the **MySQL80 port 3306** conflict (Gotcha #1).
- [ ] Start MySQL + Apache; open `http://localhost/market-odyssey/`.
- [ ] Confirm the DB auto-created (phpMyAdmin → `market_odyssey` with 5 tables).
- [ ] Log in with a demo student and run the smoke test above.
- [ ] Read `config/db.php` (connection + migrations) and `config/functions.php` (helpers) —
      these two files explain most of the app.
- [ ] Set `$DB_PASS` correctly for your machine (empty on stock XAMPP).
- [ ] (Recommended) `git init` and commit — this project is **not** under version control yet.
- [ ] Bump the `?v=` asset version whenever you change CSS/JS.

---

## 🔭 Future Work / Backlog

- **XML unit** (syllabus): add an XML export of listings with a **DTD/XML Schema** and a page
  that parses it via **DOM/SAX** — currently missing.
- **Java Servlets unit** (syllabus): not implemented (the app is PHP-only).
- **Security hardening:** full prepared statements everywhere, CSRF tokens, rate-limiting OTP.
- **Real email** for OTP (SMTP/PHPMailer) and **real Microsoft OAuth** (Azure app).
- **Verified tick on marketplace cards** (currently on product/profile/admin only).
- **Gate selling behind email verification** (optional idea).
- Product image **lightbox/zoom** and drag-to-reorder photos on the sell form.

---

## 📞 Team & Contacts

- **Primary developer / owner:** Woxsen student — mr.dabzme@gmail.com
- **Course:** Web Technologies (Woxsen University)
- **Reviewer/instructor:** _[to be filled in]_

---

_Built with HTML, CSS, PHP, MySQL & JavaScript. No frameworks, no build step — just XAMPP._
