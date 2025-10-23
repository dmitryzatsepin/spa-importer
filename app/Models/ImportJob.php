<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportJob extends Model
{
    use HasFactory;
    protected $fillable = [
        'portal_id',
        'status',
        'original_filename',
        'stored_filepath',
        'field_mappings',
        'settings',
        'total_rows',
        'processed_rows',
        'error_details',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'settings' => 'array',
        'error_details' => 'array',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
    ];

    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }

    public function updateProgress(int $processedRows, ?array $errorDetails = null): void
    {
        $this->processed_rows = $processedRows;

        if ($errorDetails) {
            $this->error_details = $errorDetails;
        }

        $this->save();
    }

    public function markAsProcessing(): void
    {
        $this->status = 'processing';
        $this->save();
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->save();
    }

    public function markAsFailed(array $errorDetails): void
    {
        $this->status = 'failed';
        $this->error_details = $errorDetails;
        $this->save();
    }

    public function getProgressPercentage(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return round(($this->processed_rows / $this->total_rows) * 100, 2);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

