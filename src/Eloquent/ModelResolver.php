<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Api\ApiRequest;
use Afeefa\ApiResources\Api\NotFoundException;
use Afeefa\ApiResources\Filter\Filters\KeywordFilter;
use Afeefa\ApiResources\Filter\Filters\OrderFilter;
use Afeefa\ApiResources\Filter\Filters\PageFilter;
use Afeefa\ApiResources\Filter\Filters\PageSizeFilter;
use Afeefa\ApiResources\Resolver\ActionResult;
use Afeefa\ApiResources\Resolver\MutationActionModelResolver;
use Afeefa\ApiResources\Resolver\QueryActionResolver;
use Closure;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder as EloquentBuilder;
use stdClass;

class ModelResolver
{
    protected ModelType $type;
    protected string $ModelClass;
    protected string $relationName;

    protected Closure $scopeFunction;
    protected Closure $paramFunction;
    protected Closure $getParamFunction;
    protected Closure $filterFunction;
    protected Closure $searchFunction;
    protected Closure $orderFunction;
    protected Closure $beforeResolveFunction;
    protected Closure $afterResolveFunction;
    protected Closure $beforeAddFunction;
    protected Closure $afterAddFunction;
    protected Closure $beforeUpdateFunction;
    protected Closure $afterUpdateFunction;
    protected Closure $beforeDeleteFunction;
    protected Closure $afterDeleteFunction;

    public function type(ModelType $type): ModelResolver
    {
        $this->type = $type;
        $this->ModelClass = $type::$ModelClass;
        return $this;
    }

    public function relationName(string $relationName): ModelResolver
    {
        $this->relationName = $relationName;
        return $this;
    }

    public function scope(Closure $scopeFunction): ModelResolver
    {
        $this->scopeFunction = $scopeFunction;
        return $this;
    }

    public function param(Closure $paramFunction): ModelResolver
    {
        $this->paramFunction = $paramFunction;
        return $this;
    }

    public function getParam(Closure $paramFunction): ModelResolver
    {
        $this->getParamFunction = $paramFunction;
        return $this;
    }

    public function filter(Closure $filterFunction): ModelResolver
    {
        $this->filterFunction = $filterFunction;
        return $this;
    }

    public function search(Closure $searchFunction): ModelResolver
    {
        $this->searchFunction = $searchFunction;
        return $this;
    }

    public function order(Closure $orderFunction): ModelResolver
    {
        $this->orderFunction = $orderFunction;
        return $this;
    }

    public function beforeResolve(Closure $beforeResolveFunction): ModelResolver
    {
        $this->beforeResolveFunction = $beforeResolveFunction;
        return $this;
    }

    public function afterResolve(Closure $afterResolveFunction): ModelResolver
    {
        $this->afterResolveFunction = $afterResolveFunction;
        return $this;
    }

    public function beforeAdd(Closure $beforeAddFunction): ModelResolver
    {
        $this->beforeAddFunction = $beforeAddFunction;
        return $this;
    }

    public function afterAdd(Closure $afterAddFunction): ModelResolver
    {
        $this->afterAddFunction = $afterAddFunction;
        return $this;
    }

    public function beforeUpdate(Closure $beforeUpdateFunction): ModelResolver
    {
        $this->beforeUpdateFunction = $beforeUpdateFunction;
        return $this;
    }

    public function afterUpdate(Closure $afterUpdateFunction): ModelResolver
    {
        $this->afterUpdateFunction = $afterUpdateFunction;
        return $this;
    }

    public function beforeDelete(Closure $beforeDeleteFunction): ModelResolver
    {
        $this->beforeDeleteFunction = $beforeDeleteFunction;
        return $this;
    }

    public function afterDelete(Closure $afterDeleteFunction): ModelResolver
    {
        $this->afterDeleteFunction = $afterDeleteFunction;
        return $this;
    }

    public function list(QueryActionResolver $r)
    {
        $r
            ->get(function (ApiRequest $request, Closure $getSelectFields) use ($r) {
                $action = $request->getAction();
                $params = $request->getParams();
                $filters = $request->getFilters();

                $table = (new $this->ModelClass())->getTable();
                $selectFields = array_map(function ($field) use ($table) {
                    return $table . '.' . $field;
                }, $getSelectFields());

                $usedFilters = [];

                $query = $this->ModelClass::query();

                // scope

                ($this->scopeFunction)($query);

                // params

                foreach ($params as $name => $value) {
                    if ($action->hasParam($name)) {
                        ($this->paramFunction)($name, $value, $query);
                    }
                }

                $countAll = $countFilters = $countSearch = $query->count();

                // filters

                $coreFilters = [
                    KeywordFilter::class,
                    OrderFilter::class,
                    PageFilter::class,
                    PageSizeFilter::class
                ];

                $filterUsed = false;

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
                    $countFilters = $countSearch = $query->count();
                }

                // search

                $keyword = $filters['q'] ?? null;

                if ($keyword || $keyword === '0') {
                    $keywordField = $filters['qfield'] ?? null;

                    ($this->searchFunction)($keyword, $keywordField, $query);

                    $countSearch = $query->count();

                    $usedFilters['q'] = $keyword;
                }

                // pagination

                /** @var PageSizeFilter */
                $pageSizeFilter = $action->getFilter('page_size');

                $page = $filters['page'] ?? 1;
                $pageSize = $filters['page_size'] ?? null;
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

                if ($action->hasFilter('order')) {
                    /** @var OrderFilter */
                    $oderFilter = $action->getFilter('order');
                    $order = $filters['order'] ?? [];
                    if (!$oderFilter->hasField($order)) {
                        $order = $oderFilter->getDefaultValue();
                    }

                    foreach ($order as $field => $direction) {
                        ($this->orderFunction)($field, $direction, $query);

                        $usedFilters['order'] = [
                            $field => $direction
                        ];
                    }
                }

                // get

                // sleep(2);

                return (new ActionResult())
                    ->data($query->get()->all())

                    ->meta([
                        'count_all' => $countAll,
                        'count_filter' => $countFilters,
                        'count_search' => $countSearch,
                        'used_filters' => $usedFilters
                    ]);
            });
    }

    public function get(QueryActionResolver $r)
    {
        $r
            ->get(function (ApiRequest $request) use ($r) {
                $action = $request->getAction();
                $params = $request->getParams();
                $selectFields = $r->getSelectFields();

                $table = (new $this->ModelClass())->getTable();
                $selectFields = array_map(function ($field) use ($table) {
                    return $table . '.' . $field;
                }, $selectFields);

                /** @var EloquentBuilder */
                $query = $this->ModelClass::query();

                // select $selectFields before counts, since withCount()
                // will add a '*' column by default, which we don't want.
                $query->select($selectFields);

                $relationCounts = $this->getRelationCounts($r);
                if (count($relationCounts)) {
                    $query->withCount($relationCounts);
                }

                // params
                foreach ($params as $name => $value) {
                    if ($action->hasParam($name)) {
                        ($this->getParamFunction)($name, $value, $query);
                    }
                }

                $model = $query->first();

                if (!$model) {
                    throw new NotFoundException('Model not found');
                }

                return $model;
            });
    }

    public function save(MutationActionModelResolver $r)
    {
        $r
            ->transaction(function (Closure $execute) {
                return DB::transaction(function () use ($execute) {
                    return $execute();
                });
            })

            ->beforeResolve(function (array $params, ?array $data) {
                return ($this->beforeResolveFunction)($params, $data);
            })

            ->afterResolve(function (?Model $model) {
                return ($this->afterResolveFunction)($model);
            })

            ->get(function (string $id) {
                $query = $this->ModelClass::query();
                return $query
                    ->where('id', $id)
                    ->first();
            })

            ->add(function (string $typeName, array $saveFields) {
                $model = new $this->ModelClass();

                $meta = new stdClass();

                $saveFields = ($this->beforeAddFunction)($model, $saveFields, $meta);

                if (!empty($saveFields)) {
                    $model->fillable(array_keys($saveFields));
                    $model->fill($saveFields);
                }

                $model->save();

                ($this->afterAddFunction)($model, $saveFields, $meta);

                return $model;
            })

            ->update(function (Model $model, array $saveFields) {
                $meta = new stdClass();

                $saveFields = ($this->beforeUpdateFunction)($model, $saveFields, $meta);

                if (!empty($saveFields)) {
                    $model->fillable(array_keys($saveFields));
                    $model->fill($saveFields);

                    $model->save();

                    ($this->afterUpdateFunction)($model, $saveFields, $meta);
                }
            })

            ->delete(function (Model $model) {
                $meta = new stdClass();

                ($this->beforeDeleteFunction)($model, $meta);

                $model->delete();

                ($this->afterDeleteFunction)($model, $meta);
            })

            ->forward(function (ApiRequest $apiRequest, Model $model) {
                $apiRequest
                    ->param('id', $model->id)
                    ->resourceType($apiRequest->getResource()::type())
                    ->actionName('get');
            });
    }

    protected function getRelationCounts(QueryActionResolver $r): array
    {
        $requestedFieldNames = $r->getRequestedFieldNames();
        $relationCounts = [];
        foreach ($requestedFieldNames as $fieldName) {
            if (preg_match('/^count_(.+)/', $fieldName, $matches)) {
                $countRelationName = $matches[1];
                if ($this->type->hasRelation($countRelationName)) {
                    $isEloquentRelationResolver = $this->type->getRelation($countRelationName)->getResolveParam('is_eloquent_relation');
                    if ($isEloquentRelationResolver) {
                        $relationCounts[] = $countRelationName . ' as count_' . $countRelationName;
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
}
