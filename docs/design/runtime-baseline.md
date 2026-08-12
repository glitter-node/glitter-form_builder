# Glitter Form Builder 런타임 기준선

> 확인일: 2026-07-31 (Asia/Seoul)  
> 이 문서는 민감한 `.env`, DB 이름·계정·host, Redis/Reverb endpoint, cookie 값을 기록하지 않는다.

## 확인 결과

| 항목 | 확인값 | 판정/근거 |
|---|---|---|
| 프로젝트 경로 | `/volume1/Gnuboard/7/Gnuboard7Official` | 사용자 지정 경로와 `pwd` 일치 |
| 운영 검증 URL | `https://gnuboard7official.glitter.kr/` | HEAD 200, GET HTML 확인 |
| G7 version | `7.0.5` | 운영 HTML generator meta. 로컬 `vendor-bundle.json`/manifest와 함께 구현 시 재확인 필요 |
| PHP | 8.3.27 | `/usr/local/bin/php83 --version` |
| Laravel | 12.62.0 | Artisan version |
| Composer PHP constraint | `^8.2` | `composer.json` |
| Node | v20.19.5 | `node --version` |
| npm | 10.8.2 | `npm --version` |
| DB connection kind | `mysql` Laravel driver | 민감값을 제외한 config 확인 |
| 실제 DB engine/version | MariaDB 10.11.11 (`-log`) | read-only `SELECT VERSION()`/`@@version_comment` |
| DB charset/collation | `utf8mb4` / `utf8mb4_unicode_ci` | read-only connection attributes |
| G7 공식 DB 하한 | MySQL 8.0+ / MariaDB 10.3+ | `README.md`, `docs/requirements.md` |
| effective queue driver | `sync` | Laravel bootstrap 후 `config('queue.default')` |
| effective cache driver | `file` | Laravel bootstrap 후 `config('cache.default')` |
| effective session driver | `file` | Laravel bootstrap 후 `config('session.driver')` |
| effective filesystem disk | `local` | Laravel bootstrap 후 `config('filesystems.default')` |
| effective broadcast driver | `null` | Laravel bootstrap 후 `config('broadcasting.default')` |
| Redis 구성 | config section과 `.env` 관련 key 존재 | 실제 사용 중이라고 단정하지 않음 |
| Reverb 구성 | config section과 `.env` 관련 key 존재 | effective broadcast가 `null`이므로 현재 사용 중이라고 단정하지 않음 |
| 활성 admin template | `sirsoft-admin_basic` | `g7_templates.status=active`, read-only |
| 활성 user template | `sirsoft-basic` | `g7_templates.status=active`, read-only |
| module lifecycle | list/install/activate/deactivate/uninstall/update/cache-clear/refresh-layout 존재 | `artisan list` |
| plugin lifecycle | list/install/activate/deactivate/uninstall/update/cache-clear/refresh-layout 존재 | `artisan list` |

## 환경 설정 확인 방식과 주의

- `.env`에서는 `APP_ENV`, `APP_URL`, `DB_CONNECTION`, `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`, `FILESYSTEM_DISK` key가 모두 문자열로 존재함만 확인했다.
- `APP_ENV=production`과 APP URL의 `https` scheme만 기록했다. URL 외 민감값은 기록하지 않았다.
- `.env` 표면 driver와 Laravel bootstrap 뒤 effective driver가 달랐다. `SettingsServiceProvider`가 `storage/app/settings/*.json`을 읽어 cache/session/queue 설정을 override할 수 있으므로 Form Builder는 `.env`를 직접 읽지 않고 `config()`의 effective 값을 사용해야 한다.
- 이 기준선에서 effective queue는 `sync`다. DB에는 `g7_jobs`, `g7_failed_jobs`가 존재하지만 이것만으로 worker가 동작한다고 판단하지 않는다.
- DB schema metadata는 PDO의 `FETCH_OBJ`, `FETCH_ASSOC` 모두 `TABLE_NAME`, `COLUMN_NAME`, `DATA_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`처럼 대문자 key로 반환됐다. 향후 호환성 코드는 `(array)` 변환 후 key를 lowercase로 정규화하고 `array_key_exists()`로 필수 key를 검사해야 한다.

근거:

- `app/Providers/SettingsServiceProvider.php:326`
- `docs/testing-guide.md:683`
- `docs/backend/admin-settings-access.md:39-75`
- `config/queue.php:16-72`

## 운영 URL 읽기 전용 확인

- `HEAD /`: HTTP 200, `text/html; charset=utf-8`.
- `GET /`: GnuBoard7 7.0.5 generator meta와 `sirsoft-basic` template asset URL을 확인했다.
- 로그인, 회원가입, 제출, API write 요청은 실행하지 않았다.
- 응답 cookie와 보안 header의 값은 이 문서에 기록하지 않았다.

## lifecycle와 manifest 기준

- module manifest identifier는 디렉토리 identifier와 일치한다.
- bundle source는 `modules/_bundled/{identifier}`이며 install/update가 active `modules/{identifier}`에 반영한다.
- module API에는 `/api/modules/{identifier}`와 route name `api.modules.{identifier}.`가 자동 prefix된다.
- 조사한 module composer package는 모두 `modules/<identifier>`다.
- 이 Phase에서는 install/update/activate/deactivate/uninstall, migration, cache clear를 실행하지 않았다.

근거:

- `modules/_bundled/gnuboard7-hello_module/module.json:1-23`
- `modules/_bundled/gnuboard7-hello_module/composer.json:1-22`
- `docs/extension/module-commands.md:29-46,72-146`
- `docs/extension/module-routing.md:61-77`

## 실제 확인 명령

민감값을 출력하지 않도록 Laravel bootstrap 코드는 key 존재/type 및 allowlist config만 출력했다.

```text
pwd
git status --short
git diff -- AGENTS.md
git diff -- modules/_bundled/glitter-media_manager
/usr/local/bin/php83 --version
/usr/local/bin/php83 artisan --version
node --version
npm --version
jq '.scripts' package.json
/usr/local/bin/php83 artisan list
/usr/local/bin/php83 artisan route:list --json
/usr/local/bin/php83 artisan route:list --path=api/modules/sirsoft-page --except-vendor
/usr/local/bin/php83 -r '<Laravel bootstrap + read-only config/DB metadata query>'
curl -sS -I --max-time 20 https://gnuboard7official.glitter.kr/
curl -sS -L --max-time 20 --range 0-4095 https://gnuboard7official.glitter.kr/
rg / nl / sed / find / jq 기반 문서·코드 조사
git status --short
git diff -- modules/_bundled/glitter-form_builder
```

## 확인하지 못했거나 의도적으로 확인하지 않은 항목

- queue worker process가 실제 상시 실행되는지: effective driver가 `sync`라 Phase 0-B 결정에 불필요하며 process/daemon 설정을 추측하지 않았다.
- Redis/Reverb endpoint, credential, port 및 연결 성공 여부: 민감·운영 범위이므로 확인하지 않았다.
- DB `max_allowed_packet`, InnoDB page size, SQL mode: migration 전 Phase 2 compatibility gate로 넘겼다.
- MySQL 8.0 실서버 DDL: 현재 서버가 MariaDB이므로 별도 test matrix가 필요하다.
- native Node binary/ACL/build freshness: frontend 변경과 build를 하지 않은 문서 단계라 실행하지 않았다.
- production 로그인/권한 화면: 영속 상태를 만들 수 있어 실행하지 않았다.
- module lifecycle 적용: 구현 파일이 없고 명시적으로 금지됐다.
