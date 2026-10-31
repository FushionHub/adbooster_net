# MASTER PROMPT — Adsbooster.net Platform Build

Use this as a single source-of-truth brief. Feed it to an AI coding assistant or hand it to a dev team as-is. Every requirement below is mandatory unless explicitly marked optional. Do not drop, simplify, or "summarize away" any feature — if something can't be finished in one pass, stub it clearly with a `// TODO` and keep the database/route/UI scaffolding in place so nothing is silently lost.

---

## 1. Project Identity

- **Website name:** Adsbooster.net
- **One-line pitch:** A multi-network advertising and social-media automation platform (Meta, Instagram, TikTok, Google Ads, Snapchat, plus an "All-in-One Combo Pack") with AI content generation, scheduled/automated posting, a marketplace, connection/dating search, team workspaces, billing, and a full admin back-office — built on the visual foundation of the "Agency" HTML/Bootstrap template, converted to database-driven PHP.
- **Core workflow (non-negotiable):**
  1. User creates a Meta / Instagram / TikTok / Google / Snapchat ad, or the Combo Pack (all networks at once).
  2. User uploads creatives, sets targeting, sets budget, confirms ad-policy compliance.
  3. User pays (Paystack or Flutterwave, selectable at checkout).
  4. User monitors results.
  5. User pauses/resumes campaigns.
  6. In parallel, users can create and auto-schedule organic social posts (manual or AI-generated text/images/UGC-style videos/reels), publish automatically to connected socials, and sync with Google Calendar.

---

## 2. Hard Technical Constraints

- **Language/runtime:** Vanilla PHP only. **No PHP framework** (no Laravel, Symfony, CodeIgniter, Slim, etc.). PDO for all DB access, prepared statements everywhere, `declare(strict_types=1)` at the top of every file.
- **Database:** MySQL (latin1), InnoDB engine, full relational schema with foreign keys.
- **Frontend base:** Reuse the original **Agency HTML template** assets untouched in look/feel:
  - HTML5 + Bootstrap 5
  - SASS-compiled `main.css` (keep the compiled CSS; do not require a SASS build step at runtime)
  - jQuery 3.7.1 + GSAP (ScrollTrigger, ScrollSmoother, SplitText)
  - Swiper, AOS, Magnific Popup, Phosphor Icons
  - Dark theme, green accent, custom cursor, preloader, mega menu, parallax — **all of this must be visually preserved exactly**, only the content becomes dynamic.
- **No external cookie-consent library.** Build a custom Cookiebot-look-alike consent manager from scratch (see §9).
- **Payments:** Paystack and Flutterwave only. User picks either at checkout.
- **Email-first:** Every meaningful event in the system (signup, login alert, payment, invoice, campaign status change, ticket update, team invite, password reset, etc.) triggers a branded HTML email.
- **No secrets hardcoded outside config.** All API keys, gateway keys, OAuth credentials, and editable site content live in a `site_settings` table, editable from the admin panel.

---

## 3. Source Template — File Inventory (must be preserved/converted)

Original static structure (`seopp/`):

```
404.html, about.html, blog-details.html, blog.html, contact.html, faq.html,
index.html, pricing-plan.html, project-details.html, projects.html,
service-details.html, services.html, team-details.html, team.html
assets/css/*, assets/js/*, assets/images/{bg,icons,logo,shapes,thumbs}/*,
assets/sass/* (abstracts, components, layout, partials incl. homeOne..homeFour/New/NewTwo, utilities)
```

Every one of these 14 pages must be converted 1:1 to `.php`, keeping identical markup/classes/animations, but with all copy, images, logos, and repeatable sections pulled from MySQL via a `get_content()` / `get_setting()` helper layer instead of being hardcoded.

The six homepage variants (Marketing, Branding, SEO, Social Media, Advertising, IT Solution) should be selectable/manageable from the admin (which variant is "active" as `index.php`, or exposed as alternate routes) — driven by `site_settings`.

---

## 4. Target File/Folder Structure (PHP)

```
seopp/
├── assets/                                 (untouched static assets: css/, js/, images/, sass/)
├── config/
│   ├── db.php                              PDO Singleton connection
│   └── site_setting_and_config.php         Dynamic BASE_URL (from Host header), settings + content loader
├── includes/
│   ├── header.php                          Unified header (preloader, meta, mega-menu, cursor)
│   ├── footer.php                          Unified footer (scripts in original order, cookie consent mount)
│   ├── auth_guard.php                      Session/role gate (user vs admin)
│   ├── tracker.php                         Auto IP/OS/Browser/device logger
│   ├── cookie_consent.php                  Custom Cookiebot-style banner markup + JS
│   └── cookie_consent_handler.php          AJAX endpoint that persists consent to DB + cookie
├── modules/
│   ├── ai_engine.php                       Text/image/video prompt dispatcher + credit metering
│   ├── social_post_engine.php              Manual + scheduled multi-network poster
│   ├── google_auth.php                     Google OAuth 2.0 (login + calendar scope)
│   ├── google_calendar.php                 Calendar event creation/sync
│   ├── payments.php                        Paystack + Flutterwave unified processor
│   ├── email_service.php                   Branded HTML transactional + marketing email sender
│   ├── invoice_generator.php               HTML/PDF invoice builder
│   └── ticket_system.php                   Support/bug/billing tickets with uploads
├── uploads/
│   ├── creatives/
│   ├── tickets/
│   └── invoices/
├── index.php, about.php, services.php, service-details.php, projects.php,
│   project-details.php, pricing-plan.php, blog.php, blog-details.php,
│   team.php, team-details.php, contact.php, faq.php, 404.php
├── login.php                               Standard + Google login
├── register.php                            Registration (+ team invite acceptance)
├── onboarding.php                          Post-registration setup, skippable
├── dashboard.php                           User home: wallet, credits, connected socials, overview
├── create-ad.php                           Multi-network / combo-pack campaign launcher
├── post-creator.php                        AI + manual post studio, scheduler, timeline
├── marketplace.php                         Products / Services / Companies directory + inline ads
├── dating-search.php                       Connection/matchmaking search
├── team-manage.php                         Invite/manage team members
├── tickets.php                             User support/bug reporting
├── p2p-chat.php                            User <-> admin direct messaging
├── checkout.php / payment-verify.php       Plan/credit purchase + gateway callback
├── admin-login.php                         Isolated admin authentication
└── admin/
    ├── index.php        Metrics, audits, platform logs
    ├── cms.php           Edit all public text/images/logos/header/footer content
    ├── campaigns.php     Approve/pause/resume/manage all ad campaigns
    ├── users.php         Manage users, top-up credits, upgrade plans, suspend
    ├── pricing.php       Manage monthly/yearly/pay-as-you-go tiers
    ├── transactions.php  Transaction log, CSV/PDF export
    ├── p2p-chat.php      Admin side of direct messaging
    └── tickets.php       Manage support/bug tickets + attachments
```

**Rule:** every feature gets its own file under `modules/` or its own top-level page — never merge unrelated features into one file. This is required "for easy correction of errors" per spec.

---

## 5. Database Schema — Required Tables

Implement (or extend) all of the following. Use `InnoDB`, `latin1_swedish_ci`, and FK constraints with sensible `ON DELETE` behavior.

**Core / platform**
- `site_settings` (key/value + group, admin-editable)
- `dynamic_content` (page_slug, section_key, item_key, content_value, media_url — powers every static string/image on every page)
- `users` (role: user/team_member/admin; parent_user_id for team hierarchy; google_id; ad_credits; ai_credits; plan_id/billing/expiry; onboarding_completed; status)
- `visitor_logs` (ip, user_agent, browser, os, device_type, request_uri, country, timestamp)
- `cookie_consents` (ip, consent_uuid, necessary/preferences/analytics/marketing flags)

**Social & AI**
- `connected_accounts` (platform enum incl. meta/instagram/tiktok/google_ads/snapchat/google_calendar, tokens, expiry)
- `scheduled_posts` (platform, post_type manual/ai_generated, caption, media_url, media_type image/ugc_video/reel, scheduled_time, google_calendar_event_id, status)
- `ai_creatives` (type text/image/video, prompt, result_output, timestamps)
- `calendar_sync_events`

**Advertising**
- `ad_campaigns` (campaign_type incl. combo_pack, objective, budget_type/amount, targeting_spec JSON, headline, primary_text, destination_url, creative_media_url, status pending/active/paused/completed/rejected)

**Commerce**
- `pricing_plans` (name, price, billing_cycle, features_json, is_featured, status)
- `transactions` (gateway paystack/flutterwave, type plan_subscription/ad_credit_topup/ai_credit_topup, amount, currency, status, invoice_number, invoice_path)

**Support**
- `support_tickets` (ticket_type support/bug_report/billing, priority, status)
- `ticket_messages` (ticket_id, sender_id, message, attachment_path)
- `p2p_messages` (sender_id, receiver_id, message, is_read)

**Marketplace / Directory / Connections**
- `marketplace_items` (item_type product/service/company, title, slug, category, price, featured_image, description, contact_email, website_url, status, views_count)
- `dating_profiles` (gender, looking_for, age, city, country, bio, interests JSON, photo_url, is_visible)

**Team**
- `team_invitations` (owner_id, email, role admin/editor/viewer, invite_token, status)

Seed `site_settings` with: site_name, site_logo, site_email, site_phone, currency_symbol, paystack keys, flutterwave keys, google client id/secret.

---

## 6. Page-by-Page & Module Requirements

### 6.1 Public marketing pages (converted from the 14 HTML files)
- Strip original `<head>...<div id="smooth-wrapper">` boilerplate → replace with `require includes/header.php` (set `$pageTitle` first).
- Strip closing scripts/footer → replace with `require includes/footer.php`.
- Replace every hardcoded string/image with `get_content('page','section','item','fallback')` and every asset path with `BASE_URL . '/assets/...'`.
- Pricing page pulls live plans from `pricing_plans`, each with a gateway-select dropdown (Paystack/Flutterwave) routed through `checkout.php`.
- Blog/blog-details pulls posts from a `blog_posts` table (title, slug, excerpt, body, featured_image, author, published_at, status) — admin-manageable.
- 404.php stays static-styled but still wrapped by header/footer includes.

### 6.2 Auth & onboarding
- `register.php` / `login.php`: separate flows from `admin-login.php` (admins never log in through the user form and vice versa).
- Google Sign-In (`modules/google_auth.php`) on both login and register.
- After registration → redirect to `onboarding.php`. Onboarding must have a working **Skip** action and a **Complete Setup** action; both must ultimately set `onboarding_completed = 1` and land on `dashboard.php`.
- Registration supports `?invite=token` to auto-attach a new user to a team via `team_invitations`.

### 6.3 Dashboard
- Shows wallet balances (`ad_credits`, `ai_credits`), current plan + renewal date, connected social accounts with connect/disconnect actions, quick links to create-ad, post-creator, marketplace, tickets, team, billing.
- Users can **upgrade/renew/downgrade** plans and choose monthly / yearly / pay-as-you-go billing.
- Users can **top up** ad credits and AI credits independently.

### 6.4 Ad campaign engine (`create-ad.php`)
- Platform choices: Meta, Instagram, TikTok, Google Ads, Snapchat, **Combo Pack** (all at once).
- Fields: campaign name, objective, budget (daily/lifetime), targeting (age, geo, interests as JSON), headline, primary text, destination URL, creative upload (image or video).
- Mandatory ad-policy compliance checkbox before submission — copy should state the campaign matches Meta/TikTok/Google/Snapchat policy.
- On submit: validate credit balance ≥ budget, deduct `ad_credits`, insert into `ad_campaigns` as `active`, store creative in `uploads/creatives/`.
- User can view all their campaigns, **pause/resume**, and monitor basic performance metrics (impressions/clicks/spend — can be simulated fields until real ad-network APIs are wired in, but the schema/UI must exist).

### 6.5 AI + social post studio (`post-creator.php`)
- AI panel: prompts `modules/ai_engine.php` for ad/post copy, headline, and a UGC-style video/reel "hook" script; deducts 1 AI credit per generation; logs to `ai_creatives`; result is editable before scheduling (view/reprompt/edit).
- AI must also cover **image generation** and **video generation** (UGC/marketing/reels/motivational) as first-class generation types in `ai_creatives.type` (`text`/`image`/`video`) — wire the dispatcher so new provider integrations can be dropped in without changing calling code.
- Manual composer: caption, media upload, platform selector, media type (image/UGC video/reel/text-only), scheduled datetime.
- Scheduling writes to `scheduled_posts`; a background dispatcher (cron-triggered PHP script, e.g. `cron/publish_scheduled_posts.php`) publishes due posts automatically to the connected social account's API and updates `status` to `published`/`failed`.
- Users can **view, edit, and cancel/reschedule** any post still in `scheduled` state.
- Optional Google Calendar sync per post (writes to `calendar_sync_events`) via `modules/google_calendar.php`.

### 6.6 Social connections
- `connected_accounts` CRUD flows per platform (Meta/Instagram/TikTok/Google Ads/Snapchat/Google Calendar) — OAuth-style connect buttons on the dashboard, disconnect action, token refresh handling for platforms that support it.

### 6.7 Marketplace (`marketplace.php`)
- Filterable directory: Products / Services / Companies.
- Every 3rd listing slot shows a live **sponsored ad** pulled at random from `ad_campaigns` where `status = 'active'` — this is the "ads shown on product/services pages" requirement.
- Listings link to contact (mailto) or a detail page; admin can moderate (`status: active/inactive/pending`).

### 6.8 Dating / Connection search (`dating-search.php`)
- Filter by gender/looking-for and city.
- Profile cards with photo, age, city/country, short bio, "Connect Now" → routes into `p2p-chat.php` with the target user as recipient.

### 6.9 Team management (`team-manage.php`)
- Invite by email + role (admin/editor/viewer editor viewer), sends branded invite email with tokenized link.
- Lists current team members (`users.parent_user_id = owner`) with role and join date.
- Invited users register under the inviter's account hierarchy.

### 6.10 Billing & invoicing
- `modules/payments.php`: `PaymentProcessor::initiatePayment()` creates a `transactions` row (pending) + generates an invoice number, then redirects to the chosen gateway's hosted checkout (Paystack `transaction/initialize`, Flutterwave `v3/payments`).
- `payment-verify.php` handles both gateway callbacks, calls `completeTransaction()` which marks the transaction successful, credits the wallet or applies the plan, and emails the invoice.
- `modules/invoice_generator.php` produces a PDF/HTML invoice saved to `uploads/invoices/` and attached/linked in the confirmation email.
- Users can view/download all past invoices from their dashboard/billing page.

### 6.11 Support system
- `tickets.php`: category (bug_report/support/billing), priority, subject, message, optional file upload → creates `support_tickets` + first `ticket_messages` row.
- Separate **"Report a bug"** entry point (can be the same form pre-set to `bug_report`) accessible from the footer, with file upload.
- Ticket thread view for users to reply and see admin responses.

### 6.12 P2P chat (`p2p-chat.php`)
- Simple polling or AJAX-refresh thread between a user and any recipient (default: admin account id 1), persisted in `p2p_messages`.

---

## 7. Admin Panel (`admin/*`) — must cover everything

Isolated auth via `admin-login.php` + `role = 'admin'` guard (never share session logic with the public login).

- **Dashboard (`admin/index.php`):** platform-wide metrics, audit trail, recent activity/logs.
- **CMS (`admin/cms.php`):** edit every piece of `dynamic_content` — text, images, logos, header/footer config — without touching code.
- **Campaigns (`admin/campaigns.php`):** view/approve/reject/pause/resume any user's ad campaign.
- **Users (`admin/users.php`):** search/manage users, top up ad/AI credits, upgrade/downgrade plans, suspend/reactivate accounts.
- **Pricing (`admin/pricing.php`):** CRUD monthly/yearly/pay-as-you-go plans and feature lists.
- **Transactions (`admin/transactions.php`):** full ledger with status filter, **CSV export** and **PDF/print export** (already implemented via `fputcsv` streaming + print-friendly view — keep this pattern).
- **P2P chat (`admin/p2p-chat.php`):** admin side of direct messaging with any user.
- **Tickets (`admin/tickets.php`):** manage support/bug/billing tickets, reply, attach files, change status/priority.
- **Email marketing:** admin can compose and send modern branded HTML campaigns to segments of users (new table `email_campaigns` + `email_campaign_recipients` if not already present).
- **Site settings:** manage all `site_settings` keys (payment gateway keys, OAuth credentials, contact info, branding).

---

## 8. Automatic Visitor/Device Telemetry

`includes/tracker.php`, invoked on every page load:
- Resolve IP through `HTTP_CLIENT_IP` → `HTTP_X_FORWARDED_FOR` → `REMOTE_ADDR` fallback chain.
- Parse User-Agent into OS (Windows 10/11, Windows 8.x, macOS, Linux/Ubuntu, iOS, Android, etc.) and Browser (Edge, Chrome, Firefox, Safari, Opera) via regex tables.
- Classify device type (Mobile/Desktop).
- Insert into `visitor_logs` with the linked `user_id` if authenticated, silently failing (never blocking page render) on DB error.

---

## 9. Cookie Consent (custom Cookiebot look-alike — no third-party library)

Must include, exactly:
- A bottom banner matching the dark/green theme.
- Buttons: **Reject**, **Customise** (expands a panel with Necessary [locked on], Preferences, Analytics, Marketing toggles), **Accept All**.
- Persists choice both client-side (cookie, 1-year expiry) and server-side (`cookie_consents` table) via `includes/cookie_consent_handler.php`.
- Must be usable/compliant for GDPR (EU), and workable for African, Asian, and American visitors, plus Google AdSense consent requirements — i.e., no analytics/marketing scripts fire until consent is granted for those categories; necessary cookies are always allowed.
- Google Analytics must respect the "analytics" toggle (only initialize GA after consent, or use consent-mode defaults that start denied and update on choice).

---

## 10. Dynamic Base URL / Multi-host Support

`config/site_setting_and_config.php` must derive `BASE_URL` purely from the incoming request (`$_SERVER['HTTP_HOST']`, protocol/HTTPS detection, script directory), never a hardcoded domain — so the same codebase works on any host/subdomain/IP without config edits. Also starts the session and exposes `get_setting()` and `get_content()` helpers backed by in-request static caches (single query per page load, not per call).

---

## 11. Email System

`modules/email_service.php`:
- One branded HTML template (dark background, green accent header/CTA) reused for every transactional email: welcome, verification, password reset, payment/invoice receipt, campaign status change, ticket updates, team invitations.
- Support at least one alternate style preset selectable by admin (modern/classic/professional/flashy) or "prompt AI to design it" — store the chosen/generated template in `site_settings` or a dedicated `email_templates` table.
- Marketing/broadcast sending capability for admin-composed campaigns (§7).
- Swap `mail()` for SMTP (PHPMailer or raw SMTP sockets) in production — note this as the recommended upgrade path, but the interface (`EmailService::sendBrandedMail()`) should not need to change if swapped later.

---

## 12. Security & Data Handling Baseline

- All DB access via PDO prepared statements (no raw string concatenation of user input into SQL).
- `password_hash()`/`password_verify()` for local credentials; `password_hash` column nullable for OAuth-only accounts.
- CSRF tokens on all state-changing forms.
- File uploads: validate MIME/extension, randomize stored filenames, store outside web-executable assumptions where possible, cap size, and scan/limit types per context (images/video for creatives, images/pdf/logs for tickets).
- Escape all output with `htmlspecialchars()`.
- Admin routes fully separated from user routes at both the auth and session-role level.
- Rate-limit login/registration attempts (basic table or file-based throttle) to reduce brute force risk.

---

## 13. Deployment / Migration Checklist

1. Copy `assets/` from the original template unmodified.
2. Rename each `*.html` to `*.php`; wrap with `includes/header.php` / `includes/footer.php`; replace hardcoded copy/images with `get_content()` / `BASE_URL`.
3. Run `adsbooster_master.sql` then `adsbooster_extensions.sql` (or the consolidated schema in §5) against MySQL.
4. Populate `site_settings` with real Paystack, Flutterwave, and Google OAuth credentials (test keys first).
5. Create writable upload directories:
   ```
   mkdir -p uploads/creatives uploads/tickets uploads/invoices
   chmod -R 755 uploads
   ```
6. Set up a cron job to run `cron/publish_scheduled_posts.php` every minute for automated posting.
7. Verify the cookie consent banner blocks analytics/marketing scripts pre-consent, and that GA (if enabled) respects the toggle.
8. Smoke-test: register → onboarding (skip + complete paths) → connect a social account → create a post (manual + AI) → schedule it → create an ad campaign (single + combo pack) → checkout with both gateways in test mode → verify invoice email → open a support ticket with an attachment → admin login → CSV/PDF export of transactions → admin CMS edit reflecting on the live page.

---

## 14. Non-Negotiable "Don't Drop This" Checklist

Use this as a final QA pass — every line must be implemented somewhere in the codebase:

- [ ] All 20 template pages/variants preserved visually, converted to PHP, content DB-driven
- [ ] Separate login/registration for users vs admin
- [ ] Google login (OAuth) on user auth
- [ ] Onboarding page with Skip
- [ ] Team invite/connect/manage
- [ ] Paystack **and** Flutterwave, selectable at checkout
- [ ] Email-first architecture (every event → email)
- [ ] Invoice generation + plan management (upgrade/renew/monthly/yearly/pay-as-you-go)
- [ ] Ad credit + AI credit top-up, separate balances
- [ ] Admin: audit, export CSV/PDF, manage campaigns/users/credits/plans/transactions, P2P chat, mass email, tickets w/ uploads
- [ ] Bug report feature with file upload
- [ ] Automatic IP/browser/OS/device logging to DB
- [ ] Dynamic BASE_URL derived from Host header (no hardcoded domain)
- [ ] Custom Cookiebot-style consent (Reject / Customise / Accept All), GDPR + AdSense + Africa/Asia/Americas ready, GA-consent-aware
- [ ] Email marketing with modern/branded HTML designs, admin-editable or AI-generated style
- [ ] AI generation + view/reprompt/edit for text, images, and video (UGC/marketing/reels/motivational)
- [ ] View/edit/manage scheduled posts and connected socials
- [ ] Unified header/footer config (single include, original nav/footer retained)
- [ ] Each feature isolated in its own file/module for easy debugging
- [ ] Marketplace: products/services/companies + inline sponsored ads
- [ ] Dating/connection search
- [ ] Adsbooster.net ad engine: Meta/Instagram/TikTok/Google/Snapchat + Combo Pack, upload creatives, targeting, budget, pay, monitor, pause/resume
- [ ] All original GSAP/AOS/Swiper/cursor/preloader/mega-menu animation and dark-green styling untouched
