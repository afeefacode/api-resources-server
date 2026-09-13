<?php

namespace Afeefa\ApiResources\Api;

use Afeefa\ApiResources\DI\ContainerAwareInterface;
use Afeefa\ApiResources\DI\ContainerAwareTrait;
use Afeefa\ApiResources\Resource\Resource;
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

    /** @var array<string, ResourceAuthConfigurator> keyed by resource type string */
    protected array $resourceConfigurators = [];

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
    public function configureType(string $typeClass): AuthConfigurator
    {
        $typeName = $typeClass::type();
        if (!isset($this->configurators[$typeName])) {
            $this->configurators[$typeName] = new AuthConfigurator($typeName);
        }
        return $this->configurators[$typeName];
    }

    /**
     * Returns the configurator of the given resource, creating it on first call.
     *
     * Keyed by the type string of the resource, so a project that swaps a
     * resource of a library for a subclass of it keeps the rule, exactly as a
     * type does.
     *
     * @internal registration goes through Api::authorize()
     */
    public function configureResource(string $ResourceClass, ?Resource $resource = null): ResourceAuthConfigurator
    {
        $resourceType = $ResourceClass::type();
        if (!isset($this->resourceConfigurators[$resourceType])) {
            $this->resourceConfigurators[$resourceType] = new ResourceAuthConfigurator($resourceType, $resource);
        }
        return $this->resourceConfigurators[$resourceType];
    }

    /**
     * Applies the rule registered for (type, operation) to the given context.
     *
     * This is the one public entry point: no Eloquent in its signature. Whoever
     * works with Eloquent builds an EloquentAuthContext around their query,
     * every other data source brings its own context.
     */
    public function applyAuthorizeType(string $typeClass, Operation $operation, AuthContext $context): void
    {
        $this->applyAuthorizeTypeByName($typeClass::type(), $operation, $context);
    }

    /**
     * Same as applyAuthorizeType(), for paths that only know the type string - a
     * nested read knows its target type by name, a saved model carries it in
     * Model::$type.
     *
     * @internal
     */
    public function applyAuthorizeTypeByName(string $typeName, Operation $operation, AuthContext $context): void
    {
        $this->runRule($this->getTypeAuthorize($typeName, $operation), $typeName, $context);
    }

    /**
     * Applies the rule the given resource registered for that operation.
     *
     * Only for the paths on which the resource is addressed directly: its
     * list, its get and its save. A nested relation reaches the type, not the
     * resource, and is therefore untouched by it.
     */
    public function applyAuthorizeResource(string $ResourceClass, Operation $operation, AuthContext $context): void
    {
        $this->applyAuthorizeResourceByName($ResourceClass::type(), $operation, $context);
    }

    /**
     * Same as applyAuthorizeResource(), for paths that only know the type
     * string of the resource.
     *
     * @internal
     */
    public function applyAuthorizeResourceByName(string $resourceType, Operation $operation, AuthContext $context): void
    {
        $this->runRule($this->getResourceAuthorize($resourceType, $operation), $resourceType, $context);
    }

    /**
     * Runs one rule on the given context, or does nothing without a rule.
     */
    protected function runRule(?AuthRule $rule, string $name, AuthContext $context): void
    {
        if (!$rule) {
            return;
        }

        $this->ruleDepth++;
        $context->beginRuleOf($name, $this);

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
    public function getTypeAuthorize(string $typeName, Operation $operation): ?AuthRule
    {
        return ($this->configurators[$typeName] ?? null)?->getRule($operation);
    }

    /**
     * Whether the slot was closed with false, e.g. create(false).
     */
    public function isTypeForbidden(string $typeName, Operation $operation): bool
    {
        return $this->getTypeAuthorize($typeName, $operation)?->isForbidden() ?? false;
    }

    /**
     * Throws when the operation is closed for the type, before any data is
     * written. Answers like a rule that denies, so a closed operation cannot be
     * told apart from a row that is out of reach.
     *
     * @internal called by the resolvers in front of add, update and delete
     */
    public function assertTypeNotForbidden(string $typeName, Operation $operation): void
    {
        if ($this->isTypeForbidden($typeName, $operation)) {
            throw new NotFoundException('Model not found');
        }
    }

    public function hasTypeAuthorize(string $typeName, Operation $operation): bool
    {
        return $this->getTypeAuthorize($typeName, $operation) !== null;
    }

    /**
     * Returns the rule the resource registered for that operation, or null.
     *
     * @internal
     */
    public function getResourceAuthorize(string $resourceType, Operation $operation): ?AuthRule
    {
        return ($this->resourceConfigurators[$resourceType] ?? null)?->getRule($operation);
    }

    /**
     * Throws when the operation is closed for direct calls of this resource,
     * before any data is written.
     *
     * @internal called by the resolvers in front of add, update and delete
     */
    public function assertResourceNotForbidden(string $resourceType, Operation $operation): void
    {
        if ($this->getResourceAuthorize($resourceType, $operation)?->isForbidden()) {
            throw new NotFoundException('Model not found');
        }
    }

    /**
     * Whether that action of that resource was locked with
     * ResourceAuthConfigurator::action().
     */
    public function isActionForbidden(string $resourceType, string $actionName): bool
    {
        return ($this->resourceConfigurators[$resourceType] ?? null)?->isActionForbidden($actionName) ?? false;
    }

    /**
     * Throws when the action is locked, before the action runs.
     *
     * Answers like a rule that denies: a locked action must not be
     * distinguishable from one that does not exist.
     *
     * @internal called by ApiRequest::dispatch()
     */
    public function assertActionNotForbidden(string $resourceType, string $actionName): void
    {
        if ($this->isActionForbidden($resourceType, $actionName)) {
            throw new NotFoundException('Model not found');
        }
    }
}
