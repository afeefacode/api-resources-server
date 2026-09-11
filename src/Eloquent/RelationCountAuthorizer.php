<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Api\Authorizator;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\V2\Operation;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Builds the withCount() constraint of a counted relation.
 *
 * A relation count is the same relation as the nested read below it: if both
 * are requested, number and list have to match, so the read rule of the target
 * type has to reach the count subquery as well.
 *
 * @internal
 */
class RelationCountAuthorizer
{
    public static function constraint(?Authorizator $authorizator, Relation $relation): Closure
    {
        $typeNames = $relation->getRelatedType()->getAllTypeNames();

        return function (EloquentBuilder $query) use ($authorizator, $typeNames): void {
            // Nothing to do for more than one possible target type: a relation
            // that may point at several types is a MorphTo, and Eloquent cannot
            // count one. withCount() builds its subquery from a single related
            // model, which a MorphTo does not have - the resulting statement
            // compares an empty column and the database rejects it. The count
            // never reaches a rule, with or without one.
            if (!$authorizator || count($typeNames) !== 1) {
                return;
            }

            $authorizator->applyAuthorizeForTypeName(
                $typeNames[0],
                Operation::READ,
                new EloquentAuthContext($query)
            );
        };
    }
}
