@extends('layouts.app')

@section('title', 'Share Detail')
@section('meta_description', 'View share/export metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Share Detail</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('shares.index') }}">Shares</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">#{{ $share->id }}</span>
        </nav>
    </div>
</div>

<div class="card user-form-card mb-4">
    <div class="card-body">
        <div class="user-form-grid">
            <section class="user-form-section" aria-labelledby="share-detail-section-title">
                <h3 class="user-form-section-title" id="share-detail-section-title">Share Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">Database</div>
                        <div class="form-control-plain">{{ $share->database?->name ?? '-' }}</div>
                        <div class="form-hint">Database being shared.</div>
                    </div>
                    <div class="form-group">
                        <div class="form-label">Owner</div>
                        <div class="form-control-plain">{{ $share->owner?->name ?? '-' }}</div>
                        <div class="form-hint">Provider/owner.</div>
                    </div>
                    <div class="form-group">
                        <div class="form-label">Recipient</div>
                        <div class="form-control-plain">{{ $share->recipient?->name ?? '-' }}</div>
                        <div class="form-hint">Third-party recipient.</div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <div class="form-label">Release Version</div>
                        <div class="form-control-plain">{{ $share->release_version ?: '-' }}</div>
                        <div class="form-hint">Concrete release/package version.</div>
                    </div>
                    <div class="form-group">
                        <div class="form-label">Status</div>
                        <div class="form-control-plain badge-only">
                            <span class="badge badge-{{ $share->status === 'released' ? 'success' : ($share->status === 'revoked' ? 'danger' : 'secondary') }}">{{ $share->status }}</span>
                        </div>
                        <div class="form-hint">Share lifecycle status.</div>
                    </div>
                    <div class="form-group">
                        <div class="form-label">Bundle Generated</div>
                        <div class="form-control-plain badge-only">
                            @if($share->bundle_ref)
                                <span class="badge badge-success">Generated</span>
                            @elseif($share->bundle_status === 'processing')
                                <span class="badge badge-warning">Processing</span>
                            @else
                                <span class="badge badge-secondary">Pending</span>
                            @endif
                        </div>
                        <div class="form-hint">Shows whether the generated bundle filename is available.</div>
                    </div>
                    <div class="form-group">
                        <div class="form-label">Bundle Hash</div>
                        <div class="form-control-plain hash-value">
                            <code class="hash-value-text" id="bundle-hash-value">{{ $share->bundle_hash ?: '-' }}</code>
                            @if($share->bundle_hash)
                                <button class="btn btn-soft btn-sm hash-copy-button" type="button" data-copy-target="bundle-hash-value" title="Copy bundle hash" aria-label="Copy bundle hash">
                                    <span class="btn-icon icon-copy" aria-hidden="true"></span>
                                </button>
                            @endif
                        </div>
                        <div class="form-hint">Hash used to verify a leaked/generated bundle.</div>
                    </div>
                </div>
            </section>
        </div>

        <div class="user-form-actions">
            <a class="btn btn-soft btn-wide" href="{{ route('shares.index') }}">
                <span class="btn-icon icon-back" aria-hidden="true"></span>
                <span>Back</span>
            </a>
            <a class="btn btn-warning btn-wide" href="{{ route('shares.edit', $share) }}">
                <span class="btn-icon icon-edit" aria-hidden="true"></span>
                <span>Edit</span>
            </a>
            @if(! $share->bundle_ref && $share->bundle_status !== 'processing')
                <button class="btn btn-warning btn-wide js-generate-bundle" type="button">
                    <span class="btn-icon icon-save" aria-hidden="true"></span>
                    <span>Generate Bundle</span>
                </button>
            @endif
        </div>
    </div>
</div>

@if(! $share->bundle_ref && $share->bundle_status !== 'processing')
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
                <p class="modal-description">Generate bundle for share #{{ $share->id }}?</p>
            </div>
            <form method="POST" action="{{ route('shares.bundle.generate', $share) }}">
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
@endif

<div class="card mb-4">
    <div class="card-header">
        <h3 class="card-title">Share Items</h3>
        <span class="text-sm text-muted">{{ $share->items->count() }} items</span>
    </div>
    <div class="card-body">
        @error('items')
            <div class="field-error mb-3">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('shares.items.store', $share) }}" class="share-item-form">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="database_table_id">Database Table</label>
                    <select class="form-control is-select" id="database_table_id" name="database_table_id" required>
                        <option value="">Select table</option>
                        @foreach($databaseTables as $table)
                            <option value="{{ $table->id }}">{{ $table->table_name }}</option>
                        @endforeach
                    </select>
                    @error('database_table_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="database_column_id">Database Column</label>
                    <select class="form-control is-select" id="database_column_id" name="database_column_id" required>
                        <option value="">None selected</option>
                        @foreach($databaseColumns as $column)
                            @php
                                $columnEncryption = 'plaintext';
                                if (str_starts_with($column->default_encryption_type, 'fhe-')) {
                                    $candidateEncryption = 'fhe-'.\Illuminate\Support\Str::slug($column->fheContext?->scheme_label ?? 'secure', '-');
                                    $columnEncryption = in_array($column->default_encryption_type, \App\Models\ShareItem::ENCRYPTION_TYPES, true)
                                        ? $column->default_encryption_type
                                        : (in_array($candidateEncryption, \App\Models\ShareItem::ENCRYPTION_TYPES, true) ? $candidateEncryption : 'fhe-secure');
                                }
                            @endphp
                            <option
                                value="{{ $column->id }}"
                                data-table-id="{{ $column->database_table_id }}"
                                data-encryption="{{ $columnEncryption }}"
                                data-fhe-context-id="{{ $column->fhe_context_id }}"
                                data-key-registry-id="{{ $recipientKeyIdsByContext[$column->fhe_context_id] ?? '' }}"
                            >{{ $column->databaseTable?->table_name }}.{{ $column->column_name }}</option>
                        @endforeach
                    </select>
                    @error('database_column_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="encryption">Encryption</label>
                    <select class="form-control is-select" id="encryption" name="encryption" required>
                        @foreach($encryptionTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('encryption')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="fhe_context_id">FHE Context</label>
                    <select class="form-control is-select js-share-fhe-field" id="fhe_context_id" name="fhe_context_id">
                        <option value="">No FHE context</option>
                        @foreach($fheContexts as $context)
                            <option value="{{ $context->id }}">{{ $context->name }} ({{ $context->scheme_label }})</option>
                        @endforeach
                    </select>
                    @error('fhe_context_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="key_registry_id">Recipient FHE Key</label>
                    <select class="form-control is-select js-share-fhe-field" id="key_registry_id" name="key_registry_id">
                        <option value="">No recipient key</option>
                        @foreach($recipientKeyRegistries as $keyRegistry)
                            <option value="{{ $keyRegistry->id }}">#{{ $keyRegistry->id }} - {{ $keyRegistry->fheContext?->name ?? 'Unknown context' }} ({{ $keyRegistry->key_status }})</option>
                        @endforeach
                    </select>
                    <div class="form-hint">Only keys owned by {{ $share->recipient?->name ?? 'the recipient' }} are listed.</div>
                    @error('key_registry_id')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="share-item-actions">
                <button class="btn btn-primary btn-wide" type="submit">
                    <span class="btn-icon icon-plus" aria-hidden="true"></span>
                    <span>Add Item</span>
                </button>
            </div>
        </form>

        <div class="share-bulk-actions">
            <form method="POST" action="{{ route('shares.items.bulk-store', $share) }}" class="share-bulk-form" id="share-bulk-source-form">
                @csrf
                @if($bulkFheContexts->isNotEmpty())
                    <div class="share-bulk-key-list">
                        @foreach($bulkFheContexts as $context)
                            @php
                                $contextKeys = $recipientKeysByContext->get($context->id, collect());
                            @endphp
                            <div class="share-bulk-key">
                                <label class="form-label" for="bulk_key_registry_id_{{ $context->id }}">{{ $context->name }} Key</label>
                                <select class="form-control is-select js-bulk-key-field" id="bulk_key_registry_id_{{ $context->id }}" name="bulk_keys[{{ $context->id }}]" required>
                                    <option value="">Select recipient key</option>
                                    @foreach($contextKeys as $keyRegistry)
                                        <option value="{{ $keyRegistry->id }}">
                                            #{{ $keyRegistry->id }} - {{ $keyRegistry->fheContext?->scheme_label ?? 'Unknown scheme' }} ({{ $keyRegistry->key_status }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-hint">{{ $context->scheme_label }}</div>
                                @if($contextKeys->isEmpty())
                                    <div class="field-error">Recipient has no key for this context.</div>
                                @endif
                                @error('bulk_keys.'.$context->id)<div class="field-error">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                @endif
                @if($hasMissingBulkFheContexts)
                    <div class="field-error share-bulk-message">Some configured FHE columns do not have an FHE context.</div>
                @endif
                <button class="btn btn-soft btn-wide {{ $bulkFheContexts->isNotEmpty() ? 'share-bulk-button' : '' }} js-confirm-add-all" type="button" @disabled($hasMissingBulkFheContexts || ($hasConfiguredFheColumns && $hasMissingBulkRecipientKeys))>
                    <span class="btn-icon icon-plus" aria-hidden="true"></span>
                    <span>Add All Configured Columns</span>
                </button>
            </form>

            <form method="POST" action="{{ route('shares.items.destroy-all', $share) }}" class="share-delete-all-form" id="share-delete-all-source-form">
                @csrf
                @method('DELETE')
                <button class="btn btn-danger btn-wide {{ $bulkFheContexts->isNotEmpty() ? 'share-bulk-button' : '' }} js-confirm-delete-all" type="button" @disabled($share->items->isEmpty())>
                    <span class="btn-icon icon-trash" aria-hidden="true"></span>
                    <span>Delete All Columns</span>
                </button>
            </form>
        </div>

        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th class="sticky-action text-center" scope="col">Action</th>
                        <th scope="col">Table</th>
                        <th scope="col">Column</th>
                        <th scope="col">Encryption</th>
                        <th scope="col">FHE Context</th>
                        <th scope="col">Recipient Key</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $previousShareItemTableId = null;
                    @endphp
                    @forelse($share->items as $item)
                        @php
                            $isPrimaryShareItem = $primaryShareItemIds->contains($item->id);
                            $shareItemTableId = $item->database_table_id ?: 'none';
                            $shareItemTableName = $item->databaseTable?->table_name ?? 'No table';
                            $shareItemColumnName = $item->databaseColumn?->column_name ?? 'None';
                            $isNewShareItemTable = $previousShareItemTableId !== $shareItemTableId;
                        @endphp
                        @if($isNewShareItemTable)
                            <tr class="share-item-table-group">
                                <td colspan="6">
                                    <span class="share-item-table-name">{{ $shareItemTableName }}</span>
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <td class="sticky-action">
                                <div class="actions-cell">
                                    @unless($isPrimaryShareItem)
                                        <button
                                            class="btn btn-warning btn-sm js-edit-share-item"
                                            type="button"
                                            title="Edit"
                                            aria-label="Edit share item"
                                            data-item-id="{{ $item->id }}"
                                            data-action="{{ route('shares.items.update', [$share, $item]) }}"
                                            data-table="{{ $shareItemTableName }}"
                                            data-column="{{ $shareItemColumnName }}"
                                            data-encryption="{{ $item->encryption }}"
                                            data-fhe-context-id="{{ $item->fhe_context_id }}"
                                            data-key-registry-id="{{ $item->key_registry_id }}"
                                        >
                                            <span class="btn-icon icon-edit" aria-hidden="true"></span>
                                        </button>
                                    @endunless
                                    <button
                                        class="btn btn-danger btn-sm js-confirm-delete-item"
                                        type="button"
                                        title="{{ $isPrimaryShareItem ? 'Delete table columns' : 'Delete' }}"
                                        aria-label="{{ $isPrimaryShareItem ? 'Delete all share items for this table' : 'Delete share item' }}"
                                        data-action="{{ route('shares.items.destroy', [$share, $item]) }}"
                                        data-table="{{ $shareItemTableName }}"
                                        data-column="{{ $shareItemColumnName }}"
                                        data-primary="{{ $isPrimaryShareItem ? '1' : '0' }}"
                                    >
                                        <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </td>
                            <td>{{ $shareItemTableName }}</td>
                            <td>
                                {{ $shareItemColumnName }}
                                @if($isPrimaryShareItem)
                                    <span class="badge badge-secondary">Primary</span>
                                @endif
                            </td>
                            <td>
                                @if($item->encryption === 'plaintext')
                                    <span class="badge badge-secondary">plaintext</span>
                                @else
                                    <span class="badge badge-success">{{ $item->encryption }}</span>
                                @endif
                            </td>
                            <td>{{ $item->fheContext?->name ?? '-' }}</td>
                            <td>
                                @if($item->keyRegistry)
                                    #{{ $item->keyRegistry->id }} - {{ $item->keyRegistry->owner?->name ?? '-' }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @php
                            $previousShareItemTableId = $shareItemTableId;
                        @endphp
                    @empty
                        <tr><td class="text-center text-muted" colspan="6">No share items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Share History</h3>
        <span class="text-sm text-muted">{{ $share->history->count() }} events</span>
    </div>
    <div class="card-body">
        <div class="table-wrapper">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th scope="col">Event</th>
                        <th scope="col">Original Owner</th>
                        <th scope="col">Shared By</th>
                        <th scope="col">Shared To</th>
                        <th scope="col">Database</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($share->history as $history)
                        <tr>
                            <td><span class="badge badge-secondary">{{ $history->action }}</span></td>
                            <td>{{ $history->originalOwner?->name ?? '-' }}</td>
                            <td>{{ $history->sharedBy?->name ?? '-' }}</td>
                            <td>{{ $history->sharedTo?->name ?? '-' }}</td>
                            <td>{{ $history->database?->name ?? '-' }}</td>
                            <td class="text-sm text-muted">{{ $history->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted" colspan="6">No share history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="edit-share-item-modal" role="dialog" aria-modal="true" aria-labelledby="edit-share-item-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon warning" aria-hidden="true">
                <span class="modal-icon-mark icon-edit"></span>
            </div>
            <div>
                <h2 class="modal-title" id="edit-share-item-title">Edit share item</h2>
            </div>
        </div>
        <form method="POST" id="edit-share-item-form">
            @csrf
            @method('PUT')
            <input id="editing_item_id" name="editing_item_id" type="hidden" value="{{ old('editing_item_id') }}">
            <div class="modal-body">
                <div class="form-group">
                    <div class="form-label">Database Column</div>
                    <div class="form-control-plain" id="edit-share-item-column">-</div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_encryption">Encryption</label>
                    <select class="form-control is-select" id="edit_encryption" name="encryption" required>
                        @foreach($encryptionTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('encryption', 'updateShareItem')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label" for="edit_fhe_context_id">FHE Context</label>
                    <select class="form-control is-select" id="edit_fhe_context_id" name="fhe_context_id">
                        <option value="">No FHE context</option>
                        @foreach($fheContexts as $context)
                            <option value="{{ $context->id }}">{{ $context->name }} ({{ $context->scheme_label }})</option>
                        @endforeach
                    </select>
                    @error('fhe_context_id', 'updateShareItem')<div class="field-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group mb-0">
                    <label class="form-label" for="edit_key_registry_id">Recipient FHE Key</label>
                    <select class="form-control is-select" id="edit_key_registry_id" name="key_registry_id">
                        <option value="">No recipient key</option>
                        @foreach($recipientKeyRegistries as $keyRegistry)
                            <option value="{{ $keyRegistry->id }}" data-context-id="{{ $keyRegistry->fhe_context_id }}">
                                #{{ $keyRegistry->id }} - {{ $keyRegistry->fheContext?->name ?? 'Unknown context' }} ({{ $keyRegistry->key_status }})
                            </option>
                        @endforeach
                    </select>
                    <div class="form-hint">Only keys owned by {{ $share->recipient?->name ?? 'the recipient' }} are listed.</div>
                    @error('key_registry_id', 'updateShareItem')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="edit-share-item-cancel">
                    <span class="btn-icon icon-close" aria-hidden="true"></span>
                    <span>Cancel</span>
                </button>
                <button class="btn btn-primary" type="submit">
                    <span class="btn-icon icon-save" aria-hidden="true"></span>
                    <span>Save</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="add-all-items-modal" role="dialog" aria-modal="true" aria-labelledby="add-all-items-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-plus"></span>
            </div>
            <div>
                <h2 class="modal-title" id="add-all-items-title">Add all configured columns?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description">This adds every configured database column to this share. Existing share items will be skipped.</p>
        </div>
        <form method="POST" action="{{ route('shares.items.bulk-store', $share) }}" id="add-all-items-form">
            @csrf
            <div id="add-all-bulk-key-inputs"></div>
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="add-all-items-cancel">
                    <span class="btn-icon icon-close" aria-hidden="true"></span>
                    <span>Cancel</span>
                </button>
                <button class="btn btn-primary" type="submit">
                    <span class="btn-icon icon-plus" aria-hidden="true"></span>
                    <span>Add All</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="delete-all-items-modal" role="dialog" aria-modal="true" aria-labelledby="delete-all-items-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-all-items-title">Delete all columns?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description">This removes every column from this share.</p>
        </div>
        <form method="POST" action="{{ route('shares.items.destroy-all', $share) }}" id="delete-all-items-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-all-items-cancel">
                    <span class="btn-icon icon-close" aria-hidden="true"></span>
                    <span>Cancel</span>
                </button>
                <button class="btn btn-danger" type="submit">
                    <span class="btn-icon icon-trash" aria-hidden="true"></span>
                    <span>Delete All</span>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="delete-item-modal" role="dialog" aria-modal="true" aria-labelledby="delete-item-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-icon" aria-hidden="true">
                <span class="modal-icon-mark icon-trash"></span>
            </div>
            <div>
                <h2 class="modal-title" id="delete-item-title">Delete column?</h2>
            </div>
        </div>
        <div class="modal-body">
            <p class="modal-description" id="delete-item-message">This action cannot be undone.</p>
        </div>
        <form method="POST" id="delete-item-form">
            @csrf
            @method('DELETE')
            <div class="modal-actions">
                <button class="btn btn-soft" type="button" id="delete-item-cancel">
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
        const copyButton = document.querySelector('[data-copy-target="bundle-hash-value"]');

        if (!copyButton) return;

        copyButton.addEventListener('click', async function () {
            const target = document.getElementById(copyButton.dataset.copyTarget);
            const value = target?.textContent.trim();

            if (!value) return;

            try {
                if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(value);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = value;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    textarea.remove();
                }

                copyButton.title = 'Copied';
                copyButton.setAttribute('aria-label', 'Bundle hash copied');
                window.setTimeout(function () {
                    copyButton.title = 'Copy bundle hash';
                    copyButton.setAttribute('aria-label', 'Copy bundle hash');
                }, 1500);
            } catch (error) {
                copyButton.title = 'Copy failed';
            }
        });
    })();

    (function () {
        const tableSelect = document.getElementById('database_table_id');
        const columnSelect = document.getElementById('database_column_id');
        const encryptionSelect = document.getElementById('encryption');
        const fheContextSelect = document.getElementById('fhe_context_id');
        const keyRegistrySelect = document.getElementById('key_registry_id');
        const fheFields = document.querySelectorAll('.js-share-fhe-field');

        if (!tableSelect || !columnSelect) return;

        function syncColumns() {
            const tableId = tableSelect.value;
            columnSelect.querySelectorAll('option[data-table-id]').forEach(function (option) {
                const visible = option.dataset.tableId === tableId;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) columnSelect.value = '';
            });
            syncColumnDefaults();
        }

        function syncColumnDefaults() {
            const option = columnSelect.selectedOptions[0];

            if (!option || !option.dataset.tableId) {
                syncFheFields();
                return;
            }

            encryptionSelect.value = option.dataset.encryption || 'plaintext';
            fheContextSelect.value = option.dataset.fheContextId || '';
            keyRegistrySelect.value = option.dataset.keyRegistryId || '';
            syncFheFields();
        }

        function syncFheFields() {
            const isFhe = encryptionSelect.value.startsWith('fhe-');

            fheFields.forEach(function (field) {
                field.required = isFhe;
                field.disabled = !isFhe;
                if (!isFhe) field.value = '';
            });
        }

        tableSelect.addEventListener('change', syncColumns);
        columnSelect.addEventListener('change', syncColumnDefaults);
        encryptionSelect.addEventListener('change', syncFheFields);
        syncColumns();
        syncFheFields();
    })();

    (function () {
        const buttons = document.querySelectorAll('.js-edit-share-item');
        const modal = document.getElementById('edit-share-item-modal');
        const form = document.getElementById('edit-share-item-form');
        const cancelButton = document.getElementById('edit-share-item-cancel');
        const itemIdInput = document.getElementById('editing_item_id');
        const columnDisplay = document.getElementById('edit-share-item-column');
        const encryptionSelect = document.getElementById('edit_encryption');
        const contextSelect = document.getElementById('edit_fhe_context_id');
        const keySelect = document.getElementById('edit_key_registry_id');
        const hasValidationErrors = {{ $errors->updateShareItem->any() ? 'true' : 'false' }};
        const failedItemId = @json((string) old('editing_item_id', ''));
        let trigger = null;

        if (!buttons.length || !modal || !form || !cancelButton || !itemIdInput || !columnDisplay || !encryptionSelect || !contextSelect || !keySelect) return;

        function syncFheFields() {
            const isFhe = encryptionSelect.value.startsWith('fhe-');
            const contextId = contextSelect.value;

            contextSelect.required = isFhe;
            contextSelect.disabled = !isFhe;
            keySelect.required = isFhe;
            keySelect.disabled = !isFhe;

            if (!isFhe) {
                contextSelect.value = '';
                keySelect.value = '';
                return;
            }

            keySelect.querySelectorAll('option[data-context-id]').forEach(function (option) {
                const matchesContext = option.dataset.contextId === contextId;
                option.hidden = !matchesContext;
                option.disabled = !matchesContext;
            });

            const selectedKey = keySelect.selectedOptions[0];
            if (selectedKey?.dataset.contextId && selectedKey.dataset.contextId !== contextId) {
                keySelect.value = '';
            }
        }

        function openModal(button) {
            trigger = button;
            form.action = button.dataset.action;
            itemIdInput.value = button.dataset.itemId;
            columnDisplay.textContent = (button.dataset.table || '-') + '.' + (button.dataset.column || '-');
            encryptionSelect.value = button.dataset.encryption || 'plaintext';
            contextSelect.value = button.dataset.fheContextId || '';
            keySelect.value = button.dataset.keyRegistryId || '';
            syncFheFields();
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            encryptionSelect.focus();
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            form.removeAttribute('action');
            if (trigger) trigger.focus();
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(button);
            });
        });

        encryptionSelect.addEventListener('change', syncFheFields);
        contextSelect.addEventListener('change', syncFheFields);
        cancelButton.addEventListener('click', closeModal);
        modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modal.classList.contains('open')) closeModal();
        });

        if (hasValidationErrors && failedItemId) {
            const failedButton = Array.from(buttons).find(function (button) {
                return button.dataset.itemId === failedItemId;
            });

            if (failedButton) {
                openModal(failedButton);
                encryptionSelect.value = @json(old('encryption', 'plaintext'));
                contextSelect.value = @json((string) old('fhe_context_id', ''));
                keySelect.value = @json((string) old('key_registry_id', ''));
                syncFheFields();
            }
        }
    })();

    (function () {
        const addAllButton = document.querySelector('.js-confirm-add-all');
        const addAllSourceForm = document.getElementById('share-bulk-source-form');
        const bulkKeyFields = document.querySelectorAll('.js-bulk-key-field');
        const addAllModal = document.getElementById('add-all-items-modal');
        const addAllCancel = document.getElementById('add-all-items-cancel');
        const addAllKeyInputs = document.getElementById('add-all-bulk-key-inputs');
        const deleteAllButton = document.querySelector('.js-confirm-delete-all');
        const deleteAllModal = document.getElementById('delete-all-items-modal');
        const deleteAllCancel = document.getElementById('delete-all-items-cancel');
        const deleteItemButtons = document.querySelectorAll('.js-confirm-delete-item');
        const deleteItemModal = document.getElementById('delete-item-modal');
        const deleteItemForm = document.getElementById('delete-item-form');
        const deleteItemMessage = document.getElementById('delete-item-message');
        const deleteItemCancel = document.getElementById('delete-item-cancel');
        let trigger = null;

        function openModal(modal, button) {
            trigger = button;
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
        }

        function closeModal(modal) {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            if (trigger) trigger.focus();
        }

        if (addAllButton && addAllSourceForm && addAllModal) {
            addAllButton.addEventListener('click', function () {
                if (!addAllSourceForm.reportValidity()) return;
                addAllKeyInputs.innerHTML = '';
                bulkKeyFields.forEach(function (field) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = field.name;
                    input.value = field.value;
                    addAllKeyInputs.appendChild(input);
                });
                openModal(addAllModal, addAllButton);
                addAllCancel.focus();
            });

            addAllCancel.addEventListener('click', function () {
                closeModal(addAllModal);
            });

            addAllModal.addEventListener('click', function (event) {
                if (event.target === addAllModal) closeModal(addAllModal);
            });
        }

        if (deleteAllButton && deleteAllModal) {
            deleteAllButton.addEventListener('click', function () {
                openModal(deleteAllModal, deleteAllButton);
                deleteAllCancel.focus();
            });

            deleteAllCancel.addEventListener('click', function () {
                closeModal(deleteAllModal);
            });

            deleteAllModal.addEventListener('click', function (event) {
                if (event.target === deleteAllModal) closeModal(deleteAllModal);
            });
        }

        if (deleteItemButtons.length && deleteItemModal && deleteItemForm && deleteItemMessage && deleteItemCancel) {
            deleteItemButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const isPrimary = button.dataset.primary === '1';
                    const table = button.dataset.table || 'this table';
                    const column = button.dataset.column || 'this column';

                    deleteItemForm.action = button.dataset.action;
                    deleteItemMessage.textContent = isPrimary
                        ? 'Delete primary key column "' + table + '.' + column + '"? This will remove all columns from table "' + table + '" in this share.'
                        : 'Delete column "' + table + '.' + column + '" from this share?';
                    openModal(deleteItemModal, button);
                    deleteItemCancel.focus();
                });
            });

            deleteItemCancel.addEventListener('click', function () {
                closeModal(deleteItemModal);
                deleteItemForm.removeAttribute('action');
            });

            deleteItemModal.addEventListener('click', function (event) {
                if (event.target === deleteItemModal) {
                    closeModal(deleteItemModal);
                    deleteItemForm.removeAttribute('action');
                }
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            if (addAllModal?.classList.contains('open')) closeModal(addAllModal);
            if (deleteAllModal?.classList.contains('open')) closeModal(deleteAllModal);
            if (deleteItemModal?.classList.contains('open')) {
                closeModal(deleteItemModal);
                deleteItemForm?.removeAttribute('action');
            }
        });
    })();

    (function () {
        const button = document.querySelector('.js-generate-bundle');
        const modal = document.getElementById('generate-bundle-modal');
        const cancelBtn = document.getElementById('generate-bundle-cancel');

        if (!button || !modal || !cancelBtn) return;

        function openModal() {
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            cancelBtn.focus();
        }

        function closeModal() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            button.focus();
        }

        button.addEventListener('click', openModal);
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
