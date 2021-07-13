<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Api\ToSchemaJsonTrait;
use Afeefa\ApiResources\Api\TypeRegistry;
use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\MissingTypeException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Validator\Validator;
use Closure;

class Field extends BagEntry
{
    use ToSchemaJsonTrait;

    public static string $type;

    protected string $name;

    protected ?Validator $validator = null;

    protected bool $required = false;

    protected bool $allowed = false;

    protected Closure $optionsRequestCallback;

    protected array $resolveParams = [];

    /**
     * @var string|callable|Closure
     */
    protected $resolveCallback = null;

    /**
     * @var string|callable|Closure
     */
    protected $resolveSaveCallback = null;

    public function created(): void
    {
        if (!static::$type) {
            throw new MissingTypeException('Missing type for field of class ' . static::class . '.');
        };
    }

    public function name(string $name): Field
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function optionsRequest(Closure $callback)
    {
        $this->optionsRequestCallback = $callback;
    }

    // anfang

    public function resolveParams(array $params): Field
    {
        $this->resolveParams = $params;
        return $this;
    }

    public function resolveParam(string $key, $value): Field
    {
        $this->resolveParams[$key] = $value;
        return $this;
    }

    public function hasResolveParam(string $name): bool
    {
        return isset($this->resolveParams[$name]);
    }

    public function getResolveParam(string $name)
    {
        return $this->resolveParams[$name];
    }

    public function getResolveParams(): array
    {
        return $this->resolveParams;
    }

    // ende

    public function validate(Closure $callback): Field
    {
        if ($this->validator) { // cloned validator
            $this->container->call(
                $callback,
                function (DependencyResolver $r) {
                    $r->fix($this->validator);
                }
            );
        } else {
            $this->container->create(
                $callback,
                function (Validator $validator) {
                    $this->validator = $validator;
                }
            );
        }

        return $this;
    }

    public function validator(Validator $validator): Field
    {
        $this->validator = $validator;
        return $this;
    }

    public function required(bool $required = true): Field
    {
        $this->required = $required;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function allowed(bool $allowed): Field
    {
        $this->allowed = $allowed;
        return $this;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * @param string|callable|Closure $classOrCallback
     */
    public function resolve($classOrCallback): Field
    {
        $this->resolveCallback = $classOrCallback;
        return $this;
    }

    public function hasResolver(): bool
    {
        return isset($this->resolveCallback);
    }

    public function getResolve(): ?Closure
    {
        $callback = $this->resolveCallback ?? null;

        if (!$callback) {
            throw new InvalidConfigurationException("Field {$this->name} does not have a get resolver.");
        }

        if (is_array($callback) && is_string($callback[0])) { // static class -> create instance
            $callback[0] = $this->container->create($callback[0]);
        }

        if (is_callable($callback)) {
            return Closure::fromCallable($callback);
        } elseif ($callback instanceof Closure) {
            return $callback;
        }

        throw new NotACallbackException("Resolve callback for field {$this->name} is not callable.");
    }

    /**
     * @param string|callable|Closure $classOrCallback
     */
    public function resolveSave($classOrCallback): Field
    {
        $this->resolveSaveCallback = $classOrCallback;
        return $this;
    }

    public function hasSaveResolver(): bool
    {
        return isset($this->resolveSaveCallback);
    }

    public function getSaveResolve(): ?Closure
    {
        $callback = $this->resolveSaveCallback ?? null;

        if (!$callback) {
            throw new InvalidConfigurationException("Field {$this->name} does not have a save resolver.");
        }

        if (is_array($callback) && is_string($callback[0])) { // static class -> create instance
            $callback[0] = $this->container->create($callback[0]);
        }

        if (is_callable($callback)) {
            return Closure::fromCallable($callback);
        } elseif ($callback instanceof Closure) {
            return $callback;
        }

        throw new NotACallbackException("Save resolve callback for field {$this->name} is not callable.");
    }

    public function clone(): Field
    {
        return $this->container->create(static::class, function (Field $field) {
            $field
                ->name($this->name)
                ->required($this->required);
            if ($this->validator) {
                $field->validator($this->validator->clone());
            }
            if (isset($this->optionsRequestCallback)) {
                $field->optionsRequest($this->optionsRequestCallback);
            }
        });
    }

    public function getSchemaJson(TypeRegistry $typeRegistry): array
    {
        $json = [
            'type' => static::$type,
            // 'name' => $this->name
        ];

        if ($this->required) {
            $json['required'] = true;
        }

        if (isset($this->optionsRequestCallback)) {
            $api = $this->container->get(Api::class);
            $request = $this->container->create(function (ApiRequest $request) use ($api) {
                $request->api($api);
                ($this->optionsRequestCallback)($request);
            });
            $json['options_request'] = $request->toSchemaJson();
        }

        if ($this->validator) {
            $typeRegistry->registerValidator(get_class($this->validator));

            $json['validator'] = $this->validator->toSchemaJson();
            unset($json['validator']['rules']);
        }

        return $json;
    }
}
