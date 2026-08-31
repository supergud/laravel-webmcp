<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- The WebMCP tools post through the same CSRF protection as any form. --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

@if (config('app.debug'))
    {{-- Presence of this tag is the only thing that makes the WebMCP layer log. --}}
    <meta name="webmcp-debug" content="1">
@endif

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/webmcp/index.js'])
@fluxAppearance
