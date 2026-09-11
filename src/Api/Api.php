<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Resource\ResourceBag;
use Afeefa\ApiResources\Type\TypeClassMap;
use Afeefa\ApiResources\Utils\HasStaticTypeTrait;
use Afeefa\ApiResources\V2\TypeConfigurator;
use Closure;

class Api implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ToSchemaJsonTrait;
    use HasStaticTypeTrait;

    protected bool $debug = false;

    protected ResourceBag $resources;

    protected array $AdditionalValidatorClasses = [];

    protected array $overriddenTypes = [];

    /** @var TypeConfigurator[] */
    protected array $typeConfigurators = [];

    /** @var array<int, true> spl_object_id of every type instance already configured */
    protected array $configuredTypeObjectIds = [];

    public function created(): void
    {
        $this->container->registerAlias($this, self::class);

        $this->resources = $this->container->create(ResourceBag::class);
        $this->resources($this->resources);

        $this->overriddenTypes = $this->overrideTypes();
        $this->configureTypes();
        $this->configureAuth();
    }

    public function debug($debug = true): static
    {
        $this->debug = $debug;
        return $this;
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function getResources(): ResourceBag
    {
        return $this->resources;
    }

    public function getResource(string $resourceType): Resource
    {
        return $this->resources->get($resourceType);
    }

    public function getOverriddenTypes(): array
    {
        return $this->overriddenTypes;
    }

    public function getAction(string $resourceType, string $actionName): Action
    {
        $resource = $this->resources->get($resourceType);
        return $resource->getAction($actionName);
    }

    public function request(Closure $callback)
    {
        /** @var ApiRequest */
        $request = $this->container->get(ApiRequest::class);
        $request->api($this);
        $callback($request);
        return $request->dispatch();
    }

    public function newRequest(Closure $callback)
    {
        /** @var ApiRequest */
        $request = $this->container->create(ApiRequest::class);
        $request->api($this);
        $callback($request);
        return $request->dispatch();
    }

    public function requestFromInput(?array $input = null): array
    {
        /** @var ApiRequest */
        $request = $this->container->get(ApiRequest::class);
        $request->api($this);
        $request->fromInput($input);
        return $request->dispatch();
    }

    public function registerValidator(string $ValidatorClass): static
    {
        $this->AdditionalValidatorClasses[] = $ValidatorClass;
        return $this;
    }

    public function toSchemaJson(): array
    {
        $resources = $this->resources->toSchemaJson();
        $usedTypes = $this->container->get(TypeClassMap::class)
            ->overrideTypes($this->overriddenTypes)
            ->createUsedTypesForApi($this);

        $this->applyTypeConfigurators($usedTypes);

        $usedValidators = $this->createAllUsedValidators($usedTypes);

        // debug_dump(array_keys($usedTypes));
        // debug_dump(array_keys($usedValidators));

        // debug_dump($typeClassMap);
        // $this->container->dumpEntries();

        $types = [];
        foreach ($usedTypes as $type) {
            $types[$type::type()] = $type->toSchemaJson();
        }

        $validators = [];
        foreach ($usedValidators as $validator) {
            $validators[$validator::type()] = $validator->toSchemaJson();
            unset($validators[$validator::type()]['params']);
            unset($validators[$validator::type()]['type']);
        }

        return [
            'type' => $this::type(),
            'resources' => $resources,
            'types' => $types,
            'validators' => $validators
        ];
    }

    /**
     * Applies the configuration collected in configureTypes() to the given type
     * instances.
     *
     * Called from toSchemaJson() and from ApiRequest::dispatch(): the configuration
     * has to reach the same type instances the resolvers read their field bags from,
     * otherwise a field excluded via write(false) would disappear from the schema but
     * stay writable in a save request.
     *
     * Types are container singletons, and the operations are not idempotent (removing
     * an already removed field throws), so each instance is configured only once.
     *
     * @param Type[] $types keyed by type name
     */
    public function applyTypeConfigurators(array $types): void
    {
        foreach ($this->typeConfigurators as $typeName => $configurator) {
            if (!isset($types[$typeName])) {
                continue;
            }

            $type = $types[$typeName];
            $objectId = spl_object_id($type);
            if (isset($this->configuredTypeObjectIds[$objectId])) {
                continue;
            }

            $configurator->apply($type);
            $this->configuredTypeObjectIds[$objectId] = true;
        }
    }

    public function configureType(string $typeClass): TypeConfigurator
    {
        // Keyed by type string, not by class: a project may swap the class for this
        // type via overrideTypes(), and the configuration has to follow the type.
        $typeName = $typeClass::type();
        if (!isset($this->typeConfigurators[$typeName])) {
            $this->typeConfigurators[$typeName] = new TypeConfigurator();
        }
        return $this->typeConfigurators[$typeName];
    }

    /**
     * Registers the authorization rules of a type.
     *
     * $all is a shorthand for read + write, so a rule that describes a plain
     * data scope is a single call. A repeated call for the same type returns
     * the same configurator and adds to it, just like configureType(): a
     * library registers its rule, a project refines it, neither erases the
     * other. Replacing instead of refining is AuthConfigurator::reset().
     */
    public function authorize(string $typeClass, ?Closure $all = null): AuthConfigurator
    {
        $configurator = $this->container->get(Authorizator::class)->configure($typeClass);

        if ($all) {
            $configurator
                ->read($all)
                ->write($all);
        }

        return $configurator;
    }

    protected function resources(ResourceBag $resources): void
    {
    }

    protected function overrideTypes(): array
    {
        return [];
    }

    protected function configureTypes(): void
    {
    }

    protected function configureAuth(): void
    {
    }

    /**
     * @param Type[] $types
     */
    protected function createAllUsedValidators(array $types): array
    {
        $validators = [];

        foreach ($types as $type) {
            $ValidatorClasses = [
                ...$this->AdditionalValidatorClasses,
                ...$type->getAllValidatorClasses()
            ];
            foreach ($ValidatorClasses as $ValidatorClass) {
                if (!isset($validators[$ValidatorClass])) {
                    $validators[$ValidatorClass] = $this->container->get($ValidatorClass);
                }
            }
        }

        return $validators;
    }
}
