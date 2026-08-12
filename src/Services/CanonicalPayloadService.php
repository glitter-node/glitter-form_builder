<?php

namespace Modules\Glitter\FormBuilder\Services;

use JsonException;

final class CanonicalPayloadService
{
    /** @param array<string, mixed> $payload */
    public function hash(array $payload): string
    {
        return hash('sha256', $this->encode($payload));
    }

    /** @param array<string, mixed> $payload */
    public function encode(array $payload): string
    {
        $canonical = $this->sort($payload);

        try {
            return json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('submission.payload_not_json_safe', 0, $exception);
        }
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sort($item), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sort($item);
        }

        return $value;
    }
}
