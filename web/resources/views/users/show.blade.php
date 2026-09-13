@extends('layouts.app')

@section('title', 'User Detail')
@section('meta_description', 'View user detail')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">User Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('users.index') }}">Users</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $user->name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="account-section-title">
                    <h3 class="user-form-section-title" id="account-section-title">Account Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Name</div>
                            <div class="form-control-plain">{{ $user->name }}</div>
                            <div class="form-hint">Name shown throughout EDBArchive.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Email</div>
                            <div class="form-control-plain">{{ $user->email }}</div>
                            <div class="form-hint">Email used by this account to sign in.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Role</div>
                            <div class="form-control-plain">
                                <span class="badge badge-{{ $user->role === 'Data Provider' ? 'info' : 'secondary' }}">
                                    {{ $user->role }}
                                </span>
                            </div>
                            <div class="form-hint">Defines whether the user provides data or accesses it as a third party.</div>
                        </div>
                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="metadata-section-title">
                    <h3 class="user-form-section-title" id="metadata-section-title">Metadata</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Created</div>
                            <div class="form-control-plain">{{ $user->created_at?->format('d M Y H:i') }}</div>
                            <div class="form-hint">When this user account was added.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Updated</div>
                            <div class="form-control-plain">{{ $user->updated_at?->format('d M Y H:i') }}</div>
                            <div class="form-hint">Most recent account profile change.</div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('users.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
                <a class="btn btn-warning btn-wide" href="{{ route('users.edit', $user) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Edit</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
