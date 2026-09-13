@extends('layouts.app')

@section('title', 'FHE Key Detail')
@section('meta_description', 'View FHE key registry metadata')

@section('content')
@php
    $generationStatus = $keyRegistry->generation_status ?? 'pending';
    $generationStatusBadge = match ($generationStatus) {
        'generated' => 'success',
        'failed' => 'danger',
        'queued', 'processing' => 'warning',
        default => 'secondary',
    };
    $canGenerateKey = ! in_array($generationStatus, ['queued', 'processing'], true);
@endphp

<div class="page-header">
    <div>
        <h1 class="page-title">FHE Key Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('fhe-key-registry.index') }}">FHE Key Registry</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">{{ $keyRegistry->owner?->name ?? 'Key #'.$keyRegistry->id }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <div class="user-form">
            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="key-section-title">
                    <h3 class="user-form-section-title" id="key-section-title">Key Information</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">Owner</div>
                            <div class="form-control-plain">{{ $keyRegistry->owner?->name ?? '-' }}</div>
                            <div class="form-hint">User who owns this keyset metadata.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Owner Email</div>
                            <div class="form-control-plain">{{ $keyRegistry->owner?->email ?? '-' }}</div>
                            <div class="form-hint">Email address for the key owner.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Status</div>
                            <div class="form-control-plain badge-only">
                                <span class="badge badge-{{ $keyRegistry->key_status === 'active' ? 'success' : ($keyRegistry->key_status === 'revoked' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($keyRegistry->key_status) }}
                                </span>
                            </div>
                            <div class="form-hint">Lifecycle status for this keyset.</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-label">FHE Context</div>
                            <div class="form-control-plain">
                                @if($keyRegistry->fheContext)
                                    {{ $keyRegistry->fheContext->name }}
                                    <span class="text-sm text-muted">
                                        ({{ $keyRegistry->fheContext->schemeRecord?->library?->name ? $keyRegistry->fheContext->schemeRecord->library->name.' - ' : '' }}{{ $keyRegistry->fheContext->scheme_label }})
                                    </span>
                                @else
                                    -
                                @endif
                            </div>
                            <div class="form-hint">FHE context, library, and scheme this keyset was generated for.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Scheme</div>
                            <div class="form-control-plain">{{ $keyRegistry->fheContext?->scheme_label ?? '-' }}</div>
                            <div class="form-hint">Scheme used by the selected FHE context.</div>
                        </div>

                        <div class="form-group">
                            <div class="form-label">Created</div>
                            <div class="form-control-plain">{{ $keyRegistry->created_at?->format('d M Y H:i') }}</div>
                            <div class="form-hint">When this registry record was created.</div>
                        </div>
                    </div>

                    <div class="form-group mb-0">
                        <div class="form-label">Key Generated</div>
                        <div class="form-control-plain badge-only">
                            <span class="badge badge-{{ $generationStatusBadge }}">{{ ucfirst($generationStatus) }}</span>
                        </div>
                        <div class="form-hint">Shows whether an external serialized keyset reference has been generated.</div>
                    </div>
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('fhe-key-registry.index') }}">
                    <span class="btn-icon icon-back" aria-hidden="true"></span>
                    <span>Back</span>
                </a>
                <a class="btn btn-warning btn-wide" href="{{ route('fhe-key-registry.edit', $keyRegistry) }}">
                    <span class="btn-icon icon-edit" aria-hidden="true"></span>
                    <span>Edit</span>
                </a>
                @if($canGenerateKey)
                    <button class="btn btn-warning btn-wide js-generate-key" type="button">
                        <span class="btn-icon icon-save" aria-hidden="true"></span>
                        <span>{{ $generationStatus === 'generated' ? 'Regenerate Key' : 'Generate Key' }}</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@if($canGenerateKey)
    <div class="modal-backdrop" id="generate-key-modal" role="dialog" aria-modal="true" aria-labelledby="generate-key-title" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-icon warning" aria-hidden="true">
                    <span class="modal-icon-mark icon-save"></span>
                </div>
                <div>
                    <h2 class="modal-title" id="generate-key-title">Generate FHE key?</h2>
                </div>
            </div>
            <div class="modal-body">
                <p class="modal-description">Generate FHE key for {{ $keyRegistry->owner?->name ?? 'this user' }}?</p>
            </div>
            <form method="POST" action="{{ route('fhe-key-registry.generate', $keyRegistry) }}">
                @csrf
                <div class="modal-actions">
                    <button class="btn btn-soft" type="button" id="generate-key-cancel">
                        <span class="btn-icon icon-close" aria-hidden="true"></span>
                        <span>Cancel</span>
                    </button>
                    <button class="btn btn-warning" type="submit">
                        <span class="btn-icon icon-save" aria-hidden="true"></span>
                        <span>{{ $generationStatus === 'generated' ? 'Regenerate' : 'Generate' }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection

@if($canGenerateKey)
    @section('js')
    <script>
        (function () {
            const modal = document.getElementById('generate-key-modal');
            const trigger = document.querySelector('.js-generate-key');
            const cancelBtn = document.getElementById('generate-key-cancel');

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
