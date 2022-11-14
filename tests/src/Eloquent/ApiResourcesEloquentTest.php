<?php

namespace Afeefa\ApiResources\Test\Eloquent;

use Afeefa\ApiResources\Test\ApiResourcesTest;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Events\Dispatcher;
use PDO;

class ApiResourcesEloquentTest extends ApiResourcesTest
{
    private static ?PDO $pdo = null;

    private static ?string $dbName = null;

    public static function setUpBeforeClass(): void
    {
        static::connectDb();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->truncateUsedTables();
    }

    protected function modelTypeBuilder(): ModelTypeBuilder
    {
        return (new ModelTypeBuilder($this->container));
    }

    protected static function connectDb()
    {
        if (!self::$pdo) {
            $capsule = new DB();
            $capsule->setEventDispatcher(new Dispatcher());
            $capsule->setAsGlobal();
            $capsule->bootEloquent();

            $dbConfig = (include('phinx.php'))['environments']['development'];

            $capsule->addConnection([
                'driver' => 'mysql',
                'host' => $dbConfig['host'],
                'port' => $dbConfig['port'],
                'database' => $dbConfig['name'],
                'username' => $dbConfig['user'],
                'password' => $dbConfig['pass']
            ]);

            self::$pdo = $capsule->getConnection()->getPdo();
            self::$dbName = $dbConfig['name'];

            // enforce morph maps

            Relation::requireMorphMap();

            // Model factories have to live here Models/Factories

            Factory::guessFactoryNamesUsing(function (string $ModelClass) {
                return preg_replace('/Models/', 'Models\Factories', $ModelClass) . 'Factory';
            });

            Factory::guessModelNamesUsing(function (Factory $factory) {
                return preg_replace('/Factories\\\(.+)Factory/', '$1', $factory::class);
            });
        }
    }

    protected static function truncateUsedTables()
    {
        $dbName = self::$dbName;
        $tables = DB::select(
            <<<EOT
                SELECT TABLE_NAME
                from information_schema.tables
                WHERE TABLE_SCHEMA = '{$dbName}'
                AND TABLE_NAME <> 'phinxlog'
                AND (TABLE_ROWS > 0 OR AUTO_INCREMENT > 1)
                EOT
        );

        $tables = array_map(fn ($table) => $table->TABLE_NAME, $tables);

        if (count($tables)) {
            $pdo = static::$pdo;
            $pdo->exec('SET foreign_key_checks = 0');
            foreach ($tables as $table) {
                $pdo->exec("truncate table {$table}");
            }
            $pdo->exec('SET foreign_key_checks = 1');
        }
    }
}
