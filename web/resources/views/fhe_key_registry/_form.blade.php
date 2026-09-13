@csrf

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="key-section-title">
        <h3 class="user-form-section-title" id="key-section-title">Key Information</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="owner_user_id">Owner</label>
                <select class="form-control is-select" id="owner_user_id" name="owner_user_id" required>
                    <option value="">Select owner</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('owner_user_id', $keyRegistry->owner_user_id) === $user->id)>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">User who owns this keyset metadata.</div>
                @error('owner_user_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="fhe_context_id">FHE Context</label>
                <select class="form-control is-select" id="fhe_context_id" name="fhe_context_id" required>
                    <option value="">Select context</option>
                    @foreach($fheContexts as $context)
                        <option value="{{ $context->id }}" @selected((int) old('fhe_context_id', $keyRegistry->fhe_context_id) === $context->id)>
                            {{ $context->name }} ({{ $context->scheme_label }})
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">FHE context this keyset was generated for.</div>
                @error('fhe_context_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="key_status">Status</label>
                <select class="form-control is-select" id="key_status" name="key_status" required>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(old('key_status', $keyRegistry->key_status ?? 'active') === $status)>
                            {{ ucfirst($status) }}
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Lifecycle status for this keyset.</div>
                @error('key_status')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('fhe-key-registry.index') }}">
        <span class="btn-icon icon-close" aria-hidden="true"></span>
        <span>Cancel</span>
    </a>
    <button class="btn btn-primary btn-wide" type="submit">
        <span class="btn-icon icon-save" aria-hidden="true"></span>
        <span>{{ $buttonText }}</span>
    </button>
</div>
