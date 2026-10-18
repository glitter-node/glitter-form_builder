# Submission Security Contract

- published immutable snapshot만 사용하고 Provider 실패는 fail-closed한다.
- unknown/nested/oversized input을 거부하고 hidden client 값을 무시한다.
- consent timestamp는 server UTC로 생성한다.
- IP/user-agent는 application-key HMAC만 저장한다.
- raw request, values, hashes를 로그/event에 포함하지 않는다.
- sensitive field typed index를 만들지 않고 admin detail에서 redaction한다.
- access 불허는 404로 숨긴다.
- guest 10/min/form/IP-HMAC, authenticated 30/min/form/user다.

Handler, ProcessingAttempt, webhook, notification, export, purge, file은 후속 Phase다.
