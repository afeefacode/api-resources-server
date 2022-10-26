<?php

use Afeefa\ApiResources\Test\TestFilter;

return new class () extends TestFilter {
    protected static string $type = 'Test.Filter';

    public static ?Closure $setupCallback;
};
