<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160329152157 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE fos_user ADD id_discipline INT DEFAULT NULL, ADD civilite VARCHAR(50) DEFAULT NULL, ADD fonction VARCHAR(50) DEFAULT NULL, ADD adresse VARCHAR(150) DEFAULT NULL, ADD num_licencie VARCHAR(150) DEFAULT NULL, ADD telephone INT DEFAULT NULL, ADD id_federation INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fos_user ADD CONSTRAINT FK_957A6479D0346EE8 FOREIGN KEY (id_discipline) REFERENCES discipline (id)');
        $this->addSql('CREATE INDEX IDX_957A6479D0346EE8 ON fos_user (id_discipline)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE fos_user DROP FOREIGN KEY FK_957A6479D0346EE8');
        $this->addSql('DROP INDEX IDX_957A6479D0346EE8 ON fos_user');
        $this->addSql('ALTER TABLE fos_user DROP id_discipline, DROP civilite, DROP fonction, DROP adresse, DROP num_licencie, DROP telephone, DROP id_federation');
    }
}
