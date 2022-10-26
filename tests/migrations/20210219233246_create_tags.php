<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTags extends AbstractMigration
{
    public function up()
    {
        $this->table('tags', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addIndex(['name'], ['unique' => true])
            ->create();

        $this
            ->table('tag_users', ['signed' => false])
            ->addColumn('tag_id', 'integer', ['signed' => false])
            ->addForeignKey('tag_id', 'tags', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])

            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('user_type', 'string', ['limit' => 255])

            ->addIndex(['user_id', 'user_type'])

            ->create();
    }

    public function down()
    {
        $this->table('tag_users')->drop()->save();
        $this->table('tags')->drop()->save();
    }
}
