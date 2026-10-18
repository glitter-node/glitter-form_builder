<?php

namespace Modules\Glitter\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Modules\Glitter\FormBuilder\Enums\RetentionMode;
use Modules\Glitter\FormBuilder\Enums\SubmissionActorType;
use Modules\Glitter\FormBuilder\Enums\SubmissionStatus;

final class Submission extends Model
{
    use HasUuids;

    protected $table = 'form_submissions';

    protected $fillable = [
        'uuid', 'form_id', 'form_version_id', 'status', 'submitted_by', 'actor_type',
        'normalized_payload', 'schema_snapshot_hash', 'payload_hash', 'idempotency_key',
        'idempotency_scope', 'locale', 'timezone', 'source', 'ip_hash', 'user_agent_hash',
        'retention_mode', 'retention_days', 'retention_due_at', 'received_at', 'processed_at',
        'cancelled_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'actor_type' => SubmissionActorType::class,
            'normalized_payload' => 'array',
            'retention_mode' => RetentionMode::class,
            'retention_due_at' => 'datetime',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (Submission $submission): void {
            $immutable = ['form_id', 'form_version_id', 'submitted_by', 'actor_type', 'normalized_payload', 'schema_snapshot_hash', 'payload_hash', 'idempotency_key', 'idempotency_scope', 'received_at'];
            foreach ($immutable as $attribute) {
                if ($submission->isDirty($attribute)) {
                    throw new LogicException(__('glitter-form_builder::messages.submission.immutable'));
                }
            }
        });
    }

    public function uniqueIds(): array { return ['uuid']; }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function form(): BelongsTo { return $this->belongsTo(Form::class); }
    public function formVersion(): BelongsTo { return $this->belongsTo(FormVersion::class); }
    public function submittedUser(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function values(): HasMany { return $this->hasMany(SubmissionValue::class)->orderBy('position'); }
}
