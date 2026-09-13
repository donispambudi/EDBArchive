<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Share extends Model
{
    public const STATUSES = ['draft', 'released', 'expired', 'revoked'];
    public const BUNDLE_STATUSES = ['pending', 'processing', 'done'];

    protected $fillable = [
        'owner_user_id',
        'recipient_user_id',
        'database_id',
        'release_version',
        'status',
        'bundle_ref',
        'bundle_hash',
        'bundle_status',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(ProviderDatabase::class, 'database_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShareItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(ShareHistory::class);
    }
}
