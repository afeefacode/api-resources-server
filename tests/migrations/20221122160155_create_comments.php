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
            ->addIndex(['owner_id', 'owner_type'])

            ->addColumn('text', 'string', ['limit' => 255])
            ->create();
    }

    public function down()
    {
        $this->table('comments')->drop()->save();
    }
}
