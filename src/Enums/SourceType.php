<?php

namespace Bader\ContentPublisher\Enums;

enum SourceType: string
{
    case Xml = 'xml';
    case Web = 'web';
    case Api = 'api';
    case Rss = 'rss';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Xml => 'XML Feed',
            self::Web => 'Web Scraper',
            self::Api => 'API Endpoint',
            self::Rss => 'RSS Feed',
            self::Text => 'Raw Text',
        };
    }
}
