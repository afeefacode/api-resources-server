<?php

namespace Afeefa\ApiResources\Test\Eloquent;

use Afeefa\ApiResources\Eloquent\ModelType;
use Afeefa\ApiResources\Test\TypeBuilder;
use Afeefa\ApiResources\Test\TypeBuilderTrait;
use Closure;
use Symfony\Component\Filesystem\Path;

/**
 * @property ModelType $type
 * @method ModelType get(bool $register = false)
 */
class ModelTypeBuilder extends TypeBuilder
{
    public function modelType(
        ?string $typeName = null,
        ?string $ModelClass = null,
        ?Closure $fieldsCallback = null,
        ?Closure $updateFieldsCallback = null,
        ?Closure $createFieldsCallback = null,
    ): static {
        parent::type($typeName, $fieldsCallback, $updateFieldsCallback, $createFieldsCallback);

        $this->type::$ModelClass = $ModelClass;

        return $this;
    }

    protected function getClassTemplateName(): string
    {
        return Path::join(__DIR__, 'class-templates', 'model-type.php');
    }
}

class TestModelType extends ModelType
{
    use TypeBuilderTrait;
}
