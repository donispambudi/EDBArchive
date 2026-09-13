<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DatabaseTable extends Model
{
    protected $fillable = [
        'database_id',
        'table_name',
    ];

    public function database(): BelongsTo
    {
        return $this->belongsTo(ProviderDatabase::class, 'database_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(DatabaseColumn::class);
    }
}
