<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use Illuminate\Http\Request;

final class AdminSubmissionDetailResource extends AdminSubmissionResource
{
    public function toArray(Request $request): array { return $this->detail($request); }
}
