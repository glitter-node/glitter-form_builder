<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;
use Modules\Glitter\FormBuilder\DTO\SubmissionReceipt;

final class SubmissionReceiptResource extends BaseApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var SubmissionReceipt $receipt */
        $receipt = $this->resource;

        return [
            'submission' => [
                'uuid' => $receipt->uuid,
                'status' => $receipt->status,
                'received_at' => $receipt->receivedAt,
                'form' => ['slug' => $receipt->formSlug, 'version' => $receipt->revision],
            ],
            'idempotent_replay' => $receipt->idempotentReplay,
        ];
    }
}
