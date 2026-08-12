# Contract Security

## Phase 4 canonical schema validation

- parser는 object/resource/Closure와 invalid UTF-8 등 JSON data가 아닌 값을 fail-closed한다.
- `SchemaLimits`는 256 KiB encoded schema, fields 100, options 100/field, locales 20/field, label 255, description 2,000, depth 16, nodes 10,000을 강제한다.
- arbitrary field/rule/callback/class/renderer property는 allowlisted envelope/field key 또는 Provider configuration validation을 통과해야 한다.
- Provider validation exception은 stable issue로 sanitize하며 exception class/message/stack/raw default/config를 API에 노출하지 않는다.
- machine issue parameter는 limit/property/locale 같은 safe metadata만 허용한다.
- renderer unavailable은 별도 warning이고 renderer가 실제 존재한다는 의미가 아니다.

## Server authority

- FormRequest가 envelope/access/rate/idempotency의 최종 권위.
- Field Provider server validation/normalization이 field 값의 최종 권위.
- frontend renderer validation은 UX 보조.
- hidden field와 client-supplied metadata를 신뢰하지 않는다.
- unknown provider/version/renderer mismatch는 fail-closed.

## Provider trust boundary

Provider는 server에서 실행되는 trusted extension code이므로 설치 자체가 높은 권한 행위다. Contract는 실수·과도한 결합·정보 노출을 줄이지만 악성 PHP extension을 완전히 sandbox하지 못한다.

- Provider install/activate는 G7 권한/lifecycle을 따른다.
- Registry origin/identifier/version을 진단 가능하게 기록한다.
- Provider에 ORM Model, Request, unrestricted container, 다른 field raw를 Contract로 전달하지 않는다.
- Field Provider는 network/queue/transaction side effect를 수행하지 않는다.
- Handler는 execution layer 밖에서 Job/retry를 만들지 않는다.

## Payload와 redaction

금지 위치:

- application/activity/processing log
- public Domain Event/G7 Hook
- queue Job payload
- exception message/context
- Handler result metadata

금지 데이터:

- raw payload 전체
- sensitive field value
- secret/token/auth header
- 외부 response body
- attachment content

UUID, status, handler ID/version, stable error code와 opaque external reference만 기본 기록한다.

## Sensitive field

- `sensitive=true`이면 `indexable=false`.
- 관리자 list preview에서 mask.
- 상세 조회와 export는 별도 permission/audit.
- 삭제/anonymize는 raw와 typed projection을 같은 작업 단위로 처리.
- provider missing fallback도 escaped raw를 무조건 노출하지 않고 permission/redaction metadata를 적용한다.

## Secret

- config와 secret reference 분리.
- secret은 FormVersion/Binding public resource/Attempt/event/Job에 복제하지 않는다.
- API는 present/last-updated 같은 metadata만 반환.
- scoped accessor는 serialize/log 금지.
- 구체 encryption/vault service는 Phase 9 deferred이며 평문 임시 저장으로 대체하면 안 된다.

## Export

- `glitter-form_builder.submissions.export` permission 필수.
- `exportable`은 permission 대체가 아니다.
- sensitive export는 목적 확인/감사/최소 범위가 필요하다.
- provider formatter가 없어도 canonical raw JSON export는 가능하지만 동일 permission/redaction 정책 적용.
- 영속 export file은 private disk + TTL 없이는 만들지 않는다.

## Activity log/Event/Queue

- activity log에는 action과 UUID만, payload 값 없음.
- public event/hook payload는 [events.md](events.md)의 redacted DTO만 사용.
- Job에는 Attempt UUID 같은 identity만 넣고 worker가 authorization 가능한 context를 재구성.
- Eloquent Model serialization로 전체 relation/payload가 새어 나가지 않게 한다.

## Anonymous submission

- 기본 `authenticated_only`, guest는 Form별 `guest_and_authenticated` opt-in.
- published Form만 제출.
- schema GET과 submit POST middleware 분리.
- named rate limiter 필수; exact rate deferred.
- CAPTCHA는 optional provider, 필수 의존 아님.
- honeypot/작성 시간 검사는 접근성·오탐 검증 후.
- IP/User-Agent 보존은 기본 필수가 아니며 개인정보 정책과 retention을 적용.

## File field

MVP 미지원이며 schema/Registry에 예약 type도 노출하지 않는다. 익명 임시 소유권, claim, orphan cleanup, MIME sniffing, size, malware hook, private disk, download authorization가 Phase 11에서 해결되기 전 활성화하지 않는다.

## Renderer/XSS

- Form Schema는 arbitrary G7 Layout/HTML/handler/script를 포함하지 않는다.
- text/textarea/label/option/provider fallback은 escaped text.
- rich text 미지원.
- renderer descriptor는 allowlisted capability data이며 component/prop/handler를 임의 실행하지 않는다.
- URL 속성은 scheme/host 정책으로 검증한다.
- server validator와 renderer major mismatch는 publish/submit 차단.

## Handler/network

- Webhook은 SSRF, DNS rebinding, redirect, private address, scheme, timeout, response size를 통제해야 한다.
- idempotency key를 외부에 전달하되 secret으로 간주하지 않는다.
- external reference를 HTML/URL로 신뢰하지 않는다.
- health probe를 application boot의 무제한 network call로 만들지 않는다.

## Failure policy

- Handler 실패는 committed Submission을 삭제/rollback하지 않는다.
- 오류 detail은 redacted stable code.
- provider unavailable은 신규 field submit fail-closed, Handler는 다른 Binding과 격리.
- duplicate Registry ID는 application boot fail-fast하여 비결정 provider 선택을 막는다.
