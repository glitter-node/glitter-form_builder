# Glitter Form Builder Submission Processing Contract 초안

> 상태: Phase 7/9 입력용 `draft`. 실제 PHP interface나 queue Job 구현이 아니다.  
> 연결 결정: FB-DEC-002, 005, 007, 009.
> Binding별 mode, 독립 Handler, transaction, append-only retry, 최소 상태와 redaction 의미는 [processing-lifecycle.md](../contracts/processing-lifecycle.md) 및 [submission-handler.md](../contracts/submission-handler.md)에 의해 **frozen by Phase 0.5**다.

## 1. 목표

Submission 원본 접수와 외부 업무 처리를 분리하면서, sync/queue 환경 모두에서 같은 durable 이력·멱등성·재시도 의미를 제공한다. Form Builder는 CRM/예약/게시판/이커머스 Model, Repository, Service, DB table, 고정 URL을 직접 참조하지 않는다.

## 2. Submission lifecycle

| 현재 | 사건 | 다음 | 조건/기록 |
|---|---|---|---|
| 없음 | server validation 성공, transaction commit | `received` | raw snapshot, schema revision, typed index, initial attempts 저장 |
| `received` | 실행 시작 | `processing` | 적어도 한 attempt `running` |
| `processing` | 모든 실행 성공 또는 의도적 skip 완료 | `processed` | completion timestamp |
| `processing` | retry 소진/permanent failure | `processing_failed` | 접수 자체는 유지 |
| `received`/`processing`/`processing_failed` | 관리자 취소 | `cancelled` | 실행 중 job의 cooperative cancellation 필요 |
| `processing_failed` | 관리자 retry | `processing` | 새 attempt 또는 retry generation |

- Handler가 하나도 없는 Form은 commit 뒤 `processed`로 정리할 수 있다.
- `cancelled`는 raw 삭제가 아니며 retention은 계속 적용한다.
- public submit 응답은 commit이 성공하면 접수 성공이다. Handler 실패를 접수 500으로 변환하지 않는다.

## 3. Handler Binding

Form 또는 published FormVersion과 Handler 설정의 연결이다.

필수 후보:

- binding internal bigint PK
- binding public UUIDv7
- form ID
- handler ID (`provider:handler`)
- provider ID
- contract version (`1.0`)
- execution mode (`sync|async|disabled`)
- enabled flag
- order
- validated configuration JSON
- encrypted secret reference
- provider/config snapshot metadata
- timestamps/actor

Binding 변경은 이미 생성된 attempt의 의미를 바꾸지 않는다. publish 시 binding snapshot을 version에 묶을지 submission 생성 시 active binding을 capture할지는 Phase 9에서 확정하되, 과거 실행 재현을 위해 attempt에는 effective snapshot hash가 반드시 남아야 한다.

## 4. Processing Attempt

각 실행 단위의 durable record다.

필수 후보:

- internal bigint PK
- attempt UUIDv7
- Submission ID
- binding UUID
- handler/provider ID와 contract version snapshot
- status
- attempt number 또는 generation
- stable idempotency key
- queue connection/name의 비민감 식별자
- started/finished/next retry timestamp
- error category/code
- redacted message
- external reference의 비민감 opaque value
- created/updated timestamp

payload 전체, secret, authorization header, 민감 field 값은 저장하지 않는다.

## 5. Attempt 상태 전이

| 현재 | 사건 | 다음 |
|---|---|---|
| 없음 | Submission commit | `pending` |
| `pending` | worker/sync executor claim | `running` |
| `running` | 성공 | `succeeded` |
| `running` | retryable failure | `failed` |
| `running` | permanent failure | `exhausted` |
| `pending` | provider unavailable/cancelled | `skipped` |
| `failed` | retry 시점 도달 | 기존 row terminal 유지 + 새 `pending` Attempt |

Attempt는 실행 중에만 mutable하고 terminal 이후 immutable하다. retry는 새 Attempt row를 추가한다. exact table 분리는 Phase 9에서 확정하지만 기존 실패 시각과 error code를 덮어쓰면 안 된다.

## 6. Execution mode

### `sync`

- Submission transaction commit 이후 같은 HTTP/console process에서 execution layer를 호출한다.
- durable attempt를 먼저 claim하고 상태를 기록한다.
- 지연이 submit latency에 영향을 주므로 network Handler의 기본값으로 권장하지 않는다.
- 실패해도 Submission 접수 응답은 성공으로 유지하며 processing state를 반환한다.

### `async`

- commit 이후 queue Job을 dispatch한다.
- queue driver가 실제 `sync`면 Laravel은 같은 process에서 실행할 수 있으나 contract와 attempt는 동일하다.
- async driver인데 worker가 없으면 attempt는 `pending`으로 남고 관리자 health warning을 제공해야 한다.

### `disabled`

- 신규 실행하지 않는다.
- publish validation에서 의도적 disabled임을 허용하되 운영자에게 명확히 표시한다.
- Submission별 `skipped` attempt 생성 여부는 감사 요구에 따라 Phase 9에서 확정한다.

### `auto`

MVP 미지원이다. 환경 설정 변경이 binding 의미를 바꾸기 때문이다.

## 7. Transaction boundary

```text
[DB transaction]
  validate against immutable FormVersion
  create Submission(raw + normalized metadata)
  create typed indexes
  create initial pending attempts
COMMIT
  dispatch/execute attempts
```

- 외부 Handler network call은 transaction 안에서 금지한다.
- queue config `after_commit=false`에 기대지 말고 Form Builder가 commit 이후 dispatch를 명시적으로 보장해야 한다.
- attempt 생성 실패면 Submission transaction 자체가 rollback될 수 있지만, 이미 commit된 Submission은 Handler failure로 삭제하지 않는다.

## 8. Idempotency

두 층을 분리한다.

1. Submission request idempotency:
   - 선택적 `Idempotency-Key`.
   - Form scope SHA-256 hash unique.
   - 동일 key와 동일 canonical payload면 기존 Submission response를 반환.
   - 동일 key와 다른 payload면 conflict.

2. Handler execution idempotency:
   - Form Builder가 binding UUID + Submission UUID + logical execution generation으로 stable key를 만든다.
   - retry는 같은 logical operation key를 전달한다.
   - manual “새 작업으로 재실행”만 새 generation/key를 만들 수 있으며 별도 권한과 확인이 필요하다.
   - Handler/Connector contract는 외부 시스템 중복 생성을 방지하도록 이 key를 사용해야 한다.

## 9. Failure taxonomy

- `RetryableHandlerException`: timeout, temporary network failure, rate limit, 외부 5xx 등 재시도 가능.
- `PermanentHandlerException`: invalid mapping/config, 외부 validation 4xx, unsupported contract 등 자동 재시도 금지.
- 알 수 없는 exception: 안전한 redaction 후 retryable 여부를 execution policy가 보수적으로 결정한다. 기본값은 제한된 retry 후 exhausted 후보.
- provider unavailable: code 실행 없이 skip/blocked reason.

정확한 timeout, max attempts, exponential backoff, jitter, per-handler override 범위는 `deferred`다.

## 10. Critical/non-critical

- MVP의 모든 Handler는 Submission 접수 관점에서 non-blocking이다.
- `critical=true`가 public HTTP rollback/500을 뜻하게 하지 않는다.
- Handler 간 dependency, stop-on-failure, saga compensation은 자동화/Workflow 범위로 Phase 12 이후 검토한다.
- 관리 화면에는 전체 성공/실패를 숨기지 않고 Submission `processing_failed`로 표시한다.

## 11. Provider disable/remove

- disable: 신규 execution claim 중단, 신규 Submission에는 skip reason 또는 disabled binding 표시.
- remove: binding row와 attempt row를 삭제하지 않고 handler/provider/version snapshot을 opaque 값으로 보존.
- re-enable: 자동 무제한 replay 금지. pending/retry 가능한 건 execution policy, exhausted/skipped는 권한 있는 manual retry로 처리.
- pending job이 provider를 resolve하지 못하면 raw payload를 로깅하지 않고 deterministic provider-unavailable 결과를 기록한다.

## 12. Configuration과 secret

- Handler는 configuration schema, defaults, redacted paths, secret field 목록을 선언한다.
- 일반 config는 validated JSON snapshot으로 저장할 수 있다.
- secret은 별도 encrypted column 또는 G7 secret storage reference에 저장한다. 실제 G7 범용 secret vault는 확인되지 않았으므로 정확한 저장 service는 Phase 9 전 추가 검증한다.
- API Resource는 secret 존재 여부/마지막 변경 시각만 반환하고 평문/암호문을 반환하지 않는다.
- FormVersion/attempt snapshot에는 secret을 넣지 않는다.

## 13. Observability와 redaction

기록 가능:

- Form/Submission/binding/attempt UUID
- handler/provider ID와 version
- status, duration, retry count
- stable non-secret error code
- 외부 opaque reference

기록 금지:

- raw/normalized payload 전체
- sensitive field 값
- secret, token, auth header
- uploaded file content
- 외부 응답 body 전체

activity log에는 관리자 binding 변경, retry/cancel/purge 행위만 기록하고 Submission 내용은 넣지 않는다. 일반 application log는 UUID와 error code로 processing record를 찾게 한다.

## 14. Proposed PHP interface pseudo-code

```php
interface SubmissionHandlerProvider
{
    public function descriptor(): HandlerDescriptor;

    public function configurationSchema(): ConfigurationSchema;

    /**
     * @throws RetryableHandlerException
     * @throws PermanentHandlerException
     */
    public function handle(SubmissionContext $context): HandlerResult;
}

interface SubmissionHandlerRegistry
{
    public function register(SubmissionHandlerProvider $provider): void;
    public function resolve(string $handlerId, string $contractVersion): SubmissionHandlerProvider;
}
```

이는 class/file명을 확정하지 않는 pseudo-code다. duplicate `register()`는 fail-fast, `resolve()`는 major compatibility를 검사한다.

## 15. Proposed DTO

```text
SubmissionContext
  submissionId: UUID
  formId: UUID
  formRevision: int
  schemaVersion: string
  submittedAt: immutable timestamp
  actor: guest | authenticated user UUID | admin UUID
  values: read-only normalized field map
  rawSnapshotAccessor: restricted/read-only
  fieldMetadata: sensitivity/export/provider snapshot
  bindingConfiguration: validated non-secret config
  secretAccessor: scoped, non-serializable
  idempotencyKey: stable string
  attemptId: UUID

HandlerResult
  outcome: succeeded
  externalReference: nullable opaque string
  publicMetadata: redacted scalar map
```

Connector는 Eloquent Model이나 Request 객체를 받지 않는다. DTO serialization 시 secret accessor와 raw unrestricted payload가 queue payload/log에 노출되지 않도록 설계해야 한다.

## 16. API 의미

- submit 성공: Submission UUID와 `received|processing|processed|processing_failed` 중 현재 상태 반환.
- idempotent replay: 원래 Submission UUID와 상태 반환.
- Handler failure: 접수된 Submission에 대해 5xx로 위장하지 않는다.
- 관리자 retry: permission `glitter-form_builder.submissions.retry`, idempotent command key, target attempt/binding 명시.
- cancel: 실행 중 외부 작업을 강제 rollback한다고 약속하지 않고 신규 claim/retry를 중단한다.

## 17. 미확정 항목

- timeout seconds
- max attempts
- backoff/jitter
- queue connection/name allowlist
- one row state mutation vs append-only generation table
- disabled binding의 매 Submission `skipped` row 생성 여부
- secret encryption service
- external reference 최대 길이
- processing health threshold와 alert 채널

이 값을 Handler가 임의 Job 생성으로 우회해서는 안 되며 Phase 9 decision addendum에서 확정한다.

## 18. Phase 0.5 frozen 정정

- retry는 기존 Attempt row 재사용이 아니라 새 Attempt row 생성.
- `retry_scheduled` 상태는 제거하고 terminal `failed` + `retryAt` + 새 `pending`으로 표현.
- terminal은 `succeeded|failed|exhausted|skipped`; terminal row를 성공으로 덮어쓰지 않음.
- mode는 Binding별이며 여러 Handler는 독립 실행.
- MVP critical Handler/dependency 없음.
- disabled Binding은 Attempt를 만들지 않음.
- sync Handler 실패도 Submission 접수를 rollback/500으로 바꾸지 않음.
- Job payload는 Attempt UUID 같은 최소 identity 우선.

권위: [Submission Handler](../contracts/submission-handler.md), [Processing Lifecycle](../contracts/processing-lifecycle.md), [Events](../contracts/events.md), [Registry](../contracts/registry.md), [DTO](../contracts/dto.md).
