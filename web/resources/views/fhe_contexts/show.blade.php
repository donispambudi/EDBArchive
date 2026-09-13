@extends('layouts.app')

@section('title', 'FHE Context Detail')
@section('meta_description', 'View FHE context detail')

@section('content')
@php
    $parameters = $context->parameters_json ?? [];
    $definitions = collect($context->schemeRecord?->configuration_parameters ?? [])->keyBy('name');
    $contextStatus = $context->context_status ?? 'pending';
    $contextStatusBadge = match ($contextStatus) {
        'generated' => 'success',
        'failed' => 'danger',
        'queued', 'processing' => 'warning',
        default => 'secondary',
    };
    $canGenerateContext = ! in_array($contextStatus, ['queued', 'processing'], true);
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">FHE Context Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('fhe-contexts.index') }}">FHE Contexts</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $context->name }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="context-section-title">
                    <h3 class="user-form-section-title" id="context-section-title">Context Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Name</div>
                            <div class="form-control-plain">{{ $context->name }}</div>
                            <div class="form-hint">Short label for this FHE context.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Library</div>
                            <div class="form-control-plain">{{ $context->schemeRecord?->library?->name ?? '-' }}</div>
                            <div class="form-hint">Library associated with this context's scheme.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Scheme</div>
                            <div class="form-control-plain">{{ $context->scheme_label }}</div>
                            <div class="form-hint">Encryption scheme associated with this context.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Context Generated</div>
                            <div class="form-control-plain badge-only">
                                <span class="badge badge-{{ $contextStatusBadge }}">{{ ucfirst($contextStatus) }}</span>
                            </div>
                            <div class="form-hint">Shows whether an external serialized context reference has been generated.</div>
                        </div>

                    </div>
                </section>

                <section class="user-form-section" aria-labelledby="parameters-section-title">
                    <h3 class="user-form-section-title" id="parameters-section-title">Parameters</h3>

                    <div class="table-wrapper">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">Parameter</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Value</th>
                                    <th scope="col">Required</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($parameters as $name => $value)
                                    @php
                                        $definition = $definitions->get($name, []);
                                    @endphp
                                    <tr>
                                        <td class="font-medium">{{ str_replace('_', ' ', ucfirst($name)) }}</td>
                                        <td><span class="badge badge-secondary">{{ ucfirst($definition['type'] ?? 'string') }}</span></td>
                                        <td class="text-sm text-muted">
                                            @if(is_array($value))
                                                <code>{{ json_encode($value) }}</code>
                                            @elseif(is_bool($value))
                                                {{ $value ? 'true' : 'false' }}
                                            @else
                                                {{ $value ?? '-' }}
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-{{ ! empty($definition['is_required']) ? 'success' : 'secondary' }}">
                                                {{ ! empty($definition['is_required']) ? 'Required' : 'Optional' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center text-muted" colspan="4">No parameters saved.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('fhe-contexts.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
                <a class="btn btn-warning btn-wide" href="{{ route('fhe-contexts.edit', $context) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Edit</span>
                </a>
                @if($canGenerateContext)
                    <button class="btn btn-warning btn-wide js-generate-context" type="button">
                        <span class="btn-icon icon-save" aria-hidden="true"></span>
                        <span>{{ $contextStatus === 'generated' ? 'Regenerate Context' : 'Generate Context' }}</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@if($canGenerateContext)
    <div class="modal-backdrop" id="generate-context-modal" role="dialog" aria-modal="true" aria-labelledby="generate-context-title" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-icon warning" aria-hidden="true">
                    <span class="modal-icon-mark icon-save"></span>
                </div>
                <div>
                    <h2 class="modal-title" id="generate-context-title">Generate FHE context?</h2>
                </div>
            </div>
            <div class="modal-body">
                <p class="modal-description">Generate FHE context for {{ $context->name }}?</p>
            </div>
            <form method="POST" action="{{ route('fhe-contexts.generate', $context) }}">
                @csrf
                <div class="modal-actions">
                    <button class="btn btn-soft" type="button" id="generate-context-cancel">
                        <span class="btn-icon icon-close" aria-hidden="true"></span>
                        <span>Cancel</span>
                    </button>
                    <button class="btn btn-warning" type="submit">
                        <span class="btn-icon icon-save" aria-hidden="true"></span>
                        <span>{{ $contextStatus === 'generated' ? 'Regenerate' : 'Generate' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@if($canGenerateContext)
    @section('js')
    <script>
        (function () {
            const modal = document.getElementById('generate-context-modal');
            const trigger = document.querySelector('.js-generate-context');
            const cancelBtn = document.getElementById('generate-context-cancel');

            function openModal() {
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                cancelBtn.focus();
            }

            function closeModal() {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                trigger.focus();
            }

            trigger.addEventListener('click', openModal);
            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) closeModal();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
            });
        })();
    </script>
    @endsection
@endif
