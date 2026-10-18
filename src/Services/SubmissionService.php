<?php

namespace Modules\Glitter\FormBuilder\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\FormBuilder\DTO\SubmissionInput;
use Modules\Glitter\FormBuilder\DTO\SubmissionReceipt;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Enums\RetentionMode;
use Modules\Glitter\FormBuilder\Enums\SubmissionStatus;
use Modules\Glitter\FormBuilder\Events\SubmissionReceived;
use Modules\Glitter\FormBuilder\Exceptions\IdempotencyConflictException;
use Modules\Glitter\FormBuilder\Exceptions\SubmissionValidationException;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\Submission;
use Modules\Glitter\FormBuilder\Rendering\FormLayoutProjector;
use Modules\Glitter\FormBuilder\Repositories\Contracts\PublicFormRepositoryInterface;
use Modules\Glitter\FormBuilder\Repositories\Contracts\SubmissionRepositoryInterface;

final class SubmissionService
{
    public function __construct(
        private readonly PublicFormRepositoryInterface $forms,
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly SubmissionNormalizationService $normalizer,
        private readonly CanonicalPayloadService $canonical,
        private readonly FormLayoutProjector $projector,
    ) {}

    public function submit(string $slug, SubmissionInput $input): SubmissionReceipt
    {
        $form = $this->forms->findPublishedBySlug($slug);
        $version = $form?->currentPublishedVersion;
        if ($form === null || $version === null || ! $this->canSubmit($form->access_mode, $input->context->actorType)) {
            throw (new ModelNotFoundException)->setModel(Form::class);
        }
        $snapshot = $version->snapshot;
        if (! is_array($snapshot)) {
            throw new SubmissionValidationException(['form' => [__('glitter-form_builder::messages.submission.unavailable')]]);
        }

        // Published snapshot and all provider/renderer identities must still resolve.
        $this->projector->project($snapshot);
        $normalized = $this->normalizer->normalize($snapshot, $input->fields, $input->context);
        $payloadHash = $this->canonical->hash($normalized->payload);
        $schemaHash = $this->canonical->hash($snapshot);
        $scope = $input->idempotencyKey === null ? null : $this->idempotencyScope($form->uuid, $input);

        if ($input->idempotencyKey !== null && $scope !== null) {
            $existing = $this->submissions->findByIdempotency((int) $version->getKey(), $scope, $input->idempotencyKey);
            if ($existing !== null) {
                return $this->replayOrConflict($existing, $payloadHash, $form->slug, (int) $version->revision);
            }
        }

        try {
            /** @var Submission $submission */
            $submission = DB::transaction(function () use ($form, $version, $input, $normalized, $payloadHash, $schemaHash, $scope): Submission {
                if ($input->idempotencyKey !== null && $scope !== null) {
                    $existing = $this->submissions->findByIdempotency((int) $version->getKey(), $scope, $input->idempotencyKey, true);
                    if ($existing !== null) {
                        if (! hash_equals($existing->payload_hash, $payloadHash)) {
                            throw new IdempotencyConflictException;
                        }

                        return $existing;
                    }
                }
                $receivedAt = CarbonImmutable::now('UTC');
                $submission = $this->submissions->create([
                    'form_id' => $form->getKey(), 'form_version_id' => $version->getKey(),
                    'status' => SubmissionStatus::Received->value, 'submitted_by' => $input->context->userId,
                    'actor_type' => $input->context->actorType, 'normalized_payload' => $normalized->payload,
                    'schema_snapshot_hash' => $schemaHash, 'payload_hash' => $payloadHash,
                    'idempotency_key' => $input->idempotencyKey, 'idempotency_scope' => $scope,
                    'locale' => $input->context->locale, 'timezone' => $input->context->timezone,
                    'source' => $input->context->source, 'ip_hash' => $input->context->ipHash,
                    'user_agent_hash' => $input->context->userAgentHash,
                    'retention_mode' => $form->retention_mode->value, 'retention_days' => $form->retention_days,
                    'retention_due_at' => $form->retention_mode === RetentionMode::Days && $form->retention_days !== null ? $receivedAt->addDays($form->retention_days) : null,
                    'received_at' => $receivedAt, 'metadata' => ['automatic_purge' => false],
                ]);
                $this->submissions->createValues($submission, $normalized->indexValues);

                event(new SubmissionReceived(
                    $submission->uuid, $form->uuid, $form->slug, (int) $version->revision,
                    $input->context->actorType, $input->context->userUuid,
                    $receivedAt->toIso8601ZuluString(), $input->context->locale, SubmissionStatus::Received->value,
                ));

                return $submission;
            }, 3);
        } catch (QueryException $exception) {
            if ($input->idempotencyKey === null || $scope === null) {
                throw $exception;
            }
            $existing = $this->submissions->findByIdempotency((int) $version->getKey(), $scope, $input->idempotencyKey);
            if ($existing === null) {
                throw $exception;
            }

            return $this->replayOrConflict($existing, $payloadHash, $form->slug, (int) $version->revision);
        }

        return $this->receipt($submission, $form->slug, (int) $version->revision, false);
    }

    private function canSubmit(FormAccessMode $mode, string $actor): bool
    {
        return match ($mode) {
            FormAccessMode::GuestAndAuthenticated => true,
            FormAccessMode::AuthenticatedOnly => $actor !== 'guest',
            FormAccessMode::AdminOnly => $actor === 'admin',
        };
    }

    private function idempotencyScope(string $formUuid, SubmissionInput $input): string
    {
        $actor = $input->context->userId === null ? 'guest:'.($input->context->ipHash ?? 'unknown') : 'user:'.$input->context->userId;

        return hash('sha256', $formUuid.'|'.$actor);
    }

    private function replayOrConflict(Submission $submission, string $payloadHash, string $slug, int $revision): SubmissionReceipt
    {
        if (! hash_equals($submission->payload_hash, $payloadHash)) {
            throw new IdempotencyConflictException;
        }

        return $this->receipt($submission, $slug, $revision, true);
    }

    private function receipt(Submission $submission, string $slug, int $revision, bool $replay): SubmissionReceipt
    {
        return new SubmissionReceipt(
            $submission->uuid,
            $submission->status instanceof SubmissionStatus ? $submission->status->value : (string) $submission->status,
            $submission->received_at->toIso8601ZuluString(),
            $slug,
            $revision,
            $replay,
        );
    }
}
