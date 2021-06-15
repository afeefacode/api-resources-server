<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\DB\ActionResolver;
use Afeefa\ApiResources\DB\ResolveContext;
use Closure;

class ModelResolver
{
    protected string $ModelClass;
    protected Closure $searchFunction;

    public function modelClass(string $ModelClass): ModelResolver
    {
        $this->ModelClass = $ModelClass;
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

    private function pageToLimit(int $page, int $pageSize, int $countAll): array
    {
        $numPages = ceil($countAll / $pageSize);
        $page = max(1, min($numPages, $page));
        $offset = $pageSize * $page - $pageSize;
        return [$offset, $pageSize, $page];
    }
}
