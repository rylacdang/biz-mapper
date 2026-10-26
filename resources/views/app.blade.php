<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/img/biz-mapper.ico?v=3" type="image/x-icon">
        <link rel="icon" href="/img/biz-mapper.png?v=3" type="image/png">
        <link rel="apple-touch-icon" href="/img/biz-mapper.png?v=3">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])

        {{-- Public profile previews also work for crawlers without JavaScript. --}}
        @if (($page['component'] ?? null) === 'businesses/Show')
            <meta name="description" content="{{ $page['props']['metadata']['description'] }}" data-inertia="description">
            <link rel="canonical" href="{{ $page['props']['metadata']['url'] }}" data-inertia="canonical">
            <meta property="og:title" content="{{ $page['props']['metadata']['title'] }}" data-inertia="og:title">
            <meta property="og:description" content="{{ $page['props']['metadata']['description'] }}" data-inertia="og:description">
            <meta property="og:url" content="{{ $page['props']['metadata']['url'] }}" data-inertia="og:url">
            <meta property="og:type" content="website" data-inertia="og:type">

            @if ($page['props']['metadata']['image'])
                <meta property="og:image" content="{{ $page['props']['metadata']['image'] }}" data-inertia="og:image">
            @endif
        @endif

        <x-inertia::head>
            <title>{{ $page['props']['metadata']['title'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
