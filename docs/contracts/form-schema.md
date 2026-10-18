# Form Schema Contract

> `schemaVersion: 1.0` architecture freeze. Phase 4 bundled experimental validator와 Phase 6 allowlisted public G7 Layout projector가 구현되었다.

구현 경로는 `src/Schema/FormSchemaParser.php`, `FormSchemaValidator.php`, `PublishSchemaValidator.php`와 `src/Schema/ValueObjects/*`다. Phase 6부터 renderer unavailable은 공개 projection 실패를 방지하기 위한 publish error다.

Phase 6 구현 경로는 `src/Rendering/FormLayoutProjector.php`와 `src/Rendering/ValueObjects/PublicFormSchema.php`다. built-in renderer는 고정 component mapping으로 available이며 hidden/sensitive default와 임의 renderer 지시는 공개 projection에서 제외된다. 외부/unknown/mismatched renderer는 공개 read에서 fail-closed한다.

## Canonical envelope

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
  "fields": [
    {
      "key": "email",
      "type": "email",
      "provider": "glitter-form_builder",
      "contractVersion": "1.0",
      "label": {"ko": "이메일", "en": "Email"},
      "description": {"ko": "답변 받을 주소", "en": "Address for the reply"},
      "required": true,
      "default": null,
      "validation": {"maxLength": 254},
      "sensitive": true,
      "indexable": false,
      "exportable": true
    }
  ],
  "providerSnapshot": [
    {
      "provider": "glitter-form_builder",
      "fieldType": "email",
      "contractVersion": "1.0",
      "storageKind": "string",
      "redactionKind": "masked"
    }
  ],
  "publishedAt": "2026-07-31T00:00:00Z"
}
```

Canonical JSON은 UTF-8, object key 정렬, array 순서 보존, 불필요한 whitespace 제거를 원칙으로 한다. 구체 canonicalization algorithm과 hash는 Phase 3 deferred다.

## Version

- `schemaVersion`은 major.minor 문자열이며 최초 `1.0`.
- unknown major는 publish/read-for-submit을 거부한다.
- 같은 major의 unknown optional property는 round-trip 보존한다.
- schema version과 provider contract version은 독립적이다.

## Field key와 type

- key: lowercase, `^[a-z][a-z0-9_]{0,63}$`, Form 내 unique.
- publish 후 같은 key의 의미를 바꾸지 않는다. rename/의미 변경은 새 key.
- built-in type: `text`, `textarea`, `email`, `number`, `select`, `radio`, `checkbox`, `date`, `datetime`, `hidden`, `consent`.
- external type: `{provider}:{localType}`. 자세한 규칙은 [field-provider.md](field-provider.md).

## Common field properties

필수: `key`, `type`, `provider`, `contractVersion`, `label`, `required`, `sensitive`, `indexable`, `exportable`.

선택: `description`, `default`, `validation`, type-specific properties.

- `sensitive=true`이면 `indexable=false`.
- `exportable=true`는 export permission을 대체하지 않는다.
- default는 provider validator를 통과해야 한다.
- hidden 값은 client가 변조할 수 있으므로 권한·가격·인증 주장에 사용하지 않는다.

## Options

`select`/`radio`만 사용한다.

```json
{
  "options": [
    {"value": "sales", "label": {"ko": "영업 문의", "en": "Sales"}},
    {"value": "support", "label": {"ko": "기술 지원", "en": "Support"}}
  ]
}
```

- option value는 field 안에서 unique, lowercase `^[a-z0-9][a-z0-9_-]{0,63}$`.
- label은 locale map.
- client label은 저장 권위가 아니며 server가 value membership을 검증한다.
- published revision에서 option value 의미를 재사용하지 않는다.

## Validation allowlist

| Rule | 대상 |
|---|---|
| `required` | 모든 field의 공통 boolean |
| `minLength`, `maxLength` | text, textarea; email은 maxLength |
| `in` | text |
| `min`, `max` | number, date, datetime |
| `integer`, `decimalPlaces` | number |
| `accepted` | checkbox, consent |

임의 regex, PHP class, `exists`, `unique`, HTTP callback, executable expression, cross-field rule은 미지원이다.

## Built-in semantics

- `text`, `textarea`: plain Unicode text, HTML 아님.
- `email`: email format 강제, local part 임의 lowercase 금지.
- `number`: exact decimal representation 정책은 Phase 3 deferred.
- `select`, `radio`: 단일 option value.
- `checkbox`: 단일 boolean; multi-select 아님.
- `date`: `YYYY-MM-DD`.
- `datetime`: timezone 포함 RFC 3339 입력과 UTC 정규화 후보; timezone 세부는 deferred.
- `hidden`: scalar, security authority 아님.
- `consent`: boolean true, `required=true`, statement/policy version snapshot. Built-in palette defaults use the stable `statementId` `consent`; legacy draft values where that exact built-in identifier was serialized as `null` or an empty string are normalized to the same identifier before validation and snapshot publication.

## Translation

Form/field/option/consent 문구는 운영자 편집 domain data이므로 locale-keyed JSON이다. 버튼·상태·validation message 같은 module UI 문장은 lang file에 두며 Layout에서는 `$t:`만 사용한다. publish 시 G7 기본/fallback locale의 필수 label이 있어야 한다.

## Immutable snapshot

- draft working copy는 mutable.
- publish 성공 시 self-contained FormVersion snapshot을 새 revision으로 만든다.
- published snapshot은 in-place update하지 않는다.
- field order는 fields array order다.
- provider implementation class, callable, secret, Layout handler는 snapshot에 넣지 않는다.

## Unknown/missing provider

- draft: unknown field object를 손실 없이 보존하고 unresolved diagnostic 표시.
- publish: 모든 provider/type/server validator/renderer descriptor resolve 필수.
- published provider 비활성: 신규 schema-for-submit/Submission 차단.
- history: raw Submission과 snapshot label/type/key로 조회 및 canonical JSON export 가능.
- provider 복귀: 같은 compatible major면 표시/index rebuild 가능; raw 재작성 금지.
- renderer만 unavailable: 관리자/history에서는 escaped plain JSON fallback, 공개 신규 제출은 fail-closed.
- server validator/frontend renderer major mismatch: publish 및 신규 제출 차단.

## Publish validation

slug/UUID, access/retention acknowledgement, unique key, provider compatibility, validation allowlist, default, sensitive/indexable invariant, option uniqueness, translations, consent metadata, canonicalization/hash를 모두 성공해야 한다.

## Unsupported

file, image, signature, address, user/CRM/reservation/product picker, rich text, calculated/payment, repeating/nested group, matrix, rating, conditional visibility, multi-step.

unknown 기능은 조용히 무시하지 않는다.

## Compatibility

- optional metadata 추가는 same-major non-breaking.
- required field 추가, type/value 의미 변경, validation 강화로 기존 valid input을 거부하면 breaking.
- published FormVersion은 migration 대신 원본 유지 + 새 derived representation 또는 새 revision을 사용한다.
- export에는 schemaVersion, Form UUID, revision, field key/type/provider version을 포함한다.

## Phase 4 validation issue contract

- path: G7 FormRequest와 일관된 dot notation. 예: `fields.2.type`.
- issue: `code`, `path`, `message_key`, safe scalar `parameters`, `severity`; API formatter가 번역된 `message`를 추가한다.
- Provider exception message/class와 default/config 원문은 issue에 포함하지 않는다.
- draft: unresolved Provider와 incomplete default locale을 diagnostic으로 보존할 수 있다. 구조/JSON-safe/duplicate 위반은 error다.
- publish: unresolved/incompatible Provider, invalid definition, required locale 누락은 error다.
- `field.renderer_unavailable`: Phase 4에서는 warning이었으나 Phase 6 공개 renderer 도입 후 publish error로 승격되었다. Phase 6 고정 component allowlist에 포함되지 않은 외부 provider는 Registry에 등록할 수 있어도 공개 폼으로 publish할 수 없다.

보안 제한은 same-major operational constraint이며 schema 의미를 바꾸는 field semantics가 아니다: encoded JSON 262,144 bytes, fields 100, options/field 100, locales/field 20, label 255 chars, description 2,000 chars, depth 16, nodes 10,000. 구현 SSoT는 `src/Schema/SchemaLimits.php`다.

## Phase 5 editing boundary

Designer는 canonical camelCase envelope를 직접 편집하며 `resources/js/designer/schema.ts::toCanonicalSchema()`가 UI state와 API payload의 명시적 경계다. `POST /api/modules/glitter-form_builder/admin/forms/validate-schema`는 저장하지 않고 `draft` 또는 `publish-preview` 결과를 HTTP 200으로 반환한다. request envelope 자체의 오류만 FormRequest 422다. publish endpoint의 schema 오류 422 의미는 유지한다.
