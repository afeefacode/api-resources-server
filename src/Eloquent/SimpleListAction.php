<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Filter\Filters\KeywordFilter;
use Afeefa\ApiResources\Filter\Filters\OrderFilter;
use Afeefa\ApiResources\Filter\Filters\PageFilter;
use Afeefa\ApiResources\Filter\Filters\PageSizeFilter;
use Afeefa\ApiResources\Filter\Filters\SelectFilter;
use Afeefa\ApiResources\Resolver\ActionResult;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class SimpleListAction extends Action
{
    protected Closure $queryFunction;
    protected Closure $paramFunction;
    protected Closure $filterFunction;
    protected Closure $searchFunction;
    protected Closure $orderFunction;
    protected Closure $mapResultFunction;

    public function created(): void
    {
        parent::filters(function (FilterBag $filters) {
            $filters->add('q', KeywordFilter::class);

            $filters->add('qfield', SelectFilter::class);

            $filters->add('order', function (OrderFilter $filter) {
                $filter
                    ->fields([
                        'id' => [OrderFilter::DESC, OrderFilter::ASC]
                    ])
                    ->default(['id' => OrderFilter::DESC]);
            });

            $filters->add('page_size', function (PageSizeFilter $filter) {
                $filter
                    ->pageSizes([15, 30, 50])
                    ->default(15);
            });

            $filters->add('page', PageFilter::class);
        });

        $this->resolveCallback = [$this, 'doResolve'];
    }

    public function params(Closure $paramsFunction): SimpleListAction
    {
        parent::params($paramsFunction);
        return $this;
    }

    public function filters(Closure $filtersFunction): SimpleListAction
    {
        $filtersFunction($this->filters);
        return $this;
    }

    public function query(Closure $queryFunction): SimpleListAction
    {
        $this->queryFunction = $queryFunction;
        return $this;
    }

    public function param(Closure $paramFunction): SimpleListAction
    {
        $this->paramFunction = $paramFunction;
        return $this;
    }

    public function filter(Closure $filterFunction): SimpleListAction
    {
        $this->filterFunction = $filterFunction;
        return $this;
    }

    public function search(Closure $searchFunction): SimpleListAction
    {
        $this->searchFunction = $searchFunction;
        return $this;
    }

    public function order(Closure $orderFunction): SimpleListAction
    {
        $this->orderFunction = $orderFunction;
        return $this;
    }

    public function map(Closure $mapResultFunction): SimpleListAction
    {
        $this->mapResultFunction = $mapResultFunction;
        return $this;
    }

    protected function doResolve(QueryActionResolver $r)
    {
        $r
            ->get(function (ApiRequest $request, Closure $getSelectFields) use ($r) {
                $action = $request->getAction();
                $params = $request->getParams();
                $filters = $request->getFilters();

                $query = $this->getBaseQuery();

                $table = $query->getModel()->getTable();
                $selectFields = array_map(function ($field) use ($table) {
                    return $table . '.' . $field;
                }, $getSelectFields());

                $usedFilters = [];

                // params

                foreach ($params as $name => $value) {
                    if ($action->hasParam($name) && $name !== 'page_size') {
                        if ($this->paramFunction ?? null) {
                            ($this->paramFunction)($name, $value, $query);
                        } else {
                            $query->where($name, $value);
                        }
                    }
                }

                $countAll = $countFilters = $countSearch = $this->calculateCount($query);

                // filters

                $coreFilters = [
                    KeywordFilter::class,
                    OrderFilter::class,
                    PageFilter::class,
                    PageSizeFilter::class
                ];

                $filterUsed = false;

                if ($this->filterFunction ?? null) {
                    $actionFilters = $action->getFilters()->getEntries();
                    foreach ($actionFilters as $name => $filter) {
                        if (!in_array(get_class($filter), $coreFilters)) {
                            $useFilter = false;
                            $value = null;

                            if (array_key_exists($name, $filters)) { // filter is given
                                $value = $filters[$name];
                                $useFilter = true;
                            } elseif ($filter->hasDefaultValue()) { // filter not given but has default
                                $value = $filter->getDefaultValue();
                                $useFilter = true;
                            }

                            if ($useFilter) {
                                ($this->filterFunction)($name, $value, $query, $filters);
                                $filterUsed = true;
                                $usedFilters[$name] = $value;
                            }
                        }
                    }

                    if ($filterUsed) {
                        $countFilters = $countSearch = $this->calculateCount($query);
                    }
                }

                // search

                $keyword = $filters['q'] ?? null;

                if ($keyword || $keyword === '0') {
                    $keywordField = $filters['qfield'] ?? null;

                    ($this->searchFunction)($keyword, $keywordField, $query);

                    $countSearch = $this->calculateCount($query);

                    $usedFilters['q'] = $keyword;
                }

                // pagination

                /** @var PageSizeFilter */
                $pageSizeFilter = $action->getFilter('page_size');

                $page = $filters['page'] ?? 1;
                $pageSize = $filters['page_size'] ?? null;

                if ($action->hasParam('page_size') && ($params['page_size'] ?? null)) {
                    $pageSize = $params['page_size'];
                }

                if (!$pageSizeFilter->hasPageSize($pageSize)) {
                    $pageSize = $pageSizeFilter->getDefaultValue();
                }

                [$offset, $pageSize, $page] = $this->pageToLimit($page, $pageSize, $countSearch);

                $query
                    ->limit($pageSize)
                    ->offset($offset);

                $usedFilters['page'] = $page;
                $usedFilters['page_size'] = $pageSize;

                // select $selectFields before counts, since withCount()
                // will add a '*' column by default, which we don't want.
                $query->select($selectFields);

                // counts

                $relationCounts = $this->getRelationCounts($r);
                if (count($relationCounts)) {
                    $query->withCount($relationCounts);
                }

                // order

                if ($this->orderFunction ?? null) {

                    if ($action->hasFilter('order')) {
                        /** @var OrderFilter */
                        $oderFilter = $action->getFilter('order');
                        $order = $filters['order'] ?? [];
                        if (!$oderFilter->hasField($order)) {
                            $order = $oderFilter->getDefaultValue();
                        }

                        foreach ($order as $field => $direction) {
                            $direction = strtolower($direction ?: '');
                            $direction = match (strtolower($direction ?: '')) {
                                'asc', 'desc' => $direction,
                                default => 'asc'
                            };

                            ($this->orderFunction)($field, $direction, $query);

                            $usedFilters['order'] = [
                                $field => $direction
                            ];
                        }
                    }
                }

                // get

                $models = $query->get()->all();

                // map

                if ($this->mapResultFunction ?? null) {
                    $models = array_map($this->mapResultFunction, $models);
                }

                return (new ActionResult())
                    ->data($models)

                    ->meta([
                        'count_all' => $countAll,
                        'count_filter' => $countFilters,
                        'count_search' => $countSearch,
                        'used_filters' => $usedFilters
                    ]);
            });
    }

    protected function getBaseQuery(): ?Builder
    {
        if ($this->queryFunction ?? null) {
            return ($this->queryFunction)();
        } elseif ($this->response->getTypeClass()) {
            $type = new ($this->response->getTypeClass());
            return $type::$ModelClass::query();
        }
        return null;
    }

    protected function getRelationCounts(QueryActionResolver $r): array
    {
        $relationCounts = [];

        if ($this->response->getTypeClass()) {
            $type = new ($this->response->getTypeClass());
            $requestedFieldNames = $r->getRequestedFieldNames();
            foreach ($requestedFieldNames as $fieldName) {
                if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                    $countRelationName = $matches[1];
                    if ($type->hasRelation($countRelationName)) {
                        $isEloquentRelationResolver = $this->type->getRelation($countRelationName)->getResolveParam('is_eloquent_relation');
                        if ($isEloquentRelationResolver) {
                            $relationCounts[] = $countRelationName . ' as count_' . $countRelationName;
                        }
                    }
                }
            }
        }

        return $relationCounts;
    }

    protected function pageToLimit(int $page, int $pageSize, int $countAll): array
    {
        $numPages = ceil($countAll / $pageSize);
        $page = max(1, min($numPages, $page));
        $offset = $pageSize * $page - $pageSize;
        return [$offset, $pageSize, $page];
    }

    protected function calculateCount(Builder $query)
    {
        return (clone $query)->getQuery()->getCountForPagination();
    }
}
