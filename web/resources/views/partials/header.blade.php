{{-- resources/views/partials/header.blade.php --}}

@php
    $user        = Auth::user();
    $nameParts   = explode(' ', trim($user->name));
    $initials    = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
    $serverNow   = now();    // Carbon instance — server time
@endphp

<header class="admin-header" role="banner">

    {{-- Hamburger --}}
    <button
        id="hamburger-btn"
        class="hamburger-btn"
        aria-label="Toggle sidebar"
        aria-controls="admin-sidebar"
        aria-expanded="false"
        type="button"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    {{-- Brand --}}
    <a href="{{ route('dashboard') }}" class="header-brand" aria-label="Go to dashboard">
        <div class="brand-icon" aria-hidden="true">DB</div>
        <span class="brand-name">EDBArchive</span>
    </a>

    <div class="header-spacer"></div>

    {{-- Right side --}}
    <div class="header-right">

        {{-- Server-seeded datetime clock --}}
        <div class="header-datetime" aria-live="polite" aria-label="Server time">
            <span
                class="time-str"
                id="js-time"
                data-server-ts="{{ $serverNow->valueOf() }}"
            >{{ $serverNow->format('H:i:s') }}</span>
            <span class="date-str" id="js-date">{{ $serverNow->isoFormat('ddd, DD MMM YYYY') }}</span>
        </div>

        {{-- Avatar + dropdown --}}
        <div class="avatar-wrapper">
            <button
                id="avatar-btn"
                class="avatar-btn"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="dropdown-menu"
                type="button"
                title="{{ $user->name }}"
            >
                <div class="avatar-circle" aria-hidden="true">{{ $initials }}</div>
                <span class="avatar-name">{{ $user->name }}</span>
                <span class="avatar-chevron" aria-hidden="true">▾</span>
            </button>

            <div
                id="dropdown-menu"
                class="dropdown-menu"
                role="menu"
                aria-labelledby="avatar-btn"
            >
                <div class="dropdown-header">
                    <div class="d-name">{{ $user->name }}</div>
                    <div class="d-role">{{ str_replace('_', ' ', ucfirst($user->role ?? 'user')) }}</div>
                </div>

                <a href="{{ route('profile') }}" class="dropdown-item" role="menuitem">
                    <span class="di-icon" aria-hidden="true">👤</span> Profile
                </a>
                <a href="{{ route('password.change') }}" class="dropdown-item" role="menuitem">
                    <span class="di-icon" aria-hidden="true">🔑</span> Change Password
                </a>

                <hr class="dropdown-divider">

                <form method="POST" action="{{ route('logout') }}" id="logout-form">
                    @csrf
                    <button type="submit" class="dropdown-item danger" role="menuitem">
                        <span class="di-icon" aria-hidden="true">🚪</span> Logout
                    </button>
                </form>
            </div>
        </div>

    </div>{{-- /.header-right --}}

</header>
