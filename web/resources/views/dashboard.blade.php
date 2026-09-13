{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Dashboard')
@section('meta_description', 'Application dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <span>Home</span>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Dashboard</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('shares.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Share</span>
    </a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon success" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/>
                <path d="M16 6l-4-4-4 4"/>
                <path d="M12 2v13"/>
            </svg>
        </div>
        <div class="stat-body">
            <div class="stat-value">{{ number_format($stats['active_shares']) }}</div>
            <div class="stat-label">Active Shares</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon primary" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </div>
        <div class="stat-body">
            <div class="stat-value">{{ number_format($stats['users']) }}</div>
            <div class="stat-label">Users</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon info" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <ellipse cx="12" cy="5" rx="8" ry="3"/>
                <path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/>
                <path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/>
            </svg>
        </div>
        <div class="stat-body">
            <div class="stat-value">{{ number_format($stats['databases']) }}</div>
            <div class="stat-label">Databases</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                <path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>
                <path d="M8 6h8"/>
                <path d="M8 10h8"/>
            </svg>
        </div>
        <div class="stat-body">
            <div class="stat-value">{{ number_format($stats['libraries']) }} / {{ number_format($stats['schemes']) }}</div>
            <div class="stat-label">Libraries / Schemes</div>
        </div>
    </div>
</div>

@php
    $encryptionColors = [
        'plaintext' => '#64748b',
        'fhe-secure' => '#f59e0b',
    ];
    $statusColors = [
        'draft' => '#64748b',
        'released' => '#10b981',
        'expired' => '#f59e0b',
        'revoked' => '#ef4444',
    ];
    $statusCursor = 0;
    $statusSegments = [];

    foreach ($shareStatusChart as $status => $count) {
        $slice = $shareStatusTotal > 0 ? ($count / $shareStatusTotal) * 100 : 0;
        if ($slice <= 0) {
            continue;
        }

        $statusSegments[] = ($statusColors[$status] ?? '#64748b').' '.$statusCursor.'% '.($statusCursor + $slice).'%';
        $statusCursor += $slice;
    }

    $shareStatusDonutStyle = $statusSegments
        ? 'background: conic-gradient('.implode(', ', $statusSegments).');'
        : 'background: #e2e8f0;';
@endphp

<div class="dashboard-grid mb-6">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Share Status</h3>
            <span class="text-sm text-muted">{{ $shareStatusTotal }} total shares</span>
        </div>
        <div class="card-body">
            <div class="dashboard-donut-layout">
                <div class="dashboard-donut" style="{{ $shareStatusDonutStyle }}" aria-hidden="true">
                    <div class="dashboard-donut-hole">
                        <span>{{ $shareStatusTotal }}</span>
                        <small>Total</small>
                    </div>
                </div>
                <div class="dashboard-legend">
                    @foreach($shareStatusChart as $status => $count)
                        @php
                            $percent = $shareStatusTotal > 0 ? round(($count / $shareStatusTotal) * 100, 1) : 0;
                            $legendColor = $statusColors[$status] ?? '#64748b';
                        @endphp
                        <div class="dashboard-legend-row">
                            <span class="dashboard-legend-dot" style="background: {{ $legendColor }};" aria-hidden="true"></span>
                            <span class="dashboard-legend-label">{{ ucfirst($status) }}</span>
                            <span class="dashboard-legend-value">{{ $count }} · {{ $percent }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Share Item Encryption</h3>
            <span class="text-sm text-muted">{{ $shareItemEncryptionTotal }} total items</span>
        </div>
        <div class="card-body">
            <div class="dashboard-bar-chart">
                @foreach($shareItemEncryptionChart as $encryption => $count)
                    @php
                        $percent = $shareItemEncryptionTotal > 0 ? round(($count / $shareItemEncryptionTotal) * 100, 1) : 0;
                        $barColor = $encryptionColors[$encryption] ?? '#64748b';
                    @endphp
                    <div class="dashboard-bar-row">
                        <div class="dashboard-bar-meta">
                            <span class="font-medium">{{ $encryption }}</span>
                            <span class="text-sm text-muted">{{ $count }} · {{ $percent }}%</span>
                        </div>
                        <div class="dashboard-bar-track" aria-hidden="true">
                            <div class="dashboard-bar-fill" style="width: {{ $percent }}%; background: {{ $barColor }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header">
        <h3 class="card-title">Shares</h3>
        <span class="text-sm text-muted">{{ $shares->total() }} total shares</span>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">Release</th>
                        <th scope="col">Database</th>
                        <th scope="col">Owner</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Status</th>
                        <th scope="col">Bundle Generated</th>
                        <th scope="col">Items</th>
                        <th scope="col">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($shares as $share)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('shares.show', $share) }}" title="View" aria-label="View share #{{ $share->id }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                </div>
                            </td>
                            <td class="font-medium">{{ $share->release_version ?: '-' }}</td>
                            <td class="text-sm text-muted">{{ $share->database?->name ?? '-' }}</td>
                            <td class="text-sm text-muted">{{ $share->owner?->name ?? '-' }}</td>
                            <td class="text-sm text-muted">{{ $share->recipient?->name ?? '-' }}</td>
                            <td>
                                <span class="badge badge-{{ $share->status === 'released' ? 'success' : ($share->status === 'revoked' ? 'danger' : 'secondary') }}">
                                    {{ $share->status }}
                                </span>
                            </td>
                            <td>
                                @if($share->bundle_ref)
                                    <span class="badge badge-success">Generated</span>
                                @elseif($share->bundle_status === 'processing')
                                    <span class="badge badge-warning">Processing</span>
                                @else
                                    <span class="badge badge-secondary">Pending</span>
                                @endif
                            </td>
                            <td class="text-sm text-muted">{{ $share->items_count }} items</td>
                            <td class="text-sm text-muted">{{ $share->updated_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="9">No shares found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($shares->hasPages())
        <div class="pagination-wrap">{{ $shares->links() }}</div>
    @endif
</div>

<div class="dashboard-grid">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">FHE Contexts Not Generated</h3>
            <span class="text-sm text-muted">{{ $pendingContextCount }} pending</span>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th class="sticky-action text-center" scope="col">Actions</th>
                            <th scope="col">Name</th>
                            <th scope="col">Library</th>
                            <th scope="col">Scheme</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingContexts as $context)
                            <tr>
                                <td class="sticky-action">
                                    <div class="actions-cell">
                                        <a class="btn btn-view btn-sm" href="{{ route('fhe-contexts.show', $context) }}" title="View" aria-label="View FHE context #{{ $context->id }}">
                                            <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                            <span class="btn-label">View</span>
                                        </a>
                                    </div>
                                </td>
                                <td class="font-medium">{{ $context->name }}</td>
                                <td class="text-sm text-muted">{{ $context->schemeRecord?->library?->name ?? '-' }}</td>
                                <td class="text-sm text-muted">{{ $context->scheme_label ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted" colspan="4">All FHE contexts are generated.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">FHE Keys Not Generated</h3>
            <span class="text-sm text-muted">{{ $pendingKeyCount }} pending</span>
        </div>
        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th class="sticky-action text-center" scope="col">Actions</th>
                            <th scope="col">Owner</th>
                            <th scope="col">FHE Context</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingKeys as $keyRegistry)
                            @php
                                $keyContext = $keyRegistry->fheContext;
                                $keyContextLabel = $keyContext
                                    ? trim(($keyContext->schemeRecord?->library?->name ? $keyContext->schemeRecord->library->name.' - ' : '').($keyContext->scheme_label ?: $keyContext->name))
                                    : '-';
                            @endphp
                            <tr>
                                <td class="sticky-action">
                                    <div class="actions-cell">
                                        <a class="btn btn-view btn-sm" href="{{ route('fhe-key-registry.show', $keyRegistry) }}" title="View" aria-label="View FHE key #{{ $keyRegistry->id }}">
                                            <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                            <span class="btn-label">View</span>
                                        </a>
                                    </div>
                                </td>
                                <td class="font-medium">{{ $keyRegistry->owner?->name ?? '-' }}</td>
                                <td class="text-sm text-muted">{{ $keyContextLabel }}</td>
                                <td>
                                    <span class="badge badge-{{ $keyRegistry->key_status === 'active' ? 'success' : ($keyRegistry->key_status === 'revoked' ? 'danger' : 'warning') }}">
                                        {{ ucfirst($keyRegistry->key_status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="text-center text-muted" colspan="4">All FHE keys are generated.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
