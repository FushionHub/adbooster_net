# Installation Guide — step by step

Everything needed to go from zero to a running Adsbooster installation — on your own
computer and on cPanel hosting. Written to be followed top-to-bottom without any
programming knowledge.

---

## Part 1 — What you need

| Requirement | Why |
|---|---|
| **PHP 8.3 or newer** (8.4 works too) | The language the platform runs on |
| **MySQL 8.0+ or MariaDB 10.3+** | Where all data is stored |
| **Apache** (Laragon/XAMPP/cPanel provide it) | Serves the website |
| The project files | Clone or download the repository |
| PHP extensions `pdo_mysql`, `curl`, `zip`, `mbstring`, `gd` | Standard on Laragon/XAMPP/cPanel; if missing, enable them in `php.ini` |
| Internet connection | For the AI studios and the connection gateways |

---

## Part 2 — Local installation (Windows: Laragon or XAMPP)

### Step 1 — Put the files in place

- **Laragon:** copy the project folder into `C:\laragon\www\` so you have
  `C:\laragon\www\adsbooster\`.
- **XAMPP:** copy it into `C:\xampp\htdocs\` so you have `C:\xampp\htdocs\adsbooster\`.

### Step 2 — Start the servers

Open the Laragon or XAMPP control panel and start **Apache** and **MySQL**. Both
icons should turn green.

### Step 3 — Create the empty database

1. Open `http://localhost/phpmyadmin` in your browser.
2. Click **New** in the left sidebar.
3. Type the database name `adsbooster`.
4. Choose collation **utf8mb4_general_ci**.
5. Click **Create** — leave it empty. **Do not import anything.**

### Step 4 — Open the website and let it install itself

1. Visit `http://localhost/adsbooster/`.
2. On the first page load the platform checks its database and **creates every
   table, index and default setting by itself** (a guarded installer). Missing
   pieces are also added automatically later — you never run SQL by hand.
3. You should see the homepage.

### Step 5 — Create your account and make it the administrator

1. Click **Register** and create your account (use a real email — reminders,
   notifications and 2FA codes are sent there).
2. Make yourself the administrator:
   - Open `http://localhost/phpmyadmin` → database `adsbooster` → table `users`
     → your row → **Edit**.
   - Set the `role` column to `admin` and click **Go**.
3. Sign in at `http://localhost/adsbooster/login` — the admin suite now appears.

### Step 6 — Add the gateway keys (Admin → Settings)

| Key | Where it comes from | What it unlocks |
|---|---|---|
| **Bundle.social key** | Your Bundle.social account | Connecting & posting to Facebook, Instagram, TikTok, X, LinkedIn, Pinterest |
| **Zernio key** | Your Zernio account | Connecting ad accounts & running paid ads |
| **Paystack / Flutterwave keys** | Their dashboards | Real payments |
| **Gemini key** *(optional)* | Google AI Studio | Premium AI photos & videos |
| **Groq key** *(optional)* | console.groq.com | Premium AI copywriting |
| **Firecrawl key** *(optional)* | firecrawl.dev | Store scraping / website import |

> Without keys the platform still works — the studios use their free routes, and the
> connection pages simply explain that the gateway is not configured yet. With
> placeholder keys the gateways deliberately stay in "sandbox" mode: nothing is ever
> published or charged half-way.

### Step 7 — Local cron (optional on Windows)

To test the background worker locally (it sends scheduled posts and launches ads):

```bat
"C:\laragon\bin\php\php-8.3.x\php.exe" C:\laragon\www\adsbooster\cron\queue_worker.php --once
```

Run it in a terminal, or create a Windows Task Scheduler task that runs it **every
minute** with the `--once` flag. On XAMPP the php path is `C:\xampp\php\php.exe`.

---

## Part 3 — Live installation on cPanel

### Step 1 — Upload the files

1. cPanel → **File Manager** → open `public_html`.
2. Upload the project as a `.zip` and use **Extract**.
3. The files should sit directly in `public_html` (or in a subfolder if you want the
   site under a path like `example.com/app/`).

### Step 2 — Create the database

1. cPanel → **MySQL Databases**.
2. Create a database, e.g. `dvbdrrml_adsbooster`.
3. Create a user, e.g. `dvbdrrml_admin`, with a strong password.
4. Under **Add User To Database** grant the user **ALL PRIVILEGES**.
5. Write down the three values: database name, username, password. Your cPanel
   username is always the prefix (here shown as `dvbdrrml`).

### Step 3 — Tell the platform about the database

The platform tries its defaults first. If your database values differ, create a file
`config/db.local.php` (it is never committed to git) with:

```php
<?php
return [
    'host' => 'localhost',
    'name' => 'dvbdrrml_adsbooster',   // your database name
    'user' => 'dvbdrrml_admin',        // your database user
    'pass' => 'YOUR-PASSWORD',         // your database password
];
```

### Step 4 — First visit (self-install)

Open your domain in a browser. The platform creates all its tables automatically.
Create your account, then set `role = admin` for your row (phpMyAdmin → database →
`users` → Edit) exactly as in the local guide.

> **Important:** delete or rename the install of the database only if you move hosts —
> the self-installer is guarded and safe to leave in place; it only ever *adds* what
> is missing.

### Step 5 — Clean URLs

The included `.htaccess` makes URLs work as `/login`, `/dashboard`, `/post-creator`
instead of `/login.php`. On cPanel this works out of the box with Apache. If pages
404, check that `.htaccess` was uploaded (File Manager → *Settings* → *Show hidden
files*).

---

## Part 4 — The cron jobs (what makes posting & ads actually run)

This is the step that turns the platform from "it schedules things" into
"it actually does things". All background work — sending posts at their exact time,
launching queued ads, pulling results, reminders — happens in these scripts, and they
only run if your hosting scheduler runs them.

### One-time preparation

cPanel → **File Manager** → inside `public_html`, create a folder named `crons`
(the logs below are written there; the scheduler will not create it for you).

### Find your PHP path

cPanel → **Terminal** → run `which php`. Or check **MultiPHP Manager** for your PHP
version: for PHP 8.3 on EasyApache the path is typically

```
/usr/local/bin/ea-php83/root/usr/bin/php
```

(the format `/usr/local/bin/ea-php99/php` seen in some guides is wrong — keep the
`/root/usr/bin/php` tail).

### The jobs

cPanel → **Cron Jobs** → *Add New Cron Job*. Use the "Common Settings" dropdown for
the schedule, then paste the command. Replace `YOURUSER` and the php path with yours.

| Priority | Schedule | Command |
|---|---|---|
| **1 — CRITICAL** | Once per minute `* * * * *` | `/usr/local/bin/ea-php83/root/usr/bin/php /home/YOURUSER/public_html/cron/queue_worker.php --once >> /home/YOURUSER/public_html/crons/queue_worker.log 2>&1` |
| 2 | Every 5 min `*/5 * * * *` | `/usr/local/bin/ea-php83/root/usr/bin/php /home/YOURUSER/public_html/cron/publish_scheduled_posts.php >> /home/YOURUSER/public_html/crons/publish.log 2>&1` |
| 3 | Every 10 min `*/10 * * * *` | `/usr/local/bin/ea-php83/root/usr/bin/php /home/YOURUSER/public_html/cron/auto_refill_check.php >> /home/YOURUSER/public_html/crons/auto_refill.log 2>&1` |
| 4 | Every 15 min `*/15 * * * *` | `/usr/local/bin/ea-php83/root/usr/bin/php /home/YOURUSER/public_html/cron/campaign_doctor.php >> /home/YOURUSER/public_html/crons/doctor.log 2>&1` |
| 5 | Every 30 min `*/30 * * * *` | `/usr/local/bin/ea-php83/root/usr/bin/php /home/YOURUSER/public_html/cron/auto_buyer_daemon.php >> /home/YOURUSER/public_html/crons/auto_buyer.log 2>&1` |
| 6 | Hourly `0 * * * *` | `/usr/local/bin/ea-php83/root/usr/bin/php /home/YOURUSER/public_html/cron/pull_campaign_metrics.php >> /home/YOURUSER/public_html/crons/metrics.log 2>&1` |
| 7 | Hourly (offset) `10 * * * *` | `/usr/local/bin/ea-php83/root/usr/bin/php /home/YOURUSER/public_html/cron/ab_test_winner.php >> /home/YOURUSER/public_html/crons/abtest.log 2>&1` |

**Why `--once` matters:** job #1 without `--once` runs as an endless loop; combined
with a every-minute cron you would stack dozens of copies. With `--once` each run
does one batch and exits — that is the correct mode for cron.

**Why job #1 is critical:** posts, ad launches, campaign pause/resume, conversion
events and reminder emails all flow through one work queue, and only the queue worker
empties it. If it is not scheduled, everything is "scheduled" but nothing happens.

### Verify after 3 minutes

cPanel → File Manager → `public_html/crons/queue_worker.log` should exist and show
lines like:

```
[14:05:02] [Worker] Batch done. Claimed: 2 | Completed: 2 | Failed/backoff: 0
```

If the file never appears: the php path is wrong — test it in Terminal with
`/usr/local/bin/ea-php83/root/usr/bin/php -v`.

---

## Part 5 — Post-install checklist

- [ ] Site loads, register + login work
- [ ] Your account has `role = admin`
- [ ] Cron job #1 scheduled and the log shows activity
- [ ] Bundle.social + Zernio keys pasted (Admin → Settings)
- [ ] A social network connected on the **Social Connect** page (the connection is
      verified with the gateway before it is stored — if the network sign-in was not
      completed, the platform says so instead of pretending it worked)
- [ ] An ad account connected (same page)
- [ ] A test post scheduled for 2 minutes ahead appears on the calendar, gets its
      reminder email, and publishes on time (visible with a real external id)
- [ ] Payment gateway keys pasted and the webhook URLs copied from
      Admin → Transactions into each gateway's dashboard
- [ ] HTTPS active on the domain (required by the OAuth sign-in windows)

## Troubleshooting

| Symptom | Fix |
|---|---|
| Blank page / "Temporary error" | Check the PHP error log (cPanel → Metrics → Errors); usually a missing PHP extension |
| Pages 404 on `/login` style URLs | `.htaccess` missing — re-upload with hidden files shown |
| "Could not verify the connection" when linking a network | The sign-in at the network's site was not completed, or the gateway key is missing — complete the sign-in and try again; the platform never stores unverified connections |
| Posts stay "scheduled" forever | Cron job #1 is not running — see Part 4; the post's failure reason (if any) is shown in the post list |
| Ads stay "pending" forever | Same cron; also check that an ad account is connected on Social Connect |
| Database connection error | Re-check `config/db.local.php` values |
| Emails not arriving | Fill the SMTP settings in Admin → Settings |

---

*© 2026 Adsbooster.net Engineering Team. All rights reserved.*
