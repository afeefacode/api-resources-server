<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\DB\ActionResolver;
use Afeefa\ApiResources\DB\RelationResolver;
use Afeefa\ApiResources\DB\ResolveContext;
use Afeefa\ApiResources\Type\ModelType;
use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModelResolver
{
    protected string $ModelClass;
    protected string $relationName;

    protected Closure $searchFunction;

    public function modelClass(string $ModelClass): ModelResolver
    {
        $this->ModelClass = $ModelClass;
        return $this;
    }

    public function relationName(string $relationName): ModelResolver
    {
        $this->relationName = $relationName;
        return $this;
    }

    public function search(Closure $searchFunction): ModelResolver
    {
        $this->searchFunction = $searchFunction;
        return $this;
    }

    public function list(ActionResolver $r)
    {
        $r
            ->load(function (ResolveContext $c) use ($r) {
                $request = $r->getRequest();
                $filters = $request->getFilters();
                $selectFields = $c->getSelectFields();

                $usedFilters = [];

                $query = $this->ModelClass::query();

                $countScope = $countFilters = $query->count();
                $countSearch = $countFilters;

                // search

                $keyword = $filters['q'] ?? null;

                if ($keyword) {
                    ($this->searchFunction)($keyword, $query);

                    $countSearch = $query->count();

                    $usedFilters['q'] = $keyword;
                }

                // pagination

                $pageSizeFilter = $r->getAction()->getFilter('page_size');

                $page = $filters['page'] ?? 1;
                $pageSize = $filters['page_size'] ?? $pageSizeFilter->getDefaultValue();

                [$offset, $pageSize] = $this->pageToLimit($page, $pageSize, $countSearch);

                $query
                    ->limit($pageSize)
                    ->offset($offset);

                $usedFilters['page'] = $page;
                $usedFilters['page_size'] = $pageSize;

                $households = $query->select($selectFields)->get()->all();

                $c->meta([
                    'count_scope' => $countScope,
                    'count_filter' => $countFilters,
                    'count_search' => $countSearch,
                    'used_filters' => $usedFilters
                ]);

                return $households;
            });
    }

    public function get(ActionResolver $r)
    {
        $r
            ->load(function (ResolveContext $c) use ($r) {
                $request = $r->getRequest();
                $selectFields = $c->getSelectFields();

                $query = $this->ModelClass::query();

                return $query->where('id', $request->getParam('id'))
                    ->select($selectFields)
                    ->first();
            });
    }

    public function update(ActionResolver $r)
    {
        $r
            ->load(function (ResolveContext $c) use ($r) {
                $request = $r->getRequest();

                $data = $request->getData();

                $query = $this->ModelClass::query();

                $model = $query->where('id', $request->getParam('id'))
                    ->first();

                $model->fillable(array_keys($data));

                $model->update($data);

                return $model;
            });
    }

    public function create(ActionResolver $r)
    {
        $r
            ->load(function () use ($r) {
                $request = $r->getRequest();

                $data = $request->getData();

                $model = new $this->ModelClass();
                $model->fillable(array_keys($data));
                $model->fill($data);
                $model->save();

                return $model->fresh();
            });
    }

    public function relation(RelationResolver $r)
    {
        $r
            ->ownerIdFields(function () use ($r) {
                // select field on the owner prior loading the relation
                $eloquentRelation = $this->getEloquentRelation($r)[2];
                if ($eloquentRelation instanceof BelongsTo) { // reference to the related in the owner table
                    return [$eloquentRelation->getForeignKeyName()];
                }
            })

            ->load(function (array $owners, ResolveContext $c) use ($r) {
                [$owner, $relationName, $eloquentRelation] = $this->getEloquentRelation($r);

                // select field on the relation prior matching the related to its owner
                $selectFields = $c->getSelectFields();
                if ($eloquentRelation instanceof HasMany) { // reference to the owner in the related table
                    $selectFields[] = $eloquentRelation->getForeignKeyName();
                }

                $builder = new Builder($owner);
                $relatedModels = $builder->afeefaEagerLoadRelation($owners, $relationName, $selectFields);

                return $relatedModels->all();
            });
    }

    private function getEloquentRelation(RelationResolver $r): array
    {
        $relationName = $r->getRelation()->getName();

        /** @var ModelType */
        $ownerType = $r->getOwnerType();
        $OwnerClass = $ownerType::$ModelClass;
        $owner = new $OwnerClass();

        $eloquentRelation = $owner->$relationName();

        return [$owner, $relationName, $eloquentRelation];
    }

    private function pageToLimit(int $page, int $pageSize, int $countAll): array
    {
        $numPages = ceil($countAll / $pageSize);
        $page = max(1, min($numPages, $page));
        $offset = $pageSize * $page - $pageSize;
        return [$offset, $pageSize, $page];
    }
}
