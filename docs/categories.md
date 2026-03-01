# Categories

---

- [Overview](#overview)
- [Static Categories](#static-categories)
- [Database Categories](#database-categories)
- [AI Category Selection](#ai-category-selection)
- [Category Model](#category-model)

<a name="overview"></a>
## Overview

Categories organise articles into topics. The pipeline supports both static categories (defined in config) and dynamic categories (stored in the database). The AI automatically selects the best-matching category during the `AiRewriteStage`.

<a name="static-categories"></a>
## Static Categories

Define categories directly in `config/scribe-ai.php`:

```php
'categories' => [
    1 => 'Technology',
    2 => 'Health',
    3 => 'Business',
    4 => 'Science',
    5 => 'Entertainment',
],
```

Pass them to the pipeline:

```bash
php artisan scribe:process-url https://example.com --categories="1:Technology,2:Health,3:Business"
```

Or programmatically:

```php
$payload = ContentPayload::fromUrl($url)->with([
    'categories' => [1 => 'Technology', 2 => 'Health', 3 => 'Business'],
]);
```

<a name="database-categories"></a>
## Database Categories

The `categories` migration creates a table for persistent categories:

| Column | Type | Description |
|--------|------|-------------|
| `name` | string | Category name |
| `slug` | string | URL-friendly slug |
| `description` | text nullable | Category description |

```php
use Badr\ScribeAi\Models\Category;

// Create categories
Category::create(['name' => 'Technology', 'slug' => 'technology']);

// Load categories for the pipeline
$categories = Category::pluck('name', 'id')->toArray();
$payload = ContentPayload::fromUrl($url)->with(['categories' => $categories]);
```

<a name="ai-category-selection"></a>
## AI Category Selection

During `AiRewriteStage`, if categories are available on the payload, the AI is instructed to select the most appropriate category. The selected `categoryId` is set on the payload and used when creating the article.

If no categories are provided, the stage skips category selection and `categoryId` remains null.

<a name="category-model"></a>
## Category Model

The `Category` model provides standard Eloquent relationships:

```php
$category = Category::find(1);

// Articles in this category
$articles = $category->articles;

// Category of an article
$article->category;      // BelongsTo
$article->category_id;   // FK
```
