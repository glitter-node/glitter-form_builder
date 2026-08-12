<?php

return [
    'form' => [
        'fetch_success' => 'Forms retrieved.',
        'create_success' => 'Form created.',
        'update_success' => 'Form updated.',
        'delete_success' => 'Form deleted or archived.',
        'publish_success' => 'A new form version was published.',
        'not_found' => 'Form not found.',
        'slug_immutable' => 'The slug cannot be changed after the first publish.',
        'archived_read_only' => 'An archived form cannot be updated or published.',
        'retention_not_acknowledged' => 'The retention policy must be acknowledged before publishing.',
    ],
    'version' => [
        'immutable' => 'A published form version cannot be updated or deleted.',
    ],
    'field_types' => ['fetch_success' => 'Field types retrieved.'],
    'schema' => ['validation_complete' => 'Schema validation completed.'],
    'public_schema' => [
        'fetch_success' => 'Published form schema retrieved.',
        'internal_error' => 'The published form cannot be rendered at this time.',
    ],
    'submission' => [
        'received' => 'Submission received.',
        'fetch_success' => 'Submissions retrieved.',
        'not_found' => 'Submission not found.',
        'validation_failed' => 'Review the submitted values.',
        'invalid_field' => 'The value is invalid.',
        'unknown_field' => 'The field is not present in the published form.',
        'payload_too_large' => 'The submission exceeds the allowed size.',
        'invalid_envelope' => 'The submission contains unsupported properties.',
        'invalid_idempotency_key' => 'The Idempotency-Key format is invalid.',
        'idempotency_conflict' => 'The request key was already used with different content.',
        'unavailable' => 'This form cannot accept submissions at this time.',
        'immutable' => 'An accepted submission snapshot cannot be modified.',
    ],
];
