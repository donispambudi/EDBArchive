@extends('layouts.app')

@section('title', 'Configure Database Columns')
@section('meta_description', 'Configure database column protection metadata')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Configure Columns</h1>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('dashboard') }}">Home</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('databases.index') }}">Databases</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <a href="{{ route('databases.show', $database) }}">{{ $database->name }}</a>
            <span class="breadcrumb-sep" aria-hidden="true">›</span>
            <span aria-current="page">Columns</span>
        </nav>
    </div>
</div>

<div class="card user-form-card">
    <div class="card-body">
        <form class="user-form" method="POST" action="{{ route('databases.columns.update', $database) }}">
            @csrf
            @method('PUT')

            <div class="user-form-grid">
                <section class="user-form-section" aria-labelledby="columns-section-title">
                    <h3 class="user-form-section-title" id="columns-section-title">Column Defaults</h3>

                    <div class="form-hint mb-4">
                        Configure column-level metadata only. This does not store actual row values, plaintext data, or ciphertext data.
                    </div>

                    @if($database->databaseTables->isEmpty())
                        <div class="text-sm text-muted">No selected tables. Edit this database and choose tables first.</div>
                    @elseif(count($columnRows) === 0)
                        <div class="text-sm text-muted">No local columns were found for the selected tables.</div>
                    @else
                        @error('columns')
                            <div class="field-error mb-3">{{ $message }}</div>
                        @enderror

                        <div class="table-wrapper">
                            <table class="table table-bordered table-striped table-hover column-config-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Use</th>
                                        <th scope="col">Table</th>
                                        <th scope="col">Column</th>
                                        <th scope="col">Data Type</th>
                                        <th scope="col">Encryption</th>
                                        <th scope="col">FHE Context</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $currentTableId = null;
                                    @endphp
                                    @foreach($columnRows as $index => $row)
                                        @if($currentTableId !== $row['table']->id)
                                            @php
                                                $currentTableId = $row['table']->id;
                                            @endphp
                                            <tr class="column-table-separator">
                                                <td>
                                                    <label class="column-select-all" title="Select all columns in {{ $row['table']->table_name }}">
                                                        <input type="checkbox" class="js-table-select-all" data-table-id="{{ $row['table']->id }}" aria-label="Select all columns in {{ $row['table']->table_name }}">
                                                    </label>
                                                </td>
                                                <td colspan="5">
                                                    <div class="column-table-separator-inner">
                                                        <span>{{ $database->name }}.{{ $row['table']->table_name }}</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endif
                                        <tr class="column-config-row" data-table-id="{{ $row['table']->id }}">
                                            <td class="column-checkbox-cell">
                                                <input type="hidden" name="columns[{{ $index }}][database_table_id]" value="{{ $row['table']->id }}">
                                                <input type="hidden" name="columns[{{ $index }}][column_name]" value="{{ $row['column_name'] }}">
                                                <input type="hidden" name="columns[{{ $index }}][column_order]" value="{{ $row['column_order'] }}">
                                                @if($row['is_primary'])
                                                    <input type="hidden" name="columns[{{ $index }}][enabled]" value="1">
                                                @endif
                                                <input
                                                    type="checkbox"
                                                    class="js-column-enabled"
                                                    name="columns[{{ $index }}][enabled]"
                                                    value="1"
                                                    @checked($row['is_primary'] || old("columns.$index.enabled", $row['enabled']))
                                                    @disabled($row['is_primary'])
                                                >
                                            </td>
                                            <td class="font-medium">{{ $row['table']->table_name }}</td>
                                            <td>
                                                {{ $row['column_name'] }}
                                                @if($row['is_primary'])
                                                    <span class="badge badge-secondary">Primary</span>
                                                @endif
                                            </td>
                                            <td class="text-sm text-muted">{{ $row['data_type'] }}</td>
                                            <td>
                                                @if($row['is_primary'])
                                                    <input type="hidden" name="columns[{{ $index }}][default_encryption_type]" value="plaintext">
                                                @endif
                                                <select class="form-control is-select js-column-encryption" name="columns[{{ $index }}][default_encryption_type]" @disabled($row['is_primary'])>
                                                    @foreach($encryptionTypes as $type)
                                                        <option value="{{ $type }}" @selected(($row['is_primary'] ? 'plaintext' : old("columns.$index.default_encryption_type", $row['default_encryption_type'])) === $type)>{{ $type }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                @if($row['is_primary'])
                                                    <input type="hidden" name="columns[{{ $index }}][fhe_context_id]" value="">
                                                @endif
                                                <select class="form-control is-select js-column-fhe-field" name="columns[{{ $index }}][fhe_context_id]" @disabled($row['is_primary'])>
                                                    <option value="">-</option>
                                                    @foreach($fheContexts as $context)
                                                        <option value="{{ $context->id }}" @selected((int) old("columns.$index.fhe_context_id", $row['fhe_context_id']) === $context->id)>
                                                            {{ $context->name }} ({{ $context->scheme_label }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div class="user-form-actions">
                <a class="btn btn-soft btn-wide" href="{{ route('databases.show', $database) }}">
                    <span class="btn-icon icon-close" aria-hidden="true"></span>
                    <span>Cancel</span>
                </a>
                <button class="btn btn-primary btn-wide" type="submit" @disabled(count($columnRows) === 0)>
                    <span class="btn-icon icon-save" aria-hidden="true"></span>
                    <span>Save Columns</span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
    (function () {
        function syncRow(row) {
            const encryption = row.querySelector('.js-column-encryption');
            const fheFields = row.querySelectorAll('.js-column-fhe-field');
            const isFhe = encryption.value.startsWith('fhe-');

            fheFields.forEach(function (field) {
                field.required = isFhe;
                field.disabled = !isFhe;
                if (!isFhe) field.value = '';
            });
        }

        document.querySelectorAll('.column-config-row').forEach(function (row) {
            const encryption = row.querySelector('.js-column-encryption');
            encryption.addEventListener('change', function () {
                syncRow(row);
            });
            syncRow(row);
        });

        document.querySelectorAll('.js-table-select-all').forEach(function (selectAll) {
            const tableId = selectAll.dataset.tableId;
            const checkboxes = document.querySelectorAll('.column-config-row[data-table-id="' + tableId + '"] .js-column-enabled');

            function syncSelectAllState() {
                const total = checkboxes.length;
                const checked = Array.from(checkboxes).filter(function (checkbox) {
                    return checkbox.checked;
                }).length;

                selectAll.checked = total > 0 && checked === total;
                selectAll.indeterminate = checked > 0 && checked < total;
            }

            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (checkbox) {
                    if (checkbox.disabled) return;
                    checkbox.checked = selectAll.checked;
                });
                syncSelectAllState();
            });

            checkboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', syncSelectAllState);
            });

            syncSelectAllState();
        });
    })();
</script>
@endsection
