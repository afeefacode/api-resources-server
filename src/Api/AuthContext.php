<?php

namespace Afeefa\ApiResources\Api;

/**
 * Base class of every authorization context.
 *
 * A context is built solely for authorizing a single access to a type. It knows
 * nothing about the running request - no params, no requested fields, no action.
 * All it can do is deny, and whatever narrowing primitive the concrete data
 * source adds on top (see EloquentAuthContext::query()).
 *
 * Rules always deny via deny(), never with an own throw: which exception is
 * used is a convention that may change, and it should change in one place
 * instead of in every registered closure.
 */
abstract class AuthContext
{
    protected ?string $typeName = null;

    protected ?Authorizator $authorizator = null;

    /**
     * Names the rule that is about to run on this context.
     *
     * Not a constructor argument: whoever builds a context knows its data
     * source, and the authorizator knows whose rule it looked up. Narrowing
     * primitives that look up a rule of their own need both halves, and only
     * here are both known - on every path, including the ones that never see a
     * type instance (see EloquentAuthContext::authorizeMorph()).
     *
     * @internal called by Authorizator::applyAuthorizeForTypeName()
     */
    public function beginRuleOf(string $typeName, Authorizator $authorizator): void
    {
        $this->typeName = $typeName;
        $this->authorizator = $authorizator;
    }

    /**
     * Takes the rule back off, once it has run.
     *
     * Together with beginRuleOf() this makes "readable during that one rule" a
     * guarantee instead of a claim: a context that is handed on afterwards -
     * kept by a caller, applied a second time - carries nothing over.
     *
     * @internal called by Authorizator::applyAuthorizeForTypeName()
     */
    public function ruleDone(): void
    {
        $this->typeName = null;
        $this->authorizator = null;
    }

    /**
     * Denies access to the current object or row.
     *
     * Always a NotFoundException, for a blocked action just as for a row that
     * is out of scope: the outside must not be able to tell a locked resource
     * apart from a missing one.
     */
    public function deny(): never
    {
        throw new NotFoundException('Model not found');
    }
}
