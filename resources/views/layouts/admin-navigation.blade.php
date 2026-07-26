<nav class="navbar navbar-dark bg-dark d-md-none">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('admin.dashboard') }}">AI CaseLab Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#admin-sidebar" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
    </div>
</nav>

<div class="collapse d-md-block bg-dark text-white" id="admin-sidebar">
    <div class="d-flex flex-column p-3" style="min-height: 100vh;">
        <a href="{{ route('admin.dashboard') }}" class="d-none d-md-flex align-items-center gap-2 mb-4 text-white text-decoration-none fs-5">
            <x-application-logo class="text-white" style="width: 1.75rem; height: 1.75rem;" />
            AI CaseLab Admin
        </a>

        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}" class="nav-link text-white {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    {{ __('Dashboard') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.cases.index') }}" class="nav-link text-white {{ request()->routeIs('admin.cases.*') ? 'active' : '' }}">
                    {{ __('Cases') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.categories.index') }}" class="nav-link text-white {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                    {{ __('Categories') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.users.index') }}" class="nav-link text-white {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    {{ __('Users') }}
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('admin.analytics.index') }}" class="nav-link text-white {{ request()->routeIs('admin.analytics.*') ? 'active' : '' }}">
                    {{ __('Analytics') }}
                </a>
            </li>
        </ul>

        <hr class="text-white-50">

        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-white text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
                {{ Auth::user()->name }}
            </a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">{{ __('Profile') }}</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">{{ __('Log Out') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>
