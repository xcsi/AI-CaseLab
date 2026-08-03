<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'AI CaseLab') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    </head>
    <body>
        <div class="min-vh-100 d-flex flex-column">
            <header class="container d-flex align-items-center justify-content-between py-4">
                <div class="d-flex align-items-center gap-2 fs-4 fw-semibold">
                    <x-application-logo class="text-primary" style="width: 2.25rem; height: 2.25rem;" />
                    AI CaseLab
                </div>

                <nav class="d-flex gap-2">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-primary">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-primary">Register</a>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="container flex-grow-1 d-flex flex-column justify-content-center text-center py-5">
                <h1 class="display-5 fw-bold">Practice real engineering incidents, not quizzes.</h1>
                <p class="lead text-secondary col-lg-8 mx-auto mt-3">
                    AI CaseLab drops you into simulated workplace incidents — tickets, logs, code, and API
                    responses — so you can investigate like an engineer and get rubric-based feedback on your
                    diagnosis.
                </p>
                <div class="d-flex justify-content-center gap-3 mt-4">
                    <a href="{{ route('cases.index') }}" class="btn btn-outline-primary btn-lg">View Demo Case</a>
                    @guest
                        <a href="{{ route('register') }}" class="btn btn-primary btn-lg">Get Started</a>
                    @endguest
                </div>
            </main>

            <footer class="text-center text-secondary small py-4">
                AI CaseLab v{{ config('app.version', '0.1.0') }} &middot; Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
            </footer>
        </div>
    </body>
</html>
