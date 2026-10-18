# Domain Event Contract

## Phase 7 implementation

`SubmissionReceived`는 transaction commit 이후 dispatch된다. submission/form UUID, form slug/revision, actor type, nullable user UUID, received_at, locale, status만 포함한다. field value, raw payload, email/phone, consent statement, IP/user-agent 및 hash는 포함하지 않으며 Handler 실행을 연결하지 않는다.

> Canonical event semantics frozen. 실제 Laravel Event class/Hook bridge는 아직 없다.

## 역할 분리

- **Laravel Domain Event**: 본체 내부의 type-safe canonical event. orchestration과 Hook bridge가 구독한다.
- **G7 Action Hook**: 외부 module/plugin의 관찰·알림·활동 로그 호환 표면. durable Handler 실행 계약이 아니다.
- Filter Hook는 Domain Event에 사용하지 않는다. 이미 commit된 사실을 외부가 변형해서는 안 된다.
- 한 canonical Event에서 Hook bridge가 정확히 한 번 Hook를 발행한다. 내부 listener와 Hook listener가 동일 부수효과를 동시에 수행하지 않도록 소유자를 하나로 정한다.

G7 `HookManager::doAction()`은 등록 callback 실행 뒤 `"hook.{name}"` Laravel string event도 dispatch하므로 별도 bridge에서 다시 같은 listener를 호출하지 않도록 한다 (`app/Extension/HookManager.php:31-62,109-135`).

## Naming

Laravel class: Past-tense PascalCase.  
G7 Hook: `glitter-form_builder.{entity}.after_{past_action}`.

예:

```text
FormPublished
glitter-form_builder.form.after_published
```

## 공개 Domain Event

| Laravel Event | G7 Hook | Payload | 알림/활동 로그 |
|---|---|---|---|
| `FormCreated` | `glitter-form_builder.form.after_created` | FormEventPayload | 활동 로그 |
| `FormUpdated` | `glitter-form_builder.form.after_updated` | FormEventPayload | 활동 로그 |
| `FormPublished` | `glitter-form_builder.form.after_published` | FormEventPayload + revision | 활동 로그, 선택 알림 |
| `FormArchived` | `glitter-form_builder.form.after_archived` | FormEventPayload | 활동 로그 |
| `FormDeleted` | `glitter-form_builder.form.after_deleted` | FormEventPayload tombstone | 활동 로그 |
| `SubmissionReceived` | `glitter-form_builder.submission.after_received` | SubmissionEventPayload | 선택 알림, 활동 로그에는 내용 제외 |
| `SubmissionProcessed` | `glitter-form_builder.submission.after_processed` | SubmissionEventPayload | 선택 알림 |
| `SubmissionProcessingFailed` | `glitter-form_builder.submission.after_processing_failed` | SubmissionEventPayload + summary codes | 운영 알림 |
| `SubmissionCancelled` | `glitter-form_builder.submission.after_cancelled` | SubmissionEventPayload | 활동 로그 |
| `SubmissionDeleted` | `glitter-form_builder.submission.after_deleted` | tombstone payload | 활동 로그 |
| `SubmissionAnonymized` | `glitter-form_builder.submission.after_anonymized` | tombstone payload | 활동 로그 |

`SubmissionReceived`는 server validation 성공과 commit을 내포하므로 `SubmissionValidated`를 별도 공개 이벤트로 만들지 않는다. validation rejection은 Aggregate 사건이 아니므로 `SubmissionRejected` 공개 이벤트도 만들지 않는다.

## 내부 orchestration Event

Hook로 공개하지 않는 `internal` 후보:

- `SubmissionProcessingRequested`
- `SubmissionProcessingStarted`
- `HandlerAttemptCreated`
- `HandlerAttemptStarted`
- `HandlerAttemptSucceeded`
- `HandlerAttemptFailed`
- `HandlerAttemptExhausted`
- `HandlerAttemptSkipped`
- `SubmissionStatusRecalculationRequested`
- `TypedIndexRebuildRequested`

Attempt 이벤트는 executor·집계·metrics용이다. 외부 Connector가 구독해 업무 작업을 수행하면 안 된다.

## Payload

### FormEventPayload

- event version
- event ID UUID
- occurredAt
- form UUID
- slug
- status
- nullable revision
- actor UUID/type
- changedFields allowlist(Updated만)

### SubmissionEventPayload

- event version
- event ID UUID
- occurredAt
- Submission UUID
- Form UUID
- FormVersion revision
- Submission status
- source/actor kind
- attempt summary counts와 redacted error codes(해당 event만)

raw/normalized payload, field values, IP, User-Agent, secret, ORM Model은 금지한다.

## Commit과 전달

- 모든 공개 이벤트는 관련 DB transaction commit 이후 발행한다.
- event payload는 scalar/array/Backed Enum value/ISO timestamp만 사용해 queue serialization 가능해야 한다.
- Eloquent `SerializesModels`에 기대어 Model을 payload로 전달하지 않는다.
- listener 실패는 이미 commit된 Aggregate를 rollback하지 않는다.
- exactly-once delivery를 보장하지 않으므로 listener는 event ID로 idempotent해야 한다.
- outbox가 필요한지와 crash window는 Phase 9 deferred다.

## Notification/activity log

- notification: `FormPublished`, `SubmissionReceived`, `SubmissionProcessingFailed` 등을 declarative definition으로 선택 구독할 수 있다.
- activity log: 관리자 Form CRUD/publish/archive, Submission retry/cancel/delete/anonymize를 기록한다.
- 익명 Submission마다 관리자 activity log를 무제한 생성하는 것은 운영/개인정보 정책으로 제한한다.
- description/context에 field 값이나 raw payload를 넣지 않는다.

## 변경 호환성

- 새 optional payload property는 same-major non-breaking.
- event 제거/rename, required property 삭제/의미 변경은 event contract major breaking.
- Hook 이름은 stable 승격 후 rename 금지.
- deprecated Hook는 최소 한 contract major 동안 canonical Event와 함께 bridge할 수 있다.
