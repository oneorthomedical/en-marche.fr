<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20200702003042 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE 
          committee_election 
        ADD 
          archived_committee_id INT DEFAULT NULL, 
          CHANGE committee_id committee_id INT UNSIGNED DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE 
          committee_election 
        DROP 
          archived_committee_id, 
          CHANGE committee_id committee_id INT UNSIGNED NOT NULL');
    }
}
