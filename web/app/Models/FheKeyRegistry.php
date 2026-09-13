<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FheKeyRegistry extends Model
{
    public const GENERATION_STATUSES = ['pending', 'queued', 'processing', 'generated', 'failed'];

    public const GENERATION_PENDING = 'pending';
    public const GENERATION_QUEUED = 'queued';
    public const GENERATION_PROCESSING = 'processing';
    public const GENERATION_GENERATED = 'generated';
    public const GENERATION_FAILED = 'failed';

    public const STATUSES = [
        'active',
        'rotated',
        'revoked',
        'expired',
    ];

    protected $table = 'fhe_key_registry';

    protected $fillable = [
        'owner_user_id',
        'fhe_context_id',
        'keyset_ref',
        'generation_status',
        'key_status',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function fheContext(): BelongsTo
    {
        return $this->belongsTo(FheContext::class);
    }
}
