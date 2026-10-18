# Submission Contract

> 상태: Phase 7 implemented, public contract experimental.

Submission은 게시된 `FormVersion` snapshot을 기준으로 서버 검증을 통과한 immutable 접수 기록이다. 내부 PK는 bigint, 공개 route key는 UUIDv7이다. 신규 상태는 `received`이며 Handler가 도입되기 전 자동으로 `processed`가 되지 않는다.

`normalized_payload`는 client body 복사본이 아니라 canonical field key별 Provider serialization 결과다. unknown field는 거부하고 hidden client 값은 무시한다. consent는 statement ID, server UTC acceptedAt, field key, revision을 함께 고정한다.

`form_submission_values`는 비민감 `indexable=true` 값만 저장하는 derived typed projection이다. 원본 권위가 아니며 sensitive field는 어떤 plaintext typed index도 생성하지 않는다.

보존 정책은 제출 시 snapshot으로 저장한다. `days`만 `retention_due_at`을 계산하며 `manual|forever`는 null이다. 자동 purge는 Phase 7 범위가 아니다.

Phase 7은 접수·저장·조회까지만 수행한다. Handler, ProcessingAttempt, webhook, queue, 알림, export, purge scheduler, file은 후속 Phase다.
