<?php

namespace Modules\Glitter\FormBuilder\Repositories;

use Modules\Glitter\FormBuilder\Enums\FormStatus;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Repositories\Contracts\PublicFormRepositoryInterface;

final class PublicFormRepository implements PublicFormRepositoryInterface
{
    public function findPublishedBySlug(string $slug): ?Form
    {
        return Form::query()
            ->with('currentPublishedVersion')
            ->where('slug', $slug)
            ->where('status', FormStatus::Published->value)
            ->whereNotNull('current_published_version_id')
            ->first();
    }
}
