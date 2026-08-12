<?php

namespace Modules\Glitter\FormBuilder\Rendering;

use Modules\Glitter\FormBuilder\Contracts\Fields\FieldProvider;

final class PublicRendererPolicy
{
    private const COMPONENTS = [
        'text' => 'Input',
        'textarea' => 'Textarea',
        'email' => 'Input',
        'number' => 'Input',
        'select' => 'Select',
        'radio' => 'Select',
        'checkbox' => 'Checkbox',
        'date' => 'Input',
        'datetime' => 'Input',
        'hidden' => 'Hidden',
        'consent' => 'Checkbox',
    ];

    public function supports(FieldProvider $provider): bool
    {
        $type = $provider->type();
        $renderer = $provider->rendererDescriptor();

        return isset(self::COMPONENTS[$type])
            && $provider->providerId() === 'glitter-form_builder'
            && $renderer->available
            && $renderer->contractMajor === 1
            && $renderer->identifier === "glitter-form_builder:{$type}";
    }

    public function component(string $type): ?string
    {
        return self::COMPONENTS[$type] ?? null;
    }
}
