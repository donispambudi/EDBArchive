{{-- resources/views/partials/sidebar.blade.php --}}

@php
    // Helper: detect active route name for highlighting
    $currentRoute = Route::currentRouteName() ?? '';
    $isActive     = fn(string $routeName): string => $currentRoute === $routeName ? 'active' : '';

    $usersActive     = str_starts_with($currentRoute, 'users.');
    $librariesActive = str_starts_with($currentRoute, 'libraries.');
    $schemesActive   = str_starts_with($currentRoute, 'schemes.');
    $contextsActive  = str_starts_with($currentRoute, 'fhe-contexts.');
    $jobsActive      = str_starts_with($currentRoute, 'fhe-jobs.');
    $keysActive      = str_starts_with($currentRoute, 'fhe-key-registry.');
    $databasesActive = str_starts_with($currentRoute, 'databases.');
    $sharesActive    = str_starts_with($currentRoute, 'shares.');
@endphp

<aside
    class="admin-sidebar"
    id="admin-sidebar"
    role="navigation"
    aria-label="Main navigation"
>

    {{-- Sidebar header / logo --}}
    <a href="{{ route('dashboard') }}" class="sidebar-header" aria-label="Go to dashboard">
        <div class="sidebar-logo" aria-hidden="true">DB</div>
        <div>
            <div class="sidebar-title">EDBArchive</div>
            <div class="sidebar-subtitle">Database System</div>
        </div>
    </a>

    {{-- Navigation --}}
    <nav class="sidebar-nav" aria-label="Sidebar menu">

        <a
            href="{{ route('dashboard') }}"
            class="nav-item {{ $isActive('dashboard') }}"
            aria-current="{{ $isActive('dashboard') ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
            </span>
            <span class="nav-label">Dashboard</span>
        </a>

        {{-- ─── MANAGE SECTION ─────────────────────────────────── --}}
        <div class="nav-section-label">Manage</div>

        {{-- Users (simple link) --}}
        <a
            href="{{ route('users.index') }}"
            class="nav-item {{ $usersActive ? 'active' : '' }}"
            aria-current="{{ $usersActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </span>
            <span class="nav-label">Users</span>
        </a>

        <a
            href="{{ route('fhe-key-registry.index') }}"
            class="nav-item {{ $keysActive ? 'active' : '' }}"
            aria-current="{{ $keysActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 2l-2 2"/>
                    <path d="M7.5 11.5 3 16v5h5l4.5-4.5"/>
                    <circle cx="14.5" cy="9.5" r="6.5"/>
                    <path d="M15 9h.01"/>
                </svg>
            </span>
            <span class="nav-label">FHE Keys</span>
        </a>

        <a
            href="{{ route('databases.index') }}"
            class="nav-item {{ $databasesActive ? 'active' : '' }}"
            aria-current="{{ $databasesActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="8" ry="3"/>
                    <path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/>
                    <path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>
                </svg>
            </span>
            <span class="nav-label">Databases</span>
        </a>

        <a
            href="{{ route('shares.index') }}"
            class="nav-item {{ $sharesActive ? 'active' : '' }}"
            aria-current="{{ $sharesActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/>
                    <path d="M16 6l-4-4-4 4"/>
                    <path d="M12 2v13"/>
                </svg>
            </span>
            <span class="nav-label">Shares</span>
        </a>

        {{-- ─── SYSTEM SECTION ─────────────────────────────────── --}}
        <div class="nav-section-label">System</div>

        <a
            href="{{ route('libraries.index') }}"
            class="nav-item {{ $librariesActive ? 'active' : '' }}"
            aria-current="{{ $librariesActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                    <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>
                    <path d="M8 6h8"/>
                    <path d="M8 10h8"/>
                </svg>
            </span>
            <span class="nav-label">Libraries</span>
        </a>

        <a
            href="{{ route('schemes.index') }}"
            class="nav-item {{ $schemesActive ? 'active' : '' }}"
            aria-current="{{ $schemesActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16"/>
                    <path d="M4 12h16"/>
                    <path d="M4 18h16"/>
                    <circle cx="8" cy="6" r="2"/>
                    <circle cx="14" cy="12" r="2"/>
                    <circle cx="10" cy="18" r="2"/>
                </svg>
            </span>
            <span class="nav-label">Schemes</span>
        </a>

        <a
            href="{{ route('fhe-contexts.index') }}"
            class="nav-item {{ $contextsActive ? 'active' : '' }}"
            aria-current="{{ $contextsActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 3v18"/>
                    <path d="M5 7h14"/>
                    <path d="M5 17h14"/>
                    <circle cx="5" cy="7" r="2"/>
                    <circle cx="19" cy="7" r="2"/>
                    <circle cx="5" cy="17" r="2"/>
                    <circle cx="19" cy="17" r="2"/>
                </svg>
            </span>
            <span class="nav-label">FHE Contexts</span>
        </a>

        <a
            href="{{ route('fhe-jobs.index') }}"
            class="nav-item {{ $jobsActive ? 'active' : '' }}"
            aria-current="{{ $jobsActive ? 'page' : 'false' }}"
        >
            <span class="nav-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m3 7 2 2 4-4"/>
                    <path d="m3 17 2 2 4-4"/>
                    <path d="M13 6h8"/>
                    <path d="M13 12h8"/>
                    <path d="M13 18h8"/>
                </svg>
            </span>
            <span class="nav-label">FHE Jobs</span>
        </a>

    </nav>

</aside>
