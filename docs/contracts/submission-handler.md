# Submission Handler Contract

> Architecture `frozen`, proposed PHP API stability `experimental`. 실제 interface는 아직 없다.

## 책임

Handler는 Submission 결과를 외부 업무로 전달하는 Connector 실행 계약이다.

- identifier/provider/contract version
- display/capability metadata
- configuration schema/validation
- secret reference 요구사항
- 지원 execution mode
- availability/health descriptor
- execute
- idempotency 준수
- retryable/permanent failure 분류
- redacted result metadata와 external reference 반환

Handler는 Submission 저장 transaction, Form Builder Model/Repository, Queue Job 생성 정책을 소유하지 않는다.

## Identifier

- provider ID: lowercase `^[a-z0-9][a-z0-9_-]{1,63}$`.
- local handler: lowercase `^[a-z][a-z0-9_-]{0,63}$`.
- canonical handler ID: `{providerId}:{localHandler}`, 최대 129자.
- 모든 Handler는 namespace를 포함한다. built-in도 예: `glitter-form_builder:webhook` 후보처럼 qualified ID를 쓴다.
- rename/reuse 금지; 새 ID + deprecation.

## Contract version과 metadata

- version은 major.minor, 최초 `1.0`.
- major 불일치 시 binding unresolved.
- metadata: ID, provider, version, localized label, modes, capabilities, availability, config schema hash, redaction paths.
- Binding/Attempt snapshot에는 ID/version/fallback label/capabilities만 저장하며 implementation class/secret은 제외한다.

## Configuration과 secret

- 일반 configuration은 JSON-serializable하고 Provider schema로 검증한다.
- secret 값은 별도 encrypted storage 또는 scoped secret reference다.
- Binding API/FormVersion/Attempt/event/log/queue payload에 평문 또는 암호문 secret을 복제하지 않는다.
- Handler는 실행 시 scoped non-serializable accessor로 필요한 secret만 얻는 후보이며 구체 secret service는 deferred다.

## Execution mode

mode는 **Binding별** `sync|async|disabled`.

- `sync`: Submission commit 후 같은 process execution layer가 호출.
- `async`: commit 후 Form Builder Job/executor가 호출. queue driver가 sync면 즉시 실행될 수 있음.
- `disabled`: 신규 Attempt를 만들지 않는다. Binding 설정/과거 이력만 유지.
- `auto`: 미지원.

Handler descriptor는 지원 mode를 선언할 수 있으나 Binding mode 결정은 Form Builder가 검증한다.

## Execute

- immutable `HandlerExecutionContext`만 받는다.
- stable idempotency key를 반드시 받는다.
- raw payload 전체 대신 normalized field map과 field sensitivity metadata의 read-only view를 기본으로 한다.
- 원본 접근 capability는 별도 privileged contract가 생기기 전 제공하지 않는다.
- external system 호출과 자기 target domain public Service 호출은 Connector 책임이다.
- Form Builder DB transaction을 commit/rollback하거나 자체 Queue Job을 생성하지 않는다.

## Idempotency

logical operation key는:

```text
sha256("glitter-form_builder:v1" + submissionUuid + bindingUuid + operationGeneration)
```

구분자는 ambiguity가 없도록 length-prefix 또는 canonical tuple encoding을 써야 하며 정확한 byte encoding은 Phase 9 test vector에서 확정한다. 같은 logical retry는 같은 key, 명시적 “새 작업으로 재실행”만 generation을 증가시킨다.

Handler/Connector는 외부 시스템에 이 key 또는 동등한 unique reference를 사용해 중복 생성을 막아야 한다.

## Failure

- `RetryableHandlerException`: timeout, rate limit, temporary network/5xx.
- `PermanentHandlerException`: invalid config/mapping, unsupported contract, 외부 validation rejection.
- unknown exception은 redaction 후 execution policy가 제한 retry 또는 exhausted로 분류한다.
- Handler가 직접 retry/dispatch하지 않는다.

실제 exception class는 아직 없다.

## Result

성공 결과:

- outcome
- nullable opaque `externalReference`
- JSON-safe redacted metadata
- optional provider status code

외부 response body 전체, token, PII는 금지한다. `externalReference`는 Attempt에 저장되며 외부 객체 FK/소유권이 아니다.

## Multiple handlers

- Binding order는 deterministic dispatch/UI 순서.
- 각 Binding은 독립 실행하며 병렬 완료 가능.
- 한 Handler 실패는 다른 Handler를 막지 않는다.
- MVP critical/non-critical 구분 없음. 모두 Submission 접수에는 non-blocking.
- sync 실패도 Submission commit/접수 응답을 rollback하지 않는다.

## Provider lifecycle

- duplicate ID: application boot fail-fast.
- inactive/missing provider: 신규 execution claim 금지.
- enabled unresolved Binding은 Attempt를 만들 수 없으며 processing plan 생성 시 `provider_unavailable` 진단을 기록한다. 이미 만들어진 pending Attempt는 `skipped`.
- disabled Binding은 Attempt를 만들지 않는다.
- 과거 Attempt/result snapshot 보존.
- compatible provider 복귀 후 관리자가 retry 가능. 자동 replay 금지.

## Health

`availability()`는 local config/capability 점검만 하는 선택적 descriptor다. 외부 network health 요청을 application boot/publish의 필수 동작으로 만들지 않는다. active probe와 timeout은 deferred다.

## Logging/redaction

허용: UUID, handler/provider/version, state, duration, retry count, stable error code, opaque external reference.

금지: raw payload, sensitive values, config secret, auth header, response body, stack context의 PII.

## Proposed interface pseudo-code

```php
// 문서용 pseudo-code. 현재 존재하지 않는다.
interface SubmissionHandler
{
    public function descriptor(): HandlerDescriptor;
    public function configurationSchema(): array;
    public function validateConfiguration(HandlerConfiguration $configuration): ConfigurationValidation;
    public function availability(HandlerConfiguration $configuration): HandlerAvailability;

    /** retryable/permanent typed exception */
    public function execute(HandlerExecutionContext $context): HandlerExecutionResult;
}
```

## Proposed DTO pseudo-code

```text
HandlerExecutionContext (public, immutable, not blindly loggable)
  attemptId, submissionId, formId, revision
  handlerId, contractVersion, idempotencyKey
  normalizedValues, fieldMetadata
  validatedConfiguration, scopedSecretAccessor

HandlerExecutionResult (public, immutable, serializable after redaction)
  outcome, externalReference?, metadata?, providerCode?
```

실제 PHP 이름에는 G7 규칙상 `Data`/`Dto` 접미사를 붙이지 않는다.

