# Submission Processing Lifecycle

## Phase 7 boundary

Phase 7은 validation을 통과한 Submission과 typed index를 원자적으로 저장하고 상태를 `received`로 유지한다. Handler Registry와 ProcessingAttempt 실행은 구현하지 않는다. 따라서 enabled binding 없음만으로 `processed`로 전환하는 일반 lifecycle 규칙은 Handler Phase가 시작될 때 적용한다.

> 상태·transaction·retry 의미는 Phase 0.5에서 frozen. timeout/backoff 수치는 deferred.

## Transaction boundary

```text
FormRequest + Field Provider server validation
  ↓
[Submission DB transaction]
  Submission(raw snapshot, FormVersion reference, idempotency hash)
  typed index projection
  enabled Binding별 initial pending Attempt
COMMIT
  ↓
SubmissionReceived event
  ↓
sync executor 또는 async queue dispatch
```

외부 Handler 호출은 transaction 안에서 금지한다. dispatch는 commit 이후여야 하며 `queue.after_commit` 설정에 암묵적으로 의존하지 않는다.

## Handler Binding

- Form Aggregate 연관 entity.
- Binding별 mode: `sync|async|disabled`.
- Binding order는 dispatch/UI ordering이며 dependency가 아니다.
- config와 secret reference 분리.
- disabled Binding은 processing plan에서 제외하며 Attempt를 만들지 않는다.

## Processing Attempt

- 한 Binding의 한 logical execution.
- 재시도마다 새 Attempt UUID/row.
- 이전 terminal Attempt는 수정하지 않는다.
- 동일 operation generation의 idempotency key는 retry 사이 안정적이다.
- 성공 external reference는 성공 Attempt에 저장한다.

## 상태 전이

### Submission

| 상태 | 의미 | 다음 |
|---|---|---|
| `received` | 유효 Submission commit 완료 | processing, processed, cancelled |
| `processing` | pending/running/retry 예정 존재 | processed, processing_failed, cancelled |
| `processed` | 모든 enabled Binding 성공 또는 enabled Binding 없음 | terminal |
| `processing_failed` | 하나 이상 exhausted이며 실행 가능 retry 없음 | processing(수동 retry), cancelled |
| `cancelled` | 신규 claim/retry 중지 | terminal |

### Attempt

| 상태 | 의미 | 다음 |
|---|---|---|
| `pending` | durable 실행 대기 | running, skipped |
| `running` | executor claim | succeeded, failed, exhausted |
| `succeeded` | 외부 처리 성공 | terminal |
| `failed` | retryable failure, retry metadata 존재 가능 | terminal; 새 pending row 생성 |
| `exhausted` | permanent 또는 retry budget 소진 | terminal |
| `skipped` | cancel/provider missing 때문에 실행 안 함 | terminal |

`retry_scheduled`는 상태가 아니라 failed Attempt의 `retryAt`과 후속 pending Attempt로 표현한다.

## Queue

- `sync` Binding: commit 후 같은 request process에서 실행.
- `async` Binding: commit 후 Job dispatch.
- effective queue driver가 `sync`면 async Job도 같은 process에서 실행할 수 있지만 Attempt 계약은 동일.
- worker 부재 시 pending 누적을 관리자 목록/health indicator에 표시한다.
- Job payload는 Attempt UUID 같은 scalar identity만 우선하며 ORM Model/raw payload/secret을 싣지 않는다. worker는 DB에서 context를 재구성한다.

G7 `DispatchHookListenerJob`이 class/method와 serialized args, request context를 전달하는 실제 사례는 있으나 Form Builder는 민감 payload 때문에 더 작은 identity-only payload를 권장한다 (`app/Jobs/DispatchHookListenerJob.php:31-58`).

## Multiple Handler 실행

- initial Attempts는 Binding order로 생성/dispatch한다.
- 완료 순서는 보장하지 않는다.
- Binding들은 독립적이며 한 실패가 다른 실행을 막지 않는다.
- MVP orchestration chain/critical/compensation 없음.
- serial-only capability가 미래에 필요하면 Handler contract major/minor capability로 추가한다.

## Retry와 exhaustion

1. running Attempt가 retryable failure면 terminal `failed`.
2. execution layer가 policy와 budget을 계산한다.
3. retry 가능하면 같은 generation/idempotency key로 새 `pending` Attempt 생성.
4. budget 소진 또는 permanent failure면 current Attempt를 `exhausted`로 종료.
5. manual retry는 권한과 audit가 필요하다.
6. “same logical operation retry”는 key 유지, “new operation replay”만 generation 증가.

timeout, max attempts, backoff, jitter는 Phase 9 deferred다.

## Disabled/missing provider

- disabled Binding: Attempt 없음, Submission completion denominator에서 제외.
- provider가 plan 생성 전에 missing: Attempt 없음 + unresolved binding diagnostic; 다른 Handler는 실행.
- 이미 pending인데 provider가 사라짐: `skipped`.
- unresolved enabled Binding은 전체 processing 결과를 `processing_failed`로 만든다. disabled Binding은 만들지 않는다.
- provider 복귀 후 manual retry 가능.

## 수동 재처리

- permission: `glitter-form_builder.submissions.retry`.
- target Submission + Binding을 명시한다.
- 기존 succeeded operation을 기본 재처리하지 않는다.
- exhausted/skipped만 same operation retry 후보.
- 새 operation generation은 중복 외부 데이터 위험 경고와 별도 idempotent command key가 필요하다.

## Idempotency derivation

- request: `(form_id, sha256(client Idempotency-Key))`.
- Handler: canonical tuple `(contract namespace v1, submission UUID, binding UUID, generation)`.
- exact separator/encoding과 test vector는 Phase 9 deferred.
- 동일 generation에서 성공은 최대 하나; DB unique 또는 execution claim으로 강제한다.

## Event timing

- `SubmissionReceived`: Submission transaction commit 후.
- `HandlerAttemptStarted`: claim commit 후 내부 event.
- success/failure/exhaustion: Attempt terminal state commit 후.
- `SubmissionProcessed|SubmissionProcessingFailed`: 집계 state commit 후.
- event dispatch 실패가 이미 commit된 Submission을 rollback하지 않는다.
- public Hook bridge는 canonical Laravel Domain Event를 받아 한 번만 발행한다.

## 공개 제출 응답

- Submission commit 성공이면 HTTP 접수 성공.
- sync Handler 실패도 500/rollback으로 바꾸지 않는다.
- 응답은 Submission UUID와 현재 `received|processing|processed|processing_failed`를 반환할 수 있다.
- request validation/rejection, unpublished/archived Form, unresolved Field Provider는 접수 전 실패다.
- processing 결과 polling/admin 조회 API shape는 후속 Phase다.
