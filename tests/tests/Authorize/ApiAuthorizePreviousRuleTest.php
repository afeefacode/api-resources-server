<?php

namespace Afeefa\ApiResources\Tests\Authorize;

use Afeefa\ApiResources\Api\Api;
use Afeefa\ApiResources\Api\PreviousAuthRule;
use Afeefa\ApiResources\Eloquent\EloquentAuthContext;
use Afeefa\ApiResources\Test\Eloquent\ApiResourcesAuthorizeTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Afeefa\ApiResources\Test\Fixtures\Blog\Types\AuthorType;

class ApiAuthorizePreviousRuleTest extends ApiResourcesAuthorizeTest
{
    protected function setUp(): void
    {
        parent::setUp();
        PreviousRuleTestService::$seesEverything = false;
        PreviousRuleTestService::$calls = 0;

        Author::factory()->count(3)->sequence(
            ['name' => 'ok one'],
            ['name' => 'ok two'],
            ['name' => 'blocked']
        )->create();
    }

    public function test_a_rule_can_apply_the_rule_it_replaces()
    {
        $names = $this->listNames(function (Api $api) {
            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c, PreviousAuthRule $previous) => $previous->call($c));
        });

        $this->assertEquals(['ok one', 'ok two'], $names);
    }

    public function test_a_rule_can_skip_the_rule_it_replaces()
    {
        $register = function (Api $api) {
            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

            $api->authorize(AuthorType::class)->read(
                function (EloquentAuthContext $c, PreviousRuleTestService $service, PreviousAuthRule $previous) {
                    if ($service->seesEverything()) {
                        return;
                    }
                    $previous->call($c);
                }
            );
        };

        $this->assertEquals(['ok one', 'ok two'], $this->listNames($register));

        PreviousRuleTestService::$seesEverything = true;

        $this->assertEquals(['blocked', 'ok one', 'ok two'], $this->listNames($register));
    }

    public function test_the_new_rule_can_narrow_on_top_of_the_rule_it_replaces()
    {
        $names = $this->listNames(function (Api $api) {
            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

            $api->authorize(AuthorType::class)->read(
                function (EloquentAuthContext $c, PreviousAuthRule $previous) {
                    $previous->call($c);
                    $c->query()->where('name', '!=', 'ok two');
                }
            );
        });

        $this->assertEquals(['ok one'], $names);
    }

    public function test_the_rule_it_replaces_still_gets_its_own_dependencies()
    {
        $names = $this->listNames(function (Api $api) {
            $api->authorize(AuthorType::class)->read(
                function (EloquentAuthContext $c, PreviousRuleTestService $service) {
                    $service::$calls++;
                    $c->query()->where('name', 'like', 'ok%');
                }
            );

            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c, PreviousAuthRule $previous) => $previous->call($c));
        });

        $this->assertEquals(['ok one', 'ok two'], $names);
        $this->assertGreaterThan(0, PreviousRuleTestService::$calls);
    }

    public function test_previous_rules_chain_over_several_registrations()
    {
        $names = $this->listNames(function (Api $api) {
            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

            $api->authorize(AuthorType::class)->read(function (EloquentAuthContext $c, PreviousAuthRule $previous) {
                $previous->call($c);
                $c->query()->where('name', '!=', 'ok one');
            });

            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c, PreviousAuthRule $previous) => $previous->call($c));
        });

        $this->assertEquals(['ok two'], $names);
    }

    public function test_without_an_earlier_rule_previous_restricts_nothing()
    {
        $exists = null;

        $names = $this->listNames(function (Api $api) use (&$exists) {
            $api->authorize(AuthorType::class)->read(
                function (EloquentAuthContext $c, PreviousAuthRule $previous) use (&$exists) {
                    $exists = $previous->exists();
                    $previous->call($c);
                }
            );
        });

        $this->assertFalse($exists);
        $this->assertEquals(['blocked', 'ok one', 'ok two'], $names);
    }

    public function test_reset_leaves_no_earlier_rule()
    {
        $names = $this->listNames(function (Api $api) {
            $api->authorize(AuthorType::class)
                ->read(fn (EloquentAuthContext $c) => $c->query()->where('name', 'like', 'ok%'));

            $api->authorize(AuthorType::class)
                ->reset()
                ->read(fn (EloquentAuthContext $c, PreviousAuthRule $previous) => $previous->call($c));
        });

        $this->assertEquals(['blocked', 'ok one', 'ok two'], $names);
    }

    public function test_each_operation_keeps_its_own_earlier_rule()
    {
        $previousOfRead = null;
        $previousOfDelete = null;

        $this->listNames(function (Api $api) use (&$previousOfRead, &$previousOfDelete) {
            $api->authorize(AuthorType::class)
                ->delete(fn (EloquentAuthContext $c) => $c->deny());

            // a later read rule must not see the delete rule as its predecessor
            $api->authorize(AuthorType::class)->read(
                function (EloquentAuthContext $c, PreviousAuthRule $previous) use (&$previousOfRead) {
                    $previousOfRead = $previous->exists();
                }
            );
        });

        $this->assertFalse($previousOfRead);
    }

    private function listNames(\Closure $configureAuth): array
    {
        ['data' => $data] = $this->request($configureAuth, [
            'resource' => 'Blog.AuthorResource',
            'action' => 'list',
            'fields' => ['name' => true]
        ]);

        $names = array_map(fn ($author) => $author->name, $data);
        sort($names);
        return $names;
    }
}

class PreviousRuleTestService
{
    public static bool $seesEverything = false;

    public static int $calls = 0;

    public function seesEverything(): bool
    {
        return static::$seesEverything;
    }
}
