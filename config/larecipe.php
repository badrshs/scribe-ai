<?php

return [

    'docs' => [
        'route'   => '/docs',
        'path'    => '/resources/docs',
        'landing' => 'overview',
        'middleware' => ['web'],
    ],

    'versions' => [
        'default'   => '1.0',
        'published' => [
            '1.0',
        ],
    ],

    'settings' => [
        'auth'       => false,
        'guard'      => null,
        'ga_id'      => '',
        'middleware'  => [
            'web',
        ],
    ],

    'cache' => [
        'enabled' => false,
        'period'  => 5,
    ],

    'search' => [
        'enabled' => true,
        'default' => 'internal',
        'engines' => [
            'internal' => [
                'index' => ['h2', 'h3'],
            ],
            'algolia' => [
                'key'   => '',
                'index' => '',
            ],
        ],
    ],

    'ui' => [
        'code_theme'    => 'dark',
        'fav'           => '',
        'fa_v4_shims'   => true,
        'show_side_bar' => true,
        'colors'        => [
            'primary'   => '#6366f1',
            'secondary' => '#3b82f6',
        ],
        'theme_order' => null,
    ],

    'seo' => [
        'author'      => 'Bader',
        'description' => 'Scribe AI — Turn any URL into a published article with AI. Full documentation.',
        'keywords'    => 'laravel, ai, content, publishing, pipeline, scribe',
        'og'          => [
            'title'       => 'Scribe AI Documentation',
            'type'        => 'article',
            'url'         => '',
            'image'       => '',
            'description' => 'Scribe AI — A Laravel package that turns any URL into a published article.',
        ],
    ],

    'forum' => [
        'enabled'  => false,
        'default'  => 'disqus',
        'services' => [
            'disqus' => [
                'site_name' => '',
            ],
        ],
    ],

    'packages' => [
        'path' => 'larecipe-components',
    ],

    'blade-parser' => [
        'regex' => [
            'code-blocks' => [
                'match'       => '/\<pre\>(.|\n)*?<\/pre\>/',
                'replacement' => '<code-block>',
            ],
        ],
    ],

];
