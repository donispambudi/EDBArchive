@extends('layouts.app')

@section('title', 'Change Password')
@section('meta_description', 'Change account password')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Change Password</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Change Password</span>
        </nav>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success mb-4">
        <div>{{ session('status') }}</div>
    </div>
@endif

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('password.update') }}">
            @csrf

            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="password-section-title">
                    <h3 class="user-form-section-title" id="password-section-title">Security</h3>

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input
                            class="form-control"
                            id="current_password"
                            name="current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                        >
                        @error('current_password')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label" for="password">New Password</label>
                            <input
                                class="form-control"
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                required
                            >
                            <div class="form-hint">Use at least 8 characters.</div>
                            @error('password')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-0">
                            <label class="form-label" for="password_confirmation">Confirm New Password</label>
                            <input
                                class="form-control"
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                required
                            >
                            <div class="form-hint">Repeat the new password to avoid typing mistakes.</div>
                            @error('password_confirmation')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('dashboard') }}">
                    <span class="btn-icon icon-close" aria-hidden="true"></span>
                    <span>Cancel</span>
                </a>
                <button class="btn btn-primary btn-wide" type="submit">
                    <span class="btn-icon icon-save" aria-hidden="true"></span>
                    <span>Update Password</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
