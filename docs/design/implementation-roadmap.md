# Glitter Form Builder 구현 로드맵

> 각 Phase는 별도 Codex CLI 작업 단위다. 모든 구현 prompt에는 한국어 follow-up, Docker 금지, `npm run dev` 금지, `php`/직접 `composer` 금지, `/usr/local/bin/php83` 사용, `_bundled`만 편집, i18n Layout 준수, lifecycle 적용, tests와 git diff 보고를 명시한다.
>
> **현재 릴리즈 상태:** Glitter Form Builder `0.2.0`의 품질 게이트와 Production Release가 완료되었다. 아래 각 Phase의 날짜별 보류·위험 기록은 해당 구현 시점의 이력이며, 현재 릴리즈 상태는 Phase 13의 완료 판정을 따른다.

## 공통 품질 규칙

- bug fix는 failing regression test부터 작성한다.
- backend change는 실제 schema 사전 검사 후 FormRequest → Service → Repository를 지킨다.
- frontend change 중에는 존재를 확인한 `npm run build:watch`를 유지하고 최종 `npm run build`; built asset freshness와 native Node binary/ACL을 검증한다.
- bundled 변경 후 version/CHANGELOG를 검토하고 module update/install/activate → active output/cache/route를 검증한다.
- production route에서 debug/demo surface가 없어야 한다.
- 각 Phase 종료 시 git status/diff, modified files, failed/skipped/unverified를 보고한다.

## Phase 0: 조사와 아키텍처 결정

- 목표: 본 문서 5종 검토와 decision record 확정.
- 포함: DB/runtime config read-only 조사, access/retention/package naming/Registry contract decision.
- 제외: code, manifest, migration, route, layout.
- 예상 파일: `docs/design/*.md`, 향후 `decision-log.md` 후보.
- 선행: stakeholder가 `foundation-analysis.md:11` 질문 답변.
- Contract: 없음; vocabulary와 invariants만.
- 테스트: 문서 link/path/용어 consistency.
- lifecycle: 없음(문서-only).
- 완료: storage/definition/render/integration/access의 승인된 선택과 미결정 목록.
- 다음 위험: 실제 DB/queue/file capability가 권고를 변경할 수 있음.

## Phase 1: Module Skeleton 및 lifecycle

**구현 상태: bundled source 및 production apply lifecycle 완료, testing DB uninstall 검증 보류 (2026-07-31).**

- 목표: 비즈니스 기능 없는 독립 module 설치/활성/비활성/update/remove.
- 포함: manifest, license, changelog, composer metadata, `Module`, base provider, empty permissions/menu/languages, test bootstrap.
- 제외: domain table/API/UI.
- 실제 파일: `module.json`, `module.php`, `composer.json`, `LICENSE`, `CHANGELOG.md`, `src/Providers/FormBuilderServiceProvider.php`, `src/lang/{ko,en}/module.php`, `resources/lang/{ko,en}.json`, `tests/ModuleTestCase.php`, `tests/Feature/{ModuleSkeletonTest,ModuleLifecycleTest}.php`, `tests/scenarios/module-skeleton-lifecycle.yaml`.
- 선행: package name/namespace/g7 constraint 결정.
- Contract: module identifier와 lifecycle only.
- 테스트: DB 비부팅 격리 실행으로 manifest/composer/namespace/provider/번역 parity/금지 surface 49 assertions 통과. production apply loop의 install/activate/deactivate/forced bundled update/reactivate 통과. 표준 test bootstrap은 `.env.testing` 부재를 안전 차단했으며 uninstall cleanup을 포함한 격리 lifecycle test는 전용 testing DB 이름을 확인하지 못해 실행하지 않음.
- lifecycle: `/usr/local/bin/php83 artisan module:install|update|activate ...`의 실제 존재를 먼저 확인해 적절한 명령 실행; deactivate/reactivate/uninstall data policy test.
- 완료: bundled Skeleton/class autoload와 active output 동기화, 0 route/permission/menu/layout 상태 및 install/activate/deactivate/update/reactivate 검증 완료. uninstall cleanup의 automated isolation 검증만 명시적 `.env.testing` 구성 후 남아 있음.
- 위험: 현재 `phpunit.xml`은 DB 이름을 `.env.testing`에 위임하지만 파일이 없어 lifecycle write 검증과 Phase 2 진입을 차단한다. Phase 2 전 전용 DB 구성과 lifecycle cleanup 통과가 필수다.

## Phase 2: Form과 Version 데이터 모델

**구현 상태: bundled source 구현 완료, migration/DB-write/lifecycle apply 검증 보류 (2026-07-31).**

- 목표: Form identity, immutable FormVersion, publish/clone/archive.
- 포함: schema inspection, migrations, enums, models, repository interfaces, DTOs, services.
- 제외: field runtime validation/submission.
- 실제 파일: `database/migrations/*form_forms*`, `*form_versions*`, `src/Enums/{FormStatus,FormAccessMode,RetentionMode}.php`, `src/Models/{Form,FormVersion}.php`, `src/Repositories/{Contracts,}/*`, `src/Services/FormService.php`, admin FormRequests/Controller/Resources/routes, tests. 공개 DTO는 Phase 3 전 생성하지 않음.
- 선행: DB decisions/UUID choice.
- API/Contract: internal `FormRepositoryInterface`, publish DTO; public API 없음.
- 테스트: DB 비부팅 model/migration syntax 16 assertions와 전체 PHP lint 통과. unique revision, publish transaction, CRUD API는 guarded DB-write test로 작성했으나 `.env.testing` 부재로 실행하지 않음.
- lifecycle: 이번 작업은 migration 실행 금지 조건 때문에 update/install을 실행하지 않음. active output은 Phase 1 상태이며 Phase 2 source of truth는 bundled에만 존재한다.
- 완료: PHP 계층과 migration 정의는 완료. 실제 schema metadata, unique/FK, transaction/rollback, permission/menu sync는 전용 testing DB 적용 전 미완료다.
- 위험: migration 미실행 상태이므로 운영 route/permission/menu를 노출하지 않는다. Phase 3 진입 전 격리 DB migration 및 lifecycle 검증이 필요하다.

## Phase 3: Field Definition 및 Schema 검증

**구현 상태: bundled source의 experimental Field Contract/Registry 및 built-in provider 구현 완료, active output 적용은 의도적으로 보류 (2026-07-31).**

- 목표: built-in field contract와 fail-closed schema validation.
- 포함: 동결된 11개 built-in type, immutable runtime value object, Registry, provider collision/version rules, definition validation, normalization/serialization/display/export/index projection.
- 제외: external provider package, complex/file fields.
- 실제 파일: `src/Contracts/Fields/FieldProvider.php`, `src/Fields/FieldTypeRegistry.php`, `src/Fields/ValueObjects/*`, `src/Fields/BuiltIn/*`, `src/Exceptions/Fields/*`, `src/lang/{ko,en}/fields.php`, DB 비의존 Unit tests.
- 선행: FormVersion storage.
- Contract: experimental `FieldProvider` `1.0`(major.minor 문자열), JSON-safe validation/renderer descriptor, immutable field runtime value objects. [field-provider.md](../contracts/field-provider.md) 참조.
- 테스트: Registry/11종 등록/conformance/핵심 field behavior/readonly·import boundary/isolated ServiceProvider boot 26 tests, 202 assertions 통과. 전체 신규 PHP lint와 ko/en parity도 DB 없이 검증한다.
- lifecycle: `register()` singleton → module `boot()` built-in 명시 등록 → application `booted()` seal. 이번 단계 명시적 금지에 따라 module update/install을 실행하지 않아 active output은 Phase 1 상태다.
- 완료: duplicate/unknown/incompatible major/late registration fail-fast, 11종 normalized contract와 안전한 metadata/descriptor 제공. FormVersion 전체 canonical schema validator는 Phase 4 publish API 결합 시 구현한다.
- 위험: renderer는 실제 frontend가 없어 `available=false` capability descriptor만 제공한다. 외부 provider integration, schema canonical hash, Submission persistence/index DB mapping은 미검증이다.

## Phase 4: 관리자 Form CRUD API

**구현 상태: Phase 2에 선행 작성된 관리자 CRUD는 그대로 두고, Phase 4 범위를 Canonical Form Schema/Publish Validation gate로 구현 완료. DB integration과 active apply는 보류 (2026-07-31).**

- 목표: canonical schema parsing, draft structural validation, Provider-aware publish validation과 안정적인 422 issue contract.
- 포함: `FormSchemaParser`, `FormSchemaValidator`, `PublishSchemaValidator`, immutable issue/result, security limits, translated response formatter, `FormService::publish()`의 transaction 이전 validation.
- 제외: Designer/layout/renderer, Submission, schema migration/up-conversion, 실제 DB publish.
- 실제 파일: `src/Schema/*`, `src/Exceptions/Schema/FormSchemaValidationException.php`, `src/lang/{ko,en}/schema.php`, `tests/Unit/Schema/*`; 기존 `FormService`와 `FormController` 최소 갱신.
- 선행: Phase 3 experimental `FieldProvider`와 sealed `FieldTypeRegistry`.
- Contract: frozen camelCase envelope `schemaVersion`/`fields`; dot path issue `{code,path,message_key,parameters,severity}`; G7 error envelope 안의 `{errors,warnings}` payload.
- 테스트: DB-free parser/limits/draft-vs-publish/provider/renderer warning/redaction/fake repository/service/422/structure tests 포함 Phase 3+4 40 tests, 266 assertions 통과.
- lifecycle: 이번 단계 금지 조건에 따라 module update/install을 실행하지 않았다. active output은 Phase 1 상태다.
- 완료: unresolved/incompatible/invalid definition은 error, renderer unavailable은 warning이며 warning만으로 invalid가 되지 않는다. validation error는 transaction/repository 호출 전 중단된다.
- 위험: Phase 2 persistence/migration/auth/permission 실제 검증, 성공 publish warning 전달, canonical JSON hash, active route response 실측은 deferred다.

## Phase 5: 관리자 Form Designer 최소 UI

- 목표: drag-and-drop 없는 ordered structured editor.
- 포함: admin route/layout, field add/edit/remove/reorder, localized labels, publish errors.
- 제외: canvas/WYSIWYG, advanced automation/calculation/signature/payment.
- 예상 파일: `resources/routes/admin.json`, `resources/layouts/admin/*.json`, `resources/lang/*`, layout tests; custom asset only if verified necessary.
- 선행: Phase 2 전용 DB persistence 검증, Phase 4 issue response의 active API 실측, verified component props. renderer unavailable warning을 Designer에서 표시할 UX를 결정한다.
- Contract: draft schema API; existing G7 actions/bindings only.
- 테스트: `createLayoutTest`, mock API, i18n key coverage, ordering and publish error.
- lifecycle: version bump/update/activate, template/layout/translation cache invalidation, build only if TS/CSS exists.
- 완료: built/active UI edits ordered schema with no hardcoded Layout sentences.
- 위험: Layout engine insufficient for dynamic nested editor.

## Phase 6: 공개 Form Schema 및 렌더링

**구현 상태: bundled DB-safe read/render foundation 완료, DB integration·active lifecycle·browser runtime 검증 보류 (2026-07-31).**

- 목표: published schema read and safe G7 Layout projection.
- 포함: `routes/user.json`, user layout, public schema Resource, adapter/renderer capability check, anonymous/auth view policies.
- 제외: submission POST.
- 실제 파일: `resources/routes/user.json`, `resources/layouts/user/user_form_builder_form_show.json`, `src/Rendering/*`, `src/Services/PublicFormService.php`, public repository/controller/resource, DB-free tests/docs.
- 선행: published schema, supported component/prop inventory.
- Contract: versioned public Form Schema response; generated Layout projection is non-SSoT.
- 테스트: fake repository access-mode matrix, missing/unpublished 404 boundary, unsupported renderer fail-closed, hidden/default redaction, route/layout/import/i18n static contract. DB-free Phase 3~6 unit suite 62 tests, 363 assertions 통과.
- lifecycle: 금지 조건에 따라 user route/layout registration, cache invalidation, active output 반영을 실행하지 않았다.
- 완료: bundled source에서 existing user template source를 건드리지 않고 versioned public schema와 allowlisted G7 Layout projection을 제공한다. active module/browser render 완료로 간주하지 않는다.
- 위험: Phase 2 migration 미실행으로 실제 published row read가 미검증이며 cross-template component portability와 API envelope/Layout binding은 active runtime에서 확인해야 한다.
- Phase 7 진입 조건: 전용 testing DB에서 Phase 2 publish→Phase 6 public read 통합 검증, active user route/layout 합성·cache invalidation·browser render 검증, payload/idempotency/rate-limit 수치 결정 및 Submission schema 재검사.
- 0.1.1 릴리즈 보강: 공개 projection allowlist가 없는 provider의 renderer warning을 publish error로 승격하여 publish 성공 후 공개 조회 실패를 차단한다. nullable description은 빈 locale map으로 투영하고 malformed snapshot은 로그가 남는 internal error로 분리한다.

## Phase 7: Submission 수집과 서버 검증

**상태: Completed (0.3.0). Submission 접수·저장과 관리자 read-only foundation까지 구현되었으며 Handler 실행은 포함하지 않는다.**

- 목표: accepted Submission atomic persistence.
- 포함: published schema Provider normalization, idempotency/conflict, access modes, canonical normalized snapshot, typed indexes, abuse throttling, public input UI, 관리자 redacted list/detail foundation, after-commit event.
- 제외: handler execution/files.
- 실제 파일: submission migrations/models/DTO/repository/services/requests/controllers/resources/routes, public handler/Layout, admin Layout, tests와 submission 계약 문서.
- 선행: DB schema reinspection, Phase 3/6.
- Contract: UUID/status receipt, `Idempotency-Key` header, canonical SHA-256, sensitive index prohibition.
- 테스트: client bypass, schema race, duplicate/replay, transaction rollback, anonymous/auth/admin access, privacy index exclusion.
- lifecycle: migrate/update/active API validation.
- 완료: accepted row는 immutable version을 참조하고 Provider가 정규화한 값만 저장한다. Handler 없이 `received`를 유지한다.
- 위험: production-like DB/browser 통합 검증과 자동 purge는 별도 운영 검증/후속 Phase 대상이다.

## Phase 8: 관리자 Submission 조회

- 목표: permission-safe list/detail/filter/export preview.
- 포함: admin API/layout, redacted presentation, allowed index filters, abilities.
- 제외: bulk export job and analytics.
- 예상 파일: admin submission resources/controllers/requests/layouts/lang/tests.
- 선행: Phase 7.
- Contract: redaction-aware Resource; no raw secret by default.
- 테스트: permissions/scope, N+1, pagination, sensitive display, CSV formula injection if export included.
- lifecycle: menu/permission/layout sync and active render.
- 완료: operator can inspect status/history without accidental raw disclosure.
- 위험: field-level access policy not yet granular.

## Phase 9: Submission Handler Registry

- 목표: typed handler discovery and durable sync/async attempts.
- 포함: registry/contracts/bindings/attempt model, queue job, retries, idempotency, partial failure/reprocess.
- 제외: target-specific connector.
- 예상 파일: contracts/registry/models/migrations/services/jobs/commands/admin APIs/tests.
- 선행: actual queue config/schema inspection.
- Contract: versioned Handler interface/DTO/result/capabilities.
- 테스트: collision, missing handler, double delivery, worker crash at each boundary, retryable/non-retryable, stop/continue, stale running reclaim.
- lifecycle: worker restart behavior after module update, active provider discovery.
- 완료: no lost processing intent between submission commit and execution; complete attempt history.
- 위험: exactly-once impossible across external systems.

## Phase 10: 알림·활동 로그·권한·검색 연계

- 목표: G7 core integrations without payload leakage.
- 포함: declarative notification definitions/extract_data, activity listener, permission refinements, metadata/index search opt-in, CacheInterface.
- 제외: custom notification channel.
- 예상 파일: `module.php`, listeners, language activity log, searchable projections, tests.
- 선행: lifecycle hooks stable.
- Contract: documented hook names and sanitized event DTO.
- 테스트: hook→GenericNotification, recipient locale, queued context, activity fields, cache invalidation, sensitive search exclusion.
- lifecycle: definitions/permissions sync and cache clearing.
- 완료: official G7 surfaces work after update/activate and uninstall cleanup.
- 위험: dynamic notification hooks require definition/cache timing.

## Phase 11: 파일 입력과 개인정보 정책

- 목표: secure file lifecycle and enforceable retention/redaction.
- 포함: Attachment/Storage feasibility, temporary→attached transaction, access/download, orphan cleanup, mime/size policies, retention scheduler, erase/redact/legal hold.
- 제외: full media library, image editor.
- 예상 파일: FileReference candidate, services/requests/jobs/commands/config/settings/layout tests.
- 선행: actual Attachment schema/API and storage config inspection.
- Contract: field file value/reference, delete/redact result.
- 테스트: guest ownership, unauthorized download, executable spoof, orphan/retry, deletion propagation, legal hold.
- lifecycle: schedule declaration, storage/ACL inheritance verification without ACL modification.
- 완료: no public temp file leakage and retention actions are auditable.
- 위험: antivirus service may be unavailable; file field may remain disabled.

## Phase 12: 첫 Connector 검증

- 목표: public contract가 real integration을 support하는지 검증.
- 포함: `glitter-crm`이 존재하고 public boundary면 separate CRM Connector; 아니면 generic webhook 또는 notification-based connector.
- 제외: connector logic in core module.
- 예상 파일: 별도 `_bundled` module/plugin package와 contract conformance tests; 본체에는 docs/test fixture only.
- 선행: Phase 9 contract stable, target inspection.
- Contract: Handler v1 conformance, mapping/config validation, idempotent result.
- 테스트: target absent/inactive/version mismatch, timeout/retry, duplicate, mapping failure, uninstall.
- lifecycle: dependency order install/activate/deactivate/uninstall.
- 완료: connector can be removed without Form Builder failure or submission loss.
- 위험: target lacks public service boundary; webhook security/SSRF.

## Phase 13: 품질 게이트와 Production Release

**상태: Completed. 0.2.0 품질 게이트와 Production Release가 완료되었다.**

- 목표: production-safe 0.2.0 정식 릴리즈.
- 포함: API docs, changelog/license, manifest/version/dependency, audit/security/performance/accessibility/i18n, upgrade/uninstall/data retention docs.
- 제외: deferred advanced features.
- 예상 파일: docs, release metadata, tests/scenarios, marketplace assets (별도 승인 시).
- 선행: all prior exit criteria.
- Contract: supported field/handler schema versions and compatibility policy.
- 테스트: full PHPUnit/Vitest/Layout/scenario/build, fresh install, upgrade, deactivate/reactivate, uninstall keep/delete data, route exposure/demo leakage.
- lifecycle: clean lifecycle matrix and active output/build freshness.
- 완료: no failed/skipped critical checks; known limitations documented. `module.json`, `composer.json`, `package.json`, `CHANGELOG.md` 및 릴리즈 문서의 버전과 상태가 0.2.0 Production Release로 일치한다.
- 위험: production-scale load/retention law needs environment-specific validation.

## Phase 0-B 결정 반영 Addendum

### Phase 0-B 완료 기준

- FB-DEC-001~012와 runtime 기준선, schema/processing contract draft가 존재한다.
- 구현/lifecycle/DB write/build 없이 문서 용어와 상태가 일치한다.
- 미확정 수치는 Phase 2/3/9/11 gate로 명시적으로 넘긴다.

### Phase 1 완료 조건 구체화

- metadata: `glitter-form_builder`, `Glitter Form Builder`, `Glitter.kr`, `MIT`, `0.1.0`.
- Composer package: `modules/glitter-form_builder`(FB-DEC-003).
- FB-DEC-012의 11개 admin permission을 category/action manifest 관례로 검증한다.
- Form route/controller와 Registry 공개 class를 skeleton 단계에서 성급히 만들지 않는다.
- 공식 install/activate/deactivate/update/uninstall flow를 test 환경에서 검증하고 active directory를 직접 편집하지 않는다.
- permission/menu/lang/layout/extension cache의 생성·회수, keep/delete data uninstall 의미, manifest version/changelog update 감지를 검증한다.
- namespace resolver의 `formbuilder` StudlyCase 결과를 실제 autoload test 전 확정하지 않는다.

### Phase 2 완료 조건 구체화

- 선행: FB-DEC-001/002/004/005/010/011.
- bigint PK + UUIDv7, Form별 revision, immutable FormVersion, draft/access/retention default, publish 후 slug immutable을 구현·테스트한다.
- Submission/typed index/attempt/FileReference와 JSON path/generated/expression/FULLTEXT는 제외한다.
- MySQL 8.0+/MariaDB 10.3+ DDL, JSON 물리 type, FK 정책, uppercase object/assoc metadata normalization을 test한다.
- concurrent revision publish, snapshot update 금지, UUID route key와 bigint PK 분리, hard delete의 Submission 보존 경계를 완료 조건으로 둔다.

### 후속 Phase 계약

- Phase 3: `schema-contract-draft.md`, duplicate fail-fast, unresolved draft round-trip, provider resolution, sensitive→non-indexable, unknown major rejection.
- Phase 7: request idempotency, `received`, raw+typed index 원자 저장, Handler transaction 외부, access별 middleware와 named limiter.
- Phase 9: `processing-contract-draft.md`; `auto`와 접수 rollback형 critical 제외. timeout/backoff/max attempts/secret service addendum 선행.
- Phase 11: Page `temp_key` 사례는 후보일 뿐이다. 익명 소유권, claim, orphan cleanup, MIME, malware hook, private download가 검증되기 전 `file` type을 노출하지 않는다.

## Phase 0.5 Architecture Freeze 반영

권위: [contracts README](../contracts/README.md), FB-DEC-013.

### Phase 1 — internal skeleton

- manifest/module class/ServiceProvider/lifecycle/autoload만 담당한다.
- Field Provider, Submission Handler, Registry, Event, 공개 DTO의 실제 PHP class/interface를 **만들지 않는다**.
- 빈 interface를 미리 공개해 signature를 고정하지 않는다.
- 안정성은 `internal`.

### Phase 2 — experimental domain

- Form Aggregate, mutable draft working copy, immutable FormVersion, status Enum, persistence serialization을 구현한다.
- current published 1개, monotonic revision, archive flow, Submission 존재 시 delete guard를 검증한다.
- Field Provider/Handler public interface는 만들지 않는다.
- Form Schema validation을 Phase 3보다 앞당겨 임의 구현하지 않는다.

### Phase 3 — experimental Field Contract

- [field-provider.md](../contracts/field-provider.md), [registry.md](../contracts/registry.md), [dto.md](../contracts/dto.md)의 첫 실제 PHP API를 구현한다.
- built-in Provider와 conformance test로 collision/version/missing/renderer mismatch를 검증한다.
- 실제 namespace/method signature를 이 단계 tests와 함께 정한다.
- Handler Contract는 Phase 9까지 만들지 않는다.

### Phase 7과 Phase 9

- Phase 7: Submission Aggregate, raw/typed index, request idempotency, public Submission event DTO.
- Phase 9: Handler/Registry/Binding/Attempt/executor, retry/idempotency tuple, secret integration.
- Attempt에 `retry_scheduled` 상태를 구현하지 않는다.
- simple conformance Handler 뒤 Webhook Connector를 검토한다.

### 안정성 승격

Phase 1 `internal` → Phase 2/3/7/9 `experimental` → 첫 독립 Connector lifecycle/queue/security conformance 후 `stable` 후보. breaking change는 해당 contract major 증가가 필요하다.

## Phase 5 bundled 구현 기록 (2026-07-31)

- 상태: bundled source 구현 완료, active output 적용과 DB runtime 검증은 보류.
- 구현: 관리자 index/create/edit Layout, canonical `schemaVersion`/ordered fields state, Registry 기반 palette API, DB-write 없는 draft/publish-preview validation API, field add/delete/move/duplicate, key/label inspector foundation, issue mapping, warning-confirm publish flow, scoped CSS와 ko/en i18n.
- 검증: Phase 5 DB-free PHPUnit 4 tests/11 assertions, 신규 PHP syntax, JSON parsing, translation key parity. frontend Vitest/typecheck는 root `node_modules/.bin` 부재로 실행하지 못했다.
- persistence: Phase 2 PUT/publish API를 호출하는 frontend 경계만 추가했으며 migration과 실제 API write는 실행하지 않았다.
- Phase 6 진입 조건: 안전한 testing DB에서 create/update/publish 통합 검증, 모듈 asset build runner 확보, Layout renderer test, inspector의 provider별 모든 config/options 편집 완성, runtime에서 module handler asset load 확인.

## Phase 5.5 frontend verification gate (2026-07-31)

- `npm ci --no-audit --no-fund`로 root lockfile 그대로 257 packages를 복구했고 package/lock/public build checksum은 불변이다.
- scoped Vitest: 4 files, 33 tests 통과. canonical JSON-safe conversion, field/key operations, issue invalidation, create/publish ordering, permission/duplicate-click guard, state isolation, handler idempotency, Layout/component/i18n/CSS 계약을 포함한다.
- DB-free PHPUnit regression: 47 tests, 304 assertions 통과.
- 임시 `/tmp` Vite library build: IIFE 7.58 kB, CSS 0.61 kB, manifest 생성 및 handler/API 문자열 포함 확인. `public/build`은 사용하지 않았다.
- TypeScript `tsc --noEmit`: root lockfile에 `typescript` package/binary가 없어 실행 불가. dependency 변경 금지에 따라 추가하지 않았다.
- 공식 `module:build`는 모듈-local `package.json` 부재 시 중단하며, 실행 시 내부 `npm install`과 cache-version write를 수행하므로 이번 gate에서 실행하지 않았다.
- Phase 6 진입은 source compile/test 관점에서 가능하지만 active runtime 진입 전 module-local build packaging, `tsc`, 실제 Layout renderer, testing DB CRUD/publish 검증이 필요하다.

## Phase 5.6 module-local packaging/readiness gate (2026-07-31)

- 공식 계약 확인: `module:build`는 bundled module의 `package.json`과 `assets.*.entry/output`을 요구하고 module-local `npm install`/`npm run build` 후 `components.json` 및 extension cache version을 쓴다. runtime은 active module의 `dist` output만 asset API/병합 bundle로 읽는다.
- 구현: private module-local package/lockfile, standalone production/test TypeScript config, Vite IIFE build, 실제 G7 runtime 최소 declaration을 추가했다. root package와 root TypeScript ownership은 변경하지 않았다.
- 검증: `tsc --noEmit` production/test 통과, scoped Vitest 4 files/33 tests 통과, DB-free PHPUnit 48 tests/308 assertions 통과, production build가 `dist/js/module.iife.js`와 `dist/css/module.css`를 생성했다.
- runtime readiness: `GlitterFormBuilderModule` IIFE와 namespaced handler registration, bounded retry, module-scoped CSS, module.json output 일치를 정적으로 확인했다. React/G7 runtime package는 번들하지 않고 `window.G7Core` boundary만 사용한다.
- 보류: 공식 `module:build`는 cache-version write를 수행하므로 실행하지 않았고 active output/운영 DB/public build도 변경하지 않았다. testing clone에서 lifecycle 적용 후 실제 Layout render와 CRUD/publish browser smoke가 필요하다.
- Phase 6 진입 판정: source packaging/typecheck/build gate는 통과. Phase 6 구현은 가능하나 active runtime 통합 완료로 간주하지 않는다.
