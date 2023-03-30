<?php

namespace Afeefa\ApiResources\Eloquent;

use Afeefa\ApiResources\Model\ModelInterface;
use Carbon\Carbon;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class Model extends EloquentModel implements ModelInterface
{
    use HasEagerLimit;

    public static $type = 'Model';

    protected $visibleFields = [];

    protected $keyType = 'string';

    public static function new(array $attributes = []): static
    {
        $model = new static();
        $model->setRawAttributes($attributes);
        return $model;
    }

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

    /**
     * Elqouent applies the database date format on the given date string
     * and does not consider any included timezone information.
     *
     * a given utc 2023-03-30T04:00:00.000000Z turns this way into a 2023-03-30T04:00:00
     * of the local time zone, e.g. Europe/Berlin
     */
    public function setAttribute($key, $value)
    {
        if ($this->isDateAttribute($key) && is_string($value)) {
            $value = Carbon::parse($value);
            $tz = date_default_timezone_get();
            $value->setTimezone(new DateTimeZone($tz));
        }

        return parent::setAttribute($key, $value);
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
