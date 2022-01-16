<?php

namespace Afeefa\ApiResources\Resolver\Mutation;

use Afeefa\ApiResources\Model\ModelInterface;

class MutationResult
{
    /**
     * @var ModelInterface[]
     */
    protected array $added = [];

    /**
     * @var ModelInterface[]
     */
    protected array $deleted = [];

    /**
     * @var ModelInterface[]
     */
    protected array $updated = [];

    /**
     * @var ModelInterface[]
     */
    protected array $saved = [];

    /**
     * @var ModelInterface[]
     */
    protected array $linked = [];

    /**
     * @var ModelInterface[]
     */
    protected array $unlinked = [];

    public function added(ModelInterface $model): self
    {
        $this->added[] = $model;
        return $this;
    }

    public function deleted(ModelInterface $model): self
    {
        $this->deleted[] = $model;
        return $this;
    }

    public function updated(ModelInterface $model): self
    {
        $this->updated[] = $model;
        return $this;
    }

    public function saved(ModelInterface $model): self
    {
        $this->saved[] = $model;
        return $this;
    }

    public function linked(ModelInterface $model): self
    {
        $this->linked[] = $model;
        return $this;
    }

    public function unlinked(ModelInterface $model): self
    {
        $this->unlinked[] = $model;
        return $this;
    }
}
