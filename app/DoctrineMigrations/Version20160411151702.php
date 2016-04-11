<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160411151702 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE statistiques_date DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE statistiques_date CHANGE id_utilisateur id_utilisateur INT NOT NULL');
        $this->addSql('ALTER TABLE statistiques_date ADD PRIMARY KEY (date_creation, id_utilisateur, type_statistiques)');
        $this->addSql('ALTER TABLE statistiques_date_temps DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE statistiques_date_temps CHANGE id_utilisateur id_utilisateur INT NOT NULL');
        $this->addSql('ALTER TABLE statistiques_date_temps ADD PRIMARY KEY (temps_debut, temps_fin, id_utilisateur, type_statistiques)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE statistiques_date DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE statistiques_date CHANGE id_utilisateur id_utilisateur INT DEFAULT NULL');
        $this->addSql('ALTER TABLE statistiques_date ADD PRIMARY KEY (date_creation, type_statistiques)');
        $this->addSql('ALTER TABLE statistiques_date_temps DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE statistiques_date_temps CHANGE id_utilisateur id_utilisateur INT DEFAULT NULL');
        $this->addSql('ALTER TABLE statistiques_date_temps ADD PRIMARY KEY (temps_debut, temps_fin, type_statistiques)');
    }
}
