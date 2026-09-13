<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Resource\Resource;
use Closure;

/**
 * Holds the authorization rules of one resource.
 *
 * Same words as at a type - read, write, update, create, delete - but a
 * different reach: a resource rule is asked only where this resource is
 * addressed directly, in its list, its get and its save. A type rule is asked
 * on every path the type appears on, a nested relation included.
 *
 * That difference is the whole point of this class. A rule that says "the
 * access list shows nobody but yourself" belongs at the resource; written at
 * the type it would also empty the creator of every record the account looks
 * at.
 *
 * On top of the operations, a single action can be locked by name. Every
 * resource has actions of its own, and they are not covered by an operation:
 * they build their queries themselves.
 */
class ResourceAuthConfigurator extends AuthConfigurator
{
    /** @var array<string, AuthRule> keyed by action name */
    protected array $actionRules = [];

    /**
     * $resource is the instance registered in the api, if it is registered
     * there - it is asked whether an action name exists at all.
     */
    public function __construct(
        string $name,
        protected ?Resource $resource = null
    ) {
        parent::__construct($name);
    }

    /**
     * Locks one action of this resource, before it runs.
     *
     * Takes false and nothing else. A closure narrows a query, and for an
     * action of its own the framework has no query to hand over - it does not
     * see into the action. Such a closure would silently never run, and a
     * permission that silently does not apply is worse than none at all.
     *
     * Whoever wants to narrow an own action applies the read rule of this
     * resource by hand, the way an own resolver applies the rule of its type
     * (see Authorizator::applyAuthorizeResource()).
     */
    public function action(string $name, Closure|false $closed): static
    {
        if ($closed instanceof Closure) {
            throw new InvalidConfigurationException(
                'The action ' . $name . ' of ' . $this->name . ' can only be locked with false. '
                . 'An action builds its query itself, so the framework cannot apply a closure to it.'
            );
        }

        // A misspelled action name locks nothing, and nothing is exactly what
        // a lock looks like from the outside until someone tries the action.
        if ($this->resource && !$this->resource->getActions()->has($name)) {
            throw new InvalidConfigurationException(
                $this->name . ' does not have an action ' . $name . '.'
            );
        }

        $this->actionRules[$name] = new AuthRule(null, $this->actionRules[$name] ?? null);

        return $this;
    }

    public function isActionForbidden(string $name): bool
    {
        return isset($this->actionRules[$name]);
    }

    public function reset(): static
    {
        $this->actionRules = [];
        return parent::reset();
    }
}
