<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FheContext extends Model
{
    public const GENERATION_STATUSES = ['pending', 'queued', 'processing', 'generated', 'failed'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_GENERATED = 'generated';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'name',
        'scheme_id',
        'scheme',
        'parameters_json',
        'context_file_ref',
        'context_status',
    ];

    protected function casts(): array
    {
        return [
            'parameters_json' => 'array',
        ];
    }

    public function schemeRecord(): BelongsTo
    {
        return $this->belongsTo(Scheme::class, 'scheme_id');
    }

    public function getSchemeLabelAttribute(): string
    {
        return $this->schemeRecord?->scheme_name ?? $this->scheme;
    }
}
