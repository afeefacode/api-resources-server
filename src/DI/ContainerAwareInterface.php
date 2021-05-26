<?php

namespace Afeefa\ApiResources\DI;

interface ContainerAwareInterface
{
    public function created(): void;

    public function container(Container $container): void;
}
