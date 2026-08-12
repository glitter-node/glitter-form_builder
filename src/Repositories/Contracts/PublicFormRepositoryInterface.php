<?php

namespace Modules\Glitter\FormBuilder\Repositories\Contracts;

use Modules\Glitter\FormBuilder\Models\Form;

interface PublicFormRepositoryInterface
{
    public function findPublishedBySlug(string $slug): ?Form;
}
