<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareHistory extends Model
{
    public const ACTIONS = ['created', 'released', 'reshared', 'revoked', 'expired'];

    protected $table = 'share_history';

    protected $fillable = [
        'share_id',
        'parent_share_id',
        'original_owner_user_id',
        'shared_by_user_id',
        'shared_to_user_id',
        'database_id',
        'action',
    ];

    public function share(): BelongsTo
    {
        return $this->belongsTo(Share::class);
    }

    public function parentShare(): BelongsTo
    {
        return $this->belongsTo(Share::class, 'parent_share_id');
    }

    public function originalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_owner_user_id');
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }

    public function sharedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_to_user_id');
    }

    public function database(): BelongsTo
    {
        return $this->belongsTo(ProviderDatabase::class, 'database_id');
    }
}
