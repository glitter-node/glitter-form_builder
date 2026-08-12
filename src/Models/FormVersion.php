<?php

namespace Modules\Glitter\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class FormVersion extends Model
{
    public $timestamps = false;

    protected $table = 'form_versions';

    protected $fillable = [
        'form_id', 'revision', 'schema_version', 'snapshot',
        'published_by', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException(__('glitter-form_builder::messages.version.immutable'));
        });
        static::deleting(static function (): never {
            throw new LogicException(__('glitter-form_builder::messages.version.immutable'));
        });
    }

    public function isImmutable(): bool
    {
        return true;
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
