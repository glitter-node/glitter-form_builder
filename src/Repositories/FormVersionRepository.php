<?php

namespace Modules\Glitter\FormBuilder\Repositories;

use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\FormVersion;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormVersionRepositoryInterface;

class FormVersionRepository implements FormVersionRepositoryInterface
{
    public function nextRevision(Form $form): int
    {
        return ((int) FormVersion::query()->where('form_id', $form->id)->max('revision')) + 1;
    }

    public function create(array $data): FormVersion
    {
        return FormVersion::query()->create($data);
    }
}
