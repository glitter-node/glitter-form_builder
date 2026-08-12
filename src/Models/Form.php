<?php

namespace Modules\Glitter\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Enums\FormStatus;
use Modules\Glitter\FormBuilder\Enums\RetentionMode;

class Form extends Model
{
    use HasUuids;

    protected $table = 'form_forms';

    protected $fillable = [
        'uuid', 'slug', 'title', 'description', 'status', 'draft_schema',
        'access_mode', 'retention_mode', 'retention_days',
        'retention_acknowledged_at', 'current_published_version_id',
        'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'description' => 'array',
            'draft_schema' => 'array',
            'status' => FormStatus::class,
            'access_mode' => FormAccessMode::class,
            'retention_mode' => RetentionMode::class,
            'retention_days' => 'integer',
            'retention_acknowledged_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class, 'form_id')->orderByDesc('revision');
    }

    public function currentPublishedVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'current_published_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
