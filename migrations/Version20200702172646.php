<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20200702172646 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE committee_candidacy ADD committee_membership_id INT UNSIGNED NOT NULL');
        $this->addSql('UPDATE committee_candidacy AS t1
INNER JOIN committees_memberships AS t2 ON t2.committee_candidacy_id = t1.id
SET t1.committee_membership_id = t2.id
');
        $this->addSql('ALTER TABLE 
          committee_candidacy 
        ADD 
          CONSTRAINT FK_9A04454FCC6DA91 FOREIGN KEY (committee_membership_id) REFERENCES committees_memberships (id) ON DELETE CASCADE');

        $this->addSql('CREATE INDEX IDX_9A04454FCC6DA91 ON committee_candidacy (committee_membership_id)');
        $this->addSql('ALTER TABLE committees_memberships DROP FOREIGN KEY FK_E7A6490E4F376ABC');
        $this->addSql('DROP INDEX UNIQ_E7A6490E4F376ABC ON committees_memberships');
        $this->addSql('ALTER TABLE committees_memberships DROP committee_candidacy_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE committee_candidacy DROP FOREIGN KEY FK_9A04454FCC6DA91');
        $this->addSql('DROP INDEX IDX_9A04454FCC6DA91 ON committee_candidacy');
        $this->addSql('ALTER TABLE committee_candidacy DROP committee_membership_id');
        $this->addSql('ALTER TABLE committees_memberships ADD committee_candidacy_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE 
          committees_memberships 
        ADD 
          CONSTRAINT FK_E7A6490E4F376ABC FOREIGN KEY (committee_candidacy_id) REFERENCES committee_candidacy (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E7A6490E4F376ABC ON committees_memberships (committee_candidacy_id)');
    }
}
