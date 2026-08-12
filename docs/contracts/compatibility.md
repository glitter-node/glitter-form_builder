# Contract Compatibility

## Version domains

| Domain | Version | 범위 |
|---|---|---|
| module package | SemVer `0.2.0` | install/update와 전체 release |
| Form Schema | major.minor `1.0` | envelope와 built-in semantics |
| Field Contract | major.minor `1.0` | Provider/DTO/capability |
| Handler Contract | major.minor `1.0` | Handler/DTO/failure semantics |
| Event Contract | major.minor `1.0` 후보 | event/hook payload |

package version 증가는 contract major 증가를 자동 의미하지 않는다. 각 snapshot/descriptor가 자기 contract version을 가진다.

## Stability

| Phase | 수준 |
|---|---|
| Phase 1 skeleton | `internal` |
| Phase 2 domain model/serialization | `experimental` |
| Phase 3 Field Contract/Registry | `experimental` |
| Phase 7 Submission DTO/event | `experimental` |
| Phase 9 Handler/processing | `experimental` |
| 첫 독립 Connector conformance + lifecycle 통과 | `stable` 후보 |

Phase 0.5의 architecture freeze는 “의미를 구현 전에 바꾸지 않는다”는 뜻이며 PHP signature stable 선언이 아니다.

## Breaking change

- identifier rename/reuse
- major version 의미 변경
- required property 추가/삭제/rename
- property type 또는 canonicalization 변경
- 기존 valid schema/input을 거부하는 validation 강화
- 상태 제거/rename/terminal 의미 변경
- event/hook 이름 또는 payload required field 변경
- idempotency key 의미/derivation 변경
- secret/redaction 보장 약화

## Non-breaking change

- optional metadata 추가
- 기존 의미를 보존하는 새 capability
- 새 field/handler identifier 추가
- 새 event 추가
- 같은 major에서 optional validation rule 지원 추가
- 내부 구현/DB index/cache 변경

consumer가 unknown optional property를 round-trip 보존해야 할 수 있으며 실행 의미를 추측하면 안 된다.

## Provider compatibility matrix

| Snapshot required | Runtime provider | 결과 |
|---|---|---|
| `1.0` | absent | history fallback만, publish/submit/execute 차단 |
| `1.0` | `1.0` | compatible |
| `1.0` | `1.x` + required capabilities | compatible |
| `1.2` | `1.1` | incompatible |
| `1.x` | `2.x` | incompatible |
| unknown major | any | fail-closed |

minor 숫자만 비교하지 않고 declared capability도 검사한다.

## Deprecation

- descriptor에 deprecated flag, replacement ID, removal target major를 제공.
- published snapshot의 deprecated provider는 compatible한 동안 동작 가능하나 관리자 경고.
- identifier는 다른 의미로 재사용 금지.
- stable Contract 제거는 최소 한 major migration window와 changelog 필요.

## Published FormVersion

- in-place schema/provider snapshot rewrite 금지.
- 새 interpretation/index projection은 derived data로 생성.
- schema 변경은 새 revision.
- provider major migration은 explicit clone/upgrade command가 새 draft/revision을 만들며 원본 유지.
- raw Submission은 제출 당시 version으로 해석한다.

## Unknown field/handler

- field draft: round-trip + diagnostic.
- field publish/new submit: fail-closed.
- history: raw JSON export/fallback display.
- handler Binding: unresolved로 보존, 신규 실행 금지; 다른 Handler는 계속.
- pending Attempt에서 handler missing: skipped.
- provider 복귀: compatible하면 manual retry/derived rebuild 가능.

## Migration policy

- module DB migration과 contract migration을 분리한다.
- migration은 published JSON 의미를 조용히 바꾸지 않는다.
- additive DB migration은 rollback/lifecycle test 필요.
- contract major upgrade는 compatibility reader 또는 explicit upgrade tooling을 제공하기 전 기존 snapshot을 제거하지 않는다.
- Provider가 자기 임의 migration으로 Form Builder snapshot을 직접 update하면 안 된다.

## Downgrade

- newer schema/contract major를 이해하지 못하는 module downgrade는 write/publish/processing을 fail-closed한다.
- raw snapshot과 unknown property를 삭제하지 않는다.
- destructive automatic downgrade migration 금지.
- 지원 downgrade matrix가 문서화되지 않은 version으로의 downgrade는 unsupported다.

## Deferred

- exact event contract version storage
- canonical JSON/hash algorithm
- minor capability negotiation class/API
- stable 승격 기준의 구체 conformance suite
- major migration CLI/API
