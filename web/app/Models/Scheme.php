<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Scheme extends Model
{
    protected $fillable = [
        'library_id',
        'scheme_name',
        'configuration_json',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'configuration_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function fheContexts(): HasMany
    {
        return $this->hasMany(FheContext::class);
    }

    public function getHandlerNameAttribute(): string
    {
        $libraryName = $this->library?->name ?? 'library';

        return Str::slug($libraryName, '_').'_'.Str::slug($this->scheme_name, '_').'.sh';
    }

    public function getConfigurationParametersAttribute(): array
    {
        return $this->configuration_json['parameters'] ?? [];
    }
}
