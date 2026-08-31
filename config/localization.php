<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | Every locale the storefront can be served in. The keys are used in URLs
    | (/en/products, /zh-TW/products) and as the translation keys for
    | translatable model attributes, so they must stay stable.
    |
    | Anything not listed here is rejected: the locale arrives from the URL and
    | from the WebMCP set_locale tool, so it is untrusted input and is never
    | handed to App::setLocale() without passing through this whitelist.
    |
    */

    'supported' => [
        'en' => [
            'name' => 'English',
            'native' => 'English',
            'regional' => 'en_US',
            'flag' => '🇺🇸',
        ],
        'zh-TW' => [
            'name' => 'Chinese (Traditional)',
            'native' => '繁體中文',
            'regional' => 'zh_TW',
            'flag' => '🇹🇼',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Key
    |--------------------------------------------------------------------------
    |
    | Where the visitor's chosen locale is remembered, so that routes without a
    | locale segment (Fortify's auth routes) still render in their language.
    |
    */

    'session_key' => 'locale',

];
