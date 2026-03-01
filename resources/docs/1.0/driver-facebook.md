# Facebook Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [Message Format](#message-format)
- [Retry Logic](#retry-logic)
- [Setup Guide](#setup-guide)

<a name="overview"></a>
## Overview

The Facebook driver publishes articles to a Facebook Page via the Graph API. It posts to the page's feed with the article title, category hashtag, and an optional link.

<a name="configuration"></a>
## Configuration

```dotenv
FACEBOOK_PAGE_ID=your-page-id
FACEBOOK_PAGE_ACCESS_TOKEN=your-long-lived-token
FACEBOOK_API_VERSION=v21.0
FACEBOOK_TIMEOUT=25
FACEBOOK_RETRIES=2
```

Config in `config/scribe-ai.php`:

```php
'drivers' => [
    'facebook' => [
        'driver'       => 'facebook',
        'page_id'      => env('FACEBOOK_PAGE_ID'),
        'access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN'),
        'api_version'  => env('FACEBOOK_API_VERSION', 'v21.0'),
        'timeout'      => (int) env('FACEBOOK_TIMEOUT', 25),
        'retries'      => (int) env('FACEBOOK_RETRIES', 2),
    ],
],
```

**Activate the channel:**

```dotenv
PUBLISHER_CHANNELS=facebook
```

<a name="message-format"></a>
## Message Format

Posts include:

- **Category hashtag** — e.g., `#Technology`
- **Article title**
- **Custom message** (if passed via options)

The article URL is sent as the `link` parameter for rich link previews.

<a name="retry-logic"></a>
## Retry Logic

The Facebook driver has built-in retry logic for transient failures:

- **Max retries**: configurable (default: 2)
- **Delay between retries**: 1 second
- **On final failure**: returns a `PublishResult::failure()` with the last error

<a name="setup-guide"></a>
## Setup Guide

1. **Create a Facebook App** at [developers.facebook.com](https://developers.facebook.com)
2. **Add the Pages API** product to your app
3. **Generate a Page Access Token**:
   - Use the Graph API Explorer
   - Select your page and request `pages_manage_posts` permission
   - Generate a **long-lived** token (short-lived tokens expire in hours)
4. **Get your Page ID** from the Page Settings or Graph API

```dotenv
FACEBOOK_PAGE_ID=123456789
FACEBOOK_PAGE_ACCESS_TOKEN=EAAxxxxxxxxxxxxxxx
```

> {warning} Use a **long-lived Page Access Token**. Short-lived tokens expire and will cause publish failures.
