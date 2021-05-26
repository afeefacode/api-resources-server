<?php

namespace Afeefa\ApiResources\DB\Medoo;

use Afeefa\ApiResources\DB\RelationResolver;
use Afeefa\ApiResources\DB\ResolveContext;
use Afeefa\ApiResources\Model\Model;
use Afeefa\ApiResources\Model\ModelInterface;
use Closure;
use Medoo\Medoo;

class B_a_id_Resolver extends RelationResolver
{
    protected string $aIdFieldName;

    protected ?Closure $queryCallback = null;

    public function created(): void
    {
        $this->loadCallback = Closure::fromCallable([$this, 'loadRelation']);
        $this->mapCallback = Closure::fromCallable([$this, 'mapRelation']);
    }

    public function aIdFieldName(string $aIdFieldName): B_a_id_Resolver
    {
        $this->aIdFieldName = $aIdFieldName;
        return $this;
    }

    public function typeClass(string $TypeClass): B_a_id_Resolver
    {
        $this->TypeClass = $TypeClass;
        return $this;
    }

    public function query(Closure $callback): B_a_id_Resolver
    {
        $this->queryCallback = $callback;
        return $this;
    }

    protected function loadRelation(array $owners, ResolveContext $c)
    {
        $selectFields = array_merge($c->getSelectFields(), [$this->aIdFieldName]);

        $ownerIds = array_unique(
            array_map(function (ModelInterface $owner) {
                return $owner->id;
            }, $owners)
        );

        $db = $this->container->get(Medoo::class);

        $result = $db->select(
            'articles',
            $selectFields,
            [
                'author_id' => $ownerIds,
                'ORDER' => [
                    'date' => 'DESC'
                ]
            ]
        );

        $objects = [];
        foreach ($result as $row) {
            $key = $row[$this->aIdFieldName];
            $objects[$key][] = Model::fromSingle($this->TypeClass::$type, $row);
        }
        return $objects;
    }

    protected function mapRelation(array $objects, ModelInterface $owner)
    {
        $key = $owner->id;
        return $objects[$key] ?? null;
    }
}
