<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateComments extends AbstractMigration
{
    public function up()
    {
        $this->table('comments', ['signed' => false])
            ->addColumn('owner_id', 'integer', ['signed' => false])
            ->addColumn('owner_type', 'string', ['limit' => 255])

            ->addColumn('author_name', 'string', ['limit' => 255])
            ->addColumn('content', 'text')

            ->addColumn('date', 'datetime', ['null' => true])

            ->addIndex(['owner_id', 'owner_type'])

            ->create();
    }

    public function down()
    {
        $this->table('comments')->drop()->save();
    }
}
