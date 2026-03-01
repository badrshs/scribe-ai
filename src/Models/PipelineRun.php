<?php

namespace Badr\ScribeAi\Models;

use Badr\ScribeAi\Enums\PipelineRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks each pipeline execution with its status, stage progress,
 * and a snapshot of the payload for resume capability.
 *
 * @property int $id
 * @property string|null $source_url
 * @property int|null $staged_content_id
 * @property int|null $article_id
 * @property PipelineRunStatus $status
 * @property int $current_stage_index
 * @property string|null $current_stage_name
 * @property array|null $stages
 * @property array|null $payload_snapshot
 * @property string|null $error_message
 * @property string|null $error_stage
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon|null $failed_at
 */
class PipelineRun extends Model
{
    protected $fillable = [
        'source_url',
        'staged_content_id',
        'article_id',
        'status',
        'current_stage_index',
        'current_stage_name',
        'stages',
        'payload_snapshot',
        'error_message',
        'error_stage',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PipelineRunStatus::class,
            'stages' => 'array',
            'payload_snapshot' => 'array',
            'current_stage_index' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  Relationships
    // ──────────────────────────────────────────────────────────

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function stagedContent(): BelongsTo
    {
        return $this->belongsTo(StagedContent::class);
    }

    // ──────────────────────────────────────────────────────────
    //  State helpers
    // ──────────────────────────────────────────────────────────

    public function isResumable(): bool
    {
        return $this->status->isResumable();
    }

    public function markRunning(int $stageIndex, string $stageName): void
    {
        $this->update([
            'status' => PipelineRunStatus::Running,
            'current_stage_index' => $stageIndex,
            'current_stage_name' => $stageName,
            'started_at' => $this->started_at ?? now(),
        ]);
    }

    public function markStageCompleted(int $stageIndex, array $payloadSnapshot): void
    {
        $this->update([
            'current_stage_index' => $stageIndex,
            'payload_snapshot' => $payloadSnapshot,
        ]);
    }

    public function markCompleted(?int $articleId = null): void
    {
        $this->update([
            'status' => PipelineRunStatus::Completed,
            'article_id' => $articleId,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $stageName, string $errorMessage): void
    {
        $this->update([
            'status' => PipelineRunStatus::Failed,
            'error_stage' => $stageName,
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    public function markRejected(string $reason): void
    {
        $this->update([
            'status' => PipelineRunStatus::Rejected,
            'error_message' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get a human-readable short name for a stage class.
     */
    public static function stageShortName(string $class): string
    {
        $short = class_basename($class);

        return str_replace('Stage', '', $short);
    }
}
