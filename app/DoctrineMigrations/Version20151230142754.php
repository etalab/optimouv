<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20151230142754 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE rapport CHANGE id_groupe id_groupe INT DEFAULT NULL');
        $this->addSql('ALTER TABLE rapport ADD CONSTRAINT FK_BE34A09C228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id)');
        $this->addSql('CREATE INDEX IDX_BE34A09C228E39CC ON rapport (id_groupe)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE rapport DROP FOREIGN KEY FK_BE34A09C228E39CC');
        $this->addSql('DROP INDEX IDX_BE34A09C228E39CC ON rapport');
        $this->addSql('ALTER TABLE rapport CHANGE id_groupe id_groupe INT NOT NULL');
    }
}
