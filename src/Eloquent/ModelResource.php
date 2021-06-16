<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Action\Action;
use Afeefa\ApiResources\Action\ActionBag;
use Afeefa\ApiResources\Action\ActionParams;
use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Field\Fields\IdAttribute;
use Afeefa\ApiResources\Filter\FilterBag;
use Afeefa\ApiResources\Filter\Filters\KeywordFilter;
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
        };

        if (!isset($this->ModelTypeClass::$ModelClass)) {
            throw new InvalidConfigurationException('Missing model class for model type of class ' . $this->ModelTypeClass . '.');
        };

        parent::created();
    }

    protected function filters(FilterBag $filters): void
    {
        $filters->add('q', KeywordFilter::class);

        $filters->add('page_size', function (PageSizeFilter $filter) {
            $filter
                ->pageSizes([15, 30, 50])
                ->default(15);
        });

        $filters->add('page', PageFilter::class);
    }

    protected function search(string $keyword, Builder $query): void
    {
    }

    protected function getEloquentResolver(): ModelResolver
    {
        return (new ModelResolver())
            ->modelClass($this->ModelTypeClass::$ModelClass)
            ->search(function (string $keyword, Builder $query) {
                $this->search($keyword, $query);
            });
    }

    protected function actions(ActionBag $actions): void
    {
        $actions->add('list', function (Action $action) {
            $action
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

        $actions->add('create', function (Action $action) {
            $action
                ->input(Type::create($this->ModelTypeClass))

                ->response($this->ModelTypeClass)

                ->resolve([$this->getEloquentResolver(), 'create']);
        });

        $actions->add('update', function (Action $action) {
            $action
                ->params(function (ActionParams $params) {
                    $params->attribute('id', IdAttribute::class);
                })

                ->input(Type::update($this->ModelTypeClass))

                ->response($this->ModelTypeClass)

                ->resolve([$this->getEloquentResolver(), 'update']);
        });

        $actions->add('delete', function (Action $action) {
            $action
                ->params(function (ActionParams $params) {
                    $params->attribute('id', IdAttribute::class);
                })

                ->response($this->ModelTypeClass)

                ->resolve([$this->getEloquentResolver(), 'delete']);
        });
    }
}
