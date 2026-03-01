# Content Payload

---

- [Overview](#overview)
- [Properties](#properties)
- [Creating Payloads](#creating-payloads)
- [Mutating the Payload](#mutating-the-payload)
- [Snapshots](#snapshots)

<a name="overview"></a>
## Overview

`ContentPayload` is the immutable DTO that carries state through every pipeline stage. All properties are `readonly`. To change values, use `with()` which returns a new instance.

<a name="properties"></a>
## Properties

| Property | Type | Description |
|----------|------|-------------|
| `sourceUrl` | `?string` | The original URL being processed |
| `sourceDriver` | `?string` | Forced content-source driver name (null for auto-detect) |
| `rawContent` | `?string` | Raw content from the source |
| `cleanedContent` | `?string` | Cleaned/processed content |
| `title` | `?string` | Article title |
| `slug` | `?string` | URL-friendly slug |
| `content` | `?string` | Final article HTML content |
| `description` | `?string` | Short article description |
| `metaTitle` | `?string` | SEO meta title |
| `metaDescription` | `?string` | SEO meta description |
| `imagePrompt` | `?string` | AI prompt for image generation |
| `imagePath` | `?string` | Path to the generated/optimised image |
| `categoryId` | `?int` | Selected category ID |
| `tags` | `array` | List of tag names |
| `categories` | `array` | Available categories (id => name) |
| `article` | `?Article` | The created Article model (set by CreateArticleStage) |
| `stagedContent` | `?StagedContent` | Associated staged content record |
| `publishResults` | `array` | Per-channel `PublishResult` DTOs |
| `rejected` | `bool` | Whether content was rejected |
| `rejectionReason` | `?string` | Why content was rejected |
| `extra` | `array` | Arbitrary metadata bag |

<a name="creating-payloads"></a>
## Creating Payloads

**From a URL:**

```php
$payload = ContentPayload::fromUrl('https://example.com/article');
```

**With full constructor:**

```php
$payload = new ContentPayload(
    sourceUrl: 'https://example.com/article',
    categories: [1 => 'Technology', 2 => 'Health'],
    sourceDriver: 'web',
);
```

**With pre-fetched content:**

```php
$payload = new ContentPayload(
    rawContent: $myContent,
    cleanedContent: $myContent,
    title: 'My Custom Title',
);
```

<a name="mutating-the-payload"></a>
## Mutating the Payload

`ContentPayload` has `readonly` properties. Always use `with()`:

```php
// ✅ Correct — creates a new instance
$newPayload = $payload->with([
    'title' => 'Updated Title',
    'content' => $newContent,
]);

// ❌ Wrong — will throw
$payload->title = 'Updated Title';
```

`with()` merges your overrides with the existing values and returns a new `ContentPayload`.

<a name="snapshots"></a>
## Snapshots

Payloads can be serialised to/from arrays for persistence (used by run tracking):

```php
// Save
$snapshot = $payload->toSnapshot();
// $snapshot is a plain array safe for JSON encoding

// Restore
$restored = ContentPayload::fromSnapshot($snapshot);
```

> {info} Eloquent models (article, stagedContent) are stored as IDs and rehydrated on restore.
