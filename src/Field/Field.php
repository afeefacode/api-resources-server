<?php

namespace Afeefa\ApiResources\Field;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Api\ToSchemaJsonTrait;
use Afeefa\ApiResources\Bag\BagEntry;
use Afeefa\ApiResources\DI\DependencyResolver;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Exception\Exceptions\NotACallbackException;
use Afeefa\ApiResources\Utils\HasStaticTypeTrait;
use Afeefa\ApiResources\Validator\Validator;
use Closure;

class Field extends BagEntry
{
    use ToSchemaJsonTrait;
    use HasStaticTypeTrait;

    protected string $name;

    protected $owner;

    protected bool $isMutation = false;

    protected $default = null;

    protected ?Validator $validator = null;

    protected bool $required = false;

    protected Closure $optionsRequestCallback;

    protected array $options = [];

    protected array $resolveParams = [];

    /**
     * @var string|callable|Closure
     */
    protected $resolveCallback = null;

    public function name(string $name): Field
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function owner($owner): Field
    {
        $this->owner = $owner;
        return $this;
    }

    public function getOwner()
    {
        return $this->owner;
    }

    public function default($default): Field
    {
        $this->default = $default;
        return $this;
    }

    public function hasDefaultValue(): bool
    {
        return $this->default !== null;
    }

    public function getDefaultValue()
    {
        return $this->default;
    }

    public function isMutation(bool $isMutation = true): Field
    {
        $this->isMutation = $isMutation;
        return $this;
    }

    public function options(array $options): Field
    {
        $this->options = $options;
        return $this;
    }

    public function hasOptions(): bool
    {
        return count($this->options);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function optionsRequest(Closure $callback): Field
    {
        $this->optionsRequestCallback = $callback;
        return $this;
    }

    public function hasOptionsRequest(): bool
    {
        return isset($this->optionsRequestCallback);
    }

    public function getOptionsRequest(): ?ApiRequest
    {
        if (isset($this->optionsRequestCallback)) {
            return $this->container->create(function (ApiRequest $request) {
                $request->api($this->container->get(Api::class));
                ($this->optionsRequestCallback)($request);
            });
        }
        return null;
    }

    public function hasResolveParam(string $name): bool
    {
        return isset($this->resolveParams[$name]);
    }

    public function getResolveParam(string $name)
    {
        return $this->resolveParams[$name] ?? null;
    }

    public function getValidatorClass(): ?string
    {
        if ($this->validator) {
            return get_class($this->validator);
        }
        return null;
    }

    public function getValidator(): ?Validator
    {
        return $this->validator;
    }

    public function validate($validatorOrCallback): Field
    {
        if ($validatorOrCallback instanceof Validator) {
            $this->validator = $validatorOrCallback;
        } else {
            if ($this->validator) { // cloned validator
                $this->container->call(
                    $validatorOrCallback,
                    function (DependencyResolver $r) {
                        $r->fix($this->validator);
                    }
                );
            } else {
                $this->container->create(
                    $validatorOrCallback,
                    function (Validator $validator) {
                        $this->validator = $validator;
                    }
                );
            }
        }

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

    /**
     * @param string|callable|Closure $classOrCallback
     */
    public function resolve($classOrCallback, array $params = []): Field
    {
        $this->resolveCallback = $classOrCallback;
        $this->resolveParams = $params;
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
            throw new InvalidConfigurationException("Field {$this->name} does not have a resolver.");
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

    public function clone(): Field
    {
        return $this->container->create(static::class, function (Field $field) {
            $field
                ->name($this->name)
                ->required($this->required)
                ->default($this->default)
                ->isMutation($this->isMutation);

            if ($this->validator) {
                $field->validator = $this->validator->clone();
            }

            if (isset($this->optionsRequestCallback)) {
                $field->optionsRequestCallback = $this->optionsRequestCallback;
            }

            if (isset($this->options)) {
                $field->options = $this->options;
            }

            if (isset($this->resolveCallback)) {
                $field->resolveCallback = $this->resolveCallback;
            }
        });
    }

    public function toSchemaJson(): array
    {
        $json = [
            'type' => $this::type()
        ];

        if ($this->required) {
            $json['required'] = true;
        }

        if (isset($this->optionsRequestCallback)) {
            $request = $this->getOptionsRequest();
            $json['options_request'] = $request->toSchemaJson();
        }

        if (count($this->options)) {
            $json['options'] = $this->options;
        }

        if ($this->validator) {
            $json['validator'] = $this->validator->toSchemaJson();
            unset($json['validator']['rules']);
        }

        return $json;
    }
}
