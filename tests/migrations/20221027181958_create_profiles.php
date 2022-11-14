<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateProfiles extends AbstractMigration
{
    public function up()
    {
        $this->table('profiles', ['signed' => false])
            ->addColumn('about_me', 'string', ['null' => true, 'limit' => 255])

            ->create();

        $this->table('authors')
            ->addColumn('profile_id', 'integer', ['signed' => false, 'null' => true])
            ->addForeignKey('profile_id', 'profiles', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])

            ->save();
    }

    public function down()
    {
        $this->table('authors')
            ->dropForeignKey('profile_id')
            ->removeColumn('profile_id')
            ->save();

        $this->table('profiles')->drop()->save();
    }
}
