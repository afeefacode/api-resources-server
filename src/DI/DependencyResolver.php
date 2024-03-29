<?php

namespace Afeefa\ApiResources\DI;

use Closure;

class DependencyResolver
{
    protected string $TypeClass;

    protected int $index = 0;

    protected ?object $fix = null;

    protected ?Closure $initFunction = null;

    protected bool $create = false;

    public function typeClass(string $TypeClass): DependencyResolver
    {
        $this->TypeClass = $TypeClass;
        return $this;
    }

    public function getTypeClass(): string
    {
        return $this->TypeClass;
    }

    public function index(int $index): DependencyResolver
    {
        $this->index = $index;
        return $this;
    }

    public function getIndex(): int
    {
        return $this->index;
    }

    public function fix(object $fix): DependencyResolver
    {
        $this->fix = $fix;
        return $this;
    }

    public function getFix(): ?object
    {
        return $this->fix;
    }

    public function create(?Closure $initFunction = null): DependencyResolver
    {
        $this->create = true;
        $this->initFunction = $initFunction;
        return $this;
    }

    public function shouldCreate(): bool
    {
        return $this->create;
    }

    public function initInstance(object $instance): void
    {
        if ($this->initFunction) {
            ($this->initFunction)($instance);
        };
    }

    public function isOf(string $TypeClass): bool
    {
        if ($this->TypeClass === $TypeClass) {
            return true;
        }

        $isSubclass = is_subclass_of($this->TypeClass, $TypeClass);
        return $isSubclass;
    }
}
