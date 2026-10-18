# Submission API Contract

`POST /api/modules/glitter-form_builder/forms/{slug}/submissions`는 `{fields, context?: {locale?, timezone?}}`만 허용한다. 최대 body는 256 KiB이고 field value는 Provider가 받는 scalar만 허용한다. `Idempotency-Key`는 printable ASCII 16~128자다.

- 신규 201, 동일 replay 200, 다른 payload conflict 409, validation 422, access 불허 404, rate limit 429.
- 공개 receipt는 submission UUID/status/received_at과 form slug/revision만 노출한다.
- `GET /admin/submissions`와 단건 API는 `glitter-form_builder.submissions.read`가 필요하다.
- 관리자 상세도 sensitive field를 가리며 raw normalized payload를 직접 반환하지 않는다.
