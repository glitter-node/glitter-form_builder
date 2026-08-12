<?php

namespace Modules\Glitter\FormBuilder\Fields;

use Modules\Glitter\FormBuilder\Contracts\Fields\FieldProvider;
use Modules\Glitter\FormBuilder\Exceptions\Fields\DuplicateFieldTypeException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\IncompatibleFieldContractException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\SealedFieldRegistryException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\UnknownFieldTypeException;

final class FieldTypeRegistry
{
    private const BUILT_IN_PROVIDER = 'glitter-form_builder';
    private const TYPE_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';
    private const PROVIDER_PATTERN = '/^[a-z0-9][a-z0-9_-]{1,63}$/';
    private const EXTERNAL_TYPE_PATTERN = '/^[a-z0-9][a-z0-9_-]{1,63}:[a-z][a-z0-9_-]{0,63}$/';

    /** @var array<string, FieldProvider> */
    private array $providers = [];
    private bool $sealed = false;

    public function register(FieldProvider $provider): void
    {
        if ($this->sealed) {
            throw new SealedFieldRegistryException('field_registry.sealed');
        }

        $type = $provider->type();
        $this->validateIdentity($provider);
        $this->validateCompatibility($provider->contractVersion());

        if (array_key_exists($type, $this->providers)) {
            throw new DuplicateFieldTypeException("field_registry.duplicate:{$type}");
        }

        $this->providers[$type] = $provider;
    }

    public function has(string $type): bool
    {
        return array_key_exists($type, $this->providers);
    }

    public function resolve(string $type): FieldProvider
    {
        if (! $this->has($type)) {
            throw new UnknownFieldTypeException("field_registry.unknown:{$type}");
        }

        return $this->providers[$type];
    }

    /** @return array<string, FieldProvider> */
    public function all(): array
    {
        $providers = $this->providers;
        ksort($providers, SORT_STRING);

        return $providers;
    }

    /** @return array<string, array<string, mixed>> */
    public function metadata(): array
    {
        $metadata = [];
        foreach ($this->all() as $type => $provider) {
            $metadata[$type] = $provider->metadata()->toArray();
        }

        return $metadata;
    }

    /**
     * Contract 1.x providers are compatible with the Phase 3 registry.
     * Required capabilities are checked explicitly rather than inferred from minor versions.
     *
     * @param list<string> $requiredCapabilities
     */
    public function validateCompatibility(string $version, array $requiredCapabilities = [], ?FieldProvider $provider = null): void
    {
        if (! preg_match('/^(\d+)\.(\d+)$/', $version, $matches) || (int) $matches[1] !== 1) {
            throw new IncompatibleFieldContractException("field_registry.incompatible_contract:{$version}");
        }

        if ($provider !== null) {
            $available = $provider->metadata()->capabilities;
            foreach ($requiredCapabilities as $capability) {
                if (! in_array($capability, $available, true)) {
                    throw new IncompatibleFieldContractException("field_registry.missing_capability:{$capability}");
                }
            }
        }
    }

    public function seal(): void
    {
        $this->sealed = true;
    }

    public function isSealed(): bool
    {
        return $this->sealed;
    }

    private function validateIdentity(FieldProvider $provider): void
    {
        $type = $provider->type();
        $providerId = $provider->providerId();
        $metadata = $provider->metadata();

        if (! preg_match(self::PROVIDER_PATTERN, $providerId)) {
            throw new IncompatibleFieldContractException('field_registry.invalid_provider_identifier');
        }

        $validType = $providerId === self::BUILT_IN_PROVIDER
            ? preg_match(self::TYPE_PATTERN, $type) === 1
            : preg_match(self::EXTERNAL_TYPE_PATTERN, $type) === 1 && str_starts_with($type, $providerId.':');

        if (! $validType) {
            throw new IncompatibleFieldContractException('field_registry.invalid_type_identifier');
        }

        if ($metadata->type !== $type || $metadata->providerId !== $providerId || $metadata->contractVersion !== $provider->contractVersion()) {
            throw new IncompatibleFieldContractException('field_registry.metadata_mismatch');
        }
    }
}
