@extends('layouts.app')

@section('title', 'Scheme Detail')
@section('meta_description', 'View scheme detail')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Scheme Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('schemes.index') }}">Schemes</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $scheme->scheme_name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="scheme-section-title">
                    <h3 class="user-form-section-title" id="scheme-section-title">Scheme Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Library</div>
                            <div class="form-control-plain">{{ $scheme->library?->name }}</div>
                            <div class="form-hint">Library this scheme belongs to.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Scheme Name</div>
                            <div class="form-control-plain">{{ $scheme->scheme_name }}</div>
                            <div class="form-hint">Scheme name shown throughout EDBArchive.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Handler</div>
                            <div class="form-control-plain">{{ $scheme->handler_name }}</div>
                            <div class="form-hint">Generated from the library and scheme name.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Status</div>
                            <div class="form-control-plain badge-only">
                                <span class="badge badge-{{ $scheme->is_active ? 'success' : 'danger' }}">
                                    {{ $scheme->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div class="form-hint">Controls whether this scheme is available for use.</div>
                        </div>
                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="configuration-section-title">
                    <h3 class="user-form-section-title" id="configuration-section-title">Configuration Parameters</h3>

                    <div class="table-wrapper">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">Parameter Name</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Default Value</th>
                                    <th scope="col">Required</th>
                                    <th scope="col">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($scheme->configuration_parameters as $parameter)
                                    <tr>
                                        <td class="font-medium">{{ $parameter['name'] ?? '-' }}</td>
                                        <td><span class="badge badge-secondary">{{ ucfirst($parameter['type'] ?? 'string') }}</span></td>
                                        <td class="text-sm text-muted">{{ $parameter['default_value'] ?? '-' }}</td>
                                        <td>
                                            <span class="badge badge-{{ ! empty($parameter['is_required']) ? 'success' : 'secondary' }}">
                                                {{ ! empty($parameter['is_required']) ? 'Required' : 'Optional' }}
                                            </span>
                                        </td>
                                        <td class="text-sm text-muted">{{ $parameter['description'] ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center text-muted" colspan="5">No parameters defined.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="metadata-section-title">
                    <h3 class="user-form-section-title" id="metadata-section-title">Metadata</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Created</div>
                            <div class="form-control-plain">{{ $scheme->created_at?->format('d M Y H:i') }}</div>
                            <div class="form-hint">When this scheme was added.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Updated</div>
                            <div class="form-control-plain">{{ $scheme->updated_at?->format('d M Y H:i') }}</div>
                            <div class="form-hint">Most recent scheme change.</div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('schemes.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
                <a class="btn btn-warning btn-wide" href="{{ route('schemes.edit', $scheme) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Edit</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
