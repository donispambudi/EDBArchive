@extends('layouts.app')

@section('title', 'FHE Job Detail')
@section('meta_description', 'View FHE background job details')

@section('content')
@php
    $statusBadge = match ($fheJob->status) {
        'completed' => 'success',
        'failed' => 'danger',
        'running' => 'warning',
        default => 'secondary',
    };
    $inputPayload = $fheJob->input_payload === null
        ? null
        : json_encode($fheJob->input_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $resultPayload = $fheJob->result_payload === null
        ? null
        : json_encode($fheJob->result_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">FHE Job #{{ $fheJob->id }}</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('fhe-jobs.index') }}">FHE Jobs</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">#{{ $fheJob->id }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="job-information-title">
                    <h3 class="user-form-section-title" id="job-information-title">Job Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Job Type</div>
                            <div class="form-control-plain">{{ ucwords(str_replace('-', ' ', $fheJob->job_type)) }}</div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Target ID</div>
                            <div class="form-control-plain">{{ $fheJob->pk ? '#'.$fheJob->pk : '-' }}</div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Status</div>
                            <div class="form-control-plain badge-only">
                                <span class="badge badge-{{ $statusBadge }}">{{ ucfirst($fheJob->status) }}</span>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Progress</div>
                            <div class="form-control-plain">{{ $fheJob->progress }}%</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Requested By</div>
                            <div class="form-control-plain">{{ $fheJob->creator?->name ?? 'System' }}</div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Created</div>
                            <div class="form-control-plain">{{ $fheJob->created_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Updated</div>
                            <div class="form-control-plain">{{ $fheJob->updated_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Exit Code</div>
                            <div class="form-control-plain">{{ $fheJob->exit_code ?? '-' }}</div>
                        </div>
                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="job-lifecycle-title">
                    <h3 class="user-form-section-title" id="job-lifecycle-title">Lifecycle</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Claimed</div>
                            <div class="form-control-plain">{{ $fheJob->claimed_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Started</div>
                            <div class="form-control-plain">{{ $fheJob->started_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
                        </div>
                        <div class="form-group">
                            <div class="form-label">Finished</div>
                            <div class="form-control-plain">{{ $fheJob->finished_at?->format('d/m/Y H:i:s') ?? '-' }}</div>
                        </div>
                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="job-payload-title">
                    <h3 class="user-form-section-title" id="job-payload-title">Payload</h3>
                    <div class="form-group">
                        <div class="form-label">Input Payload</div>
                        <pre class="code-block job-output-block">{{ $inputPayload ?? '-' }}</pre>
                    </div>
                    <div class="form-group mb-0">
                        <div class="form-label">Result Payload</div>
                        <pre class="code-block job-output-block">{{ $resultPayload ?? '-' }}</pre>
                    </div>
                </section>

                @if($fheJob->error_type || $fheJob->error_code || $fheJob->error_message)
                    <section class="user-form-section" aria-labelledby="job-error-title">
                        <h3 class="user-form-section-title" id="job-error-title">Error</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <div class="form-label">Error Type</div>
                                <div class="form-control-plain">{{ $fheJob->error_type ?? '-' }}</div>
                            </div>
                            <div class="form-group">
                                <div class="form-label">Error Code</div>
                                <div class="form-control-plain">{{ $fheJob->error_code ?? '-' }}</div>
                            </div>
                        </div>
                        <div class="form-group mb-0">
                            <div class="form-label">Error Message</div>
                            <pre class="code-block">{{ $fheJob->error_message ?? '-' }}</pre>
                        </div>
                    </section>
                @endif

                <section class="user-form-section" aria-labelledby="job-log-title">
                    <h3 class="user-form-section-title" id="job-log-title">Process Logs</h3>
                    <div class="form-group">
                        <div class="form-label">Standard Output</div>
                        <pre class="code-block job-output-block">{{ $fheJob->stdout_log ?: '-' }}</pre>
                    </div>
                    <div class="form-group mb-0">
                        <div class="form-label">Standard Error</div>
                        <pre class="code-block job-output-block">{{ $fheJob->stderr_log ?: '-' }}</pre>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('fhe-jobs.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
