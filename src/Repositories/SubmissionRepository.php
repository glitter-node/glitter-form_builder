<?php

namespace Modules\Glitter\FormBuilder\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\FormBuilder\Models\Submission;
use Modules\Glitter\FormBuilder\Repositories\Contracts\SubmissionRepositoryInterface;

final class SubmissionRepository implements SubmissionRepositoryInterface
{
    public function create(array $attributes): Submission
    {
        return Submission::query()->create($attributes);
    }

    public function createValues(Submission $submission, array $values): void
    {
        if ($values !== []) {
            $submission->values()->createMany($values);
        }
    }

    public function findByUuid(string $uuid): ?Submission
    {
        return Submission::query()->with(['form', 'formVersion', 'submittedUser', 'values'])->where('uuid', $uuid)->first();
    }

    public function findByIdempotency(int $versionId, string $scope, string $key, bool $lock = false): ?Submission
    {
        $query = Submission::query()->where('form_version_id', $versionId)->where('idempotency_scope', $scope)->where('idempotency_key', $key);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator
    {
        return Submission::query()
            ->with(['form', 'formVersion', 'submittedUser'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['form'] ?? null, fn ($query, $form) => $query->whereHas('form', fn ($formQuery) => $formQuery->where('uuid', $form)->orWhere('slug', $form)))
            ->orderByDesc('received_at')
            ->paginate($perPage);
    }
}
