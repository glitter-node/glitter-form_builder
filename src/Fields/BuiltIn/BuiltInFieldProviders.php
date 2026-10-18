<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;

final class BuiltInFieldProviders
{
    public static function register(FieldTypeRegistry $registry): void
    {
        foreach ([
            new TextFieldProvider,
            new TextareaFieldProvider,
            new EmailFieldProvider,
            new NumberFieldProvider,
            new SelectFieldProvider,
            new RadioFieldProvider,
            new CheckboxFieldProvider,
            new DateFieldProvider,
            new DatetimeFieldProvider,
            new HiddenFieldProvider,
            new ConsentFieldProvider,
        ] as $provider) {
            $registry->register($provider);
        }
    }

    private function __construct() {}
}
