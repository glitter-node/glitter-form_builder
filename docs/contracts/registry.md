# Registry Contract

> `FieldTypeRegistry`와 `SubmissionHandlerRegistry`의 semantics frozen. 실제 class는 아직 없다.

## 공통 모델

두 Registry는 Form Builder module-owned singleton memory registry다. DB source of truth가 아니며 Provider discovery database가 아니다.

최소 기능:

- `register`
- `has`
- `get`/`resolve`
- `all`
- `metadata`
- `validateCompatibility`
- 내부 `seal`

별도 `ProviderRegistry`는 두지 않는다. 각 Registry가 자기 provider metadata 일관성을 검사한다.

## 등록 시점

1. Form Builder ServiceProvider `register()`에서 Registry singleton binding.
2. 외부 extension ServiceProvider `boot()`에서 Registry resolve 후 명시적 register.
3. application boot 완료 시 Form Builder가 Registry를 seal.
4. 요청 처리 중 registry는 read-only.

Laravel는 모든 provider `register()` 후 `boot()`를 호출하므로 외부 provider는 Form Builder implementation service를 import하지 않고 public Registry contract만 resolve해야 한다. load order와 무관하게 Registry singleton은 `register()`에서 준비돼야 한다.

## 외부 등록

```php
// 문서용 pseudo-code. 실제 class/namespace는 아직 없다.
public function boot(FieldTypeRegistry $fields, SubmissionHandlerRegistry $handlers): void
{
    $fields->register($this->app->make(AcmePhoneField::class));
    $handlers->register($this->app->make(AcmeWebhookHandler::class));
}
```

자동 class scan, filesystem reflection, naming convention discovery를 필수로 하지 않는다. config 기반 class list도 본체 내부 convenience일 수 있으나 공개 discovery Contract가 아니다.

## 충돌

- canonical field type/handler ID duplicate: 예외, application boot 실패.
- first-wins/last-wins/조용한 skip 금지.
- 같은 provider ID의 origin/version metadata 충돌: 실패.
- 같은 Provider가 여러 서로 다른 type/handler를 등록하는 것은 허용.
- 동일 instance 재등록도 boot 구성 오류로 간주한다.
- error에는 identifier와 두 registration origin을 포함하되 secret/config는 포함하지 않는다.

기존 G7 `IdentityVerificationManager`는 덮어쓰기와 unregister를 제공하지만 이 Registry는 marketplace determinism을 위해 의도적으로 다르다.

## Version compatibility

`resolve(identifier, requiredVersion, requiredCapabilities)`:

- exact identifier 필요.
- major 일치 필요.
- provider minor가 consumer 요구 minor/capability를 충족해야 함.
- availability는 compatibility와 별개.
- 불일치 시 typed unresolved exception/diagnostic 후보.

## 활성 상태

- active extension ServiceProvider만 등록된다는 G7 lifecycle을 1차 신호로 사용한다.
- DB module table을 Registry가 직접 조회하지 않는다.
- provider `availability`는 config/local capability 진단이며 extension activation을 대신하지 않는다.
- active→inactive는 다음 process/bootstrap에서 provider 부재로 반영한다.
- long-running worker는 extension cache/version 변화 후 재시작이 필요할 수 있다. 정확한 worker recycle mechanism은 lifecycle 구현 단계 deferred.

## Cache와 process lifecycle

- Registry map 자체는 process memory.
- provider metadata를 persistent cache에 source of truth로 저장하지 않는다.
- admin catalog 성능을 위한 derived cache는 extension cache version으로 무효화할 수 있으나 provider object/callable을 serialize하지 않는다.
- PHP-FPM request/process와 queue worker는 각자 bootstrap에서 동일 registration 결과를 가져야 한다.
- registration order가 `all()` 결과나 dispatch 의미에 영향을 주지 않도록 canonical identifier로 정렬해 노출한다.

## Discovery와 lazy loading

- 자동 scan: 미지원 Contract.
- lazy registration: 미지원.
- resolve 시 service container에서 처음 만드는 lazy provider object는 내부 최적화 가능하지만 identifier/metadata 등록은 bootstrap에 끝나야 한다.
- provider code가 resolve될 때 새 identifier를 등록하는 side effect 금지.

## Unregister

- runtime dynamic unregister 공개 API 없음.
- provider disable/remove는 G7 extension deactivate/uninstall + 새 bootstrap으로 반영.
- test reset helper는 `internal`, production Contract가 아니다.
- sealed Registry의 register/unregister 시도는 예외.

## Phase 3 실제 구현 대응

`FieldTypeRegistry`는 `src/Fields/FieldTypeRegistry.php`에 experimental class로 구현되었다. `register`, `has`, `resolve`, stable-sorted `all`, `metadata`, `validateCompatibility`, `seal`, `isSealed`를 제공한다. `unregister`와 automatic discovery는 없다. `FormBuilderServiceProvider`가 singleton을 bind하고 built-in 11종을 명시 등록한 뒤 application `booted()`에서 seal한다. Handler Registry는 아직 구현되지 않았다.

## 기존 Proposed pseudo-code

```php
// 문서용 pseudo-code.
interface FieldTypeRegistry
{
    public function register(FieldProvider $provider): void;
    public function has(string $type): bool;
    public function get(string $type): FieldProvider;
    public function resolve(string $type, string $version, array $capabilities = []): FieldProvider;
    public function all(): array;
    public function metadata(): array;
    public function validateCompatibility(string $type, string $version, array $capabilities = []): CompatibilityResult;
}

interface SubmissionHandlerRegistry
{
    public function register(SubmissionHandler $handler): void;
    public function has(string $handlerId): bool;
    public function get(string $handlerId): SubmissionHandler;
    public function resolve(string $handlerId, string $version, array $capabilities = []): SubmissionHandler;
    public function all(): array;
    public function metadata(): array;
    public function validateCompatibility(string $handlerId, string $version, array $capabilities = []): CompatibilityResult;
}
```

Interface 배치 후보는 module-owned `src/Contracts`, Registry implementation은 internal namespace다. 정확한 파일명은 Phase 3/9에서 정한다.
