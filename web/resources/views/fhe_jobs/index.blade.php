@extends('layouts.app')

@section('title', 'FHE Jobs')
@section('meta_description', 'View FHE background jobs')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">FHE Jobs</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">FHE Jobs</span>
        </nav>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Job List</h3>
        <span class="text-sm text-muted">{{ $jobs->total() }} total jobs</span>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">ID</th>
                        <th scope="col">Job Type</th>
                        <th scope="col">Target ID</th>
                        <th scope="col">Status</th>
                        <th scope="col">Progress</th>
                        <th scope="col">Requested By</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        @php
                            $statusBadge = match ($job->status) {
                                'completed' => 'success',
                                'failed' => 'danger',
                                'running' => 'warning',
                                default => 'secondary',
                            };
                        @endphp
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('fhe-jobs.show', $job) }}" title="View" aria-label="View FHE job {{ $job->id }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                </div>
                            </td>
                            <td class="font-medium">#{{ $job->id }}</td>
                            <td>{{ ucwords(str_replace('-', ' ', $job->job_type)) }}</td>
                            <td>{{ $job->pk ? '#'.$job->pk : '-' }}</td>
                            <td><span class="badge badge-{{ $statusBadge }}">{{ ucfirst($job->status) }}</span></td>
                            <td>{{ $job->progress }}%</td>
                            <td>{{ $job->creator?->name ?? 'System' }}</td>
                            <td class="text-sm text-muted">{{ $job->created_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="8">No FHE jobs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($jobs->hasPages())
        <div class="pagination-wrap">
            {{ $jobs->links() }}
        </div>
    @endif
</div>
@endsection
