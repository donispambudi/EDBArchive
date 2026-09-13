@extends('layouts.app')

@section('title', 'Users')
@section('meta_description', 'Manage users')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Users</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Users</span>
        </nav>
    </div>
    <a class="btn btn-primary btn-wide" href="{{ route('users.create') }}">
        <span class="btn-icon icon-plus" aria-hidden="true"></span>
        <span>Create User</span>
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">User List</h3>
        <span class="text-sm text-muted">{{ $users->total() }} total users</span>
    </div>

    <div class="card-body p-0">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Actions</th>
                        <th scope="col">Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Role</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    <a class="btn btn-view btn-sm" href="{{ route('users.show', $user) }}" title="View" aria-label="View {{ $user->name }}">
                                        <span class="btn-icon icon-eye" aria-hidden="true"></span>
                                        <span class="btn-label">View</span>
                                    </a>
                                    <a class="btn btn-warning btn-sm" href="{{ route('users.edit', $user) }}" title="Edit" aria-label="Edit {{ $user->name }}">
                                        <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        <span class="btn-label">Edit</span>
                                    </a>
                                    <button
                                        class="btn btn-danger btn-sm js-delete-user"
                                        type="button"
                                        title="Delete"
                                        aria-label="Delete {{ $user->name }}"
                                        data-action="{{ route('users.destroy', $user) }}"
                                        data-name="{{ $user->name }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                        <span class="btn-label">Delete</span>
                                    </button>
                                </div>
                            </td>
                            <td class="font-medium">{{ $user->name }}</td>
                            <td class="text-sm text-muted">{{ $user->email }}</td>
                            <td>
                                <span class="badge badge-{{ $user->role === 'Data Provider' ? 'info' : 'secondary' }}">
                                    {{ $user->role }}
                                </span>
                            </td>
                            <td class="text-sm text-muted">{{ $user->created_at?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-muted" colspan="5">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($users->hasPages())
        <div class="pagination-wrap">
            {{ $users->links() }}
        </div>
    @endif
</div>

<div class="modal-backdrop" id="delete-user-modal" role="dialog" aria-modal="true" aria-labelledby="delete-user-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-user-title">Delete user?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-user-message">This action cannot be undone.</p>
        </div>
        <form method="POST" id="delete-user-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-user-cancel">
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
        const modal = document.getElementById('delete-user-modal');
        const form = document.getElementById('delete-user-form');
        const message = document.getElementById('delete-user-message');
        const cancelBtn = document.getElementById('delete-user-cancel');
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

        document.querySelectorAll('.js-delete-user').forEach(function (button) {
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
