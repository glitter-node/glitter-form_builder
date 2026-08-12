# Changelog

All notable changes to Glitter Form Builder are documented in this file.

## [0.3.16] - 2026-08-12

### Changed

- 폼 및 제출 관리자 목록에 sirsoft-admin_basic의 반응형 page padding과 일관된 section spacing을 적용했습니다.

## [0.3.15] - 2026-08-12

### Changed

- 모듈 카드와 관리자 부모 메뉴의 한국어 표시명을 `반짝이 폼 관리`로 통일했습니다.
- 동일한 브랜드 표시의 영어 이름을 `Glitter Form Management`로 통일했습니다.

## [0.3.14] - 2026-08-12

### Fixed

- 관리자 메뉴와 권한 entity 동기화 시 backend translation key가 표시명으로 저장되던 문제를 수정했습니다.
- 한국어 frontend 모듈 표시명과 KO/EN layout translation contract를 정합화했습니다.
- 관리자·공개 layout 및 route의 전체 translation key 회귀 테스트를 추가했습니다.

## [0.3.13] - 2026-08-01

### Fixed

- 관리자 제출 상세 응답을 생성할 때 Resource가 무한 재귀하여 500 오류가 발생하는 문제를 수정했습니다.

## [0.3.12] - 2026-08-01

### Fixed

- 공개 폼의 입력창과 제출 버튼이 비포커스 상태에서 식별하기 어렵고 필드 간격이 과도한 문제를 수정했습니다.
- 동의 체크박스와 동의 문구를 인접 배치하고 공개 폼의 반응형 입력 레이아웃을 개선했습니다.

## [0.3.11] - 2026-08-01

### Fixed

- 게시된 공개 폼 스키마에서 동의 필드의 안전한 statement 식별자가 누락되는 문제를 수정했습니다.

## [0.3.10] - 2026-08-01

### Fixed

- 기본 동의 필드가 Provider 정의 계약과 일치하지 않아 스키마 검증과 게시가 차단되던 문제를 수정했습니다.
- 저장된 FormResource의 동의 필드가 편집 및 검증 과정에서 canonical 정의를 유지하도록 수정했습니다.

## [0.3.9] - 2026-08-01

### Fixed

- 편집 화면에서 게시 버튼이 활성 상태임에도 게시 요청이 실행되지 않는 문제를 수정했습니다.
- 게시 사전 조건을 충족하지 못한 경우 아무 반응 없이 종료되던 동작을 사용자 오류로 표시하도록 수정했습니다.

## [0.3.8] - 2026-08-01

### Fixed

- 폼 생성 후 편집 화면에서 저장된 메타데이터와 필드가 초기화되지 않는 문제를 수정했습니다.
- 편집 화면에서 권한이 있음에도 초안 저장과 게시 버튼이 비활성으로 남는 문제를 수정했습니다.

## [0.3.7] - 2026-08-01

### Fixed

- 폼 생성 성공 응답의 빈 객체 필드가 배열로 전달될 때 편집 화면으로 이동하지 못하는 문제를 수정했습니다.
- 폼 생성 성공 후 이전 유효성 검사 오류가 화면에 남는 문제를 수정했습니다.

## [0.3.6] - 2026-08-01

### Fixed

- 폼 디자이너에서 팔레트의 필드를 추가할 때 액션 실행 오류가 발생하는 문제를 수정했습니다.

## [0.3.5] - 2026-08-01

### Fixed

- 폼 생성 및 저장 시 오래된 화면 상태가 사용되어 최신 메타데이터가 요청에 반영되지 않는 문제를 수정했습니다.

## [0.3.4] - 2026-08-01

### Fixed

- 폼 디자이너에서 새 필드의 키와 다국어 라벨이 잘못 생성되어 스키마 검증에 실패하는 문제를 수정했습니다.

## [0.3.3] - 2026-08-01

### Fixed

- 신규 폼 생성 시 슬러그, 다국어 제목 및 보존정책 확인 값이 요청에 반영되지 않는 문제를 수정했습니다.
- 폼 생성 검증 오류가 입력 영역에 표시되도록 개선했습니다.

## [0.3.2] - 2026-08-01

### Fixed

- 폼 디자이너에서 선택한 필드의 키가 속성 패널에 표시되지 않는 문제를 수정했습니다.

## [0.3.1] - 2026-08-01

### Fixed

- 폼 디자이너에서 필드 이동, 복제 및 삭제 동작이 실행되지 않는 문제를 수정했습니다.

## [0.3.0] - 2026-08-01

### Added

- 게시된 폼을 통해 사용자 입력을 제출하고 저장할 수 있습니다.
- 관리자가 제출 목록과 상세 내용을 확인할 수 있습니다.
- 중복 제출 방지와 서버 측 검증을 지원합니다.

## [0.2.3] - 2026-08-01

### Fixed

- 관리자 폼 목록과 디자이너의 작업 버튼을 제목 아래 동일한 콘텐츠 영역에 배치해 화면 오른쪽에서 잘리는 문제를 수정했습니다.

## [0.2.2] - 2026-08-01

### Fixed

- 관리자 폼 목록과 디자이너에서 작업 버튼이 화면 오른쪽으로 잘리는 문제를 수정했습니다.

## [0.2.1] - 2026-08-01

### Fixed

- 관리자 화면에서 작업 버튼이 화면 밖으로 잘리는 문제를 수정했습니다.
- 폼 디자이너의 필드 팔레트, 빈 상태, 속성 영역을 쉽게 구분할 수 있도록 개선했습니다.

## [0.2.0] - 2026-08-01

### Changed

- Standardize the module identifier, routes, permissions, translations, handlers, layouts, and runtime registry keys as `glitter-form_builder`.
- Localize the Korean module and root administrator menu display names as `폼 관리` while preserving their English product names.

## [0.1.2] - 2026-08-01

### Fixed

- Register frontend translations as nested JSON dictionaries so the G7 TranslationEngine resolves namespaced Layout keys at runtime.

## [0.1.1] - 2026-08-01

### Fixed

- Validate and snapshot the locked authoritative draft in one publish transaction.
- Project nullable form descriptions as an empty locale map while rejecting malformed values.
- Persist designer metadata, draft schema, retention mode, days, and acknowledgement in one update request.
- Align admin list search and localized title contracts.
- Reject providers without the Phase 6 allowlisted public renderer before publish.
- Distinguish missing or inaccessible forms from logged public projection failures.

### Scope

- Phase 6 provides form definition management, immutable published version reads, safe G7 Layout projection, and a read-only public preview.
- Submission POST, submission persistence, handlers, and events remain planned for Phase 7 and are not part of this release.

## [0.1.0] - 2026-07-31

### Added

- Initial GnuBoard7 module skeleton and metadata.
- Minimal module entry point and service provider.
- Korean and English translation foundation.
- Skeleton and guarded lifecycle test foundation.
- Form and immutable FormVersion domain models with UUIDv7 public Form identity.
- Portable Form/FormVersion migration definitions without database-specific JSON indexes.
- Repository contracts, repositories, and transactional Form service.
- Admin Form CRUD/publish API resources, permissions, and nested administrator menu declaration.
- Static model/migration tests and guarded database integration tests.
- Experimental Field Provider contract, immutable field value objects, and sealed FieldTypeRegistry.
- Eleven DB-independent built-in field providers with normalization, validation descriptors, safe formatting, and typed index projection.
- Canonical Form Schema parser, bounded structural validation, Provider-aware publish validation, and structured 422 issue response foundation.
- Form publish boundary validation before persistence, including unresolved Provider errors and non-blocking renderer warnings.
- Admin Form list/create/edit Layout foundation with canonical schema state and module-scoped styling.
- Registry-backed field palette metadata and DB-write-free draft/publish-preview validation endpoints.
- Accessible field ordering controls, validation issue mapping, and publish warning confirmation boundary.
- Phase 5 frontend verification fixes for canonical JSON safety, local state isolation, idempotent handler registration, supported refetch action, module-qualified Layout inheritance, and async create/publish guards.
- Reproducible module-local TypeScript, Vitest, and Vite packaging with strict runtime declarations and tracked IIFE/CSS outputs.
- Published form schema read access with authentication-mode policies and a safe, allowlisted G7 Layout projection foundation.
