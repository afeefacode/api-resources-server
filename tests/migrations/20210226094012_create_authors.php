<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuthors extends AbstractMigration
{
    public function up()
    {
        $this->table('authors', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('email', 'string', ['limit' => 255])

            ->addColumn('featured_tag_id', 'integer', ['signed' => false, 'null' => true])
            ->addForeignKey('featured_tag_id', 'tags', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])

            ->create();
    }

    public function down()
    {
        $this->table('authors')->drop()->save();
    }
}
