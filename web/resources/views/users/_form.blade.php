@csrf

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="account-section-title">
        <h3 class="user-form-section-title" id="account-section-title">Account Information</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="name">Name</label>
                <input
                    class="form-control"
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $user->name) }}"
                    autocomplete="name"
                    required
                >
                <div class="form-hint">Use the person or organization name shown throughout EDBArchive.</div>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input
                    class="form-control"
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $user->email) }}"
                    autocomplete="email"
                    required
                >
                <div class="form-hint">This email is used to sign in and must be unique.</div>
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="role">Role</label>
                <select class="form-control is-select" id="role" name="role" required>
                    <option value="">Select role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>
                            {{ $role }}
                        </option>
                    @endforeach
                </select>
                <div class="form-hint">Choose whether this user provides data or consumes data as a third party.</div>
                @error('role')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </section>

    <section class="user-form-section" aria-labelledby="security-section-title">
        <h3 class="user-form-section-title" id="security-section-title">Security</h3>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input
                class="form-control"
                id="password"
                name="password"
                type="password"
                autocomplete="new-password"
                {{ $user->exists ? '' : 'required' }}
            >
            @if($user->exists)
                <div class="form-hint">Leave blank to keep the current password.</div>
            @else
                <div class="form-hint">Use at least 8 characters.</div>
            @endif
            @error('password')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group mb-0">
            <label class="form-label" for="password_confirmation">Confirm Password</label>
            <input
                class="form-control"
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autocomplete="new-password"
                {{ $user->exists ? '' : 'required' }}
            >
            <div class="form-hint">Repeat the same password to avoid typing mistakes.</div>
            @error('password_confirmation')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('users.index') }}">
        <span class="btn-icon icon-close" aria-hidden="true"></span>
        <span>Cancel</span>
    </a>
    <button class="btn btn-primary btn-wide" type="submit">
        <span class="btn-icon icon-save" aria-hidden="true"></span>
        <span>{{ $buttonText }}</span>
    </button>
</div>
