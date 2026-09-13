@extends('layouts.app')

@section('title', 'Library Detail')
@section('meta_description', 'View library detail')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Library Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('libraries.index') }}">Libraries</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $library->name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="library-section-title">
                    <h3 class="user-form-section-title" id="library-section-title">Library Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Name</div>
                            <div class="form-control-plain">{{ $library->name }}</div>
                            <div class="form-hint">Library name shown throughout EDBArchive.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Status</div>
                            <div class="form-control-plain badge-only">
                                <span class="badge badge-{{ $library->is_active ? 'success' : 'danger' }}">
                                    {{ $library->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="form-hint">Controls whether this library is available for use.</div>
                        </div>
                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="metadata-section-title">
                    <h3 class="user-form-section-title" id="metadata-section-title">Metadata</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Created</div>
                            <div class="form-control-plain">{{ $library->created_at?->format('d M Y H:i') }}</div>
                            <div class="form-hint">When this library was added.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Updated</div>
                            <div class="form-control-plain">{{ $library->updated_at?->format('d M Y H:i') }}</div>
                            <div class="form-hint">Most recent library change.</div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('libraries.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
                <a class="btn btn-warning btn-wide" href="{{ route('libraries.edit', $library) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Edit</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
