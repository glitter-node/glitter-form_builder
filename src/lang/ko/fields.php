<?php

return [
    'types' => [
        'text' => ['name' => '텍스트', 'description' => '한 줄 일반 텍스트입니다.'],
        'textarea' => ['name' => '여러 줄 텍스트', 'description' => '여러 줄 일반 텍스트입니다.'],
        'email' => ['name' => '이메일', 'description' => '형식을 검증하는 이메일 주소입니다.'],
        'number' => ['name' => '숫자', 'description' => '정확한 십진수 값입니다.'],
        'select' => ['name' => '선택 목록', 'description' => '목록에서 하나를 선택합니다.'],
        'radio' => ['name' => '라디오', 'description' => '라디오 항목 하나를 선택합니다.'],
        'checkbox' => ['name' => '체크박스', 'description' => '단일 불리언 값입니다.'],
        'date' => ['name' => '날짜', 'description' => '달력 날짜입니다.'],
        'datetime' => ['name' => '날짜와 시간', 'description' => '사이트 현지 시간을 UTC로 정규화합니다.'],
        'hidden' => ['name' => '숨김', 'description' => '서버가 정의한 숨김 값입니다.'],
        'consent' => ['name' => '동의', 'description' => '동의문 식별자를 포함한 명시적 동의입니다.'],
    ],
    'validation' => ['invalid' => '필드 값이 올바르지 않습니다.'],
];
