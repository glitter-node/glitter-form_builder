<?php

namespace Modules\Glitter\FormBuilder\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Modules\Glitter\FormBuilder\DTO\SubmissionContext;

final class SubmissionContextFactory
{
    public function fromRequest(Request $request, bool $admin): SubmissionContext
    {
        /** @var User|null $user */
        $user = $request->user();
        $actor = $user === null ? 'guest' : ($admin ? 'admin' : 'authenticated');
        $secret = (string) config('app.key');
        $hash = static fn (?string $value): ?string => $value === null || $value === '' ? null : hash_hmac('sha256', $value, $secret);

        return new SubmissionContext(
            $actor,
            $user?->getKey(),
            $request->input('context.locale'),
            $request->input('context.timezone'),
            'public',
            $hash($request->ip()),
            $hash($request->userAgent()),
            is_string($user?->uuid) ? $user->uuid : null,
        );
    }
}
