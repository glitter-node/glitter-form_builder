<?php

namespace Modules\Glitter\FormBuilder\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\FormBuilder\Models\Submission;

interface SubmissionRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Submission;
    /** @param list<array<string, mixed>> $values */
    public function createValues(Submission $submission, array $values): void;
    public function findByUuid(string $uuid): ?Submission;
    public function findByIdempotency(int $versionId, string $scope, string $key, bool $lock = false): ?Submission;
    /** @param array<string, mixed> $filters */
    public function paginateForAdmin(array $filters, int $perPage): LengthAwarePaginator;
}
