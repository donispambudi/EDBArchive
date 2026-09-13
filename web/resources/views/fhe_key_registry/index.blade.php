@extends('layouts.app')

@section('title', 'FHE Key Registry')
@section('meta_description', 'Manage FHE key registry')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">FHE Key Registry</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">FHE Key Registry</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('fhe-key-registry.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Key</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Key Registry List</h3>
        <span class="text-sm text-muted">{{ $keys->total() }} total keys</span>
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
                        <th scope="col">Key Generated</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keys as $keyRegistry)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('fhe-key-registry.show', $keyRegistry) }}" title="View" aria-label="View key for {{ $keyRegistry->owner?->name ?? 'user' }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="{{ route('fhe-key-registry.edit', $keyRegistry) }}" title="Edit" aria-label="Edit key for {{ $keyRegistry->owner?->name ?? 'user' }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm js-delete-key"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete key for {{ $keyRegistry->owner?->name ?? 'user' }}"
                                        data-action="{{ route('fhe-key-registry.destroy', $keyRegistry) }}"
                                        data-name="{{ $keyRegistry->owner?->name ?? 'this user' }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="font-medium">{{ $keyRegistry->owner?->name ?? '-' }}</div>
                                <div class="text-sm text-muted">{{ $keyRegistry->owner?->email ?? '-' }}</div>
                            </td>
                            <td>
                                @if($keyRegistry->fheContext)
                                    <div class="font-medium">{{ $keyRegistry->fheContext->name }}</div>
                                    <span class="text-sm text-muted">
                                        {{ $keyRegistry->fheContext->schemeRecord?->library?->name ? $keyRegistry->fheContext->schemeRecord->library->name.' - ' : '' }}{{ $keyRegistry->fheContext->scheme_label }}
                                    </span>
                                @else
                                    <span class="text-sm text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $keyRegistry->key_status === 'active' ? 'success' : ($keyRegistry->key_status === 'revoked' ? 'danger' : 'warning') }}">
                                    {{ ucfirst($keyRegistry->key_status) }}
                                </span>
                            </td>
                            <td>
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
                                @if($canGenerateKey)
                                    <div class="key-generated-cell">
                                        <span class="badge badge-{{ $generationStatusBadge }}">{{ ucfirst($generationStatus) }}</span>
                                        <button
                                            class="btn btn-warning btn-sm js-generate-key"
                                            type="button"
                                            data-action="{{ route('fhe-key-registry.generate', $keyRegistry) }}"
                                            data-name="{{ $keyRegistry->owner?->name ?? 'this user' }}"
                                        >
                                            <span class="btn-icon icon-save" aria-hidden="true"></span>
                                            <span>{{ $generationStatus === 'generated' ? 'Regenerate' : 'Generate' }}</span>
                                        </button>
                                    </div>
                                @else
                                    <span class="badge badge-{{ $generationStatusBadge }}">{{ ucfirst($generationStatus) }}</span>
                                @endif
                            </td>
                            <td class="text-sm text-muted">{{ $keyRegistry->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="6">No FHE keys found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($keys->hasPages())
        <div class="pagination-wrap">
            {{ $keys->links() }}
        </div>
    @endif
</div>

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
            <p class="modal-description" id="generate-key-message">This will generate the keyset for this registry record.</p>
        </div>
        <form method="POST" id="generate-key-form">
            @csrf
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="generate-key-cancel">
                    <span class="btn-icon icon-close" aria-hidden="true"></span>
                    <span>Cancel</span>
                </button>
                <button class="btn btn-warning" type="submit">
                    <span class="btn-icon icon-save" aria-hidden="true"></span>
                    <span>Generate</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="delete-key-modal" role="dialog" aria-modal="true" aria-labelledby="delete-key-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-key-title">Delete FHE key?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-key-message">This removes the key registry metadata record only.</p>
        </div>
        <form method="POST" id="delete-key-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-key-cancel">
                    <span class="btn-icon icon-close" aria-hidden="true"></span>
                    <span>Cancel</span>
                </button>
                <button class="btn btn-danger" type="submit">
                    <span class="btn-icon icon-trash" aria-hidden="true"></span>
                    <span>Delete</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
    (function () {
        const deleteModal = document.getElementById('delete-key-modal');
        const deleteForm = document.getElementById('delete-key-form');
        const deleteMessage = document.getElementById('delete-key-message');
        const deleteCancelBtn = document.getElementById('delete-key-cancel');
        const generateModal = document.getElementById('generate-key-modal');
        const generateForm = document.getElementById('generate-key-form');
        const generateMessage = document.getElementById('generate-key-message');
        const generateCancelBtn = document.getElementById('generate-key-cancel');
        let trigger = null;

        function openDeleteModal(button) {
            trigger = button;
            deleteForm.action = button.dataset.action;
            deleteMessage.textContent = 'Delete key registry metadata for "' + button.dataset.name + '"? This does not delete external key files.';
            deleteModal.classList.add('open');
            deleteModal.setAttribute('aria-hidden', 'false');
            deleteCancelBtn.focus();
        }

        function openGenerateModal(button) {
            trigger = button;
            generateForm.action = button.dataset.action;
            generateMessage.textContent = 'Generate FHE key for "' + button.dataset.name + '"?';
            generateModal.classList.add('open');
            generateModal.setAttribute('aria-hidden', 'false');
            generateCancelBtn.focus();
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('open');
            deleteModal.setAttribute('aria-hidden', 'true');
            deleteForm.removeAttribute('action');
            if (trigger) trigger.focus();
        }

        function closeGenerateModal() {
            generateModal.classList.remove('open');
            generateModal.setAttribute('aria-hidden', 'true');
            generateForm.removeAttribute('action');
            if (trigger) trigger.focus();
        }

        function closeOpenModals() {
            if (deleteModal.classList.contains('open')) closeDeleteModal();
            if (generateModal.classList.contains('open')) closeGenerateModal();
        }

        document.querySelectorAll('.js-delete-key').forEach(function (button) {
            button.addEventListener('click', function () {
                openDeleteModal(button);
            });
        });

        document.querySelectorAll('.js-generate-key').forEach(function (button) {
            button.addEventListener('click', function () {
                openGenerateModal(button);
            });
        });

        deleteCancelBtn.addEventListener('click', closeDeleteModal);
        generateCancelBtn.addEventListener('click', closeGenerateModal);
        deleteModal.addEventListener('click', function (event) {
            if (event.target === deleteModal) closeDeleteModal();
        });
        generateModal.addEventListener('click', function (event) {
            if (event.target === generateModal) closeGenerateModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeOpenModals();
        });
    })();
</script>
@endsection
