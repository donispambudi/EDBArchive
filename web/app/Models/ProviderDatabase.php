<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderDatabase extends Model
{
    protected $table = 'databases';

    protected $fillable = [
        'provider_user_id',
        'name',
        'description',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function databaseTables(): HasMany
    {
        return $this->hasMany(DatabaseTable::class, 'database_id');
    }
}
