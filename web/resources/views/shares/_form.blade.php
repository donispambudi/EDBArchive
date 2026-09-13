@csrf

<div class="user-form-grid">
    <section class="user-form-section" aria-labelledby="share-main-section-title">
        <h3 class="user-form-section-title" id="share-main-section-title">Share Information</h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="owner_user_id">Owner</label>
                <select class="form-control is-select" id="owner_user_id" name="owner_user_id" required>
                    <option value="">Select owner</option>
                    @foreach($owners as $user)
                        <option value="{{ $user->id }}" @selected((int) old('owner_user_id', $share->owner_user_id) === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <div class="form-hint">Only users with Data Provider role are listed.</div>
                @error('owner_user_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="recipient_user_id">Recipient</label>
                <select class="form-control is-select" id="recipient_user_id" name="recipient_user_id" required>
                    <option value="">Select recipient</option>
                    @foreach($recipients as $user)
                        <option value="{{ $user->id }}" @selected((int) old('recipient_user_id', $share->recipient_user_id) === $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <div class="form-hint">Only users with Third Party role are listed.</div>
                @error('recipient_user_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="database_id">Database</label>
                <select class="form-control is-select" id="database_id" name="database_id" required>
                    <option value="">Select database</option>
                    @foreach($databases as $database)
                        <option value="{{ $database->id }}" @selected((int) old('database_id', $share->database_id) === $database->id)>{{ $database->name }}</option>
                    @endforeach
                </select>
                <div class="form-hint">Database being shared.</div>
                @error('database_id')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="release_version">Release Version</label>
                <input class="form-control" id="release_version" name="release_version" type="text" value="{{ old('release_version', $share->release_version) }}" placeholder="{{ now()->format('Y-m-d') }}-release">
                <div class="form-hint">Optional concrete release/package version.</div>
                @error('release_version')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="status">Status</label>
                <select class="form-control is-select" id="status" name="status" required>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $share->status ?? 'draft') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <div class="form-hint">Lifecycle status for this share.</div>
                @error('status')<div class="field-error">{{ $message }}</div>@enderror
            </div>

        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="expires_at">Expires At</label>
                <input class="form-control" id="expires_at" name="expires_at" type="datetime-local" value="{{ old('expires_at', $share->expires_at?->format('Y-m-d\TH:i')) }}">
                <div class="form-hint">Optional expiration time.</div>
                @error('expires_at')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="revoked_at">Revoked At</label>
                <input class="form-control" id="revoked_at" name="revoked_at" type="datetime-local" value="{{ old('revoked_at', $share->revoked_at?->format('Y-m-d\TH:i')) }}">
                <div class="form-hint">Optional revocation time.</div>
                @error('revoked_at')<div class="field-error">{{ $message }}</div>@enderror
            </div>
        </div>
    </section>
</div>

<div class="user-form-actions">
    <a class="btn btn-soft btn-wide" href="{{ route('shares.index') }}">
        <span class="btn-icon icon-close" aria-hidden="true"></span>
        <span>Cancel</span>
    </a>
    <button class="btn btn-primary btn-wide" type="submit">
        <span class="btn-icon icon-save" aria-hidden="true"></span>
        <span>{{ $buttonText }}</span>
    </button>
</div>
