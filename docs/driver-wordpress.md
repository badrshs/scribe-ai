# WordPress Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Categories & Tags](#categories-tags)
- [Featured Images](#featured-images)
- [Authentication](#authentication)
- [Setup Guide](#setup-guide)

<a name="overview"></a>
## Overview

The WordPress driver publishes articles via the WordPress REST API (v2). It creates full blog posts with categories, tags, featured images, excerpts, and custom slugs. Categories and tags are automatically created in WordPress if they don't exist.

<a name="configuration"></a>
## Configuration

```dotenv
WORDPRESS_URL=https://your-site.com
WORDPRESS_USERNAME=admin
WORDPRESS_PASSWORD=xxxx-xxxx-xxxx-xxxx  # Application Password
WORDPRESS_DEFAULT_STATUS=publish
WORDPRESS_TIMEOUT=30
```

Config in `config/scribe-ai.php`:

```php
'drivers' => [
    'wordpress' => [
        'driver'         => 'wordpress',
        'url'            => env('WORDPRESS_URL'),
        'username'       => env('WORDPRESS_USERNAME'),
        'password'       => env('WORDPRESS_PASSWORD'),
        'default_status' => env('WORDPRESS_DEFAULT_STATUS', 'publish'),
        'timeout'        => (int) env('WORDPRESS_TIMEOUT', 30),
    ],
],
```

**Activate the channel:**

```dotenv
PUBLISHER_CHANNELS=wordpress
```

<a name="how-it-works"></a>
## How It Works

The driver sends a `POST` to `{url}/wp-json/wp/v2/posts` with:

```json
{
    "title": "Article Title",
    "content": "<p>Full HTML content...</p>",
    "status": "publish",
    "slug": "article-title",
    "excerpt": "Short description...",
    "categories": [5],
    "tags": [12, 15],
    "featured_media": 42
}
```

**Options:**

- `title`, `content` — override article fields
- `status` — `publish`, `draft`, or `pending`
- `category_ids` — override auto-resolved categories
- `tag_ids` — override auto-resolved tags

<a name="categories-tags"></a>
## Categories & Tags

The driver automatically maps Scribe AI categories and tags to WordPress taxonomy terms:

1. **Search** for existing terms matching the name
2. **Create** the term if it doesn't exist
3. **Pass IDs** to the post creation endpoint

This is fully automatic — no manual mapping required.

```php
// The driver does this automatically:
$categoryId = $this->findOrCreateTerm('categories', 'Technology');
$tagId = $this->findOrCreateTerm('tags', 'AI');
```

<a name="featured-images"></a>
## Featured Images

If the article has a `featured_image`, the driver:

1. Reads the image file from the configured storage disk
2. Uploads it to WordPress's media library via `POST /wp-json/wp/v2/media`
3. Sets the returned media ID as `featured_media` on the post

The upload preserves the original filename and MIME type.

<a name="authentication"></a>
## Authentication

The driver uses **HTTP Basic Authentication** with WordPress Application Passwords:

```
Authorization: Basic base64(username:application_password)
```

Application Passwords are a native WordPress feature (since 5.6) that provides secure API access without exposing your real password.

<a name="setup-guide"></a>
## Setup Guide

1. **Enable REST API** — most WordPress installations have it enabled by default
2. **Create an Application Password**:
   - Go to **Users → Profile → Application Passwords**
   - Enter a name (e.g., "Scribe AI")
   - Click "Add New Application Password"
   - Copy the generated password (shown once)
3. **Configure the driver:**

```dotenv
WORDPRESS_URL=https://myblog.com
WORDPRESS_USERNAME=admin
WORDPRESS_PASSWORD=abcd-1234-efgh-5678
```

> {warning} The `supports()` check requires both `isPublished()` and non-empty `content` on the article.
