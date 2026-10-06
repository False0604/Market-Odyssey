# Market Odyssey — Campus Marketplace 🍎

A student-run **buy / sell / bargain** marketplace for the Woxsen campus, built for the
**Web Technologies** course. It gives the campus "Buy n Sell" WhatsApp group a proper
structure: students post a photo + title + price, an admin approves it, and buyers
message the seller in-app to arrange pickup, price and bargaining.

Built with only the topics from our syllabus: **HTML, CSS, PHP, MySQL and JavaScript.**

---

## How to run it on localhost (XAMPP)

> **⚠️ Important on this PC:** you have a separate **MySQL 8.0 service ("MySQL80")**
> installed that already sits on port **3306**, which blocks XAMPP's own MySQL from
> starting. Do **step 0** below once to free the port. (If you ever move this project
> to a plain XAMPP machine without MySQL80, just skip step 0.)

0. **Free up port 3306 for XAMPP (one-time).** Press `Win`, type *Services*, open it,
   find **MySQL80**, right-click → **Stop**. (Optional: right-click → Properties →
   Startup type → **Manual**, so it doesn't grab the port again after a reboot.)
   You can start MySQL80 again the same way whenever you need it for other work.
1. **Open XAMPP.** Make sure this project folder sits inside:
   ```
   C:\xampp\htdocs\market-odyssey
   ```
2. Start **Apache** and **MySQL**.
   - **Apache:** click **Start** in the XAMPP Control Panel.
   - **MySQL:** click **Start** in the panel — **or**, if the panel keeps saying
     *"MySQL shutdown unexpectedly"* (a known XAMPP Control Panel quirk on this PC),
     just double-click **`C:\xampp\Start-MarketOdyssey-MySQL.bat`** instead. It runs
     the exact same MariaDB reliably; keep that black window open while you use the
     site, and close it to stop MySQL. Don't start MySQL both ways at once.
3. Open your browser and go to:
   ```
   http://localhost/market-odyssey/
   ```
4. That's it. `config/db.php` automatically creates the `market_odyssey` database,
   all the tables, and the sample listings + users on first load — you do **not**
   need to touch phpMyAdmin. (On this PC the database has already been created for
   you, so the marketplace is populated from the very first visit.)

> **Note on the MySQL password:** `config/db.php` defaults to an *empty* root
> password (stock XAMPP). If your MariaDB `root` account has a password, copy
> `config/db.local.example.php` to `config/db.local.php` and set `$DB_PASS` there —
> that file is git-ignored, so the password is never committed.

### Demo logins
| Role | Email | Password | Where |
|------|-------|----------|-------|
| Student | `jhon@woxsen.edu` | `pass123` | main **Log in** page |
| Student | `aarav@woxsen.edu` / `ishita@woxsen.edu` / `riya@outlook.com` | `pass123` | main **Log in** page |
| **Admin** | `admin@woxsen.edu` | `admin123` | **Admin** link in the footer → separate admin login |

Students can also **Continue with Microsoft** (demo signs in as Riya) or **Join** to register.

---

## What you can do

- **Browse** the marketplace, filter by the category bar (Electronic, Food,
  Posters/Decor, Tickets, Other), and **search** listings.
- **Open a listing** to see the photo, description, seller, payment options and
  the seller's **UPI QR** (if provided).
- **Sell an item** — upload a photo, set title/category (with a custom name when
  you pick *Other*)/price/condition, pick a campus pickup spot (Rise, Pool area,
  Fountain, iCreate, Library), and choose payment. Choosing **UPI** lets you
  upload your **QR code**. New listings go into an **admin review** queue.
- **Buy** — click **Purchase** to reserve an item for 20 minutes. The seller is
  notified; others can still view/message but not buy. When the seller confirms
  payment they **mark it as sold**.
- **My listings** live under your **profile** (not the public grid), where you
  also confirm sales and remove items.
- **Message** a seller (you can't message yourself on your own listing).
- **Admin** (separate login) — approve/reject, **hide/show** or **remove** any
  listing, and **block/unblock** users, with live stats and an audit log.
- **Log in / register / log out** with sessions, a "remember me" cookie, and an
  optional **Continue with Microsoft** sign-in.

---

## Where each syllabus topic lives

**HTML** — tables/lists/images/forms across every `.php` page; forms in
`sell.php`, `login.php`, `register.php`.

**CSS** — all styling in `assets/css/style.css` (layout, colours, responsive grid).

**PHP**
- Variables, data types, arrays, strings, operators, control structures,
  functions → `config/functions.php`, every page.
- Reading form controls (text box, list/`<select>`, radio buttons, check boxes)
  → `sell.php`, `register.php`.
- **File uploads** → the product photo in `sell.php`.
- **MySQL**: connect, run queries, handle results → `config/db.php` and everywhere.
- **Sessions & cookies** → `login.php`, `logout.php`, `config/functions.php`.
- **File handling** (open / write / append / close a text file) → the audit log in
  `admin.php` (`uploads/admin_log.txt`).

**JavaScript** — variables, functions, event handlers (`onclick`, `onsubmit`,
`onchange`), the DOM and **form validation** → `assets/js/main.js`.

---

## Folder structure

```
market-odyssey/
├── index.php          Marketplace home (grid, search, category filter)
├── product.php        Single listing + "Message seller"
├── sell.php           Sell-an-item form (with photo upload)
├── messages.php       Buyer/seller chat
├── login.php          Log in  (session + remember-me cookie)
├── register.php       Create an account
├── logout.php         End the session
├── account.php        Your profile + your listings
├── admin.php          Admin approval queue (+ text-file log)
├── config/
│   ├── db.php         DB connection + first-run auto setup
│   ├── seed.php       Sample data (first run only)
│   └── functions.php  Helper functions, sessions
├── includes/
│   ├── header.php     Shared top bar
│   └── footer.php     Shared footer
├── assets/
│   ├── css/style.css  All styling
│   └── js/main.js     Client-side validation & DOM
├── uploads/           Uploaded product photos + admin_log.txt
├── sql/schema.sql     Reference schema (auto-created, for viewing)
└── README.md          This file
```

---

## Resetting the demo data

Want a clean start? Open **phpMyAdmin** (`http://localhost/phpmyadmin`),
drop the `market_odyssey` database, then reload the site — it rebuilds itself.
