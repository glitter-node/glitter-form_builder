<?php

namespace Modules\Glitter\FormBuilder\Repositories\Contracts;

use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\FormVersion;

interface FormVersionRepositoryInterface
{
    public function nextRevision(Form $form): int;
    public function create(array $data): FormVersion;
}
