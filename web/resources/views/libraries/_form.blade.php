@csrf

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="library-section-title">
        <h3 class="user-form-section-title" id="library-section-title">Library Information</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input
                    class="form-control"
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $library->name) }}"
                    required
                >
                <div class="form-hint">Use the library name shown throughout EDBArchive.</div>
                @error('name')
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
                        @checked((bool) old('is_active', $library->is_active ?? true))
                    >
                    <span>Active</span>
                </label>
                <div class="form-hint">Inactive libraries remain saved but are marked unavailable.</div>
                @error('is_active')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('libraries.index') }}">
        <span class="btn-icon icon-close" aria-hidden="true"></span>
        <span>Cancel</span>
    </a>
    <button class="btn btn-primary btn-wide" type="submit">
        <span class="btn-icon icon-save" aria-hidden="true"></span>
        <span>{{ $buttonText }}</span>
    </button>
</div>
