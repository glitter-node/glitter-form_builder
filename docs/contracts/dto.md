# DTO Contract

## Phase 7 implementation

`SubmissionInput`, `SubmissionContext`, `NormalizedSubmission`, `SubmissionReceipt`는 모두 readonly data-only 값이다. Request, Model, Resource, ORM Collection, Closure와 Container를 포함하지 않는다. `SubmissionReceived` event 역시 raw field payload가 아닌 안전한 identity metadata만 전달한다.

> DTO shape semantics frozen. 실제 PHP class는 아직 없다.

## G7 naming 적용

요청 단계 후보명 `FormSchemaData`, `HandlerExecutionData` 등은 G7 DTO 규칙의 `Data`/`Dto` 접미사 금지와 충돌한다 (`docs/backend/dto.md:210-234`). 실제 공개 후보명은 아래처럼 의미 이름만 사용한다.

모든 공개 DTO는 ORM Model/Repository/Request를 노출하지 않는 immutable Value Object이며 `toArray()`를 제공한다. queue/event 대상은 `fromArray()` 또는 별도 rehydrator 필요성을 구현 단계에서 결정한다.

## 공개 DTO

| DTO | 목적 | 필수 필드 | 선택 필드 | Raw/민감정보 | 직렬화 |
|---|---|---|---|---|---|
| `FormSchema` | draft/published schema envelope | schemaVersion, form metadata, fields | provider snapshots | field defaults만; Submission raw 없음 | JSON 가능 |
| `FormVersionSnapshot` | immutable publish 결과 | form UUID, revision, schema, hash, publishedAt | actor UUID | 없음 | JSON 가능 |
| `FieldDefinition` | 한 field 계약 | key/type/provider/version/labels/flags | default, validation, type config | default가 sensitive면 log 금지 | JSON 가능 |
| `FieldValidationContext` | Provider server validation 입력 | Form/revision/FieldDefinition/rawValue/actorKind | locale | 자기 field raw 가능, log 금지 | queue 금지 기본 |
| `FieldNormalizationResult` | canonical value 결과 | canonicalValue, storageKind, redactionKind | indexProjection | field value 포함 가능 | 제한 직렬화 |
| `SubmissionInput` | validation 전 입력 envelope | form identifier, idempotency key, input map, source | actor UUID | raw 포함, 민감 가능 | queue/event 금지 |
| `SubmissionSnapshot` | 저장된 원본의 restricted view | Submission/Form/revision IDs, raw snapshot ref/hash | raw accessor | raw 본문 기본 미포함 | identity만 가능 |
| `SubmissionContext` | Connector read-only 입력 | IDs, revision, normalized values, field metadata | locale/actor kind | 값 포함 가능, secret 없음 | 직접 event 금지 |
| `HandlerExecution` | 실행 계획 | attempt/submission/binding/handler IDs, mode, generation | queue metadata | raw/secret 없음 | 가능 |
| `HandlerExecutionContext` | Handler 호출 context | HandlerExecution, idempotencyKey, SubmissionContext, validated config | scoped secret accessor | 값 포함, accessor 직렬화 금지 | 전체 queue 금지 |
| `HandlerExecutionResult` | Handler 결과 | outcome | externalReference, redacted metadata, providerCode | 민감 금지 | 가능 |
| `ProcessingAttempt` | 관리자/집계 view | attempt ID, handler ID/version, status, timestamps | error code, retryAt, externalReference | payload 없음 | 가능 |
| `FormEventPayload` | 공개 Form event | eventVersion/ID/time, Form UUID/slug/status | revision, actor, changedFields | 없음 | queue 가능 |
| `SubmissionEventPayload` | 공개 Submission event | eventVersion/ID/time, Submission/Form IDs/revision/status | summary/error codes | payload/field 값 없음 | queue 가능 |

`SubmissionContextData`와 `HandlerExecutionData` 후보는 각각 `SubmissionContext`, `HandlerExecution`으로 동결한다. `FormEventData`, `SubmissionEventData`는 역할을 명확히 하려고 `FormEventPayload`, `SubmissionEventPayload`로 동결한다.

## 내부 DTO

구현 후보이며 `internal`:

Phase 3에서 외부 Field Provider가 실제로 필요로 하는 `FieldDefinition`, `FieldValidationContext`, `FieldNormalizationResult`, `FieldValidationDescriptor`, `FieldIndexValue`, `FieldRendererDescriptor`, `FieldMetadata`만 `src/Fields/ValueObjects`의 public experimental readonly class로 승격했다. ORM Model과 Request를 노출하지 않으며 실제 PHP 이름에 `Data`/`Dto` 접미사를 쓰지 않는다. 이 승격은 동결 의미를 바꾸지 않는다.

- `FieldDefinitionValidation`
- `FieldValidationDescriptor`
- `FieldDisplayContext` / `FieldDisplayResult`
- `FieldExportContext` / `FieldExportResult`
- `FieldIndexContext` / `FieldIndexProjection`
- `HandlerConfiguration` / `ConfigurationValidation`
- `HandlerAvailability`
- `CompatibilityResult`
- processing plan/aggregate summary

외부 Provider에 꼭 필요한 타입만 Phase 3/9에서 public experimental로 승격한다.

## Mutability

- 공개 DTO: immutable.
- draft editor 내부 command/carrier: internal mutable 가능.
- Form Schema snapshot: immutable.
- raw/normalized map은 DTO 생성 후 변경할 수 없어야 한다.
- array 내부 deep immutability는 PHP만으로 자동 보장되지 않으므로 defensive copy/normalization 전략은 구현 단계에 필요하다.

## Serialization

- `toArray()` key는 snake_case API/queue snapshot 후보이며 정확한 mapping은 구현 단계에서 하나로 고정한다.
- timestamp는 UTC ISO 8601/RFC 3339.
- Backed Enum은 string value.
- UUID는 canonical lowercase string.
- callable, resource, Closure, container service, Eloquent Model, secret accessor는 serialize 금지.
- event/Job은 DTO 전체보다 최소 scalar identity/metadata를 우선한다.

## Raw와 sensitive 경계

- raw payload DTO와 redacted event/attempt DTO를 분리한다.
- `SubmissionInput`, `FieldValidationContext`, `SubmissionContext`는 민감값 가능성이 있어 loggable/queue-safe 표시를 하지 않는다.
- public event payload에는 raw/normalized values가 없다.
- `HandlerExecutionResult.metadata`는 Provider가 redaction한 JSON-safe scalar만 허용한다.
- DTO `toArray()` 존재가 logging 허용을 뜻하지 않는다.

## Versioning

- DTO wire shape는 해당 Field/Handler/Event contract major에 속한다.
- 새 optional property는 non-breaking.
- required 추가/rename/type·의미 변경은 breaking.
- internal DTO는 public compatibility 보장 없음.
- published snapshot serialization은 schemaVersion으로, Provider 교환 DTO는 contractVersion으로 판별한다.

## Canonical examples

```json
{
  "attempt_id": "01989f2e-40aa-7af0-a222-112233445566",
  "submission_id": "01989f2e-3db8-7c12-b111-112233445566",
  "binding_id": "01989f2e-37d0-72aa-a000-112233445566",
  "handler_id": "acme-connector:create-lead",
  "contract_version": "1.0",
  "mode": "async",
  "generation": 1
}
```

```json
{
  "event_version": "1.0",
  "event_id": "01989f2e-45ee-7aaa-9000-112233445566",
  "occurred_at": "2026-07-31T00:00:00Z",
  "submission_id": "01989f2e-3db8-7c12-b111-112233445566",
  "form_id": "01989f2e-2f2c-7d53-8f13-112233445566",
  "revision": 1,
  "status": "received"
}
```
