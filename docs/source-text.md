# Text Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Use Cases](#use-cases)

<a name="overview"></a>
## Overview

The Text driver accepts raw, pre-fetched content directly. Instead of an identifier pointing to a remote resource, the identifier **is** the content itself. This is the catch-all fallback driver.

<a name="configuration"></a>
## Configuration

No configuration needed. The text driver has no external dependencies.

```php
'sources' => [
    'drivers' => [
        'text' => [],
    ],
],
```

<a name="how-it-works"></a>
## How It Works

The identifier is treated as the raw content itself:

```php
public function fetch(string $identifier): array
{
    return [
        'content' => $identifier,
        'title'   => null,
        'meta'    => ['source_driver' => 'text'],
    ];
}
```

**Auto-detection:** matches any identifier that is **not** a valid URL:

```php
public function supports(string $identifier): bool
{
    return filter_var($identifier, FILTER_VALIDATE_URL) === false;
}
```

<a name="use-cases"></a>
## Use Cases

**1. Process pre-fetched content:**

```php
$payload = new ContentPayload(
    rawContent: $myContent,
    cleanedContent: $myContent,
    sourceDriver: 'text',
);

$result = app(ContentPipeline::class)->process($payload);
```

**2. Custom scraping with text fallback:**

```php
// Your own scraper fetches content
$html = MyCustomScraper::fetch($url);
$cleaned = MyCustomScraper::clean($html);

// Feed into the pipeline via the text driver
$manager = app(ContentSourceManager::class);
$result = $manager->fetch($cleaned, 'text');
```

**3. Manual content entry:**

```php
$payload = new ContentPayload(
    rawContent: 'Your manually written article content here...',
    title: 'My Custom Article',
    sourceDriver: 'text',
);
```

> {info} If `rawContent` is already set on the payload, the `ScrapeStage` skips fetching entirely — making the text driver unnecessary in that case.
