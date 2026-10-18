# Connector Boundary Contract

## Dependency direction

```text
External Connector
  ├── depends on Form Builder public Contracts/DTO
  ├── registers Field Provider and/or Submission Handler
  └── calls its target domain's public Service

Form Builder
  └── knows only public Provider/Handler contracts and opaque results
```

Form Builder는 Connector class나 target module을 import하지 않는다.

## 허용

- Connector ServiceProvider가 Form Builder Registry에 명시적으로 등록.
- Handler가 Form Builder `HandlerExecutionContext`를 받음.
- Connector가 자기 설정/secret을 관리.
- Connector가 target module의 문서화된 public Service/Contract를 호출.
- Webhook Connector가 운영자가 설정한 endpoint를 SSRF 방어 후 호출.
- result로 opaque external reference와 redacted metadata 반환.
- target unavailable을 retryable/permanent exception으로 분류.

## 금지

- Form Builder가 CRM/Reservation/Board/Ecommerce Model, Repository, Service를 import.
- Form Builder 또는 Connector가 target DB table을 직접 query/write.
- Form Builder가 installed module identifier로 target-specific branch.
- 외부 route/API URL hardcode.
- Connector가 Form Builder internal Service/Model/DB를 참조.
- Handler가 Form Builder transaction을 제어하거나 자기 Queue Job으로 execution policy를 우회.
- Form Builder purge가 외부 업무 데이터를 cascade 삭제.

## 실제 G7 사례

`sirsoft-ecommerce`의 `ProductInquiryService`는 Board class를 import하지 않고 Hook filter로 설정/조회/생성을 요청한다 (`modules/_bundled/sirsoft-ecommerce/src/Services/ProductInquiryService.php:15-20,77-103,222-262`). 이는 느슨한 연결 사례지만 반환형·durable retry가 약하므로 Form Builder 업무 전달은 versioned Handler Contract를 사용하고 Hook는 관찰에 제한한다.

## Module 또는 Plugin

Connector가 다음을 가지면 module:

- 독립 domain records
- 관리자/user full page
- 자체 API/permission/menu
- 복잡한 lifecycle/migration

다음에 그치면 plugin 후보:

- 기존 target 기능에 adapter 제공
- 소규모 설정
- Handler/Field 등록과 Hook listener
- 독립 업무 UI/domain이 없음

G7 extension type은 package 규모와 lifecycle 책임으로 정하며 “Connector” 자체가 별도 G7 type은 아니다.

## 설치·비활성화

- Form Builder는 Connector 없이 설치/활성 가능.
- Connector는 Form Builder module dependency/version을 manifest에 선언해야 한다.
- Connector deactivate/uninstall은 Provider를 다음 bootstrap에서 제거한다.
- draft에는 unresolved diagnostic, published Form은 해당 Field가 있으면 신규 제출 차단.
- Handler Binding은 unresolved 상태가 되고 다른 Handler는 계속 실행.
- 과거 FormVersion/Submission/Attempt/result metadata는 보존.
- Connector 재활성 후 compatible provider는 재해석 가능하지만 자동 replay하지 않는다.

## 외부 Domain Service 책임

Connector가 target public Service와의 mapping, transaction, authorization/service account, target idempotency, 오류 번역을 소유한다. Form Builder는 target object 존재성이나 상태 전이를 판단하지 않는다.

target이 public Service Contract를 제공하지 않으면 내부 Model/Repository 접근으로 우회하지 않고 그 Connector를 보류한다.

## 첫 Contract 검증 후보

| 후보 | 장점 | 위험 | 결정 |
|---|---|---|---|
| in-memory/simple test Handler | collision/version/DTO/retry를 외부 network 없이 검증 | marketplace/lifecycle 현실성이 낮음 | Phase 3/9 conformance fixture로 사용 |
| Webhook Connector | target 비의존, config/secret/idempotency/retry/SSRF 검증 가능 | network/secret/timeout 보안 설계 필요 | 첫 독립 Connector 우선 후보 |
| CRM Connector | 실제 업무 mapping 검증 | `glitter-crm` 부재, public Service 불명 | 후속 |
| Reservation Connector | idempotency·충돌 복잡성 검증 | availability/domain coupling 큼 | 후속 |

따라서 내부 conformance fixture로 contract를 먼저 검증하고, 첫 배포형 후보는 Webhook Connector다. 이번 Phase에서는 어느 것도 구현하지 않는다.

## CRM/Reservation

현재 `glitter-crm`은 존재하지 않으며 CRM Connector를 확정하지 않는다. CRM/Reservation/Board/Ecommerce 연동은 각 target의 public Contract가 확인된 후 별도 설계·package로 수행한다.

