<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLinks extends AbstractMigration
{
    public function up()
    {
        $this->table('links', ['signed' => false])
            ->addColumn('author_id', 'integer', ['signed' => false])
            ->addForeignKey('author_id', 'authors', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])

            ->addColumn('url', 'string', ['limit' => 255])

            ->create();
    }

    public function down()
    {
        $this->table('links')->drop()->save();
    }
}
