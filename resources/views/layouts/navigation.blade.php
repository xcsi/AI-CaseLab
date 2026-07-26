<nav class="navbar navbar-expand-sm navbar-light bg-white border-bottom">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
            <x-application-logo class="text-dark" style="width: 2rem; height: 2rem;" />
            AI CaseLab
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primary-navigation" aria-controls="primary-navigation" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="primary-navigation">
            <ul class="navbar-nav me-auto">
                @auth
                    <li class="nav-item">
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                    </li>
                    <li class="nav-item">
                        <x-nav-link :href="route('cases.index')" :active="request()->routeIs('cases.*')">
                            {{ __('Case Catalog') }}
                        </x-nav-link>
                    </li>
                    <li class="nav-item">
                        <x-nav-link :href="route('progress.index')" :active="request()->routeIs('progress.*')">
                            {{ __('My Progress') }}
                        </x-nav-link>
                    </li>
                @else
                    <li class="nav-item">
                        <x-nav-link href="{{ route('cases.index') }}" :active="request()->routeIs('cases.*')">
                            {{ __('View Demo Case') }}
                        </x-nav-link>
                    </li>
                @endauth
            </ul>

            <ul class="navbar-nav">
                @auth
                    <li class="nav-item dropdown">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <a class="nav-link" href="#" role="button">
                                    {{ Auth::user()->name }}
                                </a>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('profile.edit')">
                                    {{ __('Profile') }}
                                </x-dropdown-link>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown-link :href="route('logout')"
                                            onclick="event.preventDefault(); this.closest('form').submit();">
                                        {{ __('Log Out') }}
                                    </x-dropdown-link>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </li>
                @else
                    <li class="nav-item">
                        <x-nav-link href="{{ route('login') }}" :active="request()->routeIs('login')">
                            {{ __('Login') }}
                        </x-nav-link>
                    </li>
                    @if (Route::has('register'))
                        <li class="nav-item">
                            <x-nav-link href="{{ route('register') }}" :active="request()->routeIs('register')">
                                {{ __('Register') }}
                            </x-nav-link>
                        </li>
                    @endif
                @endauth
            </ul>
        </div>
    </div>
</nav>
