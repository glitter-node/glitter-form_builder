# Submission Idempotency Contract

좌표는 `(form_version_id, actor scope, Idempotency-Key)`다. actor scope는 form UUID와 user ID 또는 HMAC 처리된 guest IP scope로부터 SHA-256으로 생성한다.

payload hash는 object key recursive lexical sort, array order 보존, compact UTF-8 JSON의 SHA-256이다. application pre-check와 transaction lock을 수행하며 DB unique constraint가 race의 최종 권위다. key가 없으면 fallback key를 영속하지 않는다.

timeout/동일 payload 재시도는 같은 key를 사용한다. 값 변경 또는 성공 후 새 제출은 새 key를 생성한다. hash와 payload는 로그에 기록하지 않는다.
