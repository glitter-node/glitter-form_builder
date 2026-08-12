<?php

namespace Modules\Glitter\FormBuilder\Repositories\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\FormBuilder\Models\Form;

interface FormRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;
    public function findOrFailByUuid(string $uuid): Form;
    public function lockById(int $id): Form;
    public function create(array $data): Form;
    public function update(Form $form, array $data): Form;
    public function delete(Form $form): bool;
}
