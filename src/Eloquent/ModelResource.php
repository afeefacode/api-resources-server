<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Action\ActionParams;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Fields\IdAttribute;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Filter\Filters\KeywordFilter;
use Afeefa\ApiResources\Filter\Filters\OrderFilter;
use Afeefa\ApiResources\Filter\Filters\PageFilter;
use Afeefa\ApiResources\Filter\Filters\PageSizeFilter;
use Afeefa\ApiResources\Resource\Resource;
use Afeefa\ApiResources\Type\Type;
use Illuminate\Database\Eloquent\Builder;

class ModelResource extends Resource
{
    public string $ModelTypeClass;

    public function created(): void
    {
        if (!isset($this->ModelTypeClass)) {
            throw new InvalidConfigurationException('Missing model type class for model resource of class ' . static::class . '.');
        }

        if (!isset($this->ModelTypeClass::$ModelClass)) {
            throw new InvalidConfigurationException('Missing model class for model type of class ' . $this->ModelTypeClass . '.');
        }

        parent::created();
    }

    protected function params(ActionParams $params): void
    {
    }

    protected function filters(FilterBag $filters): void
    {
        $filters->add('q', KeywordFilter::class);

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
    }

    protected function param(string $name, $value, Builder $query): void
    {
        $query->where($name, $value);
    }

    protected function filter(string $name, $value, Builder $query): void
    {
        $query->where($name, $value);
    }

    protected function search(string $keyword, Builder $query): void
    {
    }

    protected function order(string $field, string $direction, Builder $query): void
    {
        $query->orderBy($field, $direction);
    }

    protected function getEloquentResolver(): ModelResolver
    {
        $type = $this->container->get($this->ModelTypeClass);
        return (new ModelResolver())
            ->type($type)
            ->search(function (string $keyword, Builder $query) {
                $this->search($keyword, $query);
            })
            ->order(function (string $field, string $direction, Builder $query) {
                $this->order($field, $direction, $query);
            })
            ->filter(function (string $name, $value, Builder $query) {
                $this->filter($name, $value, $query);
            })
            ->param(function (string $name, $value, Builder $query) {
                $this->param($name, $value, $query);
            });
    }

    protected function actions(ActionBag $actions): void
    {
        $actions->add('list', function (Action $action) {
            $action
                ->params(function (ActionParams $params) {
                    $this->params($params);
                })

                ->filters(function (FilterBag $filters) {
                    $this->filters($filters);
                })

                ->response(Type::list($this->ModelTypeClass))

                ->resolve([$this->getEloquentResolver(), 'list']);
        });

        $actions->add('get', function (Action $action) {
            $action
                ->params(function (ActionParams $params) {
                    $params->attribute('id', IdAttribute::class);
                })

                ->response($this->ModelTypeClass)

                ->resolve([$this->getEloquentResolver(), 'get']);
        });

        $actions->add('save', function (Action $action) {
            $action
                ->params(function (ActionParams $params) {
                    $params->attribute('id', IdAttribute::class);
                })

                ->input($this->ModelTypeClass)

                ->response($this->ModelTypeClass)

                ->resolve([$this->getEloquentResolver(), 'save']);
        });
    }
}
