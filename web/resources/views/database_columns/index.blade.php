@extends('layouts.app')

@section('title', 'Database Columns')
@section('meta_description', 'Manage database column protection metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Database Columns</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Database Columns</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('database-columns.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Column</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Column List</h3>
        <span class="text-sm text-muted">{{ $columns->total() }} total columns</span>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">Column</th>
                        <th scope="col">Database Table</th>
                        <th scope="col">Encryption</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($columns as $databaseColumn)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('database-columns.show', $databaseColumn) }}" title="View" aria-label="View {{ $databaseColumn->column_name }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="{{ route('database-columns.edit', $databaseColumn) }}" title="Edit" aria-label="Edit {{ $databaseColumn->column_name }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm js-delete-column"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete {{ $databaseColumn->column_name }}"
                                        data-action="{{ route('database-columns.destroy', $databaseColumn) }}"
                                        data-name="{{ $databaseColumn->column_name }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td class="font-medium">{{ $databaseColumn->column_name }}</td>
                            <td>
                                <div class="font-medium">{{ $databaseColumn->databaseTable?->table_name ?? '-' }}</div>
                                <div class="text-sm text-muted">{{ $databaseColumn->databaseTable?->database?->name ?? '-' }}</div>
                            </td>
                            <td>
                                <span class="badge badge-{{ str_starts_with($databaseColumn->default_encryption_type, 'fhe-') ? 'primary' : 'secondary' }}">
                                    {{ $databaseColumn->default_encryption_type }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="4">No database columns found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($columns->hasPages())
        <div class="pagination-wrap">
            {{ $columns->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" id="delete-column-modal" role="dialog" aria-modal="true" aria-labelledby="delete-column-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-column-title">Delete column metadata?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-column-message">This removes the column configuration only. Actual data is not stored here.</p>
        </div>
        <form method="POST" id="delete-column-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-column-cancel">
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
        const modal = document.getElementById('delete-column-modal');
        const form = document.getElementById('delete-column-form');
        const message = document.getElementById('delete-column-message');
        const cancelBtn = document.getElementById('delete-column-cancel');
        let trigger = null;

        function openModal(button) {
            trigger = button;
            form.action = button.dataset.action;
            message.textContent = 'Delete column metadata for "' + button.dataset.name + '"? Actual data is not stored here.';
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

        document.querySelectorAll('.js-delete-column').forEach(function (button) {
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
