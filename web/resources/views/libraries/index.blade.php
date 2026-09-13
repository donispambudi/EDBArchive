@extends('layouts.app')

@section('title', 'Libraries')
@section('meta_description', 'Manage libraries')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Libraries</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Libraries</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('libraries.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Library</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Library List</h3>
        <span class="text-sm text-muted">{{ $libraries->total() }} total libraries</span>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($libraries as $library)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('libraries.show', $library) }}" title="View" aria-label="View {{ $library->name }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="{{ route('libraries.edit', $library) }}" title="Edit" aria-label="Edit {{ $library->name }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm js-delete-library"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete {{ $library->name }}"
                                        data-action="{{ route('libraries.destroy', $library) }}"
                                        data-name="{{ $library->name }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td class="font-medium">{{ $library->name }}</td>
                            <td>
                                <span class="badge badge-{{ $library->is_active ? 'success' : 'danger' }}">
                                    {{ $library->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-sm text-muted">{{ $library->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="4">No libraries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($libraries->hasPages())
        <div class="pagination-wrap">
            {{ $libraries->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" id="delete-library-modal" role="dialog" aria-modal="true" aria-labelledby="delete-library-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-library-title">Delete library?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-library-message">This action cannot be undone.</p>
        </div>
        <form method="POST" id="delete-library-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-library-cancel">
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
        const modal = document.getElementById('delete-library-modal');
        const form = document.getElementById('delete-library-form');
        const message = document.getElementById('delete-library-message');
        const cancelBtn = document.getElementById('delete-library-cancel');
        let trigger = null;

        function openModal(button) {
            trigger = button;
            form.action = button.dataset.action;
            message.textContent = 'Delete "' + button.dataset.name + '"? This action cannot be undone.';
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

        document.querySelectorAll('.js-delete-library').forEach(function (button) {
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
</script>
@endsection
