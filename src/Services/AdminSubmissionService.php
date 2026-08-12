<?php

namespace Modules\Glitter\FormBuilder\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Glitter\FormBuilder\Models\Submission;
use Modules\Glitter\FormBuilder\Repositories\Contracts\SubmissionRepositoryInterface;

final class AdminSubmissionService
{
    public function __construct(private readonly SubmissionRepositoryInterface $submissions) {}

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->submissions->paginateForAdmin($filters, $perPage);
    }

    public function find(string $uuid): Submission
    {
        return $this->submissions->findByUuid($uuid) ?? throw (new ModelNotFoundException)->setModel(Submission::class);
    }
}
