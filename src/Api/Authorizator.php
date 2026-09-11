<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\Type\TypeClassMap;
use Afeefa\ApiResources\V2\Operation;

/**
 * Container entry holding the authorization rules of the application.
 *
 * The Api fills it in configureAuth(); everyone who needs a rule asks the
 * container for the Authorizator - framework resolvers and own actions alike.
 * A resolver therefore never needs access to the Api.
 */
class Authorizator implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /** @var array<string, AuthConfigurator> keyed by type string */
    protected array $configurators = [];

    /** @var array<string, true> keyed by "type.relation", see narrowRelationOnce() */
    protected array $narrowedRelations = [];

    protected int $ruleDepth = 0;

    /**
     * Returns the configurator of the given type, creating it on first call.
     *
     * Keyed by type string, not by class: a project may swap the class for this
     * type via overrideTypes(), and the rule has to follow the type.
     *
     * @internal registration goes through Api::authorize()
     */
    public function configure(string $typeClass): AuthConfigurator
    {
        $typeName = $typeClass::type();
        if (!isset($this->configurators[$typeName])) {
            $this->configurators[$typeName] = new AuthConfigurator();
        }
        return $this->configurators[$typeName];
    }

    /**
     * Applies the rule registered for (type, operation) to the given context.
     *
     * This is the one public entry point: no Eloquent in its signature. Whoever
     * works with Eloquent builds an EloquentAuthContext around their query,
     * every other data source brings its own context.
     */
    public function applyAuthorize(string $typeClass, Operation $operation, AuthContext $context): void
    {
        $this->applyAuthorizeForTypeName($typeClass::type(), $operation, $context);
    }

    /**
     * Same as applyAuthorize(), for paths that only know the type string - a
     * nested read knows its target type by name, a saved model carries it in
     * Model::$type.
     *
     * @internal
     */
    public function applyAuthorizeForTypeName(string $typeName, Operation $operation, AuthContext $context): void
    {
        $rule = $this->getAuthorize($typeName, $operation);
        if (!$rule) {
            return;
        }

        $this->ruleDepth++;
        $context->beginRuleOf($typeName, $this);

        try {
            $rule->call($context, $this->container);
        } finally {
            $context->ruleDone();
            if (--$this->ruleDepth === 0) {
                $this->narrowedRelations = [];
            }
        }
    }

    /**
     * Reports whether the relation of that type still has to be narrowed, and
     * notes it as done if so.
     *
     * What is bounded here is how often one and the same relation of one and
     * the same type takes part in a single rule application. A relation may
     * point at its own type - a comment on a comment - and then the rule of the
     * target is the rule that is running: it would ask for the same relation
     * again, forever, and the query would never even finish being built. The
     * same holds for a ring, A pointing at B pointing back at A. So the pair is
     * used once; a comment on a comment is checked one level deep, and below
     * that the row is let through.
     *
     * The note lives as long as the outermost rule application, which is why
     * the depth is counted: nested applications share it, the next access
     * starts empty.
     *
     * @internal called by EloquentAuthContext::authorizeMorph()
     */
    public function narrowRelationOnce(string $typeName, string $relationName): bool
    {
        $pair = $typeName . '.' . $relationName;

        if (isset($this->narrowedRelations[$pair])) {
            return false;
        }

        $this->narrowedRelations[$pair] = true;
        return true;
    }

    /**
     * The type registered under the given name, or null if the schema does not
     * know it.
     *
     * Rules are keyed by type name, so a narrowing primitive that needs more
     * than the name - the declared targets of one of its relations, say - looks
     * the type up here. Goes through the type class map, so an overridden type
     * is the one that answers.
     *
     * @internal
     */
    public function getTypeByName(string $typeName): ?Type
    {
        $TypeClass = $this->container->get(TypeClassMap::class)->get($typeName);
        return $TypeClass ? $this->container->get($TypeClass) : null;
    }

    /**
     * Returns the rule of the given slot, or null if nothing is registered for
     * (type, operation) - in which case access is unrestricted.
     *
     * @internal
     */
    public function getAuthorize(string $typeName, Operation $operation): ?AuthRule
    {
        return ($this->configurators[$typeName] ?? null)?->getRule($operation);
    }

    public function hasAuthorize(string $typeName, Operation $operation): bool
    {
        return $this->getAuthorize($typeName, $operation) !== null;
    }
}
