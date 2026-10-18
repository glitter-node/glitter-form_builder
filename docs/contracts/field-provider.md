# Field Provider Contract

> Architecture `frozen`, PHP API stability `experimental`. Phase 3 구현 경로는 `src/Contracts/Fields/FieldProvider.php`이며 실제 method signature는 아래 구현 대응 절을 따른다.

## 책임

Field Provider는 UI component 별칭이 아니라 field의 end-to-end 의미를 제공한다.

- type/provider/contract identifier
- display/capability metadata
- configuration schema와 schema validation
- default/options validation
- Submission validation rule descriptor
- normalization과 canonical serialization
- safe display/export formatting
- typed index projection
- sensitivity/redaction metadata
- renderer descriptor

Provider는 DB transaction, Eloquent Model, HTTP Request, Layout engine 내부 Registry를 직접 제어하지 않는다.

## Identifier

- built-in type: `^[a-z][a-z0-9_]{0,63}$`, 예: `text`.
- provider ID: lowercase `^[a-z0-9][a-z0-9_-]{1,63}$`, G7 extension identifier와 일치.
- external local type: `^[a-z][a-z0-9_-]{0,63}$`.
- external canonical type: `{providerId}:{localType}`, 최대 129자.
- 대소문자 normalization은 하지 않고 uppercase 입력을 거부한다.
- published snapshot에 사용한 identifier의 rename/reuse 금지. 새 type ID를 등록하고 구 type은 deprecated 처리한다.
- built-in provider ID는 `glitter-form_builder`.

같은 provider ID로 여러 type을 등록할 수 있다. 동일 provider ID에서 version/display origin metadata가 상충하면 등록 실패다.

## Contract version

- major.minor 문자열, 최초 `1.0`.
- major 불일치: unresolved.
- minor 호환은 Registry가 required capabilities를 검사해 결정한다.
- provider 구현 version과 module package version은 별개다.

## Metadata

최소:

- canonical field type
- provider ID
- contract version
- localized display name/description key 또는 safe locale map
- storage kind
- supported validation/config properties
- sensitivity default와 redaction kind
- index/export capabilities
- renderer descriptor version/capability
- provider availability

published snapshot에는 identifier/version/storage/redaction/fallback label/capability hash를 저장한다. class name, callable, secret은 저장하지 않는다.

## Registration

- 외부 extension ServiceProvider `boot()`에서 `FieldTypeRegistry`를 resolve하여 명시적으로 `register`.
- 모든 provider `register()`가 끝난 application boot 완료 시 Registry seal.
- 자동 class scan/config file scan은 필수 Contract가 아니다.
- lazy registration, runtime unregister, overwrite는 금지.
- duplicate canonical type은 동일 object 재등록도 오류로 처리하는 fail-fast.
- provider 활성/비활성은 G7 lifecycle이 provider ServiceProvider 로딩 여부로 표현한다.

상세는 [registry.md](registry.md).

## Schema/config validation

- Provider configuration schema는 JSON-serializable data descriptor다.
- draft에서는 unknown provider object round-trip을 허용한다.
- publish에서는 provider가 config와 type-specific schema를 완전히 검증해야 한다.
- Provider는 임의 PHP class/closure/실행 표현식을 schema에 넣을 수 없다.
- options/default는 publish와 submit 모두 같은 semantics를 사용한다.

## Submission validation과 normalization

1. FormRequest가 envelope/size/access/idempotency를 검증한다.
2. Field Provider가 자기 field config에 맞는 allowlisted server validation descriptor를 제공한다.
3. server가 raw input을 검증한다.
4. Provider가 canonical normalized scalar/array를 반환한다.
5. Form Builder가 raw snapshot과 normalized/index projection을 저장한다.

frontend renderer 검증은 권위가 아니다. Provider가 Laravel Rule을 반환할지 독립 validator를 구현할지는 실제 API 단계에서 deferred지만, 외부 Provider가 임의 container/service를 통해 validation을 우회할 수는 없다.

## Serialization, display, export, index

- serialization은 deterministic하고 JSON-safe해야 한다.
- display formatter는 HTML을 반환하지 않고 escaped text/value descriptor를 반환한다.
- export formatter는 권한/locale context를 받고 민감성 규칙을 보존한다.
- index projection은 명시 type + scalar만 반환한다.
- `sensitive=true`이면 projection 없음.
- provider formatter가 없어도 raw JSON export는 가능하다.

## Renderer descriptor

descriptor는 검증된 Form Renderer capability를 가리키는 data다. 임의 G7 Layout component/prop/handler 이름을 실행 지시로 주입할 수 없다. server validator와 renderer contract major가 다르면 publish/신규 제출을 차단한다.

renderer가 사라진 history view는 field snapshot label + escaped raw JSON fallback이다.

## Missing/return lifecycle

- missing provider draft: 보존 + diagnostic.
- missing provider publish: 거부.
- missing provider published Form: 신규 schema/submit fail-closed.
- 과거 Submission: 조회/canonical JSON export 유지.
- 같은 provider/type/compatible major 복귀: 기존 FormVersion을 수정하지 않고 다시 resolve 가능.
- incompatible major만 복귀: unresolved 유지.

## 보안

- Provider code는 trusted server extension code지만 최소 권한 경계로 취급한다.
- raw 전체 Submission, 다른 field, secret, ORM Model을 기본 전달하지 않는다.
- field context에 필요한 자기 raw value와 immutable field/schema metadata만 전달한다.
- formatter 결과는 escaped output으로 취급한다.
- sensitive value를 log/event/exception message에 포함하지 않는다.
- network call, queue dispatch, DB transaction 제어는 Field Provider 책임이 아니다.

## Phase 3 실제 구현 대응

- 공개 interface: `Modules\Glitter\FormBuilder\Contracts\Fields\FieldProvider`
- Registry: `Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry`
- 공개 immutable 값: `src/Fields/ValueObjects/{FieldDefinition,FieldValidationContext,FieldNormalizationResult,FieldValidationDescriptor,FieldIndexValue,FieldRendererDescriptor,FieldMetadata}.php`
- Phase 6 allowlisted G7 Layout projector가 구현되어 built-in descriptor의 `available`은 `true`다. descriptor는 component 이름을 임의 주입하지 않고 module-owned identifier와 major capability만 제공한다.
- `checkbox`는 single boolean, `number`는 decimal string, `datetime`은 site-local/RFC3339→UTC canonical이다(FB-DEC-014).

## 기존 Proposed interface pseudo-code

```php
// 동결 당시 문서용 pseudo-code. 실제 Phase 3 signature는 위 파일을 따른다.
interface FieldProvider
{
    public function descriptor(): FieldProviderDescriptor;
    public function configurationSchema(): array;
    public function validateDefinition(FieldDefinition $field): FieldDefinitionValidation;
    public function validationDescriptor(FieldValidationContext $context): FieldValidationDescriptor;
    public function normalize(FieldValidationContext $context): FieldNormalizationResult;
    public function serialize(FieldNormalizationResult $value): mixed;
    public function display(FieldDisplayContext $context): FieldDisplayResult;
    public function export(FieldExportContext $context): FieldExportResult;
    public function projectIndex(FieldIndexContext $context): ?FieldIndexProjection;
    public function rendererDescriptor(): RendererDescriptor;
}
```

구체 namespace/method signature는 Phase 3 experimental API에서 결정한다.

## Proposed DTO pseudo-code

```text
FieldDefinition
  key, type, providerId, contractVersion
  label, description, required, default
  validation, sensitive, indexable, exportable
  typeConfiguration

FieldValidationContext
  formId, revision, field definition
  rawValue, locale, actor kind
  (다른 ORM Model/Request 없음)

FieldNormalizationResult
  canonicalValue
  storageKind
  redactionKind
  optional index projection
```

실제 PHP 이름은 G7 규칙에 따라 `Dto`/`Data` 접미사를 사용하지 않는다.

## Phase 5 palette metadata subset

`FieldTypeCatalog`는 type/provider/contractVersion, 번역된 name/description, scalar capability, safe renderer descriptor, canonical definition template만 노출한다. PHP class name, Container binding, callback, secret, hidden server-only 값은 API에 포함하지 않는다. template 생성은 공개 FieldProvider 계약을 확장하지 않고 모듈 내부 `FieldDefinitionFactory`가 담당한다.
