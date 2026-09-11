<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\V2\Operation;
use Closure;

/**
 * Holds the authorization rules of one type, one slot per operation.
 *
 * A slot that was never set means full access to that operation (allow by
 * default), just like a type that was never registered at all.
 *
 * Slots are filled in call order, so a per-op call after write() narrows that
 * single operation while the others keep the umbrella rule.
 */
class AuthConfigurator
{
    /** @var array<string, AuthRule> keyed by Operation::value */
    protected array $rules = [];

    public function read(Closure $closure): static
    {
        return $this->set(Operation::READ, $closure);
    }

    /**
     * Umbrella over every mutating operation: update, create and delete.
     *
     * Deliberately wider than the write() bag of a field, where write means
     * save only - a field cannot be deleted on its own. On type level delete
     * mutates data just as update and create do.
     */
    public function write(Closure $closure): static
    {
        return $this
            ->update($closure)
            ->create($closure)
            ->delete($closure);
    }

    public function update(Closure $closure): static
    {
        return $this->set(Operation::UPDATE, $closure);
    }

    public function create(Closure $closure): static
    {
        return $this->set(Operation::CREATE, $closure);
    }

    public function delete(Closure $closure): static
    {
        return $this->set(Operation::DELETE, $closure);
    }

    /**
     * Throws away everything registered so far for this type.
     *
     * Repeated authorize() calls add to the same configurator, so a project
     * that wants to replace a library rule instead of extending it says so.
     */
    public function reset(): static
    {
        $this->rules = [];
        return $this;
    }

    public function getRule(Operation $operation): ?AuthRule
    {
        return $this->rules[$operation->value] ?? null;
    }

    /**
     * A new rule replaces the one registered before for the same operation, but
     * keeps hold of it: the new closure can ask for it as PreviousAuthRule and
     * decide itself when it applies.
     */
    protected function set(Operation $operation, Closure $closure): static
    {
        $previous = $this->rules[$operation->value] ?? null;
        $this->rules[$operation->value] = new AuthRule($closure, $previous);
        return $this;
    }
}
