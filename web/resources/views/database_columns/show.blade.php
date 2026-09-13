@extends('layouts.app')

@section('title', 'Database Column Detail')
@section('meta_description', 'View database column protection metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Database Column Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('database-columns.index') }}">Database Columns</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $databaseColumn->column_name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="column-section-title">
                    <h3 class="user-form-section-title" id="column-section-title">Column Metadata</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Database Table</div>
                            <div class="form-control-plain">
                                {{ $databaseColumn->databaseTable?->database?->name ?? '-' }}.{{ $databaseColumn->databaseTable?->table_name ?? '-' }}
                            </div>
                            <div class="form-hint">Parent provider-owned database table.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Column Name</div>
                            <div class="form-control-plain">{{ $databaseColumn->column_name }}</div>
                            <div class="form-hint">Original logical column name.</div>
                        </div>

                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="encryption-section-title">
                    <h3 class="user-form-section-title" id="encryption-section-title">Encryption Defaults</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Default Encryption Type</div>
                            <div class="form-control-plain">
                                <span class="badge badge-{{ str_starts_with($databaseColumn->default_encryption_type, 'fhe-') ? 'primary' : 'secondary' }}">
                                    {{ $databaseColumn->default_encryption_type }}
                                </span>
                            </div>
                            <div class="form-hint">Encryption category used when the column is encrypted.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">FHE Context</div>
                            <div class="form-control-plain">{{ $databaseColumn->fheContext?->name ?? '-' }}</div>
                            <div class="form-hint">Required for FHE-encrypted columns, otherwise usually empty.</div>
                        </div>

                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('database-columns.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
                <a class="btn btn-warning btn-wide" href="{{ route('database-columns.edit', $databaseColumn) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Edit</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
