@extends('layouts.app')

@section('title', 'Schemes')
@section('meta_description', 'Manage schemes')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Schemes</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Schemes</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('schemes.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Scheme</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Scheme List</h3>
        <span class="text-sm text-muted">{{ $schemes->total() }} total schemes</span>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">Scheme Name</th>
                        <th scope="col">Library</th>
                        <th scope="col">Handler</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schemes as $scheme)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('schemes.show', $scheme) }}" title="View" aria-label="View {{ $scheme->scheme_name }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="{{ route('schemes.edit', $scheme) }}" title="Edit" aria-label="Edit {{ $scheme->scheme_name }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm js-delete-scheme"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete {{ $scheme->scheme_name }}"
                                        data-action="{{ route('schemes.destroy', $scheme) }}"
                                        data-name="{{ $scheme->scheme_name }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td class="font-medium">{{ $scheme->scheme_name }}</td>
                            <td>{{ $scheme->library?->name }}</td>
                            <td class="text-sm text-muted">{{ $scheme->handler_name }}</td>
                            <td>
                                <span class="badge badge-{{ $scheme->is_active ? 'success' : 'danger' }}">
                                    {{ $scheme->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="text-sm text-muted">{{ $scheme->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="6">No schemes found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($schemes->hasPages())
        <div class="pagination-wrap">
            {{ $schemes->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" id="delete-scheme-modal" role="dialog" aria-modal="true" aria-labelledby="delete-scheme-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-scheme-title">Delete scheme?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-scheme-message">This action cannot be undone.</p>
        </div>
        <form method="POST" id="delete-scheme-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-scheme-cancel">
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
        const modal = document.getElementById('delete-scheme-modal');
        const form = document.getElementById('delete-scheme-form');
        const message = document.getElementById('delete-scheme-message');
        const cancelBtn = document.getElementById('delete-scheme-cancel');
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

        document.querySelectorAll('.js-delete-scheme').forEach(function (button) {
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
