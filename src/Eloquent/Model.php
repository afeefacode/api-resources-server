<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Model\ModelInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Events\Dispatcher;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class Model extends EloquentModel implements ModelInterface
{
    use HasEagerLimit;

    public static $type = 'Model';

    protected $visibleFields = [];

    protected $keyType = 'string';

    public static function boot()
    {
        parent::boot();

        if (!static::$dispatcher) {
            $dispatcher = new Dispatcher();
            static::setEventDispatcher($dispatcher);
        }

        static::created(function (Model $model) {
            $model->afterCreate();
        });

        static::updated(function ($model) {
            $model->afterUpdate();
        });

        static::deleting(function ($model) {
            $model->beforeDelete();
        });

        static::deleted(function ($model) {
            $model->afterDelete();
        });
    }

    public function getTypeAttribute(): string
    {
        return static::$type;
    }

    public function apiResourcesGetId(): ?string
    {
        return $this->id ?? null;
    }

    public function apiResourcesGetType(): string
    {
        return static::$type;
    }

    public function apiResourcesSetAttribute(string $name, $value): void
    {
        $this->$name = $value;
    }

    public function apiResourcesSetRelation(string $name, $value): void
    {
        $this->$name = $value;
    }

    public function apiResourcesSetVisibleFields(array $fields): void
    {
        $this->visibleFields = $fields;
    }

    public function jsonSerialize()
    {
        $json = [];

        foreach ($this->visibleFields as $visibleFieldName) {
            $json[$visibleFieldName] = $this->$visibleFieldName;
        }

        return $json;
    }

    protected function afterCreate()
    {
        // fill in in sub class
    }

    protected function afterUpdate()
    {
        // fill in in sub class
    }

    protected function beforeDelete()
    {
        // fill in in sub class
    }

    protected function afterDelete()
    {
        // fill in in sub class
    }
}
