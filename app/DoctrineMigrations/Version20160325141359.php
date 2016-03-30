<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160325141359 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE federation (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, designation VARCHAR(100) NOT NULL, date_creation DATE NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE discipline ADD CONSTRAINT FK_75BEEE3F8AE9B1A FOREIGN KEY (id_federation) REFERENCES federation (id)');
        $this->addSql('CREATE INDEX IDX_75BEEE3F8AE9B1A ON discipline (id_federation)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE discipline DROP FOREIGN KEY FK_75BEEE3F8AE9B1A');
        $this->addSql('DROP TABLE federation');
        $this->addSql('DROP INDEX IDX_75BEEE3F8AE9B1A ON discipline');
    }
}
