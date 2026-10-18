<?php

namespace Modules\Glitter\FormBuilder\Repositories;

use App\Helpers\PermissionHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormRepositoryInterface;

class FormRepository implements FormRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Form::query()->with(['creator', 'updater', 'currentPublishedVersion']);
        PermissionHelper::applyPermissionScope($query, 'glitter-form_builder.forms.read');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($nested) => $nested
                ->where('slug', 'like', '%'.$search.'%')
                ->orWhere('title', 'like', '%'.$search.'%'));
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage);
    }

    public function findOrFailByUuid(string $uuid): Form
    {
        return Form::query()
            ->with(['creator', 'updater', 'currentPublishedVersion'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function lockById(int $id): Form
    {
        return Form::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): Form
    {
        return Form::query()->create($data);
    }

    public function update(Form $form, array $data): Form
    {
        $form->fill($data)->save();

        return $form->fresh(['creator', 'updater', 'currentPublishedVersion']);
    }

    public function delete(Form $form): bool
    {
        return (bool) $form->delete();
    }
}
