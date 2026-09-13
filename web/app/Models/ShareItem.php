<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShareItem extends Model
{
    public const ENCRYPTION_TYPES = ['plaintext', 'fhe-secure'];

    protected $fillable = [
        'share_id',
        'database_table_id',
        'database_column_id',
        'encryption',
        'fhe_context_id',
        'key_registry_id',
    ];

    public function share(): BelongsTo
    {
        return $this->belongsTo(Share::class);
    }

    public function databaseTable(): BelongsTo
    {
        return $this->belongsTo(DatabaseTable::class);
    }

    public function databaseColumn(): BelongsTo
    {
        return $this->belongsTo(DatabaseColumn::class);
    }

    public function fheContext(): BelongsTo
    {
        return $this->belongsTo(FheContext::class);
    }

    public function keyRegistry(): BelongsTo
    {
        return $this->belongsTo(FheKeyRegistry::class, 'key_registry_id');
    }
}
