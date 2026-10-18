<?php

return [
    'form' => [
        'fetch_success' => '폼을 조회했습니다.',
        'create_success' => '폼을 생성했습니다.',
        'update_success' => '폼을 수정했습니다.',
        'delete_success' => '폼을 삭제하거나 보관했습니다.',
        'publish_success' => '새 폼 버전을 게시했습니다.',
        'not_found' => '폼을 찾을 수 없습니다.',
        'slug_immutable' => '첫 게시 이후에는 슬러그를 변경할 수 없습니다.',
        'archived_read_only' => '보관된 폼은 수정하거나 게시할 수 없습니다.',
        'retention_not_acknowledged' => '게시 전에 개인정보 보존 정책을 확인해야 합니다.',
    ],
    'version' => [
        'immutable' => '게시된 폼 버전은 수정하거나 삭제할 수 없습니다.',
    ],
    'field_types' => ['fetch_success' => '필드 유형을 조회했습니다.'],
    'schema' => ['validation_complete' => '스키마 검증을 완료했습니다.'],
    'public_schema' => [
        'fetch_success' => '게시된 폼 스키마를 조회했습니다.',
        'internal_error' => '현재 게시된 폼을 렌더링할 수 없습니다.',
    ],
    'submission' => [
        'received' => '제출이 접수되었습니다.',
        'fetch_success' => '제출을 조회했습니다.',
        'not_found' => '제출을 찾을 수 없습니다.',
        'validation_failed' => '제출 내용을 확인해 주세요.',
        'invalid_field' => '유효하지 않은 값입니다.',
        'unknown_field' => '게시된 폼에 없는 필드입니다.',
        'payload_too_large' => '제출 데이터가 허용 크기를 초과했습니다.',
        'invalid_envelope' => '허용되지 않은 제출 항목이 있습니다.',
        'invalid_idempotency_key' => 'Idempotency-Key 형식이 올바르지 않습니다.',
        'idempotency_conflict' => '같은 요청 키에 다른 제출 내용이 사용되었습니다.',
        'unavailable' => '현재 이 폼의 제출을 접수할 수 없습니다.',
        'immutable' => '접수된 제출 원본은 수정할 수 없습니다.',
    ],
];
