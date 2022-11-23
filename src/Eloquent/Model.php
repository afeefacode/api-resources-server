<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Model\ModelInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;
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

        static::registerMorphType();

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

    public static function registerMorphType()
    {
        Relation::morphMap([
            static::$type => static::class
        ]);
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

    public function jsonSerialize(): mixed
    {
        $json = [];

        foreach ($this->visibleFields as $visibleFieldName) {
            $json[$visibleFieldName] = $this->$visibleFieldName;
        }

        return $json;
    }

    /**
     * @param bool $onlyVisible Useful in tests
     */
    public function toArray(bool $onlyVisible = true): array
    {
        $array = [];

        if (!$onlyVisible) {
            foreach ($this as $name => $value) {
                $this->visibleFields = ['type', 'id', ...array_keys($this->attributes), ...array_keys($this->relations)];
            }
        }

        foreach ($this->visibleFields as $visibleFieldName) {
            $value = $this->_toArray($this->$visibleFieldName);
            $array[$visibleFieldName] = $value;
        }

        return $array;
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

    private function _toArray($value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        } elseif (is_array($value)) {
            return array_map([$this, '_toArray'], $value);
        }
        return $value;
    }
}
