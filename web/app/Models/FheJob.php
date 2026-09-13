<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FheJob extends Model
{
    public const TYPE_CREATE_CONTEXT = 'create-context';
    public const TYPE_CREATE_KEYPAIR = 'create-keypair';
    public const TYPE_BACKUP = 'backup';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'job_type',
        'pk',
        'status',
        'input_payload',
        'result_payload',
        'progress',
        'exit_code',
        'error_type',
        'error_code',
        'error_message',
        'stdout_log',
        'stderr_log',
        'created_by',
        'claimed_at',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'input_payload' => 'array',
            'result_payload' => 'array',
            'claimed_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function pendingContextGeneration(FheContext $context): ?self
    {
        return self::pendingJob(
            self::TYPE_CREATE_CONTEXT,
            (int) $context->id
        );
    }

    public static function pendingKeyGeneration(FheKeyRegistry $keyRegistry): ?self
    {
        return self::pendingJob(
            self::TYPE_CREATE_KEYPAIR,
            (int) $keyRegistry->id
        );
    }

    public static function pendingShareBackup(Share $share): ?self
    {
        return self::pendingJob(
            self::TYPE_BACKUP,
            (int) $share->id
        );
    }

    private static function pendingJob(string $jobType, int $pk): ?self
    {
        return self::query()
            ->where('job_type', $jobType)
            ->where('pk', $pk)
            ->where('status', self::STATUS_PENDING)
            ->first();
    }
}
