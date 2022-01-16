<?php

namespace Afeefa\ApiResources\Tests\Action;

use Afeefa\ApiResources\Action\ActionResponse;
use Afeefa\ApiResources\Test\ApiResourcesTest;

use function Afeefa\ApiResources\Test\T;
use Afeefa\ApiResources\Type\Type;

class ActionResponseTest extends ApiResourcesTest
{
    /**
     * @dataProvider typesDataProvider
     */
    public function test_relation_link($typeClassFunction, $typeNames, $isLink, $isList, $isUnion)
    {
        $TypeClasses = $typeClassFunction();

        $response = new ActionResponse();

        $response->initFromArgument($TypeClasses);

        $this->assertEquals($typeNames, $response->getAllTypeNames());

        $this->assertEquals($isLink, $response->isLink());
        $this->assertEquals($isList, $response->isList());
        $this->assertEquals($isUnion, $response->isUnion());
    }

    public function typesDataProvider()
    {
        return [
            'simple' => [
                fn () => T('T1'),
                ['T1'],
                false, false, false
            ],
            'link' => [
                fn () => Type::link(T('T1')),
                ['T1'],
                true, false, false
            ],
            'list' => [
                fn () => Type::list(T('T1')),
                ['T1'],
                false, true, false
            ],
            'union' => [
                fn () => [T('T1'), T('T2')],
                ['T1', 'T2'],
                false, false, true
            ],
            'link+list' => [
                fn () => Type::link(Type::list(T('T1'))),
                ['T1'],
                true, true, false
            ],
            'link+union' => [
                fn () => Type::link([T('T1'), T('T2')]),
                ['T1', 'T2'],
                true, false, true
            ],
            'list+union' => [
                fn () => Type::list([T('T1'), T('T2')]),
                ['T1', 'T2'],
                false, true, true
            ],
            'link+list+union' => [
                fn () => Type::link(Type::list([T('T1'), T('T2')])),
                ['T1', 'T2'],
                true, true, true
            ],
        ];
    }
}
