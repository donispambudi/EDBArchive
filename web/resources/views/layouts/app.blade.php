{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- SEO --}}
    <title>@yield('title', 'Dashboard') - EDBArchive</title>
    <meta name="description" content="@yield('meta_description', 'EDBArchive management panel')">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Core CSS --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">

    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230369a1'/%3E%3Ctext x='50%25' y='55%25' dominant-baseline='middle' text-anchor='middle' font-family='Inter,sans-serif' font-weight='700' font-size='13' fill='white'%3EDB%3C/text%3E%3C/svg%3E">

    {{-- Child view CSS --}}
    @yield('css')
</head>
<body>

    <div class="admin-wrapper" id="admin-wrapper">

        {{-- === HEADER === --}}
        @include('partials.header')

        {{-- === SIDEBAR === --}}
        @include('partials.sidebar')

        {{-- Mobile overlay --}}
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        {{-- === MAIN CONTENT === --}}
        <main class="admin-main" id="main-content" role="main">

            {{-- Flash messages --}}
            @if(session('success'))
                <div class="alert alert-success" data-auto-dismiss="5000">
                    ✅ {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger" data-auto-dismiss="5000">
                    ❌ {{ session('error') }}
                </div>
            @endif

            {{-- Page content --}}
            @yield('content')

        </main>

        {{-- === FOOTER === --}}
        @include('partials.footer')

    </div>{{-- /.admin-wrapper --}}

    {{-- Core JS --}}
    <script src="{{ asset('assets/js/app.js') }}"></script>

    {{-- Child view JS --}}
    @yield('js')

</body>
</html>
