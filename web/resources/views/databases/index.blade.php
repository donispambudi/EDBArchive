@extends('layouts.app')

@section('title', 'Databases')
@section('meta_description', 'Manage databases')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Databases</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Databases</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('databases.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create Database</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Database List</h3>
        <span class="text-sm text-muted">{{ $databases->total() }} total databases</span>
    </div>
    <div class="card-note">
        <div class="alert alert-primary mb-0">
            To configure column protection defaults, open a database with the view button, then choose Configure Columns.
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">Name</th>
                        <th scope="col">Provider</th>
                        <th scope="col">Tables</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($databases as $database)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('databases.show', $database) }}" title="View" aria-label="View {{ $database->name }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="{{ route('databases.edit', $database) }}" title="Edit" aria-label="Edit {{ $database->name }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm js-delete-database"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete {{ $database->name }}"
                                        data-action="{{ route('databases.destroy', $database) }}"
                                        data-name="{{ $database->name }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td class="font-medium">{{ $database->name }}</td>
                            <td class="text-sm text-muted">{{ $database->provider?->name ?? '-' }}</td>
                            <td><span class="badge badge-secondary">{{ $database->database_tables_count }} selected</span></td>
                            <td class="text-sm text-muted">{{ $database->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="5">No databases found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($databases->hasPages())
        <div class="pagination-wrap">
            {{ $databases->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" id="delete-database-modal" role="dialog" aria-modal="true" aria-labelledby="delete-database-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-database-title">Delete database metadata?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-database-message">This removes the EDBArchive metadata record only. The local MariaDB database is not dropped.</p>
        </div>
        <form method="POST" id="delete-database-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-database-cancel">
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
        const modal = document.getElementById('delete-database-modal');
        const form = document.getElementById('delete-database-form');
        const message = document.getElementById('delete-database-message');
        const cancelBtn = document.getElementById('delete-database-cancel');
        let trigger = null;

        function openModal(button) {
            trigger = button;
            form.action = button.dataset.action;
            message.textContent = 'Delete metadata for "' + button.dataset.name + '"? The local MariaDB database is not dropped.';
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

        document.querySelectorAll('.js-delete-database').forEach(function (button) {
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
