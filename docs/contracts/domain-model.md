# Domain Model Contract

> 상태: Phase 0.5 architecture `frozen`; 실제 PHP/DB API는 `experimental` 이전 단계다.

## Aggregate diagram

```text
Form Aggregate
Form (root)
├── mutable draft schema working copy
├── 0..* FormVersion (immutable published snapshots)
└── 0..* IntegrationBinding (Handler configuration; no external object)

Submission Aggregate
Submission (root)
├── 0..* SubmissionValueIndex (derived typed projection)
└── 0..* ProcessingAttempt (append-only logical executions)

References
Submission -- immutable reference --> FormVersion
ProcessingAttempt -- snapshot reference --> IntegrationBinding identity
ProcessingAttempt -- opaque result --> external domain identifier
```

## Form Aggregate

### Root: Form

소유:

- internal bigint identity와 public UUIDv7
- 첫 publish 후 immutable slug
- `draft|published|archived` 상태
- mutable draft schema working copy
- 단 하나의 current published FormVersion reference
- Form별 access/retention working policy

Form은 외부 CRM/Reservation/Board/Ecommerce 객체를 소유하지 않는다.

### FormVersion

- Form 안에서 1부터 단조 증가하는 `revision`.
- publish 시 생성되는 self-contained immutable snapshot.
- schema, translations, provider metadata, access/retention policy를 고정한다.
- draft 저장으로 revision을 소비하지 않는다.
- 한 Form에 current published version은 최대 하나지만 과거 version은 모두 유지한다.

### Draft 결정

MVP draft는 별도 `DraftVersion` entity가 아니라 Form Aggregate의 mutable working copy다. publish가 성공할 때만 새 FormVersion을 생성한다. draft audit/history, 동시 편집 branch는 후속 기능이다.

### IntegrationBinding

- Form과 Handler Provider 설정을 연결하는 Form Aggregate 연관 entity다.
- Binding별 `sync|async|disabled` mode를 가진다.
- 일반 config와 secret reference를 분리한다.
- 외부 도메인 객체나 외부 DB FK를 소유하지 않는다.
- 과거 Attempt가 참조한 Binding identity/snapshot은 Binding 제거 후에도 Attempt에 남는다.

## Form lifecycle

| 현재 | 명령 | 다음 | 규칙 |
|---|---|---|---|
| 없음 | create | `draft` | 공개/제출 불가 |
| `draft` | publish | `published` | schema validation 후 revision 1 |
| `published` | save draft | `published` | current version 유지, working copy만 변경 |
| `published` | publish draft | `published` | 새 immutable revision, current pointer 교체 |
| `published` | archive | `archived` | schema GET/신규 제출 차단 |
| `draft` | archive | `archived` | 미게시 보관 |
| `archived` | restore for editing | `draft` | 직접 publish 금지; 검토 후 publish |
| `draft` | publish | `published` | 기존 revision 다음 정수 |

`archived → published` 직접 전이는 없다.

### 삭제

- Submission이 하나라도 있으면 Form/FormVersion hard delete 금지.
- 한 번이라도 publish된 Form은 기본 삭제가 아니라 archive한다.
- never-published draft이며 Submission/Attempt/Binding이 없을 때만 hard delete 허용.
- retention purge가 Submission을 제거했더라도 published FormVersion의 hard purge는 별도 관리자 파기 절차로 deferred다.
- FormVersion은 참조 Submission이 있으면 절대 삭제할 수 없다.

## Submission Aggregate

### Root: Submission

소유:

- internal bigint와 public UUIDv7
- 제출 당시 Form UUID, FormVersion revision/reference
- raw JSON snapshot(원본 권위)
- actor/source와 접수 timestamp
- request idempotency hash
- processing aggregate status
- retention/anonymization timestamps

Submission은 validation을 통과해 commit된 입력이다. 거부된 request는 Submission entity가 아니다.

### SubmissionValueIndex

- `indexable=true` field의 검색용 typed projection.
- 원본 권위가 아니며 immutable FormVersion + raw payload + resolvable provider로 재생성 가능하다.
- sensitive field는 projection 금지.
- 재생성은 audit 가능한 maintenance operation이며 raw를 변경하지 않는다.

### ProcessingAttempt

- 한 Handler Binding의 한 번의 logical execution.
- 재시도마다 새 row/identity를 추가한다.
- row는 실행 중 상태만 전이하며 terminal 이후 immutable이다.
- 이전 실패 row를 성공으로 변경하지 않는다.
- Handler result의 외부 식별자는 해당 성공 Attempt의 redacted result metadata에 저장한다.

## Submission lifecycle

| 현재 | 사건 | 다음 |
|---|---|---|
| 없음 | validation + DB commit | `received` |
| `received` | 하나 이상 attempt 시작/대기 | `processing` |
| `received` | enabled binding 없음 | `processed` |
| `processing` | 모든 enabled binding 성공 | `processed` |
| `processing` | retry 가능한 pending/failed 존재 | `processing` |
| `processing` | 하나 이상 exhausted, 실행 가능 작업 없음 | `processing_failed` |
| `received|processing|processing_failed` | cancel | `cancelled` |

`cancelled`는 raw 삭제가 아니다. 이미 외부 시스템에 성공한 결과를 되돌린다고 약속하지 않는다.

## Attempt lifecycle

최소 상태는 `pending`, `running`, `succeeded`, `failed`, `exhausted`, `skipped`다. 이전 draft의 `retry_scheduled`는 상태에서 제거하고 `failed` attempt의 `retry_at` metadata와 다음 `pending` row로 표현한다.

| 현재 | 사건 | 다음 |
|---|---|---|
| 없음 | enabled binding execution 생성 | `pending` |
| `pending` | executor claim | `running` |
| `running` | success | `succeeded` |
| `running` | retryable failure, budget 남음 | `failed` |
| `running` | permanent failure/예산 소진 | `exhausted` |
| `pending` | submission cancelled/provider unavailable | `skipped` |
| `failed` | retry due | terminal 유지 + 새 `pending` attempt |

`succeeded|failed|exhausted|skipped`는 terminal이다.

## 집계 규칙

- 여러 Handler는 독립 실행한다. 한 실패가 다른 Handler를 막지 않는다.
- MVP에는 critical Handler나 순차 dependency가 없다.
- Binding order는 관리자 표시와 deterministic dispatch 순서일 뿐 완료 dependency가 아니다.
- executor는 기본적으로 독립 Job으로 dispatch 가능하며 병렬 완료를 허용한다.
- logical operation unique key는 `(submission UUID, binding UUID, operation generation)`이다.
- 같은 generation에 성공 attempt는 하나만 허용하고 Handler idempotency key도 동일하다.

## Aggregate 간 규칙

- Submission은 mutable Form이 아니라 immutable FormVersion을 참조한다.
- Form archive 후 과거 Submission 조회/export는 유지한다.
- Provider 제거 후 raw JSON과 provider metadata snapshot으로 fallback 표시한다.
- provider가 복귀하고 같은 compatible major를 제공하면 typed index 재생성/표시를 다시 할 수 있다. raw snapshot은 바꾸지 않는다.
- Form retention policy는 Submission과 typed index 파기 시점을 결정하지만 activity log와 외부 업무 데이터에는 cascade하지 않는다.
- IntegrationBinding 삭제/disable 후 과거 Attempt는 handler/provider snapshot으로 표시한다.
- 외부 업무 데이터의 수정·삭제는 Connector/target domain 책임이다.

## 외부 경계

Form Builder DB에는 외부 Lead/Reservation/Post/Order FK를 만들지 않는다. 성공 Attempt의 `externalReference`는 opaque string일 뿐이며 존재성·삭제 lifecycle을 Form Builder가 보장하지 않는다.

