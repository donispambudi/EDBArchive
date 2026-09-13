<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseColumn extends Model
{
    public const ENCRYPTION_TYPES = [
        'plaintext',
        'fhe-secure',
    ];

    protected $fillable = [
        'database_table_id',
        'column_name',
        'column_order',
        'default_encryption_type',
        'fhe_context_id',
    ];

    public function databaseTable(): BelongsTo
    {
        return $this->belongsTo(DatabaseTable::class);
    }

    public function fheContext(): BelongsTo
    {
        return $this->belongsTo(FheContext::class);
    }
}
