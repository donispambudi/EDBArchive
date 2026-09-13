@extends('layouts.app')

@section('title', 'Database Detail')
@section('meta_description', 'View database metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Database Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('databases.index') }}">Databases</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $database->name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="database-section-title">
                    <h3 class="user-form-section-title" id="database-section-title">Database Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Name</div>
                            <div class="form-control-plain">{{ $database->name }}</div>
                            <div class="form-hint">Local MariaDB database registered in EDBArchive.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Provider</div>
                            <div class="form-control-plain">{{ $database->provider?->name ?? '-' }}</div>
                            <div class="form-hint">User responsible for this database.</div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <div class="form-label">Description</div>
                        <div class="form-control-plain">{{ $database->description ?: '-' }}</div>
                        <div class="form-hint">Logical description or data domain note.</div>
                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="database-tables-section-title">
                    <h3 class="user-form-section-title" id="database-tables-section-title">Tables To Process</h3>

                    <div class="form-group mb-0">
                        <div class="form-label">Selected Tables</div>
                        @if($database->databaseTables->isNotEmpty() && $database->databaseTables->sum(fn ($table) => $table->columns->count()) === 0)
                            <div class="alert alert-warning">
                                No columns are configured yet. Open Configure Columns to choose column-level protection defaults.
                            </div>
                        @endif
                        <div class="form-control-plain">
                            @if($database->databaseTables->isNotEmpty())
                                <div class="selected-table-list">
                                    @foreach($database->databaseTables->sortBy('table_name') as $table)
                                        <div class="selected-table-item">
                                            <span class="selected-table-name">{{ $table->table_name }}</span>
                                            <span class="selected-table-count">{{ $table->columns->count() }} columns</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                -
                            @endif
                        </div>
                        <div class="form-hint">Tables saved in database_tables for this provider database.</div>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('databases.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
                <a class="btn btn-warning btn-wide" href="{{ route('databases.edit', $database) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Edit</span>
                </a>
                <a class="btn btn-primary btn-wide" href="{{ route('databases.columns.edit', $database) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Configure Columns</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
