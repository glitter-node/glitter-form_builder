# Glitter Form Builder 기반 조사

> 상태: Phase 0 조사·설계 문서. 구현 스키마나 공개 API의 확정 명세가 아니다.  
> 기준일: 2026-07-31  
> 대상: `glitter-form_builder` / 표시명 `Glitter Form Builder` / Vendor·Author `Glitter.kr` / License `MIT` / 초기 버전 후보 `0.1.0`

## 1. 조사 범위와 방법

루트 `README.md`, `AGENTS.md`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vendor-bundle.json`, `docs/README.md`, `docs/extension/*.md`, 요청에 열거된 backend/frontend/testing/database 문서를 읽고 실제 코드를 우선 대조했다. 구현 표본은 `gnuboard7-hello_module`, `sirsoft-page`, `sirsoft-board`, `sirsoft-ecommerce`의 manifest, entry point, ServiceProvider, route, migration, model, Resource, layout, test와 코어 `app/Extension`, `app/Listeners`, `app/Services`, template engine을 사용했다.

`modules/_bundled/glitter-crm`은 현재 존재하지 않는다. 이름이 비슷한 기존 사용자 작업 `modules/_bundled/glitter-media_manager/`는 조사·수정 대상에서 제외했다.

문서와 코드가 다르면 현재 소스와 lock 파일을 런타임 근거로 우선했다. DB에 접속하거나 schema를 변경하지 않았으므로 미래 migration의 정확한 컬럼·인덱스·collation은 확정하지 않는다.

## 2. 확인한 G7 버전과 기술 환경

| 항목 | 확인 결과 | 근거 |
|---|---|---|
| G7 버전 | 현재 코드 기본값과 README는 `7.0.5`; `vendor-bundle.json`은 과거 산출물 `7.0.3` | `README.md:11`, `.env.example:6`, `config/app.php:234`, `vendor-bundle.json:12` |
| PHP 요구 | `^8.2`; 이 환경의 허용 CLI는 `/usr/local/bin/php83` | `composer.json`의 `require.php`, `AGENTS.md` PHP 실행 규칙 |
| Laravel | lock 기준 `v12.62.0` | `composer.lock`의 `laravel/framework` |
| Sanctum / Scout / Reverb | `v4.3.2` / `v11.3.0` / `v1.10.2` | `composer.lock` |
| React / Vite / Vitest / Tailwind | lock 기준 `19.2.0` / `7.1.12` / `4.1.8` / `4.1.16` | `package-lock.json` 해당 package |
| DB 지원 기준 | 문서는 MySQL 8+를 소개하지만 프로젝트 규칙은 MariaDB 호환과 실제 schema 사전 확인을 요구 | `README.md` 기술 스택, `AGENTS.md` Database Safety Rule, `docs/database-guide.md` |
| 프런트 실행 | 정적 build만 허용; dev server 금지. 이번 단계는 frontend 구현이 없어 build 미실행 | `AGENTS.md` Frontend/Generated Asset Rule |

`vendor-bundle.json`은 `generated_at`이 있는 배포 산출 메타데이터이므로 코어 현재 버전의 SSoT로 사용하면 안 된다.

## 3. 주요 근거 목록과 Form Builder에 주는 제약

### 확장 lifecycle과 패키징

- 모듈은 독립 비즈니스 기능, 플러그인은 특정 기능 확장이다 (`docs/extension/README.md:21-22`). 완전한 page layout은 모듈만 등록할 수 있고 플러그인은 `layout_extensions`만 사용한다 (`docs/extension/plugin-development.md:1044-1052`). 따라서 본체는 모듈이 적합하다.
- 활성 확장만 route가 등록되고 API prefix는 `/api/modules/{identifier}`, name prefix는 `api.modules.{identifier}.`이다 (`docs/extension/module-routing.md:42-75`). 고정 외부 URL을 계약으로 삼지 않고 route name/API 문서를 공개해야 한다.
- `AbstractModule`은 route, migration, permission, menu, hook listener, middleware, notification definition 선언 표면을 제공한다 (`docs/extension/module-basics.md:83-145`). 실제 샘플도 이를 사용한다 (`modules/_bundled/gnuboard7-hello_module/module.php:15-162`).
- `_bundled`는 활성 런타임이 아니다. 활성 디렉토리와 autoload cache는 install/update lifecycle을 거쳐 생성된다 (`docs/extension/extension-manager.md:107-128`). 다음 구현 단계부터 source 수정 후 update/install/activate가 필요하다.
- 문서의 Composer 이름 관례는 `modules/vendor-module`이다 (`docs/extension/module-basics.md:63-75`), 실제 manifest도 `modules/sirsoft-*`이다. 사용자 후보 `glitter/formbuilder`와 충돌하므로 package name은 아직 확정하지 않는다.

### Controller, validation, Service/Repository, API

- 흐름은 Controller → FormRequest → Service → Repository이며 Controller 검증과 Service 검증은 금지된다 (`docs/backend/controllers.md:12-13`, `docs/backend/validation.md:42-56`).
- 권한은 `FormRequest::authorize()`가 아니라 route middleware에서 검사한다 (`docs/backend/validation.md:524-536`).
- 동적 폼에도 빈 rules 후 `all()` fallback은 일반 사용자 입력에서 금지된다 (`docs/backend/validation.md:873-939`). Form Builder는 게시된 `FormVersion`의 field contract로 매 제출마다 rules를 생성하고 `validated()`만 수용해야 한다.
- Repository interface는 module ServiceProvider에서 구현체에 bind한다 (`docs/backend/service-repository.md:989-1063`). 실제 `BaseModuleServiceProvider` 사용 예는 `modules/_bundled/sirsoft-page/src/Providers/PageServiceProvider.php:22-47`이다.
- API는 `ResponseHelper` envelope를 사용한다 (`docs/backend/response-helper.md:37-84,120-137`). Resource는 `BaseApiResource`, Collection은 `BaseApiCollection`을 사용하고 `is_owner`/`abilities`를 반환한다 (`docs/backend/api-resources.md:35-53,83-208`). 샘플은 이를 실제 적용한다 (`modules/_bundled/gnuboard7-hello_module/src/Http/Resources/MemoResource.php:11-43`).

### Route, 인증, 권한

- 공개/선택 인증은 `optional.sanctum`으로 토큰 없음·만료를 guest로 통과시킨다 (`docs/backend/middleware.md:334-363`). 실제 공개 Page API는 이 middleware를 사용한다 (`modules/_bundled/sirsoft-page/src/routes/api.php:114-138`).
- 관리자 API는 `auth:sanctum` + permission middleware 패턴이다 (`modules/_bundled/gnuboard7-hello_module/src/routes/web.php:17-42`).
- 익명도 권한 정책에 포함하려면 guest role과 `permission:user,...` 조합을 검토할 수 있다. Board의 공개 write/read route가 실제 사례다 (`modules/_bundled/sirsoft-board/src/routes/api.php:406-455`). 단순 anonymous POST에 permission middleware를 붙일지는 abuse 정책과 운영 UX를 결정한 뒤 확정한다.
- route마다 name이 필요하고 module prefix는 자동 적용된다 (`docs/extension/module-routing.md:61-161`).

### Layout과 사용자 화면

- module layout은 `resources/layouts/admin/` 또는 `resources/layouts/user/`에 두어 각 활성 template에 자동 등록한다 (`docs/extension/module-layouts.md:42-82`).
- frontend route는 `resources/routes/admin.json`, `resources/routes/user.json`을 사용한다. 레거시 `resources/routes.json`은 admin에만 fallback되고 user에는 로드되지 않는다 (`docs/extension/module-routing.md:165-196`). 따라서 공개 form은 user route/layout을 제공하면 기존 사용자 template 원본 수정 없이 노출 가능하다.
- 실제 공개 샘플 route는 `auth_required:false`이다 (`modules/_bundled/gnuboard7-hello_module/resources/routes.json:28-35`). 다만 이 파일은 레거시 admin fallback 규칙과 충돌하는 낡은 샘플이므로 새 모듈은 `routes/user.json`을 사용해야 한다.
- Layout은 G7 JSON Layout schema이며 component names, props, actions, bindings는 실제 engine/production layout에서 확인된 것만 사용해야 한다. Form Schema는 입력·검증·정규화·저장 계약이며 Layout JSON과 동일하지 않다.
- module frontend assets는 활성화 시 동적 로드되고 namespaced handler를 `ActionDispatcher.registerHandler()`로 등록할 수 있다 (`docs/extension/module-assets.md:32-54,231-265`; 실제 메서드 `resources/js/core/template-engine/ActionDispatcher.ts:6599-6648`).
- template `ComponentRegistry`는 존재하지만 내부 component 등록 메서드는 private이고 manifest/template bundle을 읽는 registry다 (`resources/js/core/template-engine/ComponentRegistry.ts:412-483`). 이는 서버 field type registry와 다른 책임이다.
- Layout visible sentence는 `$t:` key만 사용하고 동적 값은 번역 parameter로 주입해야 한다 (`AGENTS.md` i18n Layout Rule). Frontend module locale file은 `resources/lang/{locale}.json`, backend는 `src/lang/{locale}/*.php`이다 (`docs/extension/module-i18n.md:30-52,57-121`).

### Hook, queue, 알림, 활동 로그, 검색

- `HookListenerInterface` + `getHookListeners()`가 공식 module hook 구독 표면이다 (`docs/extension/hooks.md:166-200,648-663`). action listener는 기본 비동기이며 `sync:true`가 opt-out이다 (`docs/extension/hooks.md:315-355`). Queue driver/worker 존재는 현재 환경에서 확인하지 않았으므로 처리의 영속 기록이 queue 자체에만 의존하면 안 된다.
- 알림 SSoT는 `module.php::getNotificationDefinitions()`이며 Manager가 activate/update/uninstall 시 동기화한다 (`docs/backend/notification-system.md:311-378`). 동적 hook listener가 definition의 hook을 구독하고 `{hook_prefix}.notification.extract_data` filter로 data/recipient context를 얻는다 (`docs/backend/notification-system.md:150-168`). Form 제출 알림은 이 공식 경로를 우선 사용한다.
- 활동 로그는 domain Service hook → module listener → `Log::channel('activity')` 파이프라인이다 (`docs/backend/activity-log.md:39-67,89-149`). 제출 payload 전체를 로그 properties에 넣으면 개인정보가 복제되므로 식별자·상태·field count 등 최소 정보만 기록해야 한다.
- 검색은 Laravel Scout/`FulltextSearchable` 패턴을 제공한다. `sirsoft-page`가 실제 구현한다 (`modules/_bundled/sirsoft-page/src/Models/Page.php:17-20,161-213`). submission 검색은 임의 JSON 전체보다 허용된 index row/관리 메타만 대상으로 제한해야 한다.

### UUID, 다국어 데이터, 파일

- 일관된 단일 PK 관례는 없다. Page는 bigint `id`만 쓴다 (`modules/_bundled/sirsoft-page/database/migrations/2026_04_01_000001_create_pages_table.php:15-35`). Hello sample은 bigint 내부 PK와 unique UUID 외부 ID를 함께 쓴다 (`modules/_bundled/gnuboard7-hello_module/database/migrations/2026_04_21_000001_create_gnuboard7_hello_module_memos_table.php:15-23`) and `HasUuids::uniqueIds()`로 `uuid`를 채운다 (`.../src/Models/Memo.php:15-46`). User UUID는 외부 노출용 UUID v7 주석이 있다 (`database/migrations/2026_04_01_000030_add_uuid_to_users_table.php:21`).
- 운영자가 편집하는 다국어 값은 JSON data가 적합하다. Page title/content가 JSON cast이고 fallback locale을 사용한다 (`modules/_bundled/sirsoft-page/src/Models/Page.php:68-77,137-148`). 번역 파일은 제품 UI 고정 문장·validation 메시지용이다. 따라서 form name/field label/help/choice label은 locale map snapshot 후보, 버튼/오류 문장은 lang key로 분리한다.
- 코어 `AttachmentService`와 polymorphic Attachment가 존재하고 upload/delete/download hook도 있다 (`app/Services/AttachmentService.php:40-115,162-193,227-299`; `app/Models/Attachment.php:20-124`). `FileUploader`도 endpoint customization을 지원한다 (`docs/frontend/component-props-composite.md:34-125`). 그러나 익명 temporary upload→submission 연결, quarantine, scan, retention 계약은 확인되지 않아 공통 Media API라고 간주하지 않는다.

### 테스트

- bundled module test는 module autoload/provider/routes/migration/hook를 테스트 환경에서 수동 복원한다 (`modules/_bundled/sirsoft-board/tests/ModuleTestCase.php:20-99,170-215,262-275`).
- scenario YAML 사례는 `modules/_bundled/sirsoft-board/tests/scenarios/`에 있다. Layout은 `createLayoutTest()`/`mockApi()` 기반으로 검증한다 (`docs/frontend/layout-testing.md`).
- 실제 lifecycle runtime은 bundled source test와 다르므로 unit/feature/layout test 후 install/update/activate 및 active output 검증이 별도 필요하다.

## 4. 재사용 가능한 코어 기능

| 기능 | 재사용 권고 | 경계 |
|---|---|---|
| Module lifecycle/autoload/routes/layout sync | 필수 | 활성 디렉토리 직접 편집 금지 |
| `BaseModuleServiceProvider`, Repository interface binding | 필수 | 다른 module service 구현 직접 bind 금지 |
| FormRequest + Custom Rule | 최종 권위 | client validation은 UX 전용 |
| `ResponseHelper`, Base API Resource/Collection abilities | 필수 | 민감 payload는 공개 Resource에서 제외 |
| Sanctum, optional Sanctum, permission/scope | 접근 유형별 사용 | anonymous abuse control은 별도 설계 |
| HookManager + queued listener | domain event와 extension notification에 사용 | 핵심 submission commit을 비동기 hook에 맡기지 않음 |
| declarative Notification Definition | Form 제출 알림의 우선 경로 | 실제 채널 발송은 코어/채널 plugin 책임 |
| Activity log | 관리 동작과 처리 상태 기록 | 원본 답변·secret 값 로그 금지 |
| CacheInterface | published schema/render projection 캐시 후보 | invalidate key/TTL은 구현 전 검증 |
| Scout/fulltext | form metadata 및 opt-in index 검색 후보 | raw sensitive payload 인덱싱 금지 |
| IDV declaration/interceptor | form별 optional policy 연결 후보 | 본인인증 자체 구현 금지 |
| module i18n + language pack | UI 문장/validation/enum label | 운영자 입력 labels는 data snapshot |
| Attachment/StorageInterface/FileUploader | 파일 field 검증 후보 | guest temporary lifecycle 추가 검증 필요 |

## 5. 부족하거나 불확실한 확장점

1. 서버 `FieldRegistry`, `SubmissionHandlerRegistry`, `ConnectorRegistry`는 확인되지 않음. 모듈 공개 Contract로 새로 정의하되 코어 기능처럼 표현하지 않는다.
2. Frontend `ComponentRegistry`는 확인되지만 arbitrary module field component를 직접 public registration하는 표면은 확인되지 않음. module asset handler와 template component 조합, 또는 전용 renderer의 실증이 필요하다.
3. queue worker/driver, retry policy, Redis/Reverb endpoint는 확인하지 않음. DB processing attempt/outbox 성격 기록을 정본으로 설계한다.
4. anonymous file temporary ownership, antivirus/content scan, orphan cleanup 공통 계약은 확인되지 않음.
5. generic webhook connector, signature secret storage, outbound HTTP retry의 공식 공통 서비스는 확인되지 않음.
6. 개인정보 분류·법정 retention을 강제하는 코어 policy registry는 확인되지 않음.
7. form-specific dynamic Scope 표현을 정적으로 선언할지 runtime permission entity로 만들지는 추가 검증 필요.

## 6. 제품 정의, 책임과 비범위

`Glitter Form Builder`는 폼 definition/version을 관리하고, 게시된 schema에 따라 입력을 서버 검증·정규화하여 원본 submission을 보존하며, 공개된 handler contract로 후속 업무를 전달하는 범용 데이터 입력 플랫폼이다.

본체 책임:

- Form, immutable published FormVersion, field schema 및 validation contract
- public/auth/admin submission intake, idempotency, deduplication
- raw/normalized payload 보존, field-safe display/export projection
- processing binding, attempt history, retry/partial failure visibility
- retention, anonymization/deletion, file reference lifecycle
- handler/field provider의 모듈 소유 Registry 및 안정된 DTO 계약

비범위:

- 예약 가능 시간·충돌, CRM pipeline/activity, Board 글/댓글
- 주문·결제·환불, 실제 notification channel 전송
- 전자결재/승인 engine, media library 전체, auth/IDV 자체 구현
- 전자서명, 결제 field, advanced calculation, survey analytics, automation builder
- 초기 MVP drag-and-drop designer

원본 submission 저장소를 외부 CRM/예약 업무 table로 사용하지 않는다.

## 7. 외부 의존성 경계

본체는 CRM/Reservation/Board/Ecommerce의 Model, Repository, Service, table, fixed URL을 참조하지 않는다. 본체가 공개하는 값 객체/DTO와 handler interface만 외부가 의존한다.

권장 방향:

```text
FormVersion → validated Submission commit
                         ↓ durable processing record
                 SubmissionHandlerRegistry
                         ↓ handler-owned result/reference
                 Connector or generic Notification
```

- 본체는 `handler_key`, versioned config, execution mode를 저장한다.
- Connector는 contract를 구현하고 대상 module의 공개 contract 또는 webhook을 사용한다.
- 결과 연결은 `target_type` 자유 문자열 + `target_reference` opaque string/JSON 최소값으로 저장하며 대상 DB FK를 만들지 않는다.
- Hook는 관찰/확장 알림에 적합하지만 처리 성공 여부와 retry를 보장하는 유일한 integration contract로 사용하지 않는다.

## 8. Form Schema와 JSON Layout의 관계

Form Schema는 domain contract다: stable field key, type contract, localized label/help/options, required/constraints, privacy classification, normalization, storage/display/export rules를 포함한다. JSON Layout은 G7 renderer input이다.

권장 흐름은 `Published FormVersion schema → renderer/projection → supported G7 Layout components`이다. 변환기는 allowlist mapping만 사용하고 미지원 field는 publish 전 차단한다. Layout 문법이나 prop을 schema에 그대로 저장하면 engine 변화와 domain versioning이 결합되므로 금지한다. theme/presentation hint는 별도 제한된 `presentation` 영역 후보이며 G7 Layout raw injection을 허용하지 않는다.

## 9. 공개 폼 노출 후보

1. **권장 MVP**: module `resources/routes/user.json` + `resources/layouts/user/*.json`; 공개 schema API와 submit API는 `optional.sanctum`. 기존 user template 수정 없이 module activation이 route/layout을 합친다.
2. **인증 전용 폼**: 같은 user layout, API submit route는 `auth:sanctum` 및 필요 시 user permission/IDV.
3. **관리자 내부 입력**: `routes/admin.json` + admin layout, API는 `auth:sanctum` + admin permission.
4. **전용 React renderer**: 복잡한 dynamic fields가 기본 Layout component로 안전하게 표현되지 않을 때만 검토. module asset build/lifecycle과 editor integration 비용이 크다.

공개 URL slug, exact endpoint, rate limit, CAPTCHA/anti-bot은 아직 확정하지 않는다.

## 10. 주요 기술 위험

- dynamic validation이 schema lookup 실패 시 우회되는 위험
- published schema mutation으로 과거 submission 해석이 깨지는 위험
- at-least-once handler 실행에서 외부 업무 중복 생성
- core queue 실패와 submission 처리 상태가 불일치
- raw payload/index/export/log/cache에 개인정보가 중복 확산
- guest file orphan, 접근 제어, malware, retention 문제
- Layout component/prop가 template마다 달라 render portability가 깨짐
- Registry provider의 충돌, unload/update, contract version 불일치
- EAV 과도 사용 시 query/maintenance 복잡도, JSON-only 사용 시 검색/삭제 어려움
- lifecycle version bump/CHANGELOG 누락으로 active output이 갱신되지 않음

## 11. 설계 전에 확인이 필요한 질문

Phase 0 decision gate에서 다음을 한국어로 확인해야 한다.

1. 운영 목표 DB는 MySQL과 MariaDB 중 무엇이며 지원 최소 버전은 무엇인가?
2. anonymous form의 기본 정책은 허용/금지 중 무엇이며 CAPTCHA 또는 외부 anti-bot provider를 요구하는가?
3. raw submission의 기본 retention, field별 retention, legal hold가 필요한가?
4. 공개 identifier는 UUID v7, random token, slug 중 무엇을 외부 계약으로 원하는가?
5. field/provider와 handler/provider를 third-party extension이 등록하는 최소 안정성·versioning 정책은 무엇인가?
6. file field MVP에 virus scan/temporary upload가 필수인가, 아니면 Phase 11까지 제외 가능한가?
7. queue가 없는 설치에서도 synchronous handler를 허용할 것인가, 아니면 submission 수집 후 `pending`만 유지할 것인가?
8. `Glitter.kr`의 Composer namespace 정책상 `glitter/formbuilder`가 필수인가? G7 문서 관례 `modules/glitter-form_builder`와의 차이를 승인할 것인가?

## 12. 문서와 코드 사이의 불일치

| 항목 | 문서/표기 | 실제 판단 |
|---|---|---|
| G7 version | README/config 7.0.5, vendor bundle 7.0.3 | 코드 기본값 7.0.5 우선; bundle은 stale 가능 |
| Vite | package.json `^7.0.4` | lock은 7.1.12 |
| module routes | 일부 hello sample은 legacy `resources/routes.json`에 user route 포함 | routing 문서는 user가 legacy file을 로드하지 않는다고 명시; 새 구현은 split files |
| Composer package | 사용자 후보 `glitter/formbuilder` | G7 문서/실제 모듈은 `modules/{identifier}`; 미확정 |
| module layout 문서 예시 | 여러 명령이 `php artisan` 표기 | 이 환경에서는 반드시 `/usr/local/bin/php83 artisan`; 이번 단계 미실행 |
| `ComponentRegistry` 일반 설명 | README는 custom component 등록 가능이라고 표현 | 실제 core registry의 register는 private이며 template manifest 기반; module field registration 표면은 추가 검증 필요 |

## 13. 이번 단계의 명시적 권고

- identifier `glitter-form_builder`, display name `Glitter Form Builder`, Vendor/Author `Glitter.kr`, MIT, initial version candidate `0.1.0`.
- 본체에 예약/CRM/Board/Ecommerce 전용 handler를 포함하지 않는다.
- 현재 `glitter-crm`이 없으므로 첫 CRM Connector를 확정하지 않는다. 향후 공개 Service contract가 충분하면 CRM Connector를 우선 검증하고, 내부 구현 참조가 필요하면 generic webhook 또는 G7 notification 연계를 첫 검증 대상으로 선택한다.
- MVP designer는 drag-and-drop이 아니라 ordered structured editor를 우선한다.
- raw submission과 downstream domain data는 분리한다.
- 저장·definition·render·integration 상세 권고는 `architecture-options.md`, aggregate 초안은 `domain-model-draft.md`를 따른다.

## 14. Phase 0-B 결정 게이트 갱신 (2026-07-31)

이 절은 앞 절의 미결정 조사 항목을 대체한다. 상세 결정은 `decision-log.md`, 런타임 사실은 `runtime-baseline.md`를 따른다.

| 항목 | 결론 | Decision |
|---|---|---|
| DB | MySQL 8.0+/MariaDB 10.3+, 실제 MariaDB 10.11.11 포함, JSON snapshot + typed index hybrid | FB-DEC-001 |
| 식별자 | bigint 내부 PK + UUIDv7 공개 ID, immutable field key, Form별 integer revision | FB-DEC-002 |
| package | identifier `glitter-form_builder`, Composer `modules/glitter-form_builder` | FB-DEC-003 |
| 접근 | `authenticated_only`, `guest_and_authenticated`, `admin_only`; 익명 opt-in | FB-DEC-004 |
| 보존 | Form별 `manual|forever|days`, 자동 purge는 Phase 11 | FB-DEC-005 |
| 파일 | Phase 1~8과 schema에서 제외 | FB-DEC-006 |
| 처리 | durable attempt, `sync|async|disabled`, `auto` 제외 | FB-DEC-007 |
| 확장 | module-owned versioned Field/Handler Registry, duplicate fail-fast | FB-DEC-008/009 |
| MVP | 11개 built-in, flat single-page, conditional/multi-step 제외 | FB-DEC-010 |
| route | frontend `/forms/:slug`, API `/api/modules/glitter-form_builder/...` | FB-DEC-011 |
| 권한 | forms/submissions/integrations/settings 세분화 admin permission | FB-DEC-012 |

### 추가 확인된 실제 환경

- 운영 HTML은 GnuBoard7 `7.0.5`, 로컬은 PHP 8.3.27 / Laravel 12.62.0이다.
- DB는 `mysql` Laravel driver로 연결된 MariaDB 10.11.11, `utf8mb4`/`utf8mb4_unicode_ci`다.
- bootstrap 뒤 유효 driver는 queue `sync`, cache `file`, session `file`, filesystem `local`, broadcast `null`이다.
- 활성 template은 admin `sirsoft-admin_basic`, user `sirsoft-basic`이다.
- schema metadata key는 object/assoc 모두 대문자로 반환됐다. 호환성 코드는 lowercase 정규화와 `array_key_exists()`가 필요하다.

근거: `runtime-baseline.md`, `README.md:352-356`, `docs/requirements.md:155-168`, `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasUuids.php:16-29`.

### superseded와 추가 불일치

- UUID version 미확정 → **superseded**: 현재 `HasUuids`는 UUIDv7이다(FB-DEC-002).
- `glitter/formbuilder` package 후보 → **superseded**: `modules/glitter-form_builder`를 권장한다(FB-DEC-003).
- DB 저장/typed index 미확정 → **superseded**: hybrid와 DB 고유 JSON index 비의존을 확정했다. 물리 column type만 Phase 2 DDL 검증 대상이다.
- public route 미확정 → **superseded**: `/forms/:slug`와 module-prefixed API를 권장한다(FB-DEC-011).
- 공통 임시 파일 연결 미확인 → 일부 **superseded**: Page module의 `temp_key` link/move는 확인됐다. 그러나 관리자 경계이며 범용 익명 소유권·orphan cleanup·malware scan 계약은 확인되지 않아 MVP 제외를 유지한다.
- `config/queue.php`/`.env` 표면값만으로 runtime을 판단할 수 없다. `SettingsServiceProvider` override 뒤 현재 queue는 `sync`다(`app/Providers/SettingsServiceProvider.php:326`, `docs/testing-guide.md:683`).
- `README.md:72`는 MySQL만 표시하지만 요구사항 본문은 MariaDB 10.3+도 공식 지원한다(`README.md:355`, `docs/requirements.md:159-162`). 더 명시적인 본문을 따른다.
- routing 문서는 split admin/user route를 권장하지만 `sirsoft-page`는 legacy `resources/routes.json`이다. 새 모듈은 split 관례를 사용하되 Phase 6 실제 합성을 검증한다.
