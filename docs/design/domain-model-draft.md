# Glitter Form Builder 도메인 모델 초안

> 구현용 확정 schema가 아니다. 이름은 ubiquitous language 후보이며 migration 전에 실제 DB schema와 MariaDB 호환성을 다시 조사한다.  
> Aggregate 경계, lifecycle, 최소 상태와 Attempt retry 의미는 [domain-model.md](../contracts/domain-model.md) 및 [processing-lifecycle.md](../contracts/processing-lifecycle.md)에 의해 **frozen by Phase 0.5**다. 실제 table/class/FK는 draft다.

## 1. Aggregate 후보

### Form aggregate

`Form`이 lifecycle root다. stable public identity, operator-facing localized name, status, current draft/published version reference, access/retention policy reference를 소유한다.

`FormVersion`은 publish 시 immutable snapshot이다. schema contract version, ordered `FieldDefinition` 목록, localized labels/options, validation/privacy/presentation hint, publish metadata를 자체 완결적으로 보존한다. 기존 version을 수정하지 않고 새 version을 만든다. `sirsoft-page`의 Page/PageVersion 분리는 참고할 수 있다 (`modules/_bundled/sirsoft-page/database/migrations/2026_04_01_000001_create_pages_table.php:15-35`, `...000002_create_page_versions_table.php:15-31`).

`FieldDefinition`은 우선 FormVersion schema 내부 value object 후보다. stable field key는 version 내 unique이고, type/provider contract version, required/constraints, normalization, localized label/help/options, privacy classification, index/export policy를 가진다. designer 요구가 field row query를 실제 필요로 할 때 normalized draft table을 검토한다.

### Submission aggregate

`Submission`은 하나의 published FormVersion에 대한 accepted input root다. submission source (`anonymous`, `authenticated`, `admin`, 후보), actor reference, idempotency key/hash, raw/normalized payload snapshots, consent/evidence reference, validation acceptance time, processing summary, retention timestamps를 가진다.

`SubmissionValue` 또는 `SubmissionFieldIndex`는 정본이 아닌 projection 후보다. searchable/sortable/exportable 허용 field만 typed value와 redaction metadata로 저장한다. EAV 전체 정본으로 사용하지 않는다.

`SubmissionProcessingAttempt`는 binding별 실행 이력이다. handler key/version, attempt number, status, execution mode, idempotency key, claimed/started/finished timestamps, retry schedule, sanitized error, result reference를 보존한다. submission 전체 status는 attempt들의 derived summary로 본다.

### Integration configuration

`IntegrationBinding`은 Form 또는 FormVersion과 handler를 연결한다. handler key, contract version constraint, enabled/order, execution mode, encrypted/validated config, failure policy를 소유한다. CRM model/table FK를 저장하지 않는다.

`FileReference`는 후보 모델이다. Form Builder가 core Attachment를 직접 morph relation으로 쓸지 module-owned reference를 둘지 미정이다. 적어도 submission, field key, attachment opaque ID/hash, lifecycle state, retention/delete 상태를 연결해야 한다. binary와 media library 메타의 소유자는 Storage/Attachment 계층이다.

## 2. 관계 초안

```text
Form 1 ── * FormVersion (published immutable)
  │                │
  │                └── * FieldDefinition value objects
  │
  ├── * IntegrationBinding
  └── * Submission ── 1 accepted FormVersion
             ├── * SubmissionFieldIndex (projection)
             ├── * SubmissionProcessingAttempt (per binding)
             └── * FileReference (candidate)
```

Binding snapshot을 submission 시점에 복사할지 live binding을 참조할지는 중요하다. 재처리 재현성을 위해 handler key/config revision/failure policy snapshot은 attempt에 남기는 방향을 권고한다. secret config 원문 복사는 금지하고 secret reference/version만 snapshot한다.

## 3. 식별자 후보

| 객체 | 내부 PK 후보 | 외부 ID 후보 | 판단 |
|---|---|---|---|
| Form | bigint | UUID v7 또는 opaque UUID | hybrid 권장 후보 |
| FormVersion | bigint | 필요 시 UUID | 내부 version number + form FK가 주 용도 |
| Submission | bigint | UUID v7/opaque token | 외부 status/reference에 UUID 권장 |
| Binding | bigint | UUID | admin/API와 attempt correlation |
| Attempt | bigint | UUID/job id 후보 | 내부 순차 조회 + correlation |
| FieldDefinition | JSON stable key | field key | normalized row가 아니면 PK 없음 |

G7 관례는 혼재한다. Page는 bigint only, Hello Memo는 bigint + UUID (`foundation-analysis.md` 근거). 따라서 “모든 PK UUID”를 확정하지 않는다. public exposure와 join/query 성능을 분리하기 위해 bigint + UUID 병용을 우선 검토한다.

## 4. 상태 Enum (Phase 0.5 frozen)

Backed Enum을 사용해야 한다 (`docs/backend/enum.md:54` 고정 상태 규칙). migration native enum column을 뜻하지 않는다.

- `FormStatus`: `draft`, `published`, `archived`
- FormVersion은 immutable published snapshot이므로 별도 lifecycle status를 두지 않는다.
- `SubmissionStatus`: `received`, `processing`, `processed`, `processing_failed`, `cancelled`
- `ProcessingAttemptStatus`: `pending`, `running`, `succeeded`, `failed`, `exhausted`, `skipped`
- `BindingExecutionMode`: `sync`, `async`, `disabled`
- Handler failure dependency/critical policy는 MVP에 두지 않는다.
- `PrivacyClassification`: `public`, `personal`, `sensitive`, `secret`
- `FileReferenceStatus`: `temporary`, `attached`, `quarantined`, `deleted`

`validated`를 Submission status로 둘 필요는 낮다. accepted submission은 server validation 통과를 invariant로 삼는다. downstream “business accepted/rejected”는 handler result이지 submission validity가 아니다.

## 5. 불변 조건

1. accepted Submission은 존재하며 publish된 FormVersion을 정확히 참조한다.
2. published FormVersion의 schema/presentation/labels/bindings snapshot은 mutation하지 않는다.
3. field key는 FormVersion 내 unique이며 reserved key와 충돌하지 않는다.
4. payload key/type는 referenced FormVersion과 server Field Registry contract로 검증된다.
5. client validation 성공만으로 Submission을 만들지 않는다.
6. 동일 idempotency scope/key는 같은 semantic submission을 한 번만 생성한다. 동일 key + 다른 payload hash는 conflict다.
7. raw payload commit과 최초 processing intent/attempt 생성은 가능한 한 같은 DB transaction이다.
8. handler 성공은 durable attempt result가 기록된 후에만 반영된다.
9. retry는 같은 handler idempotency identity를 유지한다.
10. index projection은 raw/normalized snapshot의 정본이 아니며 rebuild 가능하다.
11. activity/notification log에는 sensitive answer 원문을 넣지 않는다.
12. external domain reference는 opaque result이며 본체가 외부 FK를 강제하지 않는다.

## 6. Form 게시·수정·보관 흐름

1. `Form` 생성: draft만 존재; structured designer가 schema draft를 편집한다.
2. publish validation: provider availability, schema contract, field key uniqueness, localized fallback, server rules, renderer support, access/privacy/file capability, binding config를 검사한다.
3. publish: immutable FormVersion을 생성하고 Form의 current published pointer를 원자적으로 변경한다. cache/route layout projection을 invalidate한다.
4. 수정: published snapshot을 clone해 새 draft/revision을 만든다.
5. republish: 새 version을 publish하며 이전 version은 `superseded` 후보. 과거 Submission은 이전 version을 계속 참조한다.
6. archive: 새 submission만 차단하고 definition/version/submission은 retention 정책에 따라 보존한다.
7. withdraw version: 잘못된 특정 published version의 신규 입력만 차단하는 별도 operation 후보.

## 7. 제출·처리 흐름

1. route가 access mode와 throttle/permission/IDV를 확인한다.
2. FormRequest가 route form/version을 service/repository query port로 resolve하고 dynamic rules를 구성한다. schema 없음/field provider 없음은 fail closed.
3. normalizer가 field contract에 따라 canonical value를 만든다.
4. idempotency key와 canonical payload hash를 비교한다.
5. transaction에서 Submission raw/normalized snapshot, allowed indexes, binding execution records를 생성한다.
6. commit 후:
   - sync binding은 명시된 budget 내 실행하고 attempt 기록
   - async binding은 durable pending attempt를 Job에 dispatch
   - lifecycle hook를 발행
7. handler는 idempotent external call을 수행하고 opaque target reference/result summary를 반환한다.
8. retryable failure는 backoff와 next retry를 기록한다. non-retryable failure는 terminal failed.
9. 여러 binding 중 일부 성공이면 `partially_failed`; failure policy가 stop이면 후속은 `skipped/cancelled`.
10. admin retry는 새 attempt row를 만들고 이전 history를 변경하지 않는다.

HTTP 응답이 submission accepted를 의미하는지 모든 sync handler 성공까지 의미하는지는 access/SLA 결정이 필요하다. 권고는 submission commit 성공 시 accepted를 반환하고 downstream status를 별도 조회하는 것이다.

## 8. 중복, 멱등성, 재시도

- scope 후보: `(form_id or version_id, access principal/anonymous bucket, client idempotency key)`.
- server-generated fingerprint만으로 사람의 동일 답변을 중복으로 판단하지 않는다.
- idempotency key가 없는 anonymous submit은 short-window anti-replay fingerprint를 별도 abuse signal로만 사용할 수 있다.
- Connector에는 core submission UUID + binding UUID를 전달하고 대상이 idempotency를 지원하면 그대로 전달한다.
- queue delivery는 at-least-once로 가정한다. `running` timeout reclaim과 maximum attempts는 Phase 9 failure test 후 확정한다.

## 9. 개인정보 삭제와 원본 보존 충돌

권장 정책은 logical record/history 보존과 answer content 보존을 분리하는 것이다.

- **Redaction**: 개인정보 field value를 raw/normalized payload에서 irreversible placeholder로 교체하고 index/file을 삭제한다. submission UUID, form version, timestamps, processing status, non-sensitive audit metadata는 유지할 수 있다.
- **Deletion**: 법적·운영 요구가 없으면 aggregate와 attempts를 삭제하거나 tombstone만 유지한다.
- **Legal hold**: 있으면 automatic purge를 중단하되 접근을 제한하고 이유/기간을 기록한다.
- **Crypto erasure 후보**: per-submission encryption key 폐기로 원문을 복구 불가하게 하는 방식은 key management 기반 확인 전 확정하지 않는다.
- **Downstream propagation**: Connector result reference를 사용해 erase request를 handler에 요청할 수 있으나 외부 삭제 성공은 별도 attempt/history로 기록한다. 본체 DB 삭제가 CRM 데이터 삭제를 암묵적으로 보장하지 않는다.
- notification/activity logs에 원문을 복제하지 않아 삭제 범위를 줄인다.

삭제 후에도 schema snapshot은 개인 데이터가 아닌 한 유지한다. field label 자체에 운영자가 개인정보를 입력할 가능성은 별도 sanitization/retention consideration이다.

## 10. 도메인 데이터 연결 방식

본체는 `Submission`의 source identity와 `ProcessingAttempt`의 result만 소유한다. 외부 result 후보:

```text
handler_key
target_system (opaque)
target_type (opaque)
target_reference (string)
result_metadata (allowlisted, non-secret)
```

다른 module table FK, model morph class FQCN, internal Service class name을 공개 저장 contract로 쓰지 않는다. Connector가 자체 mapping table을 소유할 수 있다. webhook은 endpoint secret/config를 Binding에 보관하되 encrypted settings 표면 확인이 필요하다.

## 11. migration 전에 확인할 DB 항목

DB Safety Rule에 따라 실제 연결 schema에서 다음을 먼저 조회한다.

- DB engine/version, default charset/collation, table prefix policy
- users PK/UUID, FK signedness, delete behavior
- modules/permissions/attachments/notifications/activity logs 실제 columns/indexes
- JSON type 지원·index/generated column·MariaDB 차이
- text/blob limit, row size, packet size, encryption/cast compatibility
- timestamp precision/timezone/defaults, soft delete convention
- UUID storage form(`char(36)`, native alias, binary)과 UUID v7 generation service
- unique key length/collation case sensitivity
- FK naming/table prefix, migration rollback ordering
- fulltext engine/locale limits
- jobs/failed_jobs schema와 queue driver actual config
- attachment morph map and temporary attachment behavior

metadata query 결과는 stdClass/array, upper/lowercase key를 normalize하고 required key는 `array_key_exists()`로 검증해야 한다 (`AGENTS.md` Database Metadata Compatibility).

## 12. Phase 0-B 도메인 결정 반영

이 문서는 migration 확정본이 아니다. 아래 논리 계약은 `decision-log.md`가 확정하며 충돌하는 앞 절의 후보를 **superseded**한다.

### Aggregate와 식별자

- `Form`: bigint PK, UUIDv7 public ID, publish 후 immutable slug.
- `FormVersion`: bigint PK 후보, `(form_id, revision)` unique, immutable schema/provider/access/retention snapshot.
- `FieldDefinition`: normalized row는 Designer query 필요가 입증될 때만 사용한다. published schema snapshot이 권위다.
- `Submission`: bigint PK, UUIDv7, 한 FormVersion 고정 참조, raw snapshot/actor/idempotency 소유.
- `SubmissionValue`: 전 값 EAV가 아니라 `indexable=true` 값만 typed index.
- `SubmissionProcessingAttempt`: bigint PK + UUIDv7 correlation, redacted durable history.
- `IntegrationBinding`: bigint PK + UUIDv7, versioned handler/config/secret reference/mode.
- `FileReference`: Phase 11까지 model/schema type 모두 deferred.

연결 결정: FB-DEC-001/002/006/007.

### 상태 확정안

- Form: `draft`, `published`, `archived`.
- Submission: `received`, `processing`, `processed`, `processing_failed`, `cancelled`.
- ProcessingAttempt: `pending`, `running`, `succeeded`, `failed`, `exhausted`, `skipped`.

이전 draft의 `accepted`, `partially_failed`, `failed`, `redacted`, `deleted`, `claimed`, `retry_scheduled`, `cancelled` Attempt 후보는 **superseded**한다. 부분 실패는 attempt 집계로 표시하며 Submission summary는 `processing_failed`다.

### 추가 불변 조건

1. published FormVersion은 update/delete하지 않고 새 revision으로만 변경한다.
2. Submission은 정확히 한 FormVersion을 참조한다.
3. sensitive field는 typed index를 만들 수 없다.
4. Handler network call은 Submission transaction 밖이다.
5. provider 부재는 raw Submission을 삭제/재정규화하지 않는다.
6. publish 시 모든 provider/renderer/server validator가 resolve돼야 한다.
7. slug와 field key는 publish 이후 해당 의미에서 immutable이다.
8. raw와 typed index purge는 같은 개인정보 작업 단위다.
9. Connector 외부 업무 데이터 FK를 본체 DB에 만들지 않는다.

보존은 Form별 `manual|forever|days`, default `manual`이다. `days` 자동 purge와 `anonymize_after_days`는 Phase 11이다(FB-DEC-005).

### migration 직전 추가 확인

- MySQL 8.0/MariaDB 10.3에서 JSON 물리 type의 실제 metadata/validation.
- unsigned bigint FK와 restrict/cascade/nullOnDelete 결과.
- UUID `string(36)` index, typed decimal precision, string/date/datetime index 길이.
- nullable idempotency hash unique scope.
- payload application limit와 `max_allowed_packet`.
- append-only attempt generation의 table 분리 여부.

## 13. Phase 0.5 frozen 정정

- 별도 `DraftVersion`은 MVP에 두지 않고 Form의 mutable working copy로 동결했다.
- current published FormVersion은 최대 하나이며 새 publish는 새 revision을 만든다.
- archived Form은 신규 제출 불가, 복구는 `archived → draft → published`.
- Submission 존재 시 Form/FormVersion hard delete 금지.
- typed index는 derived이며 재생성 가능.
- Attempt는 terminal 이후 immutable, retry는 새 row.
- 최소 Attempt 상태는 `pending|running|succeeded|failed|exhausted|skipped`; `retry_scheduled` 후보는 **superseded**.
- disabled Binding은 Attempt를 만들지 않으며 provider가 pending 뒤 사라질 때만 `skipped`.

권위 문서: [domain-model.md](../contracts/domain-model.md), [processing-lifecycle.md](../contracts/processing-lifecycle.md). append-only의 실제 table 분리는 deferred다.
