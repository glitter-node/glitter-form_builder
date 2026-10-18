# Glitter Form Builder 아키텍처 대안

> 상태: 비교·권고안. migration/API/contract 확정안이 아니다. 공통 제품 표기는 `glitter-form_builder`, `Glitter Form Builder`, `Glitter.kr`, `MIT`, `0.1.0` 후보다.

## 1. 평가 기준

G7 적합성, 과거 제출 해석 가능성, 개인정보 삭제·익명화, 검색/내보내기, transaction과 retry, third-party marketplace 격리성, 운영 복잡도를 평가한다. 현재 확인된 기반은 module lifecycle (`docs/extension/module-basics.md:83-145`), user/admin module layout (`docs/extension/module-layouts.md:56-82`), hook queue (`docs/extension/hooks.md:315-392`), FormRequest 권위 (`docs/backend/validation.md:42-56`)다.

## 2. 제출 저장 구조

| 대안 | 장점 | 단점 | G7 적합성·유지보수·배포성 |
|---|---|---|---|
| EAV (`submission` + value per field) | field별 query/index/delete가 쉽고 type column 분리 가능 | row 폭증, type별 join/aggregation, 원본 ordering/unknown field 보존이 복잡 | Laravel로 가능하나 generic export와 version snapshot 재구성이 무겁다. marketplace provider type 추가 시 column/type migration 압력 |
| 단일 JSON payload | atomic raw snapshot, schema version과 함께 재해석 쉬움, 신규 field 무마이그레이션 | arbitrary search/index가 약하고 field별 erase가 JSON rewrite, DB별 JSON query 차이 | G7이 JSON/mediumText snapshot을 널리 사용 (`page_versions`, ecommerce snapshots). 가장 단순하지만 privacy/search 요구만으로는 부족 |
| JSON + field index hybrid | raw snapshot 보존과 opt-in 검색/삭제/정렬 균형, index rebuild 가능 | 이중 write/consistency, index 정책과 민감값 복제 통제 필요 | **권장**. submission transaction에서 raw/normalized snapshot을 저장하고 search/export 허용 field만 typed index에 projection. index는 정본이 아니며 rebuild 가능 |

권장안은 hybrid다. `raw_payload`와 `normalized_payload`의 필요 여부, 암호화 단위, index table의 exact columns는 DB schema 확인 뒤 결정한다. 모든 field를 무조건 index하지 않는다. password/secret/consent evidence/file binary는 index 제외가 기본이다.

## 3. 폼 정의 저장 구조

| 대안 | 장점 | 단점 | 평가 |
|---|---|---|---|
| 단일 JSON schema | 편집/clone/import가 단순, field type 확장에 유연 | field별 query/unique/order 관리와 부분 update가 약함 | MVP에는 빠르지만 publish snapshot과 draft concurrency를 별도 설계해야 함 |
| normalized form/field tables | field row order/query/operation이 명확 | provider option/constraint 변화마다 schema migration, snapshot 재구성이 복잡 | built-in field만 있을 때 적합; marketplace 확장성과 충돌 |
| Form + draft schema + immutable FormVersion snapshot hybrid | 편집 root와 게시 snapshot 분리, 과거 submission 해석 보장, provider schema 유연 | publish validation/clone 로직 필요 | **권장**. Form은 identity/lifecycle, FormVersion은 self-contained schema snapshot. FieldDefinition은 schema 내부 value object가 기본 후보이며 designer query 요구가 입증될 때 normalized draft projection 검토 |

운영자 편집 form name/field label/options는 locale map data로 version snapshot에 포함한다. UI 고정 문장은 lang file을 사용한다 (`docs/extension/module-i18n.md:30-52`).

## 4. 렌더링 구조

| 대안 | 장점 | 단점 | 평가 |
|---|---|---|---|
| JSON Layout 직접 저장 | G7 renderer 즉시 사용 | Form Schema와 UI engine 결합, server validation contract 중복, unsupported syntax 저장 위험 | 비권장. 필수 원칙과 충돌 |
| 독립 Form Schema → Layout 변환 | domain contract 안정, allowlist component/prop, server/client 동일 field metadata | 변환 layer와 template compatibility matrix 필요 | **MVP 권장**. 실제 지원 component/props만 adapter가 생성 |
| 전용 React Renderer | dynamic repeat/group/complex field UX에 강함 | build/assets/API contract/testing/lifecycle 비용, template style integration | 초기 대안 아님. Layout adapter로 표현 불가능한 requirement가 증명된 후 선택 |

변환 결과는 generated projection/cache이며 source of truth가 아니다. raw Layout syntax를 operator schema에 허용하지 않는다. MVP designer는 ordering 가능한 structured editor로 제한한다.

## 5. 연동 구조

| 대안 | 장점 | 단점 | 적합성 |
|---|---|---|---|
| Hook 중심 | G7 공식, 관찰자 다수, notification/activity 연결 쉬움 | 반환 결과/멱등성/attempt별 상태/부분 실패 표현이 약함 | lifecycle event 발행과 optional observer용 |
| Submission Handler Registry | typed contract, capability/config validation, 결과·retry 정책 통제 | G7 core에 없음; module contract/version/conflict 정책을 설계해야 함 | **핵심 권장** |
| Queue Job | request latency 분리, retry/backoff | queue 환경 미확인, job과 business attempt 상태 drift, side effect duplicate | handler execution mechanism. Registry의 대체가 아님 |
| Connector module | 독립 lifecycle/version/dependency, target-specific admin config/UI 제공 | package 수 증가, target 공개 contract 필요 | target domain adapter가 크거나 설정/UI/권한을 가지면 권장 |

권장 조합:

1. submission과 processing records를 한 transaction에 확정한다.
2. `SubmissionHandlerRegistry`가 binding key를 handler contract에 resolve한다.
3. sync handler는 명시적으로 safe/fast한 경우만; async는 durable attempt를 만든 뒤 Job이 claim한다.
4. handler는 submission UUID + binding UUID + attempt/idempotency key를 받아야 한다.
5. 결과/exception/retryable flag를 attempt에 기록한다.
6. after-commit hook는 activity, notification, third-party observer용이다.

DB outbox 별도 table을 둘지는 아직 확정하지 않는다. `SubmissionProcessingAttempt`가 dispatch intent까지 충분히 표현하는지 Phase 9에서 failure injection으로 검증한다.

## 6. 필드 확장 구조

| 대안 | 장점 | 단점 | 평가 |
|---|---|---|---|
| 고정 Enum | 단순·안전·IDE 친화 | third-party field 추가 불가, module release 필요 | built-in baseline 목록에만 사용 |
| module-owned Registry | validation/normalize/display/export contract를 묶을 수 있음 | collision/provider unload/version contract 필요 | **권장 핵심** |
| plugin/module Field Provider | marketplace 독립 배포 | provider dependency와 inactive field version 해석, frontend component 제공 방식 복잡 | Registry 위 provider mechanism 후보 |

Field type contract 후보:

- stable `typeKey`, `contractVersion`
- schema/config validation
- submission Laravel rules/Custom Rules 생성
- normalization 및 storage serialization
- safe display/export/index projection
- privacy/file capabilities
- renderer capability 또는 supported G7 component mapping

Provider가 제거돼도 과거 submission은 raw snapshot과 stored display metadata로 최소 해석 가능해야 한다. provider executable code 없이 완전 export가 필요한지 결정해야 한다.

Connector/Field Provider를 **플러그인**으로 선택할 기준은 기존 module 기능에 hook/field adapter만 추가하고 완전한 page layout이 필요 없을 때다. 독립 설정 화면·domain records·full layouts·자체 API가 필요하면 module이다. 근거는 `docs/extension/README.md:21-22`, `docs/extension/plugin-development.md:1044-1052`.

## 7. 공개 폼 접근 모델

| 접근 | Backend | Frontend | 위험/정책 |
|---|---|---|---|
| 익명 | `optional.sanctum`, strong rate limit, idempotency; guest permission 적용 여부 결정 | `routes/user.json`, `auth_required:false` | abuse, PII consent, guest file ownership |
| 인증 사용자 | `auth:sanctum`, user permission/Scope, optional IDV | user route with `auth_required:true` | owner link, token-only auth |
| 관리자 내부 | `auth:sanctum`, admin permission | `routes/admin.json` + admin layout | operator impersonation/audit, source=`admin` |

세 모드는 별도 endpoint/action이어도 동일한 published FormVersion validation pipeline을 사용해야 한다. FormVersion에 access policy reference를 두는 것은 후보이나 core IDV/permission identifier 형식은 실제 선언 API와 함께 Phase 6 이전 검증한다.

## 8. 권장 아키텍처

- Module: 독립 `glitter-form_builder`.
- Definition: Form identity + immutable published FormVersion snapshot hybrid.
- Submission: atomic JSON raw/normalized snapshot + allowlisted typed field index hybrid.
- Render: independent Form Schema → supported G7 Layout projection.
- Field: small built-in set + module-owned Registry contract; external Provider는 이후.
- Integration: Handler Registry + durable attempt + queue execution; hooks for observation, declarative notification for delivery.
- Public UI: module user routes/layouts; authenticated/admin variants share pipeline.
- Files: initial core attachment reuse feasibility를 Phase 11에서 검증하고 file field는 그 전까지 publish-disabled 또는 experimental.
- First connector: `glitter-crm` public boundary가 실제 생기면 CRM; 없거나 internal dependency가 필요하면 generic webhook/notification validation.

## 9. 유지보수성과 마켓 배포성 원칙

- 본체 manifest dependencies에 CRM/Reservation/Board/Ecommerce를 넣지 않는다.
- handler/field contract는 semantic contract version과 capability introspection을 가진다.
- unknown provider binding은 form publish 차단 또는 binding disable로 명확히 처리하고 submission 원본 수집 정책은 form setting으로 정한다.
- Connector는 본체의 public DTO만 import하고 본체는 Connector class를 import하지 않는다.
- version behavior 변경 시 `module.json`, `composer.json`, `CHANGELOG.md` 일관성을 lifecycle 전에 검사한다 (`docs/extension/module-basics.md:729-754`).
- layout/translation/config 변경은 template/route/extension cache invalidation을 lifecycle로 확인한다.

## 10. 아직 확정해서는 안 되는 항목

- 실제 table/column/index/FK명, JSON/native JSON/mediumText 타입, encryption 방식
- UUID version과 public route binding, bigint 병용 범위
- Composer package name `glitter/formbuilder` 대 G7 관례 `modules/glitter-form_builder`
- exact Form Schema JSON shape와 field type key 목록
- handler PHP interface namespace/method signature, sync timeout, retry counts/backoff
- queue driver, cache driver, Redis/Reverb/domain/ports
- public slug/endpoint URL, rate limit/CAPTCHA
- file upload endpoint, temporary ownership, Media API 존재
- Layout component names/props/actions beyond verified engine use
- retention duration, legal hold, consent evidence 규정
- indexable field default와 export formats

## 11. Phase 0-B 선택 결과

앞 절의 비교는 근거 기록으로 유지한다. 아래 결과가 충돌하는 이전 “후보/미정” 표현을 **superseded**한다.

| 축 | 선택 | 제외/유보 | Decision |
|---|---|---|---|
| Submission | raw JSON snapshot + typed index hybrid | EAV/JSON-only 제외 | FB-DEC-001 |
| Form definition | editable draft + immutable version JSON snapshot hybrid | Layout 직접 저장 제외 | FB-DEC-001/010 |
| rendering | 독립 Form Schema → Layout/renderer 변환 | JSON Layout을 Form Schema로 사용 금지 | FB-DEC-010 |
| integration | Handler Registry + durable attempt + execution layer | Hook-only 제외, Connector 별도 package | FB-DEC-007/009 |
| field extension | Registry, built-in + namespaced external provider | fixed Enum-only 제외 | FB-DEC-008 |
| access | 세 access mode, guest opt-in | 기본 익명 공개 제외 | FB-DEC-004 |

DB 내부 JSON query는 공개 계약이 아니다. MySQL native JSON과 MariaDB `LONGTEXT` alias 차이 때문에 JSON path/generated/expression index는 선택 최적화다. raw JSON/FULLTEXT 대신 typed index를 쓴다. migration의 `json` 대 `longText + JSON_VALID` 물리 선택은 양 DB DDL test 뒤 확정한다.

package는 `modules/glitter-form_builder`다(FB-DEC-003). 본체에는 CRM/예약/게시판/ecommerce Handler를 넣지 않는다. Connector는 본체 DTO/Registry contract만 의존한다.

계속 미확정인 수치는 payload bytes, decimal precision/scale, Handler timeout/backoff/max attempts, secret service, purge schedule이다. `schema-contract-draft.md`와 `processing-contract-draft.md`를 따른다.
