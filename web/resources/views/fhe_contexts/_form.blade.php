@csrf

@php
    $matchedScheme = $context->scheme_id
        ? $schemes->firstWhere('id', $context->scheme_id)
        : $schemes->firstWhere('scheme_name', $context->scheme);
    $selectedSchemeId = (int) old('scheme_id', $matchedScheme?->id);
    $currentParameters = old('parameters', $context->parameters_json ?? []);
    $schemeDefinitions = $schemes->mapWithKeys(function ($scheme) {
        return [
            $scheme->id => [
                'parameters' => $scheme->configuration_parameters,
            ],
        ];
    });
@endphp

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="context-section-title">
        <h3 class="user-form-section-title" id="context-section-title">Context Information</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input
                    class="form-control"
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $context->name) }}"
                    required
                >
                <div class="form-hint">Use a short label that identifies this FHE context.</div>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="scheme_id">Scheme</label>
                <select class="form-control is-select" id="scheme_id" name="scheme_id" required>
                    <option value="">Select scheme</option>
                    @foreach($schemes as $scheme)
                        <option value="{{ $scheme->id }}" @selected($selectedSchemeId === $scheme->id)>
                            {{ $scheme->library?->name ? $scheme->library->name.' - ' : '' }}{{ $scheme->scheme_name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Parameters below come from the selected scheme definition.</div>
                @error('scheme_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>

    <section class="user-form-section" aria-labelledby="parameters-section-title">
        <h3 class="user-form-section-title" id="parameters-section-title">Parameters</h3>

        <div class="form-row" id="scheme-parameter-fields"></div>
        <div class="form-hint" id="scheme-parameter-empty">Select a scheme to configure its parameters.</div>

        @foreach($errors->getMessages() as $field => $messages)
            @if(str_starts_with($field, 'parameters.'))
                <div class="field-error">{{ $messages[0] }}</div>
            @endif
        @endforeach
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('fhe-contexts.index') }}">
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
        const schemeSelect = document.getElementById('scheme_id');
        const fields = document.getElementById('scheme-parameter-fields');
        const empty = document.getElementById('scheme-parameter-empty');
        const definitions = @json($schemeDefinitions);
        const values = @json($currentParameters);

        function labelFor(name) {
            return name.replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
                return letter.toUpperCase();
            });
        }

        function valueFor(parameter) {
            const name = parameter.name || '';

            if (Object.prototype.hasOwnProperty.call(values, name)) {
                const value = values[name];
                return typeof value === 'object' && value !== null ? JSON.stringify(value, null, 2) : value;
            }

            return parameter.default_value || '';
        }

        function buildInput(parameter) {
            const name = parameter.name || '';
            const type = parameter.type || 'string';
            const required = Boolean(parameter.is_required);
            const currentValue = valueFor(parameter);

            if (type === 'boolean') {
                const wrapper = document.createElement('div');
                const hidden = document.createElement('input');
                const checkbox = document.createElement('input');

                hidden.type = 'hidden';
                hidden.name = 'parameters[' + name + ']';
                hidden.value = '0';

                checkbox.type = 'checkbox';
                checkbox.name = 'parameters[' + name + ']';
                checkbox.value = '1';
                checkbox.checked = currentValue === true || currentValue === 1 || currentValue === '1' || currentValue === 'true';

                wrapper.append(hidden, checkbox);
                return wrapper;
            }

            if (type === 'json') {
                const textarea = document.createElement('textarea');
                textarea.className = 'form-control';
                textarea.name = 'parameters[' + name + ']';
                textarea.id = 'parameter_' + name;
                textarea.required = required;
                textarea.value = currentValue;
                return textarea;
            }

            const input = document.createElement('input');
            input.className = 'form-control';
            input.name = 'parameters[' + name + ']';
            input.id = 'parameter_' + name;
            input.type = type === 'integer' || type === 'decimal' ? 'number' : 'text';
            input.required = required;
            input.value = currentValue;

            if (type === 'integer') {
                input.step = '1';
            } else if (type === 'decimal') {
                input.step = 'any';
            }

            return input;
        }

        function renderParameters() {
            fields.replaceChildren();

            const scheme = definitions[schemeSelect.value];
            const parameters = scheme ? scheme.parameters : [];

            if (!parameters || parameters.length === 0) {
                empty.textContent = schemeSelect.value ? 'This scheme does not define any parameters.' : 'Select a scheme to configure its parameters.';
                empty.hidden = false;
                return;
            }

            empty.hidden = true;

            parameters.forEach(function (parameter) {
                const name = parameter.name || '';

                if (!name) return;

                const group = document.createElement('div');
                const label = document.createElement('label');
                const hint = document.createElement('div');

                group.className = 'form-group';
                label.className = 'form-label';
                label.htmlFor = 'parameter_' + name;
                label.textContent = labelFor(name);

                hint.className = 'form-hint';
                hint.textContent = parameter.description || (parameter.type ? 'Type: ' + parameter.type : '');

                group.append(label, buildInput(parameter), hint);
                fields.appendChild(group);
            });
        }

        schemeSelect.addEventListener('change', renderParameters);
        renderParameters();
    })();
</script>
@endsection
