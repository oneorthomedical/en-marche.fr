<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20200703112951 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE committees ADD current_designation_id INT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE 
          committees 
        ADD 
          CONSTRAINT FK_A36198C6B4D2A5D1 FOREIGN KEY (current_designation_id) REFERENCES designation (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_A36198C6B4D2A5D1 ON committees (current_designation_id)');
        $this->addSql('ALTER TABLE 
          committee_election 
        DROP 
          INDEX UNIQ_2CA406E5ED1A100B, 
        ADD 
          INDEX IDX_2CA406E5ED1A100B (committee_id)');
        $this->addSql('ALTER TABLE committee_election DROP archived_committee_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE 
          committee_election 
        DROP 
          INDEX IDX_2CA406E5ED1A100B, 
        ADD 
          UNIQUE INDEX UNIQ_2CA406E5ED1A100B (committee_id)');
        $this->addSql('ALTER TABLE committee_election ADD archived_committee_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE committees DROP FOREIGN KEY FK_A36198C6B4D2A5D1');
        $this->addSql('DROP INDEX UNIQ_A36198C6B4D2A5D1 ON committees');
        $this->addSql('ALTER TABLE committees DROP current_designation_id');
    }
}
