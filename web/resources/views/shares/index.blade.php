@extends('layouts.app')

@section('title', 'Shares')
@section('meta_description', 'Manage shares and exports')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Shares</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Shares</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('shares.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Share</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Share List</h3>
        <span class="text-sm text-muted">{{ $shares->total() }} total shares</span>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">Database</th>
                        <th scope="col">Owner</th>
                        <th scope="col">Recipient</th>
                        <th scope="col">Release</th>
                        <th scope="col">Status</th>
                        <th scope="col">Bundle Generated</th>
                        <th scope="col">Items</th>
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
                                    <a class="btn btn-warning btn-sm" href="{{ route('shares.edit', $share) }}" title="Edit" aria-label="Edit share #{{ $share->id }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    @if($share->bundle_ref)
                                        <a class="btn btn-success btn-sm" href="{{ route('shares.bundle.download', $share) }}" title="Download bundle" aria-label="Download bundle for share #{{ $share->id }}">
                                            <span class="btn-icon icon-download" aria-hidden="true"></span>
                                            <span class="btn-label">Download</span>
                                        </a>
                                    @endif
                                    <button
                                        class="btn btn-danger btn-sm js-delete-share"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete share #{{ $share->id }}"
                                        data-action="{{ route('shares.destroy', $share) }}"
                                        data-name="#{{ $share->id }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td class="font-medium">{{ $share->database?->name ?? '-' }}</td>
                            <td class="text-sm text-muted">{{ $share->owner?->name ?? '-' }}</td>
                            <td class="text-sm text-muted">{{ $share->recipient?->name ?? '-' }}</td>
                            <td class="text-sm text-muted">{{ $share->release_version ?: '-' }}</td>
                            <td><span class="badge badge-{{ $share->status === 'released' ? 'success' : ($share->status === 'revoked' ? 'danger' : 'secondary') }}">{{ $share->status }}</span></td>
                            <td>
                                @if($share->bundle_ref)
                                    <span class="badge badge-success">Generated</span>
                                @elseif($share->bundle_status === 'processing')
                                    <span class="badge badge-warning">Processing</span>
                                @else
                                    <div class="key-generated-cell">
                                        <span class="badge badge-secondary">Pending</span>
                                        <button
                                            class="btn btn-warning btn-sm js-generate-bundle"
                                            type="button"
                                            data-action="{{ route('shares.bundle.generate', $share) }}"
                                            data-name="#{{ $share->id }}"
                                        >
                                            <span class="btn-icon icon-save" aria-hidden="true"></span>
                                            <span>Generate</span>
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="text-sm text-muted">{{ $share->items_count }} items · {{ $share->history_count }} events</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="8">No shares found.</td>
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

<div class="modal-backdrop" id="delete-share-modal" role="dialog" aria-modal="true" aria-labelledby="delete-share-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-share-title">Delete share?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-share-message">This action cannot be undone.</p>
        </div>
        <form method="POST" id="delete-share-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-share-cancel">
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

<div class="modal-backdrop" id="generate-bundle-modal" role="dialog" aria-modal="true" aria-labelledby="generate-bundle-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon warning" aria-hidden="true">
                <span class="modal-icon-mark icon-save"></span>
            </div>
            <div>
                <h2 class="modal-title" id="generate-bundle-title">Generate share bundle?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="generate-bundle-message">This will start bundle generation for this share.</p>
        </div>
        <form method="POST" id="generate-bundle-form">
            @csrf
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="generate-bundle-cancel">
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
@endsection

@section('js')
<script>
    (function () {
        const modal = document.getElementById('delete-share-modal');
        const form = document.getElementById('delete-share-form');
        const message = document.getElementById('delete-share-message');
        const cancelBtn = document.getElementById('delete-share-cancel');
        let trigger = null;

        function openModal(button) {
            trigger = button;
            form.action = button.dataset.action;
            message.textContent = 'Delete share ' + button.dataset.name + '? This action cannot be undone.';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            cancelBtn.focus();
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            form.removeAttribute('action');
            if (trigger) trigger.focus();
        }

        document.querySelectorAll('.js-delete-share').forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(button);
            });
        });

        cancelBtn.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('open')) {
                closeModal();
            }
        });
    })();

    (function () {
        const modal = document.getElementById('generate-bundle-modal');
        const form = document.getElementById('generate-bundle-form');
        const message = document.getElementById('generate-bundle-message');
        const cancelBtn = document.getElementById('generate-bundle-cancel');
        let trigger = null;

        function openModal(button) {
            trigger = button;
            form.action = button.dataset.action;
            message.textContent = 'Generate bundle for share ' + button.dataset.name + '?';
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            cancelBtn.focus();
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            form.removeAttribute('action');
            if (trigger) trigger.focus();
        }

        document.querySelectorAll('.js-generate-bundle').forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(button);
            });
        });

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
