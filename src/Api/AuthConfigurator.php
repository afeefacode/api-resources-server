<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\V2\Operation;
use Closure;

/**
 * Holds the authorization rules of one type, one slot per operation. A
 * resource is configured with the same words, see ResourceAuthConfigurator.
 *
 * A slot that was never set means full access to that operation (allow by
 * default), just like a type that was never registered at all.
 *
 * Slots are filled in call order, so a per-op call after write() narrows that
 * single operation while the others keep the umbrella rule.
 *
 * A mutating slot takes false instead of a closure: the operation does not
 * exist for this type. A closure can only decide about a row, and the row of a
 * create exists only after the insert - a rule that denies every create would
 * therefore run too late, or not at all when the insert already fails. A closed
 * slot is asked before any data is written.
 */
class AuthConfigurator
{
    /** @var array<string, AuthRule> keyed by Operation::value */
    protected array $rules = [];

    /**
     * $name is the type or resource this configurator belongs to, used in
     * error messages.
     */
    public function __construct(
        protected string $name = ''
    ) {
    }

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
    public function write(Closure|false $closure): static
    {
        return $this
            ->update($closure)
            ->create($closure)
            ->delete($closure);
    }

    public function update(Closure|false $closure): static
    {
        return $this->set(Operation::UPDATE, $closure);
    }

    public function create(Closure|false $closure): static
    {
        return $this->set(Operation::CREATE, $closure);
    }

    public function delete(Closure|false $closure): static
    {
        return $this->set(Operation::DELETE, $closure);
    }

    /**
     * Locks a single action of a resource, see ResourceAuthConfigurator.
     *
     * Lives here so that Api::authorize() can declare one return type for
     * both kinds of rule: a type and a resource are configured with the same
     * words, and only a resource has actions. A type says so instead of
     * leaving the call to an editor's underline.
     */
    public function action(string $name, Closure|false $closed): static
    {
        throw new InvalidConfigurationException(
            'action() locks an action of a resource, and ' . $this->name
            . ' is a type. Operations of a type are read, write, update, create and delete.'
        );
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
    protected function set(Operation $operation, Closure|false $closure): static
    {
        $previous = $this->rules[$operation->value] ?? null;
        $this->rules[$operation->value] = new AuthRule($closure === false ? null : $closure, $previous);
        return $this;
    }
}
