<?php
declare (strict_types = 1);

use Phinx\Migration\AbstractMigration;

final class CreateAuthors extends AbstractMigration
{
    public function up()
    {
        $this->table('authors', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 255])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('password', 'string', ['limit' => 255])
            ->create();
    }

    public function down()
    {
        $this->table('authors')->drop()->save();
    }
}
