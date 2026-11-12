# Website Screenshots

Captured from the running application at `http://localhost/seopp` (1440 × 900, full page).

| Group | Pages |
|---|---|
| **Public** | `homepage` · `login` · `register` · `pricing` · `jobs` · `reviews` |
| **Member** | `dashboard` · `referrals` · `ugc-generator` · `ugc-video-studio` · `post-creator` · `create-ad` · `social-connect` · `calendar` · `ab-test` · `audience-builder` · `ecommerce-sync` · `security-2fa` · `api-settings` · `profile` · `billing` · `portfolio` · `portfolio-builder` · `checkout` · `checkout-payment-error` · `developer-docs` · `status` · `ad-spy` · `marketplace` |
| **Admin** | `admin-dashboard` · `admin-transactions` · `admin-referrals` · `admin-users` · `admin-pricing` · `admin-settings` · `admin-payouts` · `admin-analytics` · `admin-system-health` · `admin-cms` · `admin-listings` · `admin-jobs` · `admin-reviews` · `admin-campaigns` · `admin-tickets` · `admin-agency-approvals` · `admin-ugc-studio` · `admin-social-media` · `admin-server-tracking` · `admin-seo` · `admin-policy-rules` · `admin-translations` · `admin-fraud-watch` |

## The screenshots that matter for payments & referrals

| Page | What it shows |
|---|---|
| [`checkout-payment-error.jpg`](checkout-payment-error.jpg) | A failed verification redirected to the checkout page: the **Transaction Under Review** card (transaction id, gateway, reference, amount), the error banner and the pointer to `update-any-error.sql`, with the **Order Summary** still intact. |
| [`admin-transactions.jpg`](admin-transactions.jpg) | The **Gateway Webhook URLs** panel with a copy button per gateway, plus the transaction list with **Verify** / **Mark failed** / **Open on checkout** actions. |
| [`admin-referrals.jpg`](admin-referrals.jpg) | Referral management: the stats cards, the programme settings form, the status filter, **Pay all pending**, and the per-referral **pay / reset / reject** actions. |
| [`referrals.jpg`](referrals.jpg) | The member-facing referral dashboard: the unique affiliate link, the earnings cards and the referred-partner table. |

## Regenerating them

```bash
# 1. Make sure Apache + MySQL are running and the app is installed.
# 2. Sign in as a member and as an admin in a headless browser, then capture
#    each route with fullPage screenshots.
```

The capture script uses Playwright with the locally installed Chrome, dismisses the
cookie-consent banner and the first-run onboarding modal, and skips any route that
redirects to the login page while capturing it authenticated instead.
