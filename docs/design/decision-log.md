# Glitter Form Builder Phase 0-B 결정 기록

## FB-DEC-Phase7 — Submission receipt boundary (2026-08-01)

- bigint internal identity + UUIDv7 public route key.
- 신규 상태는 `received`; Handler가 없으므로 자동 processed 전환 없음.
- Provider-serialized canonical field map만 저장하고 client body 전체는 저장하지 않음.
- recursive key-sorted canonical JSON SHA-256을 payload/schema hash로 사용.
- idempotency unique는 version + actor scope + key; key 없는 요청은 영속 idempotency 없음.
- guest 10/min/form/IP-HMAC, authenticated 30/min/form/user.
- IP/user-agent 원문 대신 application-key HMAC 저장.
- sensitive typed index 금지와 관리자 redaction 적용.
- retention due date만 계산하고 purge/Handler/ProcessingAttempt/webhook/notification은 후속 Phase.

> 기준일: 2026-07-31  
> 대상: `glitter-form_builder` / 표시명 `Glitter Form Builder`  
> 메타데이터: Vendor·Author `Glitter.kr`, License `MIT`, 초기 버전 후보 `0.1.0`  
> 상태 의미: `accepted`는 Phase 1~3 계약 기준, `deferred`는 스키마 예약 또는 후속 조사, `rejected`는 MVP에서 사용하지 않는 대안이다.

## FB-DEC-001 — DB compatibility

- 상태: `accepted`
- 결정:
  - 지원 하한은 G7 공식 범위인 MySQL 8.0 이상 또는 MariaDB 10.3 이상이다.
  - 실제 기준 환경 MariaDB 10.11.11도 반드시 포함한다.
  - Form 정의는 immutable JSON snapshot, Submission은 raw JSON snapshot과 검색용 typed index를 분리하는 hybrid를 사용한다.
  - JSON path index, expression index, generated column, multi-valued index는 MVP 필수 조건으로 사용하지 않는다.
  - JSON 문서는 애플리케이션에서 검증하며 migration의 구체 타입(`json` 또는 `longText` + `JSON_VALID`)은 Phase 2 직전 양 DB DDL 검증 후 확정한다. 어느 쪽이든 JSON 내부 경로를 DB 계약으로 삼지 않는다.
  - raw JSON, 민감값, 다국어 JSON에는 FULLTEXT를 만들지 않는다. 검색은 typed index 및 명시적 메타데이터 컬럼으로 제한한다.
  - 상태는 DB native enum이 아닌 `varchar`와 PHP Backed Enum으로 관리한다.
  - FK는 내부 bigint에만 연결한다. 사용자 참조는 `nullOnDelete`, Submission 하위 index/attempt는 명시적 Submission hard purge 때만 cascade 후보이며, Form/FormVersion에서 Submission으로 이어지는 묵시적 cascade 삭제는 금지한다.
  - Form과 Submission은 soft delete와 정책 기반 purge를 분리한다.
- 근거:
  - G7 공식 최소 범위는 MySQL 8.0+ 또는 MariaDB 10.3+다.
  - 현재 서버는 MariaDB 10.11.11이며 `DB_CONNECTION=mysql`을 사용한다.
  - MySQL의 JSON은 native binary 형식이고 MariaDB의 JSON은 `LONGTEXT` alias이므로 저장·비교·인덱스 동작을 동일하다고 볼 수 없다.
  - InnoDB index key는 바이트 제한을 받으므로 `utf8mb4`의 임의 길이 값 전체를 복합 index로 만들면 안 된다.
- 검토한 대안:
  - EAV 전용: 타입·버전별 해석과 원본 재현 비용이 커서 `rejected`.
  - 단일 JSON 전용: 검색·삭제·내보내기 성능과 민감값 분리가 약해 `rejected`.
  - MySQL JSON path/generated index 필수: MariaDB 호환성과 migration 복잡도 때문에 `rejected`.
  - typed index를 포함한 hybrid: `accepted`.
- 영향:
  - typed index에는 `field_key`, value type, 타입별 nullable value 컬럼과 검색 허용 여부가 필요하다.
  - raw payload 최대 크기는 HTTP/PHP/DB packet 한계보다 훨씬 낮은 모듈 설정으로 제한해야 하나 정확한 바이트 값은 Phase 3 부하 검증까지 `deferred`.
  - schema JSON의 canonical encoding과 hash가 필요하다.
- 후속 작업:
  - Phase 2에서 MySQL 8.0 및 MariaDB 10.3 호환 DDL을 테스트한다.
  - FK 이름, index 길이, collation, engine, JSON CHECK 생성 결과를 실제 schema metadata로 검증한다.
- 실제 근거:
  - `README.md:352-356`
  - `docs/requirements.md:155-168`
  - `docs/database-guide.md:193`
  - `docs/backend/search-system.md:141-155`
  - `config/queue.php:16-72`
  - MariaDB JSON 문서: <https://mariadb.com/docs/server/reference/data-types/string-data-types/json>
  - MySQL JSON 문서: <https://dev.mysql.com/doc/refman/8.0/en/json.html>
  - MySQL InnoDB 제한: <https://dev.mysql.com/doc/refman/8.0/en/innodb-limits.html>

## FB-DEC-002 — identifier strategy

- 상태: `accepted`
- 결정:
  - 모든 저장 객체의 내부 PK는 auto-increment unsigned bigint 후보를 기본으로 한다.
  - `Form`, `Submission`, `IntegrationBinding`, `SubmissionProcessingAttempt`의 외부 노출·상관 식별자는 UUIDv7 문자열을 사용한다. `FormVersion`과 typed index는 외부 UUID가 필요하지 않다.
  - Laravel `HasUuids`를 사용한다면 `uniqueIds()`로 UUID 컬럼을 지정하고 numeric `id`를 PK로 유지한다.
  - Form 공개 URL은 slug를 사용하고, 관리 API route key는 UUID를 사용한다.
  - slug는 소문자 ASCII, 숫자, 하이픈으로 제한하며 첫 publish 후 MVP에서는 immutable이다.
  - Field stable key는 `^[a-z][a-z0-9_]{0,63}$`의 사람이 읽을 수 있는 immutable string이다. 의미가 바뀌는 rename은 새 key로 취급한다.
  - `FormVersion.revision`은 Form 내부에서 1부터 증가하는 정수이며 `(form_id, revision)` unique다.
  - Submission의 공개 식별자는 UUIDv7이다.
  - 클라이언트 `Idempotency-Key`는 선택값이며 길이·문자 검증 후 원문 대신 SHA-256 hash를 저장한다. `(form_id, idempotency_key_hash)`를 unique 후보로 삼고 동일 key의 다른 payload는 conflict로 응답한다.
- 근거:
  - 현재 Laravel 12.62의 `HasUuids::newUniqueId()`는 `Str::uuid7()`을 반환한다.
  - Hello module은 bigint PK와 별도 `uuid`를 함께 사용한다.
- 검토한 대안:
  - UUID primary key: DB join/index 크기와 기존 관례 때문에 `rejected`.
  - slug만 공개 식별자로 사용: rename 및 외부 참조 안정성이 약해 `rejected`.
  - UUIDv4 추정: 실제 framework 구현과 달라 `rejected`.
- 영향:
  - UUID 컬럼은 36자 문자열 관례를 먼저 따르며 binary UUID 최적화는 MVP에서 사용하지 않는다.
  - slug 변경 이력/redirect table은 MVP에 없다.
- 후속 작업:
  - Phase 2 migration 전 기존 UUID 컬럼 타입·index와 `HasUuids` 사용 테스트를 재확인한다.
  - idempotency 재응답 보존 기간과 payload hash 알고리즘의 상세는 Phase 7에서 확정한다.
- 실제 근거:
  - `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php:7-29`
  - `modules/_bundled/gnuboard7-hello_module/src/Models/Memo.php`
  - `modules/_bundled/gnuboard7-hello_module/database/migrations/2024_01_01_000000_create_hello_memos_table.php`

## FB-DEC-003 — Composer package name

- 상태: `accepted`
- 결정:
  - module identifier는 `glitter-form_builder`다.
  - Composer package name은 `modules/glitter-form_builder`를 권장한다.
  - `glitter/formbuilder`는 G7 번들 관례와 다르므로 MVP package name으로 사용하지 않는다.
  - identifier, Composer package name, PHP namespace는 서로 다른 식별자다.
- 근거:
  - 조사한 모든 번들 모듈 composer package가 `modules/<identifier>`를 사용한다.
- 검토한 대안:
  - `glitter/formbuilder`: 일반 Packagist 관례에는 가깝지만 G7 런타임 모듈 관례와 달라 `rejected`.
  - `modules/glitter-form_builder`: `accepted`.
- 영향:
  - Phase 1 namespace 후보는 `Modules\Glitter\FormBuilder`가 아니라 G7 디렉토리 변환 규칙에 맞춘 `Modules\Glitter\FormBuilder`/`FormBuilder` 실제 생성 결과를 먼저 검증해야 한다. 대소문자는 아직 확정하지 않는다.
- 후속 작업:
  - Phase 1에서 extension namespace resolver의 실제 변환 테스트 후 PSR-4 값을 확정한다.
- 실제 근거:
  - `modules/_bundled/gnuboard7-hello_module/composer.json:2-12`
  - `modules/_bundled/sirsoft-page/composer.json`
  - `modules/_bundled/sirsoft-board/composer.json`
  - `modules/_bundled/sirsoft-ecommerce/composer.json`
  - `docs/extension/module-routing.md:50-57`

## FB-DEC-004 — submission access modes

- 상태: `accepted`
- 결정:
  - access enum은 `authenticated_only`, `guest_and_authenticated`, `admin_only` 세 값이다.
  - 새 Form은 `draft`이고 기본 access mode는 `authenticated_only`다. publish 전에는 어떤 mode도 공개 제출을 허용하지 않는다.
  - 익명 제출은 Form별 `guest_and_authenticated` opt-in이다. `guest_only`는 제공하지 않는다.
  - `admin_only` 입력은 공개 endpoint가 아니라 관리자 endpoint를 사용한다.
  - 공개 schema GET과 submit POST는 별도 route 및 middleware 정책을 가진다.
  - `guest_and_authenticated`는 `optional.sanctum`, 나머지 인증 사용자 제출은 `auth:sanctum`, 관리자 입력은 `auth:sanctum` + admin permission을 사용한다.
  - submit endpoint에는 별도 named rate limiter가 필수다. 구체 rate는 Phase 7에서 운영 설정으로 확정한다.
  - CAPTCHA는 MVP 필수 의존성이 아니라 anti-bot provider 후보로 둔다. 서버 검증 honeypot과 최소 작성 시간 검사는 내장 가능하되 접근성·오탐 테스트 후 Phase 7에서 활성 기본값을 결정한다.
  - IP/User-Agent 원문 보존은 기본 비활성 후보다. 보안상 필요하면 Form별 고지·retention과 함께 hash/truncation 방식을 선택한다.
- 근거:
  - public page API가 `optional.sanctum`과 throttle을 조합한다.
  - G7 인증 문서는 Bearer Sanctum을 권위로 둔다.
- 검토한 대안:
  - 모든 published Form 익명 허용: 개인정보·스팸 위험 때문에 `rejected`.
  - 단일 public middleware: schema 조회와 쓰기의 위험이 달라 `rejected`.
  - CAPTCHA 필수: 공급자 종속 때문에 `rejected`.
- 영향:
  - access mode는 FormVersion snapshot에 포함돼 제출 당시 정책을 재현해야 한다.
  - 관리자 내부 입력도 일반 Submission 원본·검증·처리 이력을 사용하되 actor context를 기록한다.
- 후속 작업:
  - Phase 6에서 user route가 활성 사용자 템플릿에 합성되는지 lifecycle 테스트한다.
  - Phase 7에서 limiter key, proxy IP 신뢰 설정, honeypot 정책을 결정한다.
- 실제 근거:
  - `modules/_bundled/sirsoft-page/src/routes/api.php:114-138`
  - `docs/backend/authentication.md`
  - `docs/backend/middleware.md`

## FB-DEC-005 — privacy retention

- 상태: `accepted`(정책 모델), `deferred`(자동 집행)
- 결정:
  - 보존 정책은 Form별이며 `manual`, `forever`, `days`를 Phase 2 schema에 정의한다.
  - 기본값은 `manual`이며 publish 시 운영자가 정책과 안내문을 명시적으로 확인해야 한다. `forever`를 숨은 기본값으로 사용하지 않는다.
  - MVP에서 `manual`과 `forever`는 실제 동작한다. `days`는 기간·due date·관리자 경고를 계산하지만 scheduler purge는 Phase 11까지 `deferred`이며 UI/API에 “자동 삭제 미활성”을 명확히 표시한다.
  - `anonymize_after_days`와 legal hold는 Phase 11 이후로 `deferred`.
  - soft delete는 접근 차단이고 개인정보 파기가 아니다. purge/anonymize는 raw payload와 typed index를 같은 작업 단위로 처리한다.
  - 처리 이력에는 payload 전체를 복제하지 않고 식별자, 상태, 오류 code, redacted summary만 둔다.
  - Form 정의·FormVersion은 제출 해석을 위해 유지한다. Submission 원본과 typed index는 같은 보존 정책을 따른다.
  - export 파일은 MVP에서 서버 영속 파일로 만들지 않는 방식을 우선하며, 만들 경우 짧은 별도 TTL과 private disk가 필수다.
  - attachment는 MVP 제외다. Phase 11에서 Submission과 동일하거나 더 짧은 정책을 정한다.
  - activity log는 코어 정책의 소유이며 민감값을 넣지 않는다.
  - Connector가 만든 외부 업무 데이터는 Connector/외부 모듈 소유이며 Form Builder가 삭제하지 않는다. 삭제 요청 전달 계약은 별도 opt-in이다.
- 검토한 대안:
  - module 전역 TTL: Form 목적별 법적·운영 차이를 표현하지 못해 `rejected`.
  - 즉시 자동 purge: scheduler 및 복구 검증 전에는 위험해 `deferred`.
  - `anonymize_after_days`: field별 익명화 의미가 필요해 `deferred`.
- 영향:
  - Form에는 retention mode, nullable days, acknowledgement 정보가 필요하다.
  - purge는 idempotent하며 Connector 데이터와 분리돼야 한다.
- 후속 작업:
  - Phase 11에서 purge/anonymize dry-run, scheduler, export/attachment TTL, legal hold를 설계한다.
- 실제 근거:
  - `docs/backend/activity-log.md`
  - `docs/extension/storage-driver.md`
  - `modules/_bundled/sirsoft-page/tests/scenarios/page-attachment-order-and-seo-cache.yaml:30-41`

## FB-DEC-006 — file field MVP

- 상태: `rejected`(MVP), `deferred`(Phase 11)
- 결정:
  - Phase 1~8 built-in schema에 `file`/`image`를 등록하거나 미래 예약 type으로 노출하지 않는다.
  - Phase 11에서 익명 임시 소유권, claim, orphan cleanup, MIME/확장자/크기, malware scan hook, private disk, 다운로드 권한, 삭제 정책을 별도 결정한 후 추가한다.
- 근거:
  - Page module에는 `temp_key` 업로드와 제출 후 link/move 사례가 실제로 있다.
  - 그러나 해당 upload route는 관리자 권한 경계이며, 범용 익명 임시 업로드 capability나 malware scan hook, Form/Submission용 다운로드 정책은 확인되지 않았다.
  - `FileUploader`는 UI component이지 Form field의 서버 전체 계약이 아니다.
- 검토한 대안:
  - Page attachment 구현 복제: 도메인 결합과 보안 공백 때문에 `rejected`.
  - Core `AttachmentService` 직접 재사용: 일부 저장 기능은 재사용 후보이나 익명 lifecycle 전체가 아니므로 `deferred`.
- 영향:
  - 초기 field set, schema example, Designer에 file이 없다.
- 후속 작업:
  - Phase 11에서 공통 storage/attachment contract와 공개 다운로드 authorization hook을 재평가한다.
- 실제 근거:
  - `modules/_bundled/sirsoft-page/src/routes/api.php:89-112`
  - `modules/_bundled/sirsoft-page/tests/Unit/Services/PageAttachmentServiceTest.php:78-157`
  - `modules/_bundled/sirsoft-page/tests/scenarios/page-attachment-order-and-seo-cache.yaml:30-41`
  - `docs/frontend/component-props-composite.md`
  - `docs/extension/storage-driver.md`

## FB-DEC-007 — handler execution mode and lifecycle

- 상태: `accepted`
- 결정:
  - binding execution mode는 `sync`, `async`, `disabled`를 지원한다. `auto`는 런타임 결과가 환경 변경에 따라 달라져 MVP에서 `rejected`.
  - Submission 저장 transaction에는 원본, typed index, durable initial attempt까지만 포함한다. 외부 Handler 호출은 commit 이후다.
  - `async`는 queue job, `sync`는 commit 이후 같은 요청에서 execution layer를 호출한다. queue driver가 `sync`더라도 attempt 상태 계약은 동일하다.
  - 현재 런타임 effective queue가 `sync`이므로 worker 없이도 동작해야 한다.
  - Handler 실패는 이미 접수된 Submission을 유실시키거나 public submit을 500으로 바꾸지 않는다. 응답은 접수 성공과 processing 상태를 구분한다.
  - MVP에서 `critical`은 HTTP 접수 rollback 의미로 사용하지 않는다. binding의 후속 중단 정책은 모두 독립 실행을 기본으로 하고 orchestration/critical chain은 `deferred`.
  - Submission 상태는 `received → processing → processed | processing_failed`, 별도 관리자 조치로 `cancelled`다.
  - 이 결정의 초기 ProcessingAttempt 후보는 FB-DEC-013에 의해 **superseded**됐다. frozen 상태는 `pending → running → succeeded | failed | exhausted`, 실행 전 provider 부재/cancel은 `skipped`이며 retry는 새 `pending` Attempt를 생성한다.
- 근거:
  - G7 queue 기본 config는 database이나 SettingsServiceProvider가 effective driver를 override한다.
  - 현재 effective driver는 `sync`.
  - Hook async job은 `afterCommit=true`를 사용한다.
- 검토한 대안:
  - 원본 저장과 외부 호출 단일 transaction: 외부 장애가 접수를 유실시켜 `rejected`.
  - `auto`: 운영 예측성이 낮아 `rejected`.
  - Hook-only 처리: durable binding/attempt/retry 표현이 없어 `rejected`.
- 영향:
  - 공개 응답은 `submission_id`, `status`, processing summary를 반환할 수 있으나 Handler 결과를 성공 전제로 하지 않는다.
  - queue worker 부재 시 async attempt가 pending으로 남아 관리자 경고 대상이다.
- 후속 작업:
  - Phase 9에서 retry/backoff/timeout/max attempts와 queue health 표시를 확정한다.
- 실제 근거:
  - `config/queue.php:16-72`
  - `app/Providers/SettingsServiceProvider.php:326`
  - `app/Jobs/DispatchHookListenerJob.php:20-43`
  - `docs/extension/hooks.md:351`
  - `docs/requirements.md:285`

## FB-DEC-008 — field contract versioning

- 상태: `accepted`
- 결정:
  - Registry는 `glitter-form_builder`가 소유한다.
  - built-in type은 `text`, `textarea`처럼 unqualified stable identifier다.
  - external type은 `{provider_identifier}:{local_type}` 형식이다. 예: `acme-fields:phone`.
  - provider identifier는 G7 extension identifier이며 local type은 `^[a-z][a-z0-9_-]{0,63}$`다.
  - contract version은 major/minor 문자열(`1.0`)이다. 호환성 판단은 major 일치가 기본이다.
  - 같은 canonical type의 중복 등록은 bootstrap/publish validation에서 fail-fast한다. first/last wins는 금지한다.
  - draft는 unresolved provider field를 보존할 수 있지만 경고 상태이며 publish할 수 없다.
  - publish는 server validator, normalizer, storage/export descriptor, renderer capability가 모두 resolvable이고 contract major가 맞아야 한다.
  - published FormVersion에는 provider ID, type, contract version, 표시/export fallback metadata snapshot을 저장한다.
  - provider 제거 후 기존 raw Submission은 fallback label과 raw-safe representation으로 조회·내보낼 수 있다. 새 제출은 validation 권위를 잃으므로 해당 published version을 unavailable 처리한다.
  - provider 부재가 raw 값을 삭제하거나 snapshot을 다시 쓰게 해서는 안 된다.
- 검토한 대안:
  - fixed Enum만 사용: 외부 확장 목표와 충돌해 `rejected`.
  - draft도 unresolved 저장 금지: provider 일시 비활성화 시 편집 복구가 어려워 `rejected`.
  - publish 후 provider 부재에도 제출 허용: server validation 부재로 `rejected`.
- 영향:
  - Registry resolution 결과와 snapshot fallback은 별도 객체다.
  - renderer와 validator contract mismatch는 publish 및 runtime schema 제공을 차단한다.
- 후속 작업:
  - Phase 3에서 collision, missing provider, major mismatch, snapshot fallback 테스트를 정의한다.
- 실제 근거:
  - `app/Extension/`의 Registry/Provider 패턴
  - `docs/extension/layout-extensions.md`
  - `docs/backend/validation.md`

## FB-DEC-009 — handler contract versioning

- 상태: `accepted`
- 결정:
  - handler ID는 `{provider_identifier}:{local_handler}` 형식이며 항상 namespace를 포함한다.
  - provider identifier는 G7 extension identifier, contract version은 major/minor 문자열(`1.0`)이다.
  - duplicate registration은 fail-fast다.
  - Handler는 configuration schema와 redaction metadata를 제공한다. 일반 config JSON과 secret은 분리하며 secret은 encrypted storage 또는 secret reference로 저장하고 snapshot/log/API에 평문을 포함하지 않는다.
  - execution layer는 Submission UUID, attempt UUID, binding UUID에서 파생한 stable idempotency key를 Handler에 전달한다.
  - Handler는 retryable exception과 permanent exception을 구분한다. retry/backoff/job 생성은 Form Builder execution layer만 통제한다.
  - provider disable/remove 시 신규 실행을 중단하고 attempt를 reason과 함께 `skipped` 또는 관리자 재시도 대기로 남긴다. binding과 과거 attempt의 opaque identifier/snapshot은 보존한다.
  - payload 전체를 processing log나 exception context에 기록하지 않는다.
  - timeout/backoff/max attempts의 수치는 아직 `deferred`.
- 검토한 대안:
  - Hook name만 binding으로 저장: config/version/secret/retry 계약이 부족해 `rejected`.
  - Handler 자체 queue dispatch: 중앙 retry·감사 계약을 우회해 `rejected`.
- 영향:
  - Connector는 Form Builder 공개 DTO만 받고 다른 모듈 내부 Model/Repository를 본체에 노출하지 않는다.
- 후속 작업:
  - Phase 9에서 PHP interface와 exception hierarchy를 실제 코드로 확정한다.
- 실제 근거:
  - `docs/extension/hooks.md`
  - `app/Extension/HookListenerRegistrar.php:145-205`
  - `app/Jobs/DispatchHookListenerJob.php`

## FB-DEC-010 — MVP field set and Form Schema

- 상태: `accepted`
- 결정:
  - built-in은 `text`, `textarea`, `email`, `number`, `select`, `radio`, `checkbox`, `date`, `datetime`, `hidden`, `consent`다.
  - `checkbox`는 단일 boolean이며 복수 선택은 MVP에 없다.
  - field key는 FB-DEC-002 규칙을 따른다.
  - label/description/options는 운영자 편집 데이터이므로 locale-keyed JSON map으로 snapshot한다. Layout 고정 문장만 language file의 `$t:` key를 사용한다.
  - 공통 속성은 `required`, `default`, `validation`, `sensitive`, `indexable`, `exportable`이다.
  - `sensitive=true`이면 `indexable=false`를 강제한다. `exportable`은 별도 명시하며 권한 없는 export를 허용하지 않는다.
  - validation은 allowlist만 허용하고 임의 PHP rule, DB `exists/unique`, 임의 regex/callback은 금지한다.
  - 단일 페이지, flat ordered fields, 구조화 편집과 순서 변경만 지원한다.
  - visibility condition, multi-step, repeating/nested group, 계산식은 MVP 제외다.
  - draft는 변경 가능하고 published FormVersion snapshot은 immutable이다.
  - file/image/signature/address/complex phone/user·CRM·reservation·product picker/rich text/calculated/payment/repeating/nested/matrix/rating은 제외한다.
- 영향:
  - 구체 envelope와 allowlist는 `schema-contract-draft.md`가 Phase 2/3 기준이다.
- 후속 작업:
  - Phase 3에서 JSON Schema 또는 전용 validator 선택, canonical hash, 제한값을 테스트한다.
- 실제 근거:
  - `docs/backend/validation.md`
  - `docs/frontend/state-management-forms.md`
  - `docs/frontend/layout-json.md`
  - `AGENTS.md` i18n Layout Rule

## FB-DEC-011 — public and admin routes

- 상태: `accepted`
- 결정:
  - user frontend route 후보는 `/forms/:slug`를 권장한다.
  - Phase 6 구현 public API는 read-only `GET /api/modules/glitter-form_builder/forms/{slug}/schema`다. `POST /api/modules/glitter-form_builder/forms/{slug}/submissions`는 frozen future contract이며 Phase 7 planned 상태다.
  - admin API base는 `/api/modules/glitter-form_builder/admin/forms`다. 상세 route는 UUID binding을 사용한다.
  - `GET /api/forms/...` 같은 core-looking prefix와 `/api/admin/forms`는 사용하지 않는다.
  - slug는 첫 publish 후 immutable이다. MVP에는 redirect history가 없다.
  - Form public UUID는 관리·Connector의 stable identity이고 slug는 사람용 공개 route identity다.
- 근거:
  - ModuleRouteServiceProvider가 `/api/modules/{identifier}`를 자동 prefix한다.
  - Page module은 public API에서 published slug를 사용하고 admin API를 `/admin/pages`로 분리한다.
- 검토한 대안:
  - `/f/{public_id}`: 안정적이나 사람이 읽기 어렵고 기존 Page 관례와 멀어 `rejected`.
  - `/form/{slug}`: 단수 resource라 `/forms/{slug}`보다 일관성이 낮아 `rejected`.
  - mutable slug + redirect history: MVP 범위를 늘려 `deferred`.
- 영향:
  - user route layout은 G7 routes 합성으로 노출해야 하며 기존 사용자 template 수정은 금지한다.
- 후속 작업:
  - Phase 6에서 현재와 다른 user template에서도 합성되는지 검증한다.
- 실제 근거:
  - `docs/extension/module-routing.md:61-77`
  - `docs/extension/module-routing.md:165-180`
  - `modules/_bundled/sirsoft-page/src/routes/api.php:16-20,114-138`
  - `modules/_bundled/sirsoft-page/resources/routes.json:1-32`

## FB-DEC-012 — permission model

- 상태: `accepted`
- 결정:
  - 모두 `type=admin`으로 다음 권한을 선언한다.
    - `glitter-form_builder.forms.read`
    - `glitter-form_builder.forms.create`
    - `glitter-form_builder.forms.update`
    - `glitter-form_builder.forms.delete`
    - `glitter-form_builder.forms.publish`
    - `glitter-form_builder.submissions.read`
    - `glitter-form_builder.submissions.export`
    - `glitter-form_builder.submissions.delete`
    - `glitter-form_builder.submissions.retry`
    - `glitter-form_builder.integrations.manage`
    - `glitter-form_builder.settings.manage`
  - manifest category/action으로 선언하고 G7가 최종 identifier를 합성하는 관례를 따른다.
  - `forms` category는 `resource_route_key=form`, `owner_key=created_by`를 사용할 후보로 확정한다.
  - Submission은 submitter 소유권과 관리자 관리 범위가 다르므로 MVP에서 `self` scope를 억지로 적용하지 않고 global admin permission으로 둔다.
  - integrations/settings도 global admin permission이다. Form 단위 delegation은 후속 policy로 `deferred`.
- 근거:
  - G7 scope는 permission pivot의 `all/role/self`와 category의 route/owner key를 사용한다.
  - Page가 `resource_route_key=page`, `owner_key=created_by`를 사용한다.
- 검토한 대안:
  - 모든 category에 self scope: Submission/Integration의 owner 의미가 불명확해 `rejected`.
  - 단일 manage permission: 최소권한·export/delete 분리에 부적합해 `rejected`.
- 영향:
  - API Resource `abilities`는 이 권한을 근거로 반환해야 한다.
- 후속 작업:
  - Phase 1에서 permission manifest shape, Phase 4/8에서 middleware와 ability matrix를 테스트한다.
- 실제 근거:
  - `modules/_bundled/sirsoft-page/module.php:27-76`
  - `modules/_bundled/sirsoft-page/src/routes/api.php:32-86`
  - `docs/extension/permissions.md:155-167,211-238`

## 결정 간 우선순위

충돌 시 개인정보 원본 보존/삭제 경계(FB-DEC-005), server validation 권위(FB-DEC-008/010), durable processing(FB-DEC-007/009), G7 lifecycle/route 관례(FB-DEC-003/011)를 우선한다. 수치가 `deferred`인 항목을 구현자가 임의 상수로 공개 계약에 고정해서는 안 된다.

## FB-DEC-013 — Phase 0.5 Architecture Freeze

- 상태: `accepted`
- 결정:
  - Phase 0-B 선택을 `docs/contracts/` 12개 규격으로 동결한다. architecture semantics는 frozen이지만 실제 PHP API는 아직 없고 `experimental` 이전 단계다.
  - Form Aggregate는 Form root, mutable draft working copy, immutable FormVersion, IntegrationBinding으로 구성한다. current published version은 최대 하나이며 publish는 새 revision을 만든다.
  - 첫 publish 후 slug는 immutable이다. archived Form은 신규 제출 불가이며 `archived → draft → published`만 허용한다.
  - Submission이 있으면 Form/FormVersion hard delete를 금지한다.
  - Submission raw JSON이 원본 권위이고 typed index는 derived projection이다.
  - Attempt는 terminal 이후 immutable하며 retry마다 새 row를 만든다. 상태는 `pending|running|succeeded|failed|exhausted|skipped`; `retry_scheduled`는 metadata/새 Attempt로 대체한다.
  - 여러 Handler는 Binding별 mode로 독립 실행한다. 한 실패가 다른 실행을 막지 않으며 MVP critical Handler는 없다. disabled Binding은 Attempt를 만들지 않는다.
  - Laravel Domain Event를 canonical type-safe 표면, G7 Action Hook를 외부 관찰·알림·활동 로그 표면으로 사용한다. durable processing은 Hook에 맡기지 않는다.
  - 공개 event/hook는 commit 후 최소 redacted payload만 발행한다.
  - Field/Handler Registry는 외부 ServiceProvider `boot()`의 명시적 register, duplicate fail-fast, application boot 후 seal, runtime unregister 금지다.
  - 공개 DTO는 immutable하고 ORM Model을 노출하지 않는다. 실제 PHP 이름에는 G7 규칙에 따라 `Data`/`Dto` 접미사를 쓰지 않는다.
  - Connector 의존 방향은 Connector → Form Builder public Contract → target public Service뿐이다.
- 검토한 대안:
  - 별도 DraftVersion entity는 MVP에서 `deferred`.
  - retry 시 기존 Attempt 갱신, `retry_scheduled` 상태, Hook-only 처리, Registry scan/last-wins/runtime unregister는 `rejected`.
  - 모든 Contract 즉시 stable 선언은 첫 Connector conformance 전이라 `rejected`.
- 영향:
  - Phase 1은 internal skeleton만 만들고 공개 Contract class를 만들지 않는다.
  - Phase 2는 Aggregate/Enum/serialization을 experimental로 구현하며 Field/Handler interface는 만들지 않는다.
  - Phase 3에서 Field Contract/Registry, Phase 7에서 Submission/event DTO, Phase 9에서 Handler Contract/Registry를 처음 experimental로 구현한다.
- 후속 작업:
  - Phase 3 전 canonical JSON/hash, number/datetime 세부를 결정한다.
  - Phase 9 전 timeout/backoff/max attempts/secret service/idempotency tuple test vector를 결정한다.
  - simple conformance fixture 이후 Webhook Connector를 첫 독립 검증 후보로 평가한다.
- 실제 근거:
  - `docs/contracts/README.md`
  - `docs/contracts/domain-model.md`
  - `docs/contracts/form-schema.md`
  - `docs/contracts/field-provider.md`
  - `docs/contracts/submission-handler.md`
  - `docs/contracts/processing-lifecycle.md`
  - `docs/contracts/events.md`
  - `docs/contracts/registry.md`
  - `docs/contracts/dto.md`
  - `docs/contracts/connector-boundary.md`
  - `docs/contracts/compatibility.md`
  - `docs/contracts/security.md`
  - `docs/backend/dto.md:7-15,208-239`
  - `docs/backend/enum.md:7-15,160-170`
  - `app/Extension/HookManager.php:31-62,109-135`
  - `app/Jobs/DispatchHookListenerJob.php:31-58`
  - `app/Extension/IdentityVerification/IdentityVerificationManager.php:87-105`
  - `modules/_bundled/sirsoft-page/src/Providers/PageServiceProvider.php:49-70`
  - `modules/_bundled/sirsoft-ecommerce/src/Services/ProductInquiryService.php:15-20,77-103,222-262`

## Phase 0.5 권위 순서

FB-DEC-013 이후 공개 계약 의미는 `docs/contracts/`가 권위다. 기존 design draft와 충돌하면 contracts 문서를 따른다. DB 물리 타입, 수치, 실제 class/namespace/method signature 등 `deferred` 또는 pseudo-code 항목은 계속 미확정이다.

## FB-DEC-014 — Phase 3 Field runtime 세부 결정

- 상태: `accepted`
- 결정:
  - Field Contract major/minor는 동결 계약과 같이 문자열 `1.0`으로 표현한다. 복잡한 semantic resolver 없이 Registry는 major `1`과 명시 capability를 검사한다.
  - `checkbox`는 동결된 Form Schema 의미인 단일 boolean이다. 복수 선택은 MVP 미지원이며 `consent`는 statement ID가 필요한 명시 동의로 분리한다.
  - `number`의 canonical 값은 float가 아닌 normalized decimal string이다. scientific notation과 float 입력을 거부하며 DB precision/scale은 계속 `deferred`다.
  - `datetime`은 G7의 실제 site-local 입력 관례에 맞춰 context의 site timezone으로 local 입력을 해석하고 UTC `Y-m-d\TH:i:s\Z`로 serialize한다. 명시 offset이 있는 RFC 3339도 UTC로 정규화한다.
  - Registry singleton은 Provider `register()`, built-in 등록은 `boot()`, seal은 application `booted()` callback에서 수행한다. 동일 instance 재등록도 duplicate다.
  - hidden은 client 값을 권위로 사용하지 않고 `schema_default` server value만 canonicalize한다. renderer는 아직 없으므로 descriptor를 `available=false`로 명시한다.
- 근거:
  - Phase 0.5 frozen semantics: `docs/contracts/form-schema.md`, `field-provider.md`, `registry.md`, `compatibility.md`.
  - G7 site-local→UTC 관례: `app/Helpers/TimezoneHelper.php:180-200`, `modules/_bundled/sirsoft-ecommerce/src/Http/Requests/Admin/StoreCouponRequest.php:173-180`.
  - application boot 완료 callback 관례: `modules/_bundled/sirsoft-page/src/Providers/PageServiceProvider.php:64-70`.
- 영향:
  - 실제 experimental API는 `src/Contracts/Fields/FieldProvider.php`, `src/Fields/FieldTypeRegistry.php`, `src/Fields/ValueObjects/*`다.
  - Submission typed index DB 컬럼과 precision은 Phase 7 전 별도 결정이 필요하다.
- 후속 작업:
  - Phase 4 publish boundary에서 Form Schema 전체 validator와 provider resolution을 결합한다.
  - 실제 renderer 구현 후 descriptor availability와 renderer major compatibility를 검증한다.

## FB-DEC-015 — Phase 4 Canonical Schema validation 경계와 제한

- 상태: `accepted`
- 결정:
  - frozen envelope의 camelCase `schemaVersion: "1.0"`과 ordered `fields`를 canonical input으로 유지한다. automatic up-conversion은 하지 않는다.
  - issue path는 G7 FormRequest error key와 일치하는 dot notation(`fields.0.key`)이며 issue 순서는 parser → envelope → field order → publish Provider 검사 순으로 안정적이다.
  - draft는 unresolved Provider와 기본 locale 누락을 보존 가능한 diagnostic으로 취급한다. JSON-safe 위반·duplicate key·구조 한계는 draft도 error다. publish는 Provider unresolved/incompatible/definition invalid와 기본 locale 누락을 error로 승격한다.
  - renderer unavailable은 Phase 4에서 `field.renderer_unavailable` warning이며 publish validity를 깨지 않는다.
  - 보안 제한은 encoded JSON 262,144 bytes, fields 100, field당 options 100, locales 20, label 255 chars, description 2,000 chars, depth 16, nodes 10,000이다. 모두 `SchemaLimits` named constant다.
  - API/저장 메타데이터와 혼동 가능한 `id`, `uuid`, `form_id`, `submission_id`, `created_at`, `updated_at`, `deleted_at`, `metadata`, `abilities`를 MVP reserved field key로 둔다.
  - Provider exception은 class/message/raw value를 노출하지 않고 stable `field.definition_invalid`로 sanitize한다.
- 근거:
  - frozen schema: `docs/contracts/form-schema.md`.
  - G7 dot validation path와 422 envelope: `app/Http/Controllers/Api/Base/BaseApiController.php:107-121`, `app/Helpers/ResponseHelper.php:75-92`.
  - 기존 설계의 field 100 제안과 FormRequest title/description 제한: `docs/design/schema-contract-draft.md`, `src/Http/Requests/Admin/StoreFormRequest.php`.
- 영향:
  - `FormService::publish()`는 scope 확인 후 transaction 시작 전에 parser/publish validator를 실행한다.
  - 성공 publish warning의 response 전달은 반환형을 ORM과 결합하지 않고 Phase 5 active API 설계로 deferred한다. `validatePublishSchema()` result에는 warning이 보존된다.
- 후속 작업:
  - 전용 testing DB에서 실제 transaction 및 422 route response를 검증한다.
  - Phase 5에서 warning preview와 renderer capability UX를 결정한다.

## FB-DEC-016 — Phase 5 Designer editing boundary

- 상태: `accepted`
- 결정:
  - Designer state는 별도 wire format이 아니라 canonical camelCase `schemaVersion: "1.0"` + ordered `fields`를 권위로 사용하며 `toCanonicalSchema()`가 전송 경계다.
  - palette는 `FieldTypeRegistry`를 읽는 `GET admin/field-types`가 안전한 metadata와 내부 `FieldDefinitionFactory` template를 공급한다. PHP class/callback/secret은 노출하지 않는다.
  - `POST admin/forms/validate-schema`는 request envelope 오류만 422이며, schema 검증 성공 자체는 invalid schema도 HTTP 200 + `valid/errors/warnings`다. mode는 `draft|publish-preview`만 허용한다.
  - renderer unavailable warning은 숨기지 않고 publish 전에 확인한다. publish 성공 응답 wrapper는 도입하지 않고 preview warning을 사용하며 publish service는 transaction 전 동일 검증을 다시 수행한다.
  - Phase 5 동시 편집은 migration 변경 없이 안전한 atomic compare를 보장할 수 없어 last-write-wins 위험을 명시하고 optimistic concurrency는 deferred다.
  - 실제 runtime에 module-owned React component를 Layout Registry에 등록하는 공개 API가 확인되지 않아 검증된 Layout component + namespaced ActionDispatcher handler/state 경계를 사용한다.
- 근거: `resources/js/core/template-engine/ActionDispatcher.ts`, `modules/_bundled/sirsoft-ecommerce/resources/js/index.ts`, `modules/_bundled/sirsoft-page/resources/layouts/admin/admin_page_list.json`, `src/Fields/FieldTypeRegistry.php`.
- 후속: Phase 6 전 active template renderer에서 Layout/handler 통합과 optimistic locking을 별도 결정한다.

## FB-DEC-017 — Phase 5.5 frontend state와 build verification

- 상태: `accepted`
- 결정:
  - Designer state는 Layout instance의 `_local` context에 두며 module-level mutable store를 두지 않는다. navigation/re-entry의 `initialize`는 selection/issues/loading state를 reset한다.
  - handler 등록은 module namespace를 강제하고 같은 dispatcher에는 한 번만 등록한다. locale 전환으로 새 dispatcher가 생성되면 다시 등록한다.
  - clone key는 현재 fields에서 가장 작은 사용 가능 `{type}_{n}`을 결정적으로 재사용한다. 사용자 key 중복은 보존하며 server validator가 최종 권위다.
  - canonical conversion은 plain JSON data만 허용하고 frontend-only key를 제거하며 undefined/function/class instance/non-finite number/잘못된 surrogate를 fail-closed한다.
  - source build 검증은 운영 `public/build` 대신 temporary Vite outDir을 사용한다. 공식 module build packaging과 TypeScript dependency 추가는 deferred다.
- 근거: `resources/js/core/template-engine/ActionDispatcher.ts:414-438,6606-6640`, `app/Console/Commands/Module/BuildModuleCommand.php:107-175`, `app/Services/TemplateService.php:1623-1665`.

## FB-DEC-018 — Phase 5.6 module-local frontend toolchain

- 상태: `accepted`
- 결정:
  - Form Builder frontend build/typecheck/test dependency는 private module-local `package.json`과 lockfile이 소유한다. root package/lock에는 TypeScript를 추가하지 않는다.
  - 현재 root와 검증된 exact Vite/Vitest 계열을 사용하고 TypeScript와 Node type도 lockfile로 고정한다. official modules와 동일하게 output은 bundled module의 tracked `dist/js/module.iife.js`, `dist/css/module.css`다.
  - build format/global은 IIFE/`GlitterFormBuilderModule`이며 React 또는 G7 runtime을 package dependency로 bundle하지 않는다. runtime은 typed `window.G7Core` adapter를 통해서만 접근한다.
  - CSS는 JS entry에서 import하되 module.json의 별도 CSS output으로도 선언한다. Vite manifest는 runtime에서 소비되지 않으므로 생성하지 않는다.
  - handler는 global asset priority 100으로 Layout 실행 전에 로드되는 공식 asset pipeline을 사용하고, dispatcher 지연 시 최대 51회(초기 + 50 retry) 뒤 중단한다. 무한 retry나 import되지 않는 별도 entry는 두지 않는다.
  - 공식 `module:build`의 npm/build 부분은 `npm ci` + module-local scripts로 재현한다. cache version/components manifest write가 허용되지 않은 gate에서는 Artisan 명령을 실행하지 않는다.
- 근거:
  - `app/Console/Commands/Module/BuildModuleCommand.php:107-224,280-367`
  - `app/Extension/AbstractModule.php:1158-1245`
  - `app/Extension/ModuleManager.php:1058-1071`
  - `app/Http/Controllers/Api/Public/PublicModuleController.php:64-103`
  - `docs/extension/module-assets.md:60-166,350-410`
  - `modules/_bundled/sirsoft-ecommerce/package.json`
  - `modules/_bundled/sirsoft-ecommerce/vite.config.ts`
- 영향:
  - `npm run typecheck`, `npm test`, `G7_BUILD_SOURCEMAP=0 npm run build`가 module directory에서 재현 가능하다.
  - active output과 extension cache 반영은 testing clone의 lifecycle gate까지 보류한다.

## FB-DEC-019 — Phase 6 public renderer boundary

- 상태: `accepted`
- 결정:
  - Phase 6은 Submission이 아니라 published FormVersion의 공개 read와 G7 Layout projection만 구현한다.
  - built-in 11종의 Field renderer descriptor는 allowlisted module renderer가 존재하므로 `available=true`, contract major `1`, output `g7-layout`으로 전환한다.
  - projection은 고정 mapping만 사용하고 Provider/Schema의 임의 component, prop, handler를 실행하지 않는다. `radio`는 동일한 단일 option 의미를 유지하는 `Select`로 표현하며 `hidden`은 공개 layout에서 제외한다.
  - public schema는 hidden/sensitive default, retention 내부 정책, provider class/callback/config를 노출하지 않는다.
  - `guest_and_authenticated`는 guest/member, `authenticated_only`는 member, `admin_only`는 forms.read admin permission 보유자에게만 read를 허용하며 거부는 존재 은닉을 위해 404 경계로 통일한다.
  - user layout은 Phase 7 POST가 없음을 명확히 하기 위해 입력과 버튼을 disabled read-only로 렌더링한다.
- 영향:
  - Phase 4 publish preview에서 built-in renderer unavailable warning은 더 이상 발생하지 않는다. 외부/unknown/mismatched renderer는 계속 fail-closed다.
  - 실제 DB row, route 합성, cache, browser render는 active lifecycle 금지 때문에 deferred다.
- 근거:
  - `docs/contracts/form-schema.md`, `field-provider.md`, `security.md`
  - `docs/extension/module-routing.md`
  - `templates/_bundled/sirsoft-basic/src/components/composite/PageSkeleton.tsx`
