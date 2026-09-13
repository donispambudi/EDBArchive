@extends('layouts.app')

@section('title', 'FHE Contexts')
@section('meta_description', 'Manage FHE contexts')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">FHE Contexts</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">FHE Contexts</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('fhe-contexts.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Context</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Context List</h3>
        <span class="text-sm text-muted">{{ $contexts->total() }} total contexts</span>
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
                        <th scope="col">Context Generated</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contexts as $context)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('fhe-contexts.show', $context) }}" title="View" aria-label="View {{ $context->name }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="{{ route('fhe-contexts.edit', $context) }}" title="Edit" aria-label="Edit {{ $context->name }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm js-delete-context"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete {{ $context->name }}"
                                        data-action="{{ route('fhe-contexts.destroy', $context) }}"
                                        data-name="{{ $context->name }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td class="font-medium">{{ $context->name }}</td>
                            <td>{{ $context->schemeRecord?->library?->name ?? '-' }}</td>
                            <td class="font-medium">{{ $context->scheme_label }}</td>
                            <td>
                                @php
                                    $contextStatus = $context->context_status ?? 'pending';
                                    $contextStatusBadge = match ($contextStatus) {
                                        'generated' => 'success',
                                        'failed' => 'danger',
                                        'queued', 'processing' => 'warning',
                                        default => 'secondary',
                                    };
                                    $canGenerateContext = ! in_array($contextStatus, ['queued', 'processing'], true);
                                @endphp
                                @if($canGenerateContext)
                                    <div class="key-generated-cell">
                                        <span class="badge badge-{{ $contextStatusBadge }}">{{ ucfirst($contextStatus) }}</span>
                                        <button
                                            class="btn btn-warning btn-sm js-generate-context"
                                            type="button"
                                            data-action="{{ route('fhe-contexts.generate', $context) }}"
                                            data-name="{{ $context->name }}"
                                        >
                                            <span class="btn-icon icon-save" aria-hidden="true"></span>
                                            <span>{{ $contextStatus === 'generated' ? 'Regenerate' : 'Generate' }}</span>
                                        </button>
                                    </div>
                                @else
                                    <span class="badge badge-{{ $contextStatusBadge }}">{{ ucfirst($contextStatus) }}</span>
                                @endif
                            </td>
                            <td class="text-sm text-muted">{{ $context->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="6">No FHE contexts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($contexts->hasPages())
        <div class="pagination-wrap">
            {{ $contexts->links() }}
        </div>
    @endif
</div>

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
            <p class="modal-description" id="generate-context-message">This will generate the context for this record.</p>
        </div>
        <form method="POST" id="generate-context-form">
            @csrf
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="generate-context-cancel">
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

<div class="modal-backdrop" id="delete-context-modal" role="dialog" aria-modal="true" aria-labelledby="delete-context-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-context-title">Delete FHE context?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-context-message">This action cannot be undone.</p>
        </div>
        <form method="POST" id="delete-context-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-context-cancel">
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
        const deleteModal = document.getElementById('delete-context-modal');
        const deleteForm = document.getElementById('delete-context-form');
        const deleteMessage = document.getElementById('delete-context-message');
        const deleteCancelBtn = document.getElementById('delete-context-cancel');
        const generateModal = document.getElementById('generate-context-modal');
        const generateForm = document.getElementById('generate-context-form');
        const generateMessage = document.getElementById('generate-context-message');
        const generateCancelBtn = document.getElementById('generate-context-cancel');
        let trigger = null;

        function openDeleteModal(button) {
            trigger = button;
            deleteForm.action = button.dataset.action;
            deleteMessage.textContent = 'Delete "' + button.dataset.name + '"? This action cannot be undone.';
            deleteModal.classList.add('open');
            deleteModal.setAttribute('aria-hidden', 'false');
            deleteCancelBtn.focus();
        }

        function openGenerateModal(button) {
            trigger = button;
            generateForm.action = button.dataset.action;
            generateMessage.textContent = 'Generate FHE context for "' + button.dataset.name + '"?';
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

        document.querySelectorAll('.js-delete-context').forEach(function (button) {
            button.addEventListener('click', function () {
                openDeleteModal(button);
            });
        });

        document.querySelectorAll('.js-generate-context').forEach(function (button) {
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
