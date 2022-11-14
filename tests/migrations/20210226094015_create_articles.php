<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateArticles extends AbstractMigration
{
    public function up()
    {
        $this->table('articles', ['signed' => false])
            ->addColumn('author_id', 'integer', ['signed' => false])
            ->addForeignKey('author_id', 'authors', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])

            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('summary', 'string', ['limit' => 400, 'null' => true])
            ->addColumn('content', 'text', ['null' => true])
            ->addColumn('date', 'datetime')

            ->create();
    }

    public function down()
    {
        $this->table('articles')->drop()->save();
    }
}
