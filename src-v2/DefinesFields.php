<?php

namespace Afeefa\ApiResources\V2;

trait DefinesFields
{
    protected function setupV2Fields(): void
    {
        $v2Fields = $this->container->create(FieldBag::class)
            ->owner($this);

        $this->defineFields($v2Fields);

        $this->fields = $v2Fields->forOperation(Operation::READ);
        $this->updateFields = $v2Fields->forOperation(Operation::UPDATE);
        $this->createFields = $v2Fields->forOperation(Operation::CREATE);
    }

    protected function defineFields(FieldBag $fields): void
    {
    }
}
