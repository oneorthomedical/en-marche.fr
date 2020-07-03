<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20200703120200 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE 
          committees 
        DROP 
          INDEX UNIQ_A36198C6B4D2A5D1, 
        ADD 
          INDEX IDX_A36198C6B4D2A5D1 (current_designation_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE 
          committees 
        DROP 
          INDEX IDX_A36198C6B4D2A5D1, 
        ADD 
          UNIQUE INDEX UNIQ_A36198C6B4D2A5D1 (current_designation_id)');
    }
}
