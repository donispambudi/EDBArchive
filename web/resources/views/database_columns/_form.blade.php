@csrf

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="column-section-title">
        <h3 class="user-form-section-title" id="column-section-title">Column Metadata</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="database_table_id">Database Table</label>
                <select class="form-control is-select" id="database_table_id" name="database_table_id" required>
                    <option value="">Select table</option>
                    @foreach($databaseTables as $table)
                        <option value="{{ $table->id }}" @selected((int) old('database_table_id', $databaseColumn->database_table_id) === $table->id)>
                            {{ $table->database?->name ?? 'Unknown database' }}.{{ $table->table_name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Column metadata belongs to one registered database table.</div>
                @error('database_table_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="column_name">Column Name</label>
                <input
                    class="form-control"
                    id="column_name"
                    name="column_name"
                    type="text"
                    value="{{ old('column_name', $databaseColumn->column_name) }}"
                    placeholder="age"
                    required
                >
                <div class="form-hint">Original logical column name, such as age, salary, diagnosis, or region.</div>
                @error('column_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

    </section>

    <section class="user-form-section" aria-labelledby="encryption-section-title">
        <h3 class="user-form-section-title" id="encryption-section-title">Encryption Defaults</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="default_encryption_type">Default Encryption Type</label>
                <select class="form-control is-select" id="default_encryption_type" name="default_encryption_type" required>
                    @foreach($encryptionTypes as $type)
                        <option value="{{ $type }}" @selected(old('default_encryption_type', $databaseColumn->default_encryption_type ?? 'plaintext') === $type)>
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Use an FHE variant only when this column should use an FHE context. Recipient keys are selected in Shares.</div>
                @error('default_encryption_type')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="fhe_context_id">FHE Context</label>
                <select class="form-control is-select js-fhe-field" id="fhe_context_id" name="fhe_context_id">
                    <option value="">No FHE context</option>
                    @foreach($fheContexts as $context)
                        <option value="{{ $context->id }}" @selected((int) old('fhe_context_id', $databaseColumn->fhe_context_id) === $context->id)>
                            {{ $context->name }} ({{ $context->scheme_label }})
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Required when default encryption type starts with fhe; cleared for non-FHE columns.</div>
                @error('fhe_context_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

        </div>
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('database-columns.index') }}">
        <span class="btn-icon icon-close" aria-hidden="true"></span>
        <span>Cancel</span>
    </a>
    <button class="btn btn-primary btn-wide" type="submit">
        <span class="btn-icon icon-save" aria-hidden="true"></span>
        <span>{{ $buttonText }}</span>
    </button>
</div>

@section('js')
<script>
    (function () {
        const encryptionType = document.getElementById('default_encryption_type');
        const fheFields = document.querySelectorAll('.js-fhe-field');

        function syncFheFields() {
            const isFhe = encryptionType.value.startsWith('fhe-');
            fheFields.forEach(function (field) {
                field.required = isFhe;
                field.disabled = !isFhe;
                if (!isFhe) field.value = '';
            });
        }

        encryptionType.addEventListener('change', syncFheFields);
        syncFheFields();
    })();
</script>
@endsection
