# Glitter Form Builder Contracts

> Architecture Freeze: Phase 0.5 / 2026-07-31  
> Module: `glitter-form_builder` (`modules/glitter-form_builder`)  
> Product: `Glitter Form Builder` / Vendor·Author: `Glitter.kr` / License: `MIT` / Version: `0.2.0`

Phase 6의 구현 범위는 published schema read와 read-only preview다. Submission API와 Handler/Event runtime은 frozen future contract를 유지하지만 Phase 7 planned 상태이며 0.1.1에 구현되지 않았다.

## 목적

이 디렉토리는 Form Builder 본체와 외부 Field Provider·Submission Handler·Connector 사이의 공개 경계를 규정한다. Aggregate, 상태, DTO shape, 식별자, 등록, 이벤트, 호환성 및 보안 의미를 동결한다.

**주의:** 현재는 Markdown 규격뿐이다. 실제 PHP interface, DTO, Enum, Registry, Event, Model, migration은 존재하지 않는다. 문서의 pseudo-code를 현재 호출 가능한 코드로 해석하면 안 된다.

## 공개 Contract와 내부 구현

공개 Contract:

- Form Schema envelope와 immutable published snapshot
- Field Provider 및 Submission Handler의 식별자·책임·버전 의미
- 외부 Connector가 사용하는 immutable DTO
- 두 Registry의 등록·조회·충돌 의미
- 공개 Domain Event와 G7 Hook 관찰 표면
- 상태값과 외부에서 관찰 가능한 전이

내부 구현:

- Eloquent Model, Repository, Service, Job
- DB table/column/index/FK
- queue connection, timeout, backoff
- Form Schema → G7 Layout 변환기
- Registry 저장 배열과 locking 구현
- transaction/outbox 구현

외부 확장은 공개 Contract만 의존해야 하며 내부 namespace나 DB를 사용해서는 안 된다.

## 안정성 수준

| 수준 | 의미 |
|---|---|
| `internal` | 본체 구현 전용. 외부 사용 금지, 예고 없이 변경 가능 |
| `experimental` | 외부 시험 가능. 문서화된 version 규칙을 따르지만 `0.x`에서 피드백에 따른 변경 가능 |
| `stable` | 호환 가능한 변경만 허용. breaking change는 contract major 증가 |
| `deprecated` | 기존 동작은 유지하지만 대체 계약과 제거 목표 major를 공지 |

Phase 0.5의 architecture semantics는 frozen이지만 실제 PHP API는 아직 없으므로 모든 제안 interface/DTO/Registry는 `experimental` 후보이다. 첫 독립 Connector 통합·lifecycle·queue 테스트 전에는 `stable`로 선언하지 않는다.

## Versioning

- Form Schema: `schemaVersion` major.minor, 최초 `1.0`.
- Field Contract: provider별 `contractVersion` major.minor, 최초 `1.0`.
- Handler Contract: provider별 `contractVersion` major.minor, 최초 `1.0`.
- Composer/module version: SemVer이며 contract version과 별개다.
- major 불일치: publish/신규 실행 fail-closed.
- 같은 major의 높은 minor: consumer가 요구 capability를 확인할 때만 허용.
- identifier는 version이 아니며 한 번 published snapshot에 사용되면 의미를 재사용하지 않는다.

상세는 [compatibility.md](compatibility.md)를 따른다.

## 호환성 원칙

1. published FormVersion은 in-place migration하거나 다시 쓰지 않는다.
2. unknown provider가 있어도 raw Submission과 snapshot은 보존한다.
3. provider가 없으면 과거 raw 조회/JSON export는 가능하지만 신규 제출·새 실행은 차단한다.
4. optional property 추가는 non-breaking, required property 추가·의미 변경·삭제는 breaking이다.
5. DTO는 ORM Model을 노출하지 않는다.
6. event/queue payload는 raw payload와 secret을 기본 포함하지 않는다.

## 외부 확장 개발 순서

1. [compatibility.md](compatibility.md)에서 지원 major를 선택한다.
2. [security.md](security.md)의 trust/redaction 규칙을 적용한다.
3. Field라면 [field-provider.md](field-provider.md), Handler라면 [submission-handler.md](submission-handler.md)를 구현 대상으로 삼는다.
4. [dto.md](dto.md)의 공개 immutable 값 객체만 교환한다.
5. extension ServiceProvider `boot()`에서 Registry를 명시적으로 resolve/register한다.
6. duplicate, missing provider, inactive provider, version mismatch를 테스트한다.
7. Connector는 [connector-boundary.md](connector-boundary.md)의 단방향 의존만 사용한다.
8. 본체 lifecycle과 Connector enable/disable 순서를 통합 테스트한 뒤 stable 승격을 제안한다.

자동 class scan, DB 등록, runtime unregister는 공개 Contract가 아니다.

## 문서 지도

- [domain-model.md](domain-model.md): Aggregate와 lifecycle
- [form-schema.md](form-schema.md): 동결된 Form Schema
- [field-provider.md](field-provider.md): Field Provider
- [submission-handler.md](submission-handler.md): Handler
- [processing-lifecycle.md](processing-lifecycle.md): 처리·retry·queue
- [events.md](events.md): Domain Event와 Hook
- [registry.md](registry.md): 등록과 충돌
- [dto.md](dto.md): 공개/내부 DTO
- [connector-boundary.md](connector-boundary.md): 외부 의존 방향
- [compatibility.md](compatibility.md): version과 하위 호환
- [security.md](security.md): trust와 개인정보

## G7 근거와 의도적 차이

- Contract 위치 관례: `app/Contracts/*`, module repository contract는 `src/Repositories/Contracts/*`.
- DTO 관례: module `src/DTO`, 값 객체는 불변, `Dto`/`Data` 접미사 금지 (`docs/backend/dto.md:7-15,208-239`).
- Enum 관례: module `src/Enums`, string Backed Enum (`docs/backend/enum.md:7-15,160-170`).
- ServiceProvider는 `register()`에서 container binding, `boot()`에서 runtime 기여자 등록을 수행한다 (`app/Extension/AbstractExtensionServiceProvider.php:80-107`, `modules/_bundled/sirsoft-page/src/Providers/PageServiceProvider.php:49-70`).
- 기존 `IdentityVerificationManager::register()`는 같은 ID를 덮어쓰고 unregister도 제공한다 (`app/Extension/IdentityVerification/IdentityVerificationManager.php:87-105`). Form Builder Registry는 marketplace의 비결정성을 막기 위해 의도적으로 duplicate fail-fast와 seal-after-boot를 채택한다.
