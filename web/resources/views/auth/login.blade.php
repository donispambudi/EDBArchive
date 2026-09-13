<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - EDBArchive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
    <style>
        body {
            min-height: 100vh;
            background:
                linear-gradient(135deg, rgba(3, 105, 161, .12), rgba(6, 182, 212, .08)),
                var(--color-bg);
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }

        .login-panel {
            width: 100%;
            max-width: 420px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            padding: 2rem;
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 1.75rem;
        }

        .login-brand-mark {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: linear-gradient(135deg, var(--color-primary), var(--color-info));
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .login-title {
            font-size: 1.375rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .login-subtitle {
            color: var(--color-text-muted);
            font-size: .875rem;
            margin-top: .25rem;
        }

        .field-error {
            color: var(--color-danger);
            font-size: .8125rem;
            margin-top: .375rem;
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: 1.25rem;
            color: var(--color-text-muted);
            font-size: .875rem;
        }

        .remember-row input {
            width: 1rem;
            height: 1rem;
            accent-color: var(--color-primary);
        }

        @media (max-width: 480px) {
            .login-page { padding: 1rem; }
            .login-panel { padding: 1.5rem; }
        }
    </style>
</head>
<body>
    <main class="login-page">
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-brand">
                <div class="login-brand-mark" aria-hidden="true">DB</div>
                <div>
                    <h1 class="login-title" id="login-title">Login</h1>
                    <p class="login-subtitle">Use your existing user account.</p>
                </div>
            </div>

            @if(session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input
                        class="form-control"
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        autofocus
                        required
                    >
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input
                        class="form-control"
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                    >
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <label class="remember-row" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1">
                    <span>Remember me</span>
                </label>

                <button class="btn btn-primary w-100 btn-lg" type="submit">Login</button>
            </form>
        </section>
    </main>
</body>
</html>
