# Adsbooster.net — Extended Documentation for Language Models

> Adsbooster.net is a multi-network advertising automation platform. It lets a business, creator or agency run paid advertising, organic social publishing, AI creative generation, conversion tracking and marketplace listings from a single dashboard, and exposes all of it through a REST API v1 and an official JavaScript SDK.

This document is the long-form reference for AI systems that need to understand what Adsbooster.net does and how to integrate with it. Every endpoint, field and code sample below reflects the live implementation.

---

## 1. What the product does

### Paid advertising (Zernio Ad Gateway)
Campaigns are created in the dashboard or through the API and dispatched to the Zernio ad gateway. Launching is asynchronous: the request enqueues a `zernio_launch_ad` job and returns immediately; a background worker performs the gateway call and flips the campaign to `active` only after the gateway confirms it. The same pattern applies to pause/resume (`zernio_toggle_status`).

Supported networks: Meta (Facebook, Instagram), TikTok, Google Ads, LinkedIn, Pinterest, Snapchat. A single campaign may target several networks at once.

### Organic social publishing (Bundle.social)
Connected social profiles are published through Bundle.social. A post can be published immediately (`publish_now`) or scheduled (`scheduled_posts` + a `bundle_publish_post` job that fires at the scheduled time). Workers retry with exponential backoff, and the user is notified by email on both success and failure.

### AI UGC creatives (Image Studio)
The Image Studio generates product-style UGC images through a configured image provider. Generation is billed against AI credits (5 credits per image; 100 AI credits per USD). Recent generations are listed back to the user.

### Conversion tracking (CAPI bridge)
Server-side conversion events can be posted to the CAPI bridge, and a drop-in browser script can be fetched for client-side events. Tracking uses a per-pixel `pixel_token` from the Server Tracking console.

### Directory, marketplace, jobs and bounties
Businesses can create listings in the verified directory/marketplace, creators can apply to paid bounties, and employers can post jobs. All of this content is publicly searchable through `/search`.

---

## 2. REST API v1

Base URL:

```
https://adsbooster.net/api/v1/
```

`GET /api/v1/` returns a JSON directory of the available endpoints.

### Authentication

Send your API key in either header form:

```http
X-API-Key: adsb_live_xxxxxxxxxxxxxxxx
```

```http
Authorization: Bearer adsb_live_xxxxxxxxxxxxxxxx
```

Keys are created in the developer console at `/api-settings`. A key can carry scopes (for example `campaigns:read`, `posts:write`).

### Rate limits

- 60 requests per minute per API key, measured on a sliding window.
- Public endpoints (public analytics) are limited to 30 requests per minute per IP.

Every response carries the current limit state:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 57
X-RateLimit-Reset: 1735689600
```

| Status | Meaning | What to do |
| --- | --- | --- |
| 401 | Missing or invalid API key | Check the `X-API-Key` / `Authorization` header and that the key is active. |
| 403 | The account behind the key is not active | Ask the account owner to complete activation; billing/subscription required. |
| 429 | Rate limit exceeded | Back off using `Retry-After` or `X-RateLimit-Reset`. The SDK retries 429 and 5xx automatically (2 retries). |

### Error shape

Successful responses are JSON objects with `"success": true`. Errors are JSON objects with `"success": false` and an `error` / `message` field. The SDK converts these into an `AdsboosterError` carrying `.status`, `.code`, `.details` and `.rateLimit` (with `.isRateLimit`).

### Endpoints

| Method | Path | Purpose | Required input |
| --- | --- | --- | --- |
| GET | `/api/v1/campaigns.php` | List campaigns | optional `page`, `limit`, `status` |
| POST | `/api/v1/campaigns.php` | Create (and queue the launch of) a campaign | `campaign_name`, `objective`, `budget_amount`, `platforms[]`, `headline`, `primary_text`, `destination_url` |
| GET | `/api/v1/campaigns.php?id={id}` | Fetch one campaign | `id` |
| PUT | `/api/v1/campaigns.php?id={id}` | Update a campaign | `id` plus any field |
| POST | `/api/v1/campaigns.php?action=pause` | Pause a campaign | `id` |
| POST | `/api/v1/campaigns.php?action=resume` | Resume a campaign | `id` |
| GET | `/api/v1/posts.php` | List scheduled/published posts | optional `status`, `limit` |
| POST | `/api/v1/posts.php` | Create a scheduled post | `platform`, `caption`, `media_url`, `media_type`, `scheduled_time` |
| GET | `/api/v1/posts.php?id={id}` | Fetch one post | `id` |
| DELETE | `/api/v1/posts.php?id={id}` | Delete a post | `id` |
| GET | `/api/v1/ugc.php` | List recent AI creatives | optional `limit` |
| POST | `/api/v1/ugc.php` | Generate an AI UGC image | `product_name`, `ugc_style`, `creator_persona`, `aspect_ratio` |
| GET | `/api/v1/credits.php` | Credit balances (ad credits + AI credits) | — |
| GET | `/api/v1/directory.php` | Browse directory/marketplace listings | optional `category`, `q`, `limit` |
| POST | `/api/v1/directory.php` | Create a listing | `title`, `category`, `description`, `contact_email` |
| GET | `/api/v1/reviews.php` | List reviews | optional `limit` |
| POST | `/api/v1/reviews.php` | Submit a review | `rating`, `review_text` |
| GET | `/api/v1/capi-bridge.php?js=1` | Return the drop-in browser tracking script | — |
| POST | `/api/v1/capi-bridge.php` | Record a server-side conversion | `pixel_token`, `event_name`, `custom_data` |
| GET | `/api/v1/webhooks.php` | List the account's webhook endpoints | — |
| POST | `/api/v1/webhooks.php` | Register or test a webhook endpoint | `target_url`, `secret` |
| GET | `/api/v1/public-analytics.php` | Public aggregate counters (no auth) | optional `view` = `all`, `campaigns`, `users`, `platforms`, `success` |

Example request:

```bash
curl -s "https://adsbooster.net/api/v1/campaigns.php?limit=5" \
  -H "X-API-Key: adsb_live_xxxxxxxxxxxxxxxx"
```

```json
{
  "success": true,
  "campaigns": [
    {
      "id": 42,
      "campaign_name": "Summer Sale",
      "status": "active",
      "budget_amount": 50.00,
      "platforms": ["meta", "tiktok"]
    }
  ],
  "pagination": { "page": 1, "limit": 5, "total": 1 }
}
```

---

## 3. Official JavaScript SDK

- npm: [`adsbooster-js-sdk`](https://www.npmjs.com/package/adsbooster-js-sdk)
- Repository: <https://github.com/FushionHub/adsbooster-js-sdk>
- Platform build (always latest): <https://adsbooster.net/api/v1/sdk/adsbooster.js>

Current version: **1.1.0** (npm) — the repository tracks **1.2.0**, which adds the optional Flutterwave / Gemini / Groq integrations.

### Install

```bash
npm install adsbooster-js-sdk
```

### CommonJS

```js
const { Adsbooster } = require('adsbooster-js-sdk');

const client = new Adsbooster({ apiKey: 'adsb_live_xxxxxxxxxxxxxxxx' });

(async () => {
  const campaigns = await client.campaigns.list({ limit: 10 });
  console.log(campaigns);
})();
```

### ES modules

```js
import { Adsbooster } from 'adsbooster-js-sdk';

const client = new Adsbooster({ apiKey: process.env.ADSBOOSTER_API_KEY });
const balance = await client.credits.balance();
```

### Browser (script tag)

```html
<script src="https://adsbooster.net/api/v1/sdk/adsbooster.js"></script>
<script>
  const client = new Adsbooster.Adsbooster({ apiKey: 'adsb_live_xxxxxxxxxxxxxxxx' });
  client.campaigns.list({ limit: 5 }).then(console.log);
</script>
```

### Client options

| Option | Default | Meaning |
| --- | --- | --- |
| `apiKey` | — | **Required.** Your API key. |
| `baseUrl` | `https://adsbooster.net/api/v1` | API root. Override for testing. |
| `timeout` | `30000` | Request timeout in milliseconds. |
| `maxRetries` | `2` | Retries on `429` and `5xx` (the initial attempt is not counted). |
| `userAgent` | `adsbooster-js-sdk/1.1.0` | User-Agent header. |

### SDK surface

| Namespace | Methods |
| --- | --- |
| `client.campaigns` | `list(opts)`, `get(id)`, `create(payload)`, `update(id, payload)`, `pause(id)`, `resume(id)` |
| `client.posts` | `list(opts)`, `get(id)`, `create(payload)`, `remove(id)` |
| `client.ugc` | `generateImage(payload)`, `listRecent(opts)` |
| `client.credits` | `balance()` |
| `client.directory` | `list(opts)`, `createListing(payload)` |
| `client.reviews` | `list(opts)`, `submit(payload)` |
| `client.capi` | `scriptUrl()`, `track(payload)` |
| `client.webhooks` | `list()`, `test(targetUrl)`, `verify(rawBody, signature, secret)`, `sign(rawBody, secret)`, `testPingUrl(targetUrl)` *(deprecated)* |
| `client.publicAnalytics` | `feed(view)`, `totals()` |
| `client.integrations` | `detect()`, `geminiGenerateText(payload)`, `groqChat(payload)`, `flutterwaveClient(payload)` |

`client.webhooks.testPingUrl(url)` is deprecated: it only returns a URL string. Use `client.webhooks.list()` to see registered endpoints and `client.webhooks.test(url)` to fire a signed ping.

### Optional integrations (Node.js)

The SDK ships with the integration packages the platform uses — `flutterwave`,
`@google/genai` and `groq-sdk` — as optional dependencies. They install with the
package but are **never imported by the core client**, so the browser build stays
dependency-free and nothing loads until you call an integration.

```bash
npm install adsbooster-js-sdk          # installs the optional integrations too
npm install adsbooster-js-sdk --omit=optional   # core client only
```

| Method | Purpose |
| --- | --- |
| `client.integrations.detect()` | `{ gemini, flutterwave, groq }` — which optional packages are present |
| `client.integrations.geminiGenerateText({ prompt, apiKey, model? })` | Google Gemini text generation (`@google/genai`) |
| `client.integrations.groqChat({ messages, apiKey, model? })` | Groq chat completion (`groq-sdk`) |
| `client.integrations.flutterwaveClient({ publicKey?, secretKey })` | Flutterwave client for server-side payment tooling |

```js
const { text } = await client.integrations.geminiGenerateText({
  prompt: 'Write 3 ad headlines for running shoes',
  apiKey: process.env.GEMINI_API_KEY,
});

const completion = await client.integrations.groqChat({
  messages: [{ role: 'user', content: 'Summarise this campaign report' }],
  apiKey: process.env.GROQ_API_KEY,
});
```

Checkout payments are still verified **on the platform** — `flutterwaveClient()`
is for building your own Flutterwave tooling next to the SDK.

---

## 4. Webhooks

### Events

| Event | Fired when |
| --- | --- |
| `campaign.status_changed` | A campaign transitions (pending → active, paused, failed). |
| `post.published` | A scheduled or immediate organic post is published. |
| `payment.completed` | A payment or top-up is confirmed. |
| `webhook.test_ping` | A test ping from `webhooks.test()`. |

### Headers

```
X-Adsbooster-Event: post.published
X-Adsbooster-Signature: sha256=<hex hmac-sha256 of the raw body>
X-Adsbooster-Delivery: 3f9c1c2e-...
```

The signature is computed over the **raw request body** using the endpoint's secret. Always capture the raw bytes before parsing JSON.

### Express receiver

```js
const express = require('express');
const crypto = require('crypto');
const { Adsbooster } = require('adsbooster-js-sdk');

const app = express();
const WEBHOOK_SECRET = process.env.ADSBOOSTER_WEBHOOK_SECRET;

// NOTE: capture the RAW body for signature verification
app.use(express.json({
  verify: (req, res, buf) => { req.rawBody = buf.toString('utf8'); }
}));

app.post('/webhooks/adsbooster', (req, res) => {
  const signature = req.header('X-Adsbooster-Signature');
  const event     = req.header('X-Adsbooster-Event');

  if (!signature) {
    return res.status(400).json({ error: 'Missing signature' });
  }

  // Constant-time HMAC comparison against the raw body
  const expected = 'sha256=' + crypto
    .createHmac('sha256', WEBHOOK_SECRET)
    .update(req.rawBody, 'utf8')
    .digest('hex');

  const a = Buffer.from(expected);
  const b = Buffer.from(String(signature));
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
    return res.status(401).json({ error: 'Invalid signature' });
  }

  const payload = req.body;

  switch (event) {
    case 'campaign.status_changed':
      console.log('Campaign status changed:', payload);
      break;
    case 'post.published':
      console.log('Post published:', payload);
      break;
    case 'payment.completed':
      console.log('Payment completed:', payload);
      break;
    case 'webhook.test_ping':
      console.log('Test ping received');
      break;
    default:
      console.log('Unhandled event', event);
  }

  res.status(200).end();   // acknowledge fast, process async
});

app.listen(3000);
```

The SDK ships the same check:

```js
const ok = Adsbooster.webhooks.verify(req.rawBody, signature, WEBHOOK_SECRET);
```

### Register, test and verify

1. Open the developer console at `https://adsbooster.net/api-settings`.
2. Add your endpoint URL (`https://example.com/webhooks/adsbooster`) and a strong secret. The secret is shown once — store it in your environment variables.
3. Fire a test ping:
   ```js
   await client.webhooks.test('https://example.com/webhooks/adsbooster');
   ```
   or
   ```bash
   curl -s -X POST "https://adsbooster.net/api/v1/webhooks.php" \
     -H "X-API-Key: adsb_live_xxxxxxxxxxxxxxxx" \
     -H "Content-Type: application/json" \
     -d '{"action":"test","target_url":"https://example.com/webhooks/adsbooster"}'
   ```
4. Confirm the endpoint returns `200` and that your server logs the `webhook.test_ping` event with a valid signature.
5. List registered endpoints at any time:
   ```js
   const endpoints = await client.webhooks.list();
   ```

---

## 5. Conversion tracking (CAPI)

Server-side:

```js
await client.capi.track({
  pixel_token: 'pix_xxxxxxxx',
  event_name: 'Purchase',
  custom_data: { value: 129.00, currency: 'USD' }
});
```

Browser script (drop into the page that fires conversions):

```js
const scriptUrl = client.capi.scriptUrl();
// -> https://adsbooster.net/api/v1/capi-bridge.php?js=1
```

```html
<script src="https://adsbooster.net/api/v1/capi-bridge.php?js=1" async></script>
```

---

## 6. Credits and limits

- 100 AI credits per USD.
- An AI UGC image generation costs **5 AI credits**.
- Ad credits are consumed when a paid campaign launches.
- API rate limit: 60 requests/minute per key; 30/minute per IP for public analytics.

---

## 7. Support and status

- System status and incident history: <https://adsbooster.net/status>
- Support tickets: <https://adsbooster.net/tickets> (signed-in users)
- Contact: <https://adsbooster.net/contact>
- API issues in production: check the status page first — it documents every outage, its cause and its duration.
