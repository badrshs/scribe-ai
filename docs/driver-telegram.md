# Telegram Driver

---

- [Overview](#overview)
- [Configuration](#configuration)
- [Message Format](#message-format)
- [Setup Guide](#setup-guide)

<a name="overview"></a>
## Overview

The Telegram driver publishes articles to a Telegram channel or group via the Bot API. Messages are formatted in HTML with the article title, description, tags, and a source link.

> {info} This is the **publishing** driver (one-way output). For the interactive approval workflow with inline buttons, see the [Telegram Approval Extension](/docs/1.0/extension-telegram-approval).

<a name="configuration"></a>
## Configuration

```dotenv
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
TELEGRAM_CHAT_ID=-1001234567890
TELEGRAM_PARSE_MODE=HTML
```

Config in `config/scribe-ai.php`:

```php
'drivers' => [
    'telegram' => [
        'driver'     => 'telegram',
        'bot_token'  => env('TELEGRAM_BOT_TOKEN'),
        'chat_id'    => env('TELEGRAM_CHAT_ID'),
        'parse_mode' => env('TELEGRAM_PARSE_MODE', 'HTML'),
    ],
],
```

**Activate the channel:**

```dotenv
PUBLISHER_CHANNELS=telegram
```

<a name="message-format"></a>
## Message Format

Messages are sent as HTML with this structure:

```html
<b>Article Title</b>

Short description (max 200 chars)...

#Technology

📎 https://example.com/article
```

- **Title** — bold
- **Description** — truncated to 200 characters
- **Tags** — hashtag format
- **Source link** — optional, from the article's source URL

The `disable_web_page_preview` is set to `false`, so Telegram renders a link preview.

<a name="setup-guide"></a>
## Setup Guide

1. **Create a bot** via [@BotFather](https://t.me/BotFather) → save the bot token
2. **Create a channel** or group
3. **Add the bot** as an administrator with "Post messages" permission
4. **Get the chat ID**:
   - For public channels: use `@channelname`
   - For private channels: forward a message to [@userinfobot](https://t.me/userinfobot) or use the Telegram API

```dotenv
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNO
TELEGRAM_CHAT_ID=-1001234567890
```

> {warning} The bot must be an administrator of the channel/group to post messages.
