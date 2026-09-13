@csrf

@php
    $oldParameters = old('parameters');
    $parameters = is_array($oldParameters) ? $oldParameters : ($scheme->configuration_parameters ?? []);
    $parameterTypes = ['string', 'integer', 'decimal', 'boolean', 'json'];
@endphp

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="scheme-section-title">
        <h3 class="user-form-section-title" id="scheme-section-title">Scheme Information</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="library_id">Library</label>
                <select class="form-control is-select" id="library_id" name="library_id" required>
                    <option value="">Select library</option>
                    @foreach($libraries as $library)
                        <option value="{{ $library->id }}" @selected((int) old('library_id', $scheme->library_id) === $library->id)>
                            {{ $library->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Choose the library this scheme belongs to.</div>
                @error('library_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="scheme_name">Scheme Name</label>
                <input
                    class="form-control"
                    id="scheme_name"
                    name="scheme_name"
                    type="text"
                    value="{{ old('scheme_name', $scheme->scheme_name) }}"
                    required
                >
                <div class="form-hint">Use the scheme name shown throughout EDBArchive.</div>
                @error('scheme_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <div class="form-label">Status</div>
                <input type="hidden" name="is_active" value="0">
                <label class="checkbox-card">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        @checked((bool) old('is_active', $scheme->is_active ?? true))
                    >
                    <span>Active</span>
                </label>
                <div class="form-hint">Inactive schemes remain saved but are marked unavailable.</div>
                @error('is_active')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>

    <section class="user-form-section" aria-labelledby="configuration-section-title">
        <div class="section-heading-row">
            <h3 class="user-form-section-title" id="configuration-section-title">Configuration Parameters</h3>
            <button class="btn btn-soft btn-sm" type="button" id="add-parameter-row">
                <span class="btn-icon icon-plus" aria-hidden="true"></span>
                <span>Add Parameter</span>
            </button>
        </div>

        <div class="table-wrapper parameter-table-wrap">
            <table class="table table-bordered parameter-table">
                <thead>
                    <tr>
                        <th class="text-center" scope="col">Action</th>
                        <th scope="col">Parameter Name</th>
                        <th scope="col">Type</th>
                        <th scope="col">Default Value</th>
                        <th scope="col">Required</th>
                        <th scope="col">Description</th>
                    </tr>
                </thead>
                <tbody id="parameter-rows">
                    @forelse($parameters as $index => $parameter)
                        <tr class="parameter-row">
                            <td class="text-center parameter-action-cell">
                                <button class="btn btn-danger btn-sm js-remove-parameter" type="button" title="Remove" aria-label="Remove parameter">
                                    <span class="btn-icon icon-trash" aria-hidden="true"></span>
                                    <span class="btn-label">Remove</span>
                                </button>
                            </td>
                            <td>
                                <input class="form-control" name="parameters[{{ $index }}][name]" type="text" value="{{ $parameter['name'] ?? '' }}" placeholder="poly_modulus_degree">
                            </td>
                            <td>
                                <select class="form-control is-select" name="parameters[{{ $index }}][type]">
                                    @foreach($parameterTypes as $type)
                                        <option value="{{ $type }}" @selected(($parameter['type'] ?? 'string') === $type)>{{ ucfirst($type) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input class="form-control" name="parameters[{{ $index }}][default_value]" type="text" value="{{ $parameter['default_value'] ?? '' }}">
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="parameters[{{ $index }}][is_required]" value="0">
                                <input type="checkbox" name="parameters[{{ $index }}][is_required]" value="1" @checked((bool) ($parameter['is_required'] ?? false))>
                            </td>
                            <td>
                                <input class="form-control" name="parameters[{{ $index }}][description]" type="text" value="{{ $parameter['description'] ?? '' }}">
                            </td>
                        </tr>
                    @empty
                        <tr class="parameter-empty-row">
                            <td class="text-center text-muted" colspan="6">No parameters defined.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="form-hint">Only rows with a parameter name are saved into the scheme configuration JSON.</div>
        @error('parameters')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('schemes.index') }}">
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
        const rows = document.getElementById('parameter-rows');
        const addButton = document.getElementById('add-parameter-row');
        const types = @json($parameterTypes);
        let parameterIndex = rows.querySelectorAll('.parameter-row').length;

        function syncEmptyRow() {
            const emptyRow = rows.querySelector('.parameter-empty-row');
            const hasRows = rows.querySelectorAll('.parameter-row').length > 0;

            if (emptyRow) {
                emptyRow.hidden = hasRows;
            }
        }

        function typeOptions() {
            return types.map(function (type) {
                return '<option value="' + type + '">' + type.charAt(0).toUpperCase() + type.slice(1) + '</option>';
            }).join('');
        }

        function addRow() {
            const index = parameterIndex;
            parameterIndex += 1;
            const row = document.createElement('tr');
            row.className = 'parameter-row';
            row.innerHTML = [
                '<td class="text-center parameter-action-cell"><button class="btn btn-danger btn-sm js-remove-parameter" type="button" title="Remove" aria-label="Remove parameter"><span class="btn-icon icon-trash" aria-hidden="true"></span><span class="btn-label">Remove</span></button></td>',
                '<td><input class="form-control" name="parameters[' + index + '][name]" type="text" placeholder="poly_modulus_degree"></td>',
                '<td><select class="form-control is-select" name="parameters[' + index + '][type]">' + typeOptions() + '</select></td>',
                '<td><input class="form-control" name="parameters[' + index + '][default_value]" type="text"></td>',
                '<td class="text-center"><input type="hidden" name="parameters[' + index + '][is_required]" value="0"><input type="checkbox" name="parameters[' + index + '][is_required]" value="1"></td>',
                '<td><input class="form-control" name="parameters[' + index + '][description]" type="text"></td>',
            ].join('');
            rows.appendChild(row);
            syncEmptyRow();
            row.querySelector('input').focus();
        }

        addButton.addEventListener('click', addRow);

        rows.addEventListener('click', function (event) {
            const button = event.target.closest('.js-remove-parameter');

            if (!button) {
                return;
            }

            button.closest('.parameter-row').remove();
            syncEmptyRow();
        });

        syncEmptyRow();
    })();
</script>
@endsection
