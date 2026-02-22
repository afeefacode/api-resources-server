<?php

namespace Afeefa\ApiResources\Tests\Eloquent;

use Afeefa\ApiResources\Test\Eloquent\ApiResourcesEloquentTest;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Article;
use Afeefa\ApiResources\Test\Fixtures\Blog\Models\Author;
use Carbon\Carbon;

class ModelTest extends ApiResourcesEloquentTest
{
    public function test_model()
    {
        Author::factory()
            ->count(5)
            ->has(Article::factory()->count(5))
            ->create();

        $this->assertEquals(5, Author::count());
        $this->assertEquals(25, Article::count());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('timezonesDataProvider')]
    public function test_datetime_timezone(string $localTz, string $timeString, string $isoTimeString)
    {
        date_default_timezone_set($localTz);

        $this->assertEquals($localTz, date_default_timezone_get());

        $article = Article::factory()
            ->for(Author::factory())
            ->create(['date' => $timeString]);

        $this->assertEquals(1, Article::count());

        $this->assertEquals(Carbon::parse($timeString), $article->date);
        $this->assertEquals($isoTimeString, $article->date->toJSON());

        $article = Article::first();

        $this->assertEquals(Carbon::parse($timeString), $article->date);
        $this->assertEquals($isoTimeString, $article->date->toJSON());
    }

    public static function timezonesDataProvider()
    {
        $times = [
            0 => ['2023-03-30T04:00:00+02:00', '2023-03-30T02:00:00.000000Z'],
            1 => ['2023-03-30T01:00:00+02:00', '2023-03-29T23:00:00.000000Z'],
            2 => ['2023-03-30T23:00:00+02:00', '2023-03-30T21:00:00.000000Z'],
            3 => ['2023-03-30T05:00:00+10:00', '2023-03-29T19:00:00.000000Z'],
            4 => ['2023-03-29T19:00:00.000000Z', '2023-03-29T19:00:00.000000Z'],
            'local' => ['2023-03-29T19:00:00', '2023-03-29T19:00:00.000000Z'], // use local tz
            'local2' => ['2023-03-30T01:00:00', '2023-03-30T01:00:00.000000Z'] // use local tz
        ];

        $localTimeZones = [
            'utc',
            'Europe/Berlin',
            'Asia/Tokyo'
        ];

        $data = [];

        foreach ($localTimeZones as $tz) {
            foreach ($times as $key => $timeArray) {
                $record = [
                    $tz,
                    $timeArray[0],
                    $timeArray[1]
                ];

                if ($key === 'local') {
                    $record[2] = match ($tz) {
                        'Europe/Berlin' => '2023-03-29T17:00:00.000000Z',
                        'Asia/Tokyo' => '2023-03-29T10:00:00.000000Z',
                        default => $record[2]
                    };
                }

                if ($key === 'local2') {
                    $record[2] = match ($tz) {
                        'Europe/Berlin' => '2023-03-29T23:00:00.000000Z',
                        'Asia/Tokyo' => '2023-03-29T16:00:00.000000Z',
                        default => $record[2]
                    };
                }

                $data[] = $record;
            }
        }

        return $data;
    }
}
