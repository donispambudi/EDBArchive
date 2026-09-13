@csrf

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="database-section-title">
        <h3 class="user-form-section-title" id="database-section-title">Database Information</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="name">Local Database</label>
                <select class="form-control is-select" id="name" name="name" required>
                    <option value="">Select database</option>
                    @foreach($localDatabases as $localDatabase)
                        <option value="{{ $localDatabase }}" @selected(old('name', $database->name) === $localDatabase)>
                            {{ $localDatabase }}
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Choose an existing local MariaDB database. System schemas and this web app database are excluded.</div>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="provider_user_id">Provider</label>
                <select class="form-control is-select" id="provider_user_id" name="provider_user_id" required>
                    <option value="">Select provider</option>
                    @foreach($providers as $provider)
                        <option value="{{ $provider->id }}" @selected((int) old('provider_user_id', $database->provider_user_id) === $provider->id)>
                            {{ $provider->name }} ({{ $provider->email }})
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Only users with the Data Provider role can own a database.</div>
                @error('provider_user_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="form-group mb-0">
            <label class="form-label" for="description">Description</label>
            <textarea
                class="form-control"
                id="description"
                name="description"
                rows="5"
            >{{ old('description', $database->description) }}</textarea>
            <div class="form-hint">Optional note describing the logical database or data domain.</div>
            @error('description')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
    </section>

    <section class="user-form-section" aria-labelledby="database-tables-section-title">
        <h3 class="user-form-section-title" id="database-tables-section-title">Tables To Process</h3>

        <div class="form-group mb-0">
            <div class="form-label">Selected Tables</div>
            <div class="form-hint mb-3">Choose the local MariaDB tables that EDBArchive should process. The selection is saved as database table metadata.</div>

            <div class="table-picker-empty text-sm text-muted" id="table-picker-empty">
                Select a local database first.
            </div>

            @foreach($localTablesByDatabase as $databaseName => $tableNames)
                <div class="table-picker-group" data-database="{{ $databaseName }}" hidden>
                    @if(count($tableNames) > 0)
                        <div class="checkbox-grid">
                            @foreach($tableNames as $tableName)
                                <label class="checkbox-card">
                                    <input
                                        type="checkbox"
                                        name="table_names[]"
                                        value="{{ $tableName }}"
                                        @checked(in_array($tableName, $selectedTables, true))
                                    >
                                    <span>{{ $tableName }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <div class="text-sm text-muted">No base tables found for this database.</div>
                    @endif
                </div>
            @endforeach

            @error('table_names')
                <div class="field-error">{{ $message }}</div>
            @enderror
            @error('table_names.*')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('databases.index') }}">
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
        const databaseSelect = document.getElementById('name');
        const emptyState = document.getElementById('table-picker-empty');
        const groups = document.querySelectorAll('.table-picker-group');

        function syncTablePicker() {
            const selectedDatabase = databaseSelect.value;
            let visibleGroup = null;

            groups.forEach(function (group) {
                const isVisible = group.dataset.database === selectedDatabase;
                group.hidden = !isVisible;
                group.querySelectorAll('input[type="checkbox"]').forEach(function (checkbox) {
                    checkbox.disabled = !isVisible;
                    if (!isVisible) checkbox.checked = false;
                });
                if (isVisible) visibleGroup = group;
            });

            emptyState.hidden = Boolean(visibleGroup);
        }

        databaseSelect.addEventListener('change', syncTablePicker);
        syncTablePicker();
    })();
</script>
@endsection
