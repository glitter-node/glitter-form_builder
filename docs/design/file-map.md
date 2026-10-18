# Glitter Form Builder 예상 파일 맵

> Phase 1 Skeleton, Phase 2 bundled domain/API, Phase 3 experimental Field Contract가 실제 생성되었다. 이후 구조는 설계 후보이며 구현 확정으로 간주하면 안 된다.

## 1. 표기

- **실제**: 현재 bundled source에 생성됨.
- **근거 있음**: G7 module/runtime에서 동일 책임·위치가 확인된 예상 파일.
- **후보**: Form Builder 설계를 위해 제안했으나 exact name/shape 미확정.

## 2. 예상 디렉토리

```text
modules/_bundled/glitter-form_builder/
├── docs/
│   ├── design/                         # 실제: Phase 0
│   ├── contracts/                      # 실제: Phase 0.5 architecture freeze
│   └── api/                            # 근거 있음: Phase 4+
├── module.json                         # 실제: Phase 1
├── module.php                          # 실제: Phase 1
├── composer.json                       # 실제: Phase 1
├── LICENSE                             # 실제: Phase 1
├── CHANGELOG.md                        # 실제: Phase 1
├── config/
│   └── settings/defaults.json          # 근거 있음, 필요성은 후보
├── database/
│   ├── migrations/                     # 근거 있음: Phase 2+
│   ├── factories/                      # 근거 있음: tests
│   └── seeders/                        # 필요성 후보; declarative entities는 module.php가 SSoT
├── resources/
│   ├── routes/admin.json               # 근거 있음: Phase 5
│   ├── routes/user.json                # 근거 있음: Phase 6
│   ├── layouts/admin/*.json            # 근거 있음: Phase 5+
│   ├── layouts/user/*.json             # 근거 있음: Phase 6
│   ├── lang/{ko,en}.json               # 실제: Phase 1 최소 metadata 번역
│   └── js/                             # 후보: Layout만으로 부족할 때
├── src/
│   ├── Providers/                      # 실제: FormBuilderServiceProvider.php
│   ├── Enums/
│   ├── Fields/ValueObjects/            # 실제: Phase 3 공개 immutable runtime 값
│   ├── Models/
│   ├── Repositories/Contracts/
│   ├── Repositories/
│   ├── Services/
│   ├── Contracts/Fields/
│   ├── Contracts/Handlers/
│   ├── Fields/FieldTypeRegistry.php    # 실제: Phase 3
│   ├── Fields/BuiltIn/                  # 실제: Phase 3 built-in providers
│   ├── Exceptions/Fields/               # 실제: Phase 3 fail-closed 오류
│   ├── Schema/                           # 실제: Phase 4 parser/validator/result/limits
│   ├── Exceptions/Schema/               # 실제: Phase 4 publish domain error
│   ├── Jobs/
│   ├── Listeners/
│   ├── Rules/
│   ├── Http/Controllers/{Admin,Public}/
│   ├── Http/Requests/{Admin,Public}/
│   ├── Http/Resources/
│   ├── routes/api.php
│   └── lang/{ko,en}/module.php         # 실제: Phase 1
├── tests/
│   ├── ModuleTestCase.php
│   ├── Unit/
│   ├── Feature/
│   └── scenarios/
└── upgrades/                            # schema/data upgrade가 생길 때
```

## 3. 실제 생성 파일

| 파일 | 책임 | 단계 |
|---|---|---|
| `docs/design/foundation-analysis.md` | 조사 근거, 제약, 재사용/불확실점 | Phase 0 |
| `docs/design/architecture-options.md` | 대안 비교와 권고 | Phase 0 |
| `docs/design/domain-model-draft.md` | aggregate/invariant/flow 초안 | Phase 0 |
| `docs/design/implementation-roadmap.md` | Phase별 구현·검증 gate | Phase 0 |
| `docs/design/file-map.md` | source/connector 경계와 예상 파일 | Phase 0 |
| `module.json` | G7 manifest metadata와 빈 dependencies | Phase 1 |
| `module.php` | `AbstractModule` 최소 entry point | Phase 1 |
| `composer.json` | `Modules\\Glitter\\FormBuilder\\` PSR-4 metadata | Phase 1 |
| `src/Providers/FormBuilderServiceProvider.php` | DB 접근 없는 최소 provider와 번역 bootstrap | Phase 1 |
| `src/lang/{ko,en}/module.php` | backend module metadata 번역 | Phase 1 |
| `resources/lang/{ko,en}.json` | frontend translation foundation | Phase 1 |
| `LICENSE`, `CHANGELOG.md` | MIT license와 0.1.0 실제 변경 기록 | Phase 1 |
| `tests/ModuleTestCase.php` | 전용 testing DB fail-safe guard | Phase 1 |
| `tests/Feature/ModuleSkeletonTest.php` | metadata/namespace/parity/금지 surface 검증 | Phase 1 |
| `tests/Feature/ModuleLifecycleTest.php` | 공식 lifecycle와 cleanup 검증(전용 DB 필요) | Phase 1 |
| `tests/scenarios/module-skeleton-lifecycle.yaml` | Phase 1 lifecycle scenario | Phase 1 |
| `database/migrations/*form_forms*.php` | Form mutable working copy, UUID/public slug, access/retention policy | Phase 2 |
| `database/migrations/*form_versions*.php` | immutable JSON snapshot과 Form별 unique revision | Phase 2 |
| `src/Enums/{FormStatus,FormAccessMode,RetentionMode}.php` | persisted backed enum values | Phase 2 |
| `src/Models/{Form,FormVersion}.php` | Form root와 immutable published snapshot | Phase 2 |
| `src/Repositories/Contracts/Form*RepositoryInterface.php` | Form/Version persistence ports | Phase 2 |
| `src/Repositories/Form*Repository.php` | Eloquent persistence adapters | Phase 2 |
| `src/Services/FormService.php` | draft CRUD, archive, transactional revision publish | Phase 2 |
| `src/Http/Requests/Admin/*FormRequest.php` | admin request validation | Phase 2 |
| `src/Http/Controllers/Admin/FormController.php` | admin CRUD/publish API adapter | Phase 2 |
| `src/Http/Resources/Form*.php` | BaseApiResource/BaseApiCollection envelope data와 abilities | Phase 2 |
| `src/routes/api.php` | module-prefixed admin-only Form routes | Phase 2 |
| `tests/{Unit,Feature}/*Form*Test.php` | static tests와 guarded DB integration tests | Phase 2 |
| `src/Contracts/Fields/FieldProvider.php` | experimental 공개 field end-to-end 계약 | Phase 3 |
| `src/Fields/ValueObjects/*` | ORM/Request 비노출 immutable field runtime 값 | Phase 3 |
| `src/Fields/FieldTypeRegistry.php` | 명시 등록, version 검증, duplicate fail-fast, booted seal | Phase 3 |
| `src/Fields/BuiltIn/*` | 11개 built-in normalization/descriptor/format/index 구현 | Phase 3 |
| `src/Exceptions/Fields/*` | raw 값을 포함하지 않는 registry/definition/normalization 진단 | Phase 3 |
| `src/lang/{ko,en}/fields.php` | field metadata와 validation 사용자 문구 | Phase 3 |
| `tests/Unit/{FieldTypeRegistry,BuiltInFieldRegistry,FieldProviderConformance,BuiltInFieldBehavior,FieldContractStructure,ServiceProviderFieldRegistry}Test.php` | DB 비의존 Phase 3 contract 검증 | Phase 3 |
| `src/Schema/FormSchemaParser.php` | Provider와 분리된 frozen envelope parsing/default 보완/order 보존 | Phase 4 |
| `src/Schema/FormSchemaValidator.php` | version/key/locale/shape/size/depth structural validation | Phase 4 |
| `src/Schema/PublishSchemaValidator.php` | Registry resolve, Provider definition, renderer warning aggregation | Phase 4 |
| `src/Schema/SchemaLimits.php` | 공개 보안 제한 상수 | Phase 4 |
| `src/Schema/ValueObjects/*` | immutable FormSchema/issue/result | Phase 4 |
| `src/Schema/Enums/SchemaIssueSeverity.php` | `error|warning` backed enum | Phase 4 |
| `src/Schema/Internal/SchemaIssueCollector.php` | mutable internal issue accumulator | Phase 4 |
| `src/Schema/SchemaValidationResponse.php` | G7 422 envelope용 translated safe payload | Phase 4 |
| `src/Exceptions/Schema/FormSchemaValidationException.php` | Service validation failure domain boundary | Phase 4 |
| `src/lang/{ko,en}/schema.php` | schema issue/summary 번역 | Phase 4 |
| `tests/Unit/Schema/*` | DB-free parser/validator/service/response/boundary tests | Phase 4 |

## 4. 근거가 확인된 skeleton 파일

| 예상 파일 | 책임 | 구현 단계 | 근거 |
|---|---|---|---|
| `module.json` | identifier/vendor/name/version/license/g7/dependencies; 미사용 assets 미선언 | 1 | `gnuboard7-hello_module/module.json:1-23` 및 최근 모듈의 optional assets 관례 |
| `module.php` | AbstractModule entry, permissions/menu/hooks/notifications | 1,4,10 | `gnuboard7-hello_module/module.php:15-162`, `docs/backend/notification-system.md:311-378` |
| `composer.json` | module autoload | 1 | `gnuboard7-hello_module/composer.json:1-22`; package `modules/glitter-form_builder` |
| `src/Providers/FormBuilderServiceProvider.php` | repository/cache/storage binding | 1+ | `sirsoft-page/src/Providers/PageServiceProvider.php:22-47` |
| `src/routes/api.php` | named module API routes | 4+ | `sirsoft-page/src/routes/api.php:16-20,32-138` |
| `resources/routes/{admin,user}.json` | template type별 frontend routes | 5-6 | `docs/extension/module-routing.md:165-196` |
| `resources/layouts/{admin,user}/*.json` | module-owned page layouts | 5-6 | `docs/extension/module-layouts.md:56-82` |
| `resources/lang/{ko,en}.json` | fixed frontend UI text | 5+ | `docs/extension/module-i18n.md:126-204` |
| `src/lang/{ko,en}/*.php` | backend/validation/activity text | 1+ | `docs/extension/module-i18n.md:57-121` |
| `database/migrations/*.php` | module-owned schema | 2+ | existing module migrations |
| `tests/ModuleTestCase.php` | bundled module runtime bootstrap | 1 | `sirsoft-board/tests/ModuleTestCase.php:20-99` |
| `docs/api/*.md` | endpoint reference | 4+ | existing module `docs/api` structures |
| `upgrades/Upgrade_*.php` | installed data/schema transition | 필요 시 | `docs/extension/extension-update-system.md`, existing `sirsoft-board/upgrades/` |

## 5. 도메인 후보 파일

| 후보 파일/그룹 | 책임 | 단계 | 확정 전 확인 |
|---|---|---|---|
| `Models/Form.php`, `FormVersion.php` | **실제** definition aggregate/published snapshot | 2 | DB 적용 검증만 남음 |
| `Models/Submission.php` | accepted raw/normalized snapshot | 7 | encryption/JSON type |
| `Models/SubmissionFieldIndex.php` | allowlisted typed projection | 7 | exact typed columns/index |
| `Models/IntegrationBinding.php` | form→handler config | 9 | binding version/snapshot |
| `Models/SubmissionProcessingAttempt.php` | durable execution history | 9 | outbox 역할 포함 여부 |
| `Models/FileReference.php` | submission field→attachment link | 11 | core Attachment reuse |
| `Enums/FormStatus.php`, `FormAccessMode.php`, `RetentionMode.php` | **실제** Phase 2 persisted values | 2 | 후속 Submission/Handler Enum은 미생성 |
| `Fields/ValueObjects/*.php` | **실제** Field Contract runtime immutable 값 | 3 | Form Schema envelope DTO는 아직 미생성 |
| `Repositories/Contracts/Form*RepositoryInterface.php` | **실제** persistence ports | 2 | DB integration 미실행 |
| `Services/FormService.php` | **실제** draft/publish/archive | 2 | transaction DB integration 미실행 |
| `Services/Submission*.php` | validation/commit/redact | 7+ | raw vs normalized |
| `Schema/{FormSchema,PublishSchema}Validator.php` | **실제** structural/publish schema validation | 4 | Registry contract; FormRequest dynamic submission rule은 Phase 7 |
| `Http/Requests/Public/SubmitFormRequest.php` | final server validation | 7 | dynamic rule resolution, fail closed |
| `Http/Resources/*` | envelope payload/abilities/redaction | 4+ | public exposure |

## 6. 공개 확장 계약 후보 파일

다음은 코어에서 확인된 기존 Registry가 아니라 `glitter-form_builder`가 소유할 후보다.

| 후보 | 책임 | 단계 | 경계 |
|---|---|---|---|
| `Contracts/Fields/FieldProvider.php` | **실제 experimental** definition/rules/normalize/display/export/index/render capability | 3 | provider implementation을 본체 enum에 하드코딩하지 않음 |
| `Fields/FieldTypeRegistry.php` | **실제 experimental** type 등록/resolve/conflict/version/seal | 3 | frontend `ComponentRegistry`와 별개 |
| `Contracts/Handlers/SubmissionHandler.php` | external work delivery | 9 | target module model/service/table 미노출 |
| `Contracts/Handlers/HandlerContext.php` | submission/binding/idempotency context | 9 | sensitive data 최소 disclosure |
| `Contracts/Handlers/HandlerResult.php` | success/retryable/result reference | 9 | opaque downstream reference |
| `Registries/SubmissionHandlerRegistry.php` | handler key discovery/capability | 9 | core에 기존 구현 없음 |
| `Jobs/ExecuteSubmissionHandler.php` | async execution | 9 | queue driver 추정 금지 |

Contract를 외부 package가 compile-time import할 경로와 semantic version policy는 Phase 0 decision으로 남긴다.

## 7. Layout/renderer 후보

| 후보 | 책임 | 단계 | 주의 |
|---|---|---|---|
| `Services/FormSchemaLayoutProjector.php` | independent schema → allowlisted G7 Layout projection | 6 | generated projection은 SSoT 아님 |
| `resources/layouts/user/public_form.json` | shell/data source/form slot | 6 | 실제 component/prop 검증 후만 생성 |
| `resources/js/*Renderer*` | 전용 React renderer | deferred | Layout adapter 부족이 입증될 때만 |

### Phase 6 bundled 구현

| 파일 | 책임 | 경계 |
|---|---|---|
| `src/Rendering/FormLayoutProjector.php` | frozen schema/Provider capability를 allowlisted G7 component projection으로 변환 | domain snapshot을 수정하지 않는 non-SSoT adapter |
| `src/Rendering/ValueObjects/PublicFormSchema.php` | ORM/Request 비노출 immutable public read value | public application output |
| `src/Services/PublicFormService.php` | slug read, access mode, current published version, projection 조정 | application service |
| `src/Repositories/Contracts/PublicFormRepositoryInterface.php` | published slug read port | repository contract |
| `src/Repositories/PublicFormRepository.php` | published/current version read query | persistence adapter |
| `src/Http/Controllers/User/PublicFormSchemaController.php` | optional auth actor와 GET response | HTTP read boundary |
| `src/Http/Resources/PublicFormSchemaResource.php` | versioned public response serialization | HTTP resource |
| `resources/routes/user.json` | `/forms/:slug` user route | frontend route composition |
| `resources/layouts/user/user_form_builder_form_show.json` | safe read-only field rendering | G7 Layout; submission action 없음 |
| `tests/Unit/Phase6/*` | projection/access/import/layout regression | DB-free |
| `resources/js/handlers/*` | namespaced ActionDispatcher handlers | 필요 시 | `registerHandler`는 확인됨; build/lifecycle 필요 |

정확한 JSON Layout filename, component, handler, binding 문법은 기존 engine/production layout을 Phase별로 다시 확인하기 전 확정하지 않는다.

## 8. 본체와 Connector 경계

### 본체에 포함

- Form/FormVersion/Submission/attempt persistence
- Field/Handler public contracts and registries
- generic binding UI/API
- G7 notification/activity/permission/search integration
- generic webhook handler를 본체에 둘지는 보안 검토 후 결정

### 본체에 포함하지 않음

- CRM/Reservation/Board/Ecommerce namespace import
- target module migration/table/FK/URL
- target-specific mapping/settings/UI/permissions
- target lifecycle commands

### 향후 Connector package 후보

```text
modules/_bundled/glitter-form_builder-crm/       # 독립 records/UI/API가 필요한 경우
plugins/_bundled/glitter-form_builder-webhook/   # hook/adapter만 필요한 경우 후보
```

이 이름과 extension type은 확정이 아니다. Connector가 full page layout·자체 domain records를 가지면 module, 기존 기능에 adapter만 붙이면 plugin이라는 G7 기준을 적용한다 (`docs/extension/README.md:21-22`, `docs/extension/plugin-development.md:1044-1052`).

Connector는 본체 Handler contract만 구현한다. CRM이 존재해도 공개 Service interface가 없으면 내부 class/table을 참조하지 않고 webhook/notification 기준 Connector로 전환한다.

## 9. 생성 금지 대상

- `modules/glitter-form_builder/` active output 직접 편집(공식 lifecycle만 생성 가능)
- `public/build/*`, generated manifest/cache
- core `app/*`, existing module/template/plugin source
- migration/route/layout/TS/React 및 Phase 2+ 비즈니스 PHP
- demo data/assets/debug routes

다음 session은 이 문서를 파일 생성 허가로 오해하지 말고 해당 Phase의 선행 decision과 실제 runtime 근거를 먼저 확인해야 한다.

## Phase 5 실제 파일

- `resources/routes/admin.json`: 관리자 UUID edit route와 index/create route.
- `resources/layouts/admin/admin_form_builder_form_{index,create,edit}.json`: 목록 및 순서 기반 Designer Layout.
- `resources/js/designer/{schema,store,publishFlow}.ts`: canonical 변환, 순수 state 연산, publish warning 경계.
- `resources/js/handlers/designerHandlers.ts`, `resources/js/index.ts`: 모듈 namespaced handler 등록과 API orchestration.
- `resources/css/main.css`: `[data-module="glitter-form_builder"]` 관리자 scope.
- `src/Fields/FieldDefinitionFactory.php`: palette용 안전한 canonical template 내부 factory.
- `src/Services/{FieldTypeCatalog,SchemaEditingService}.php`: safe metadata 및 DB-free validator orchestration.
- `src/Http/Controllers/Admin/{FieldTypeController,SchemaValidationController}.php`, `src/Http/Requests/Admin/ValidateSchemaRequest.php`: 읽기/검증 API 경계.
- `tests/Unit/Phase5/*`, `resources/js/__tests__/*`: DB-free backend/frontend contract tests. Phase 5 당시 미실행이었으나 Phase 5.5에서 scoped Vitest를 통과했다.

### Phase 5.5 검증 보정

- `vitest.config.ts`: module directory를 explicit root로 사용해 module tests만 수집한다.
- `tsconfig.json`, `tsconfig.test.json`: root compiler config에 의존하지 않는 strict production/test typecheck 범위다. test config만 Node/Vitest와 ES2021 `WeakRef` type surface를 추가한다.
- `resources/js/index.ts`: manifest의 실제 entry이며 dispatcher 재생성은 허용하되 동일 dispatcher 등록은 WeakSet으로 멱등화한다.
- `resources/js/handlers/designerHandlers.ts`: 상태는 module singleton이 아니라 Layout `ActionContext.state/setState`의 `_local.formDesigner`에 귀속된다.
- `package.json`, `package-lock.json`: private module-local toolchain과 재현 가능한 dependency graph. `node_modules`는 root `.gitignore`의 bundled module 규칙으로 제외된다.
- `vite.config.ts`: `resources/js/index.ts`와 imported CSS를 고유 `GlitterFormBuilderModule` IIFE로 빌드하고 module-owned `dist`만 비운다.
- `resources/js/types/g7-runtime.d.ts`: 실제 사용한 `window.G7Core`, API client, ActionDispatcher/Context 최소 표면만 선언한다.
- `dist/js/module.iife.js`, `dist/css/module.css`: module.json output과 일치하는 공식 추적 대상 build 산출물. runtime manifest는 별도 Vite manifest를 읽지 않는다.

## Phase 7 actual files

- `database/migrations/*form_submissions*`, `*form_submission_values*`: immutable receipt와 typed projection.
- `src/Models/{Submission,SubmissionValue}.php`, `src/Enums/Submission*.php`: aggregate persistence.
- `src/DTO/Submission*.php`, `src/Events/SubmissionReceived.php`: readonly boundary와 safe after-commit event.
- submission services/repository, public/admin HTTP boundaries and routes.
- public input handler/Layout, admin submission list/detail Layout, scoped CSS와 ko/en translations.
- `docs/contracts/{submission,submission-api,idempotency,submission-security}.md`와 Phase 7 tests.

## 10. Phase 0-B 추가 문서와 후보 갱신

### 이번 단계 실제 생성

| 파일 | 책임 |
|---|---|
| `docs/design/decision-log.md` | accepted/deferred/rejected 결정과 실제 근거 |
| `docs/design/runtime-baseline.md` | 민감값 제외 runtime/DB/template/lifecycle 기준선 |
| `docs/design/schema-contract-draft.md` | Phase 2/3 Form Schema 계약 초안 |
| `docs/design/processing-contract-draft.md` | Phase 7/9 Handler 처리 계약 초안 |

### Decision으로 근거가 강화된 설계 후보

| 후보 | 단계 | 근거/제약 | 실제 생성 |
|---|---:|---|---|
| Form/FormVersion model·migration | 2 | bigint + UUIDv7, immutable revision | 아니오 |
| Form Schema validator/value object | 3 | FB-DEC-008/010 | 아니오 |
| Submission/typed index | 7 | JSON snapshot + typed index hybrid | 아니오 |
| ProcessingAttempt/Binding | 9 | durable versioned processing | 아니오 |
| Field Registry | 3 | module-owned, collision fail-fast | 아니오 |
| Handler Registry/execution layer | 9 | execution layer owns retry | 아니오 |
| File provider/reference | 11 | 익명 temp lifecycle 전 생성 금지 | 아니오 |

Phase 1 namespace는 실제 resolver에 따라 `Modules\\Glitter\\FormBuilder\\`로 구현했고 package는 FB-DEC-003에 따라 `modules/glitter-form_builder`로 확정했다. Phase 2+ class/file명과 Connector 이름/type은 계속 미확정이다.

Connector는 `SubmissionContext` DTO와 Handler contract만 사용한다. target Model/Repository/table, Connector secret을 본체 snapshot에 복제하지 않는다. CRM 공개 Service가 충분하면 CRM을 우선하고, 내부 접근이 필요하면 Webhook/Notification Connector를 먼저 검증한다.

## 11. Phase 0.5 contracts 문서 맵

이번 단계 실제 생성 Markdown:

| 파일 | 책임 | 안정성 |
|---|---|---|
| `docs/contracts/README.md` | 공개/내부 경계, 안정성, 진입 순서 | frozen semantics |
| `docs/contracts/domain-model.md` | Aggregate, 상태, 삭제, 참조 | frozen semantics |
| `docs/contracts/form-schema.md` | schemaVersion 1.0 Form Schema | frozen semantics |
| `docs/contracts/field-provider.md` | Field Provider 계약 | PHP API experimental 후보 |
| `docs/contracts/submission-handler.md` | Handler 계약 | PHP API experimental 후보 |
| `docs/contracts/processing-lifecycle.md` | transaction/Attempt/retry/queue | frozen semantics |
| `docs/contracts/events.md` | Domain Event/Hook/payload/timing | PHP API experimental 후보 |
| `docs/contracts/registry.md` | Registry 등록/충돌/seal | PHP API experimental 후보 |
| `docs/contracts/dto.md` | 공개/내부 DTO와 직렬화 | PHP API experimental 후보 |
| `docs/contracts/connector-boundary.md` | Connector 의존 방향 | frozen semantics |
| `docs/contracts/compatibility.md` | version/하위 호환/deprecation | frozen semantics |
| `docs/contracts/security.md` | trust/redaction/secret/export/XSS | frozen semantics |

### 향후 파일 생성 단계 정정

- Phase 1: 공개 Contract/DTO/Registry/Event PHP 파일 생성하지 않음.
- Phase 2: Aggregate/Enum/internal persistence 후보만 생성.
- Phase 3: Field Contract/FieldTypeRegistry/public Field DTO 첫 생성.
- Phase 7: Submission/event DTO 후보.
- Phase 9: Handler Contract/SubmissionHandlerRegistry/processing DTO·executor 후보.

pseudo-code의 class/file명은 생성 허가가 아니다. 실제 namespace와 signature는 각 experimental 구현 Phase의 test-first 결정 대상이다.
