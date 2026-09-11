<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Api\AuthContext;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Relation;
use Afeefa\ApiResources\Type\Type;
use Afeefa\ApiResources\V2\Operation;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Database\Query\Expression;

/**
 * Authorization context of every Eloquent based path.
 *
 * Narrowing primitive is the query itself: a rule adds its where clauses to
 * what query() returns.
 */
class EloquentAuthContext extends AuthContext
{
    /**
     * $tablePrefix names the target table explicitly, for the paths where the
     * query does not carry it: a MorphTo whose type is not resolved yet sits on
     * the query of its parent, so its from part names the owner table.
     */
    public function __construct(
        protected EloquentBuilder|EloquentRelation $query,
        protected ?string $tablePrefix = null
    ) {
    }

    /**
     * The query the rule narrows down.
     *
     * On an eager load this is the relation, not its builder: a MorphTo replays
     * the calls it receives on each of its per-type queries, and reaching past
     * it to the builder would drop them.
     */
    public function query(): EloquentBuilder|EloquentRelation
    {
        return $this->query;
    }

    /**
     * The name the target table carries in exactly this query - its alias, if
     * Eloquent gave it one.
     *
     * A hand written table name cannot do that: it is fixed when the rule is
     * written, the prefix only when it runs, and it is not foreseeable when one
     * is assigned (Eloquent aliases a self relation, among others). Rules put
     * this in front of their columns.
     */
    public function getTablePrefix(): string
    {
        if ($this->tablePrefix !== null) {
            return $this->tablePrefix;
        }

        $query = $this->query instanceof EloquentRelation
            ? $this->query->getQuery()
            : $this->query;

        $from = $query->getQuery()->from;

        if ($from instanceof Expression) { // subquery as source, no name to qualify with
            return $query->getModel()->getTable();
        }

        // "table as alias" - the alias is what the rest of the query refers to
        if (preg_match('/\s+as\s+(\S+)\s*$/i', $from, $matches)) {
            return trim($matches[1], '`"[]');
        }

        return $from;
    }

    /**
     * Drops the rows whose polymorphic target the rule of its own type does not
     * reach.
     *
     * The relation may point at several types, so which rule applies is not
     * known before the morph column is read. whereHasMorph() asks per type and
     * hands over a query on that target table, which is what a rule expects:
     * for Blog.Article the article rule runs, for Blog.Author the author rule.
     * The statement is one EXISTS per type.
     *
     * The type list comes from the relation as it is declared, not from the
     * data: Eloquent's '*' reads the morph column and turns every value it
     * finds into a class name, so a value without a type behind it - a leftover
     * row, a removed type, a typo in a seed - ends in a class not found.
     *
     * Without this call the row stays and the relation is empty, which is the
     * default. Both are valid and a decision of the application, hence no
     * default and only this one call made comfortable.
     */
    public function authorizeMorph(string $relationName, Operation $operation = Operation::READ): static
    {
        if (!$this->authorizator || !$this->typeName) {
            throw new InvalidConfigurationException(
                'authorizeMorph() only works in a rule registered with Api::authorize().'
            );
        }

        $type = $this->authorizator->getTypeByName($this->typeName);

        if (!$type) {
            throw new InvalidConfigurationException(
                'authorizeMorph() runs in the rule of ' . $this->typeName
                . ', a type the schema does not know.'
            );
        }

        $relation = $this->getDeclaredRelation($type, $relationName, $operation);

        if (!$relation) {
            throw new InvalidConfigurationException(
                'authorizeMorph() got the relation ' . $relationName . ', which ' . $this->typeName
                . ' does not have for ' . $operation->value . '.'
            );
        }

        $query = $this->query instanceof EloquentRelation ? $this->query->getQuery() : $this->query;

        // Not by counting the declared targets: a polymorphic relation may well
        // declare a single one today and a second one in a year.
        if (!$query->getRelation($relationName) instanceof MorphTo) {
            throw new InvalidConfigurationException(
                'authorizeMorph() got the relation ' . $relationName . ' of ' . $this->typeName
                . ', which is not polymorphic.'
            );
        }

        if (!$this->authorizator->narrowRelationOnce($this->typeName, $relationName)) {
            return $this; // already narrowed further up, see narrowRelationOnce()
        }

        // Eloquent goes by model class and resolves the morph map before it
        // calls back, so the way back to the type name is kept here.
        $ModelClasses = [];
        $typeNameOfModelClass = [];

        foreach ($relation->getRelatedType()->getAllTypeNames() as $targetTypeName) {
            $targetType = $this->authorizator->getTypeByName($targetTypeName);

            if (!$targetType instanceof ModelType) {
                throw new InvalidConfigurationException(
                    'authorizeMorph() needs an Eloquent model behind every target of '
                    . $relationName . ', but ' . $targetTypeName . ' does not have one.'
                );
            }

            $ModelClass = $targetType::$ModelClass;
            $ModelClasses[] = $ModelClass;
            $typeNameOfModelClass[$ModelClass] = $targetTypeName;
        }

        $authorizator = $this->authorizator;

        $this->query->whereHasMorph(
            $relationName,
            $ModelClasses,
            function (EloquentBuilder $query, string $ModelClass) use ($authorizator, $operation, $typeNameOfModelClass): void {
                $authorizator->applyAuthorizeForTypeName(
                    $typeNameOfModelClass[$ModelClass],
                    $operation,
                    new EloquentAuthContext($query)
                );
            }
        );

        return $this;
    }

    /**
     * The relation as the bag of that operation declares it.
     *
     * Read and write see different fields, so which relation is meant depends
     * on the operation the rule sits in. Delete has no bag of its own and reads
     * along with the read bag.
     */
    protected function getDeclaredRelation(Type $type, string $relationName, Operation $operation): ?Relation
    {
        return match ($operation) {
            Operation::UPDATE => $type->hasUpdateRelation($relationName) ? $type->getUpdateRelation($relationName) : null,
            Operation::CREATE => $type->hasCreateRelation($relationName) ? $type->getCreateRelation($relationName) : null,
            default => $type->hasRelation($relationName) ? $type->getRelation($relationName) : null
        };
    }
}
