# Blogger Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Labels](#labels)
- [Authentication](#authentication)
- [Setup Guide](#setup-guide)

<a name="overview"></a>
## Overview

The Blogger driver publishes articles to a Google Blogger blog via the Blogger API v3. Articles are created as full blog posts with HTML content, labels (categories + tags), and optional draft mode.

<a name="configuration"></a>
## Configuration

```dotenv
BLOGGER_BLOG_ID=1234567890
BLOGGER_API_KEY=your-api-key
GOOGLE_APPLICATION_CREDENTIALS=/path/to/credentials.json
```

Config in `config/scribe-ai.php`:

```php
'drivers' => [
    'blogger' => [
        'driver'           => 'blogger',
        'blog_id'          => env('BLOGGER_BLOG_ID'),
        'api_key'          => env('BLOGGER_API_KEY'),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
    ],
],
```

**Activate the channel:**

```dotenv
PUBLISHER_CHANNELS=blogger
```

<a name="how-it-works"></a>
## How It Works

The driver sends a `POST` to `https://www.googleapis.com/blogger/v3/blogs/{blog_id}/posts/` with:

```json
{
    "kind": "blogger#post",
    "title": "Article Title",
    "content": "<p>Full HTML content...</p>",
    "labels": ["Technology", "AI", "Productivity"]
}
```

**Options:**

- `title` — override the article title
- `content` — override the article content
- `draft` — set to `true` to create as draft instead of publishing

```php
$driver->publish($article, ['draft' => true]);
```

<a name="labels"></a>
## Labels

Labels are automatically built from:

1. **Article category** — the category name (e.g., "Technology")
2. **Article tags** — all associated tag names

These map directly to Blogger's label system for post categorisation.

<a name="authentication"></a>
## Authentication

The driver supports Google Service Account authentication:

1. Creates a JWT from the service account credentials
2. Exchanges it for an OAuth2 access token
3. Uses the token in the `Authorization: Bearer` header

The access token is cached for the duration of the request.

<a name="setup-guide"></a>
## Setup Guide

1. **Enable the Blogger API** in [Google Cloud Console](https://console.cloud.google.com)
2. **Create a Service Account** with Blogger API access
3. **Download the JSON key file** and save it securely
4. **Get your Blog ID** from the Blogger dashboard URL

```dotenv
BLOGGER_BLOG_ID=1234567890123456789
GOOGLE_APPLICATION_CREDENTIALS=/secrets/google-service-account.json
```

> {primary} The `supports()` check requires both `isPublished()` and non-empty `content` on the article.
