<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'AI CaseLab') }} — Admin</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <a href="#main-content" class="visually-hidden-focusable">Skip to content</a>

        <div class="d-md-flex min-vh-100">
            <div class="flex-shrink-0" style="width: 100%; max-width: 240px;">
                @include('layouts.admin-navigation')
            </div>

            <div class="flex-grow-1 bg-light">
                <!-- Top bar: breadcrumb -->
                @isset($breadcrumb)
                    <div class="bg-white border-bottom px-4 py-3">
                        {{ $breadcrumb }}
                    </div>
                @endisset

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white border-bottom">
                        <div class="px-4 py-3">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="p-4" id="main-content">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
