<?php

return [
    'types' => [
        'text' => ['name' => 'Text', 'description' => 'Single-line plain text.'],
        'textarea' => ['name' => 'Textarea', 'description' => 'Multi-line plain text.'],
        'email' => ['name' => 'Email', 'description' => 'Validated email address.'],
        'number' => ['name' => 'Number', 'description' => 'Exact decimal value.'],
        'select' => ['name' => 'Select', 'description' => 'Single selection from a list.'],
        'radio' => ['name' => 'Radio', 'description' => 'Single radio selection.'],
        'checkbox' => ['name' => 'Checkbox', 'description' => 'Single boolean value.'],
        'date' => ['name' => 'Date', 'description' => 'Calendar date.'],
        'datetime' => ['name' => 'Date and time', 'description' => 'Site-local date and time normalized to UTC.'],
        'hidden' => ['name' => 'Hidden', 'description' => 'Server-defined hidden value.'],
        'consent' => ['name' => 'Consent', 'description' => 'Explicit consent with a statement identifier.'],
    ],
    'validation' => ['invalid' => 'The field value is invalid.'],
];
