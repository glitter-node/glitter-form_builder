# Glitter Form Builder Form Schema Contract 초안

> 상태: Phase 2/3 입력으로 사용할 `draft`. PHP class, JSON Layout 문법, migration을 확정하는 문서가 아니다.  
> 연결 결정: FB-DEC-002, 004, 005, 006, 008, 010, 011.
> Envelope, built-in set, identifier/options/validation/translation/provider-missing 의미는 [form-schema.md](../contracts/form-schema.md)와 [field-provider.md](../contracts/field-provider.md)에 의해 **frozen by Phase 0.5**다.

## 1. 목적과 경계

Form Schema는 운영자가 정의한 데이터 입력·검증 계약이다. G7 JSON Layout Schema가 아니며 Layout component/handler/prop을 저장하지 않는다. Renderer/transformer가 현재 template capability에 맞춰 Form Schema를 Layout 또는 전용 renderer input으로 변환한다.

server FormRequest와 Field provider validator가 최종 권위다. client validation은 사용성 보조다.

## 2. Envelope

```json
{
  "schemaVersion": "1.0",
  "form": {
    "publicId": "01989f2e-2f2c-7d53-8f13-112233445566",
    "slug": "contact-us",
    "revision": 1,
    "accessMode": "authenticated_only",
    "title": {"ko": "문의하기", "en": "Contact us"},
    "description": {"ko": "문의 내용을 입력해 주세요.", "en": "Send us your question."},
    "retention": {
      "mode": "manual",
      "days": null,
      "automaticEnforcement": false
    }
  },
  "fields": [],
  "providerSnapshot": [],
  "publishedAt": "2026-07-31T00:00:00Z"
}
```

### Envelope 규칙

- `schemaVersion`: contract major/minor. unknown major는 거부, 같은 major의 unknown optional property는 보존 후 무시할 수 있다.
- `form.publicId`: UUIDv7 Form identity.
- `form.slug`: 첫 publish 후 immutable.
- `form.revision`: Form 내부 증가 정수.
- `accessMode`: `authenticated_only|guest_and_authenticated|admin_only`.
- `title`, `description`: locale map. title은 publish 시 기본 locale 값 필수다.
- `retention`: FB-DEC-005 정책 snapshot.
- `fields`: flat ordered list. 배열 순서가 표시·export 기본 순서다.
- `providerSnapshot`: 외부 provider fallback metadata. 실행 code를 직렬화하지 않는다.
- `publishedAt`: immutable publish timestamp.

draft 저장 envelope에는 `publicId`, `revision`, `publishedAt`이 없거나 임시일 수 있다. published snapshot에는 모두 있어야 한다.

## 3. Field 공통 속성

```json
{
  "key": "email",
  "type": "email",
  "provider": "glitter-form_builder",
  "contractVersion": "1.0",
  "label": {"ko": "이메일", "en": "Email"},
  "description": {"ko": "답변 받을 주소", "en": "Address for the reply"},
  "required": true,
  "default": null,
  "validation": {
    "maxLength": 254
  },
  "sensitive": true,
  "indexable": false,
  "exportable": true
}
```

| 속성 | 규칙 |
|---|---|
| `key` | `^[a-z][a-z0-9_]{0,63}$`, revision 사이 의미 안정성 유지 |
| `type` | built-in unqualified 또는 external `{provider}:{local_type}` |
| `provider` | built-in은 `glitter-form_builder`, external은 G7 extension identifier |
| `contractVersion` | major/minor 문자열 |
| `label` | locale map, 기본 locale 필수 |
| `description` | locale map, 선택 |
| `required` | boolean |
| `default` | type validator를 통과해야 함. 없는 값은 `null` |
| `validation` | type별 allowlist object |
| `sensitive` | 민감값 표시·로그 redaction marker |
| `indexable` | typed search index 생성 여부. sensitive면 반드시 false |
| `exportable` | 권한 있는 export에 포함 가능한지 여부 |

MVP 최대 field 수의 제안값은 100개다. 이 값과 schema/payload byte 제한은 Phase 3 성능 테스트에서 확정 전까지 config 후보이며 public contract 상수로 하드코딩하지 않는다.

## 4. Built-in field

### `text`

- 값: Unicode string.
- 속성: 공통 속성.
- validation: `minLength`, `maxLength`, `in`.
- 정규화: line ending 정규화, 앞뒤 공백 제거 여부는 field option이 아니라 일관된 built-in 정책으로 Phase 3에서 확정한다.

### `textarea`

- 값: Unicode string.
- validation: `minLength`, `maxLength`.
- HTML로 해석하지 않고 plain text로 저장·표시한다.

### `email`

- 값: string.
- validation: `maxLength`; email 형식은 type 자체가 강제한다.
- 정규화: domain case 처리 가능, local part를 임의 소문자화하지 않는다.

### `number`

- 값: JSON number 또는 decimal string 중 하나로 canonicalize해야 한다. 금액/고정소수점 오차 때문에 Phase 3에서 decimal string을 우선 검증한다.
- validation: `min`, `max`, `integer`, `decimalPlaces`.
- DB typed index는 `value_number`의 정확한 decimal precision을 migration 전 확정한다.

### `select`

- 값: option `value` 하나.
- validation: options membership 자동 강제.
- options 필수, 빈 options는 publish 불가.

### `radio`

- 값: option `value` 하나.
- validation과 options는 `select`와 동일.

### `checkbox`

- 값: boolean 하나.
- 복수 checkbox group은 MVP 미지원.
- validation: `accepted`만 허용 가능.

### `date`

- 값: `YYYY-MM-DD` canonical string.
- validation: 고정 ISO date인 `min`, `max`.
- locale 표시와 저장 형식을 분리한다.

### `datetime`

- 값: timezone offset을 포함한 RFC 3339 string으로 입력받고 UTC canonical value로 정규화하는 후보.
- validation: 고정 RFC 3339 `min`, `max`.
- timezone 없는 local datetime 처리 정책은 Phase 3에서 확정 전까지 publish validation으로 금지하는 방향을 권장한다.

### `hidden`

- 값: scalar string.
- default 필수 후보.
- client가 바꿀 수 있으므로 인증·권한·가격 같은 보안 주장에 사용하면 안 된다.
- indexable 기본 false.

### `consent`

- 값: boolean.
- `required=true`와 `accepted=true`가 publish 기본 조건이다.
- 추가 속성:

```json
{
  "statement": {"ko": "개인정보 처리에 동의합니다.", "en": "I agree to the privacy notice."},
  "policyUrl": "https://example.invalid/privacy",
  "policyVersion": "2026-07-31"
}
```

- `statement`는 운영자 편집 다국어 데이터다. `policyUrl`은 허용 scheme 검증, `policyVersion`은 제출 snapshot에 보존한다.

## 5. Options 구조

`select`와 `radio`만 사용한다.

```json
{
  "options": [
    {
      "value": "sales",
      "label": {"ko": "영업 문의", "en": "Sales"}
    },
    {
      "value": "support",
      "label": {"ko": "기술 지원", "en": "Support"}
    }
  ]
}
```

- `value`는 field 안에서 unique한 stable string이며 `^[a-z0-9][a-z0-9_-]{0,63}$` 권장.
- label은 locale map.
- published revision에서 option value의 의미를 바꾸지 않는다. 삭제/rename은 새 FormVersion에만 적용된다.
- client label은 신뢰하지 않고 server가 value membership을 검증한다.

## 6. Validation allowlist

| Rule | 대상 | 값 |
|---|---|---|
| `minLength` | text, textarea | non-negative integer |
| `maxLength` | text, textarea, email | positive integer |
| `in` | text | scalar array, options 대체 용도 남용 금지 |
| `min` | number, date, datetime | 해당 type canonical scalar |
| `max` | number, date, datetime | 해당 type canonical scalar |
| `integer` | number | boolean |
| `decimalPlaces` | number | bounded integer |
| `accepted` | checkbox, consent | boolean true |

`required`는 공통 속성이다. 다음은 MVP에서 금지한다.

- 임의 regex
- PHP/Laravel rule class name
- `exists`, `unique`
- 외부 HTTP callback
- 다른 field와 비교하는 조건
- executable expression
- client-only custom validator

provider는 자기 type의 선언된 validation schema를 제공할 수 있으나 publish 시 contract snapshot과 server validator가 함께 resolve돼야 한다.

## 7. Translation 저장 전략

- Form 이름, 설명, field label/description, option label, consent statement는 운영자가 바꾸는 domain data이므로 locale-keyed JSON object로 저장하고 FormVersion snapshot에 복사한다.
- module 메뉴, 버튼, validation message, status label 등 제품 UI 문장은 `resources/lang`에 두고 Layout에서는 `$t:` key만 참조한다.
- schema의 operator text를 `$t:` key로 위장하지 않는다.
- publish 시 site 기본 locale 또는 모듈이 정한 fallback locale의 required text가 있어야 한다.
- locale fallback 순서와 지원 locale 목록은 G7 설정을 재사용하며 schema가 독자 locale 정책을 만들지 않는다.

## 8. 민감성·검색·내보내기

- 기본값 후보: `sensitive=false`, `indexable=false`, `exportable=true`.
- `sensitive=true`는 `indexable=false`를 강제한다.
- sensitive 값은 activity log, processing log, validation exception context에 포함하지 않는다.
- `exportable=true`는 export 권한을 대체하지 않는다.
- typed index에는 indexable field만 넣으며 raw payload가 권위 원본이다.
- email은 개인 식별 가능성이 높으므로 Designer default preset은 sensitive=true를 권장하되 operator가 낮추는 정책은 Phase 3에서 검토한다.

## 9. Provider 부재와 unknown field

- draft load: unknown field object를 손실 없이 보존하고 `unresolved` 진단을 표시한다.
- draft save: unknown property를 provider namespace 아래 보존할 수 있지만 Form Builder가 해석한 것처럼 정규화하지 않는다.
- publish: unresolved provider/type, duplicate type, contract major mismatch, renderer/validator 누락이면 실패한다.
- published runtime: provider가 사라지면 새 schema 제공/submit을 unavailable 처리한다.
- history: provider가 없어도 snapshot label, type, field key와 raw JSON으로 조회·내보내기 가능하다. raw 값은 plain safe representation으로만 표시한다.

## 10. Publish validation

publish는 최소 다음을 원자적으로 확인하고 새 immutable FormVersion을 만든다.

1. Form은 draft/editable 상태다.
2. slug와 public UUID가 유효하다.
3. access mode와 retention acknowledgement가 존재한다.
4. field key가 Form 안에서 unique하고 규칙에 맞는다.
5. 모든 field/provider/contract가 resolve된다.
6. built-in/provider validation schema가 allowlist와 맞는다.
7. default가 field validator를 통과한다.
8. sensitive/indexable invariant가 맞는다.
9. select/radio options가 비어 있지 않고 value가 unique하다.
10. 기본 locale의 required translation이 있다.
11. consent policy metadata가 유효하다.
12. canonical JSON 생성과 schema hash 계산이 성공한다.

publish 후 snapshot은 수정하지 않는다. 변경은 새 revision을 만든다.

## 11. Provider snapshot 예

```json
{
  "providerSnapshot": [
    {
      "provider": "acme-fields",
      "fieldType": "acme-fields:phone",
      "contractVersion": "1.0",
      "fallbackLabel": {"ko": "전화번호", "en": "Phone"},
      "storageKind": "string",
      "redactionKind": "masked"
    }
  ]
}
```

provider implementation class, callable, secret, Layout handler 이름은 snapshot에 넣지 않는다.

## 12. 미지원 기능

- file/image/signature
- address/국가별 phone
- user/CRM/reservation/product picker
- rich text
- calculation/payment
- repeat/nested group, matrix, rating
- conditional visibility
- multi-step
- cross-field rule
- arbitrary Layout component/prop/handler

unknown feature를 조용히 무시하지 않고 draft diagnostic 또는 publish error로 반환한다.

## 13. 미래 호환성

- major schema version이 다르면 write/publish 금지.
- 같은 major의 unknown optional envelope property는 round-trip 보존을 우선한다.
- field provider version은 schema version과 독립적이다.
- published snapshot migration은 원본을 덮어쓰지 않고 새 derived representation 또는 새 revision을 만든다.
- export에는 `schemaVersion`, Form UUID, revision, field key/type/provider snapshot을 함께 포함한다.
- Form Schema → Layout 변환기는 지원 확인된 G7 component/prop/handler만 생성해야 한다.

## 14. 구현 전 남은 수치 결정

- schema/payload 최대 bytes
- field 최대 개수 100 제안값
- text/textarea 기본 maxLength
- decimal precision/scale
- datetime timezone 입력 정책
- supported locale/fallback 호출 API

이 값은 Phase 3 테스트 근거 없이 public API 상수로 확정하지 않는다.

## 15. Phase 0.5 권위 연결

- [Form Schema Contract](../contracts/form-schema.md)
- [Field Provider Contract](../contracts/field-provider.md)
- [DTO Contract](../contracts/dto.md)
- [Compatibility](../contracts/compatibility.md)
- [Security](../contracts/security.md)

canonicalization algorithm과 제한 수치·실제 PHP API는 deferred다. 실제 DTO 이름에는 G7 관례에 따라 `Data`/`Dto` 접미사를 사용하지 않는다.
