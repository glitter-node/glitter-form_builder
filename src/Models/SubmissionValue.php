<?php

namespace Modules\Glitter\FormBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Modules\Glitter\FormBuilder\Enums\SubmissionValueKind;

final class SubmissionValue extends Model
{
    public $timestamps = false;

    protected $table = 'form_submission_values';

    protected $fillable = [
        'submission_id', 'field_key', 'field_type', 'value_kind', 'value_text', 'value_number',
        'value_boolean', 'value_date', 'value_datetime', 'value_hash', 'is_sensitive',
        'is_indexable', 'position',
    ];

    protected function casts(): array
    {
        return [
            'value_kind' => SubmissionValueKind::class,
            'value_boolean' => 'boolean',
            'value_date' => 'date:Y-m-d',
            'value_datetime' => 'datetime',
            'is_sensitive' => 'boolean',
            'is_indexable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException(__('glitter-form_builder::messages.submission.immutable'));
        });
    }

    public function submission(): BelongsTo { return $this->belongsTo(Submission::class); }
}
