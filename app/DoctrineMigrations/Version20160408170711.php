<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160408170711 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE statistiques_date (date_creation DATE NOT NULL, type_statistiques VARCHAR(50) NOT NULL, id_utilisateur INT DEFAULT NULL, id_discipline SMALLINT NOT NULL, id_federation SMALLINT NOT NULL, valeur INT NOT NULL, INDEX IDX_22E250CB50EAE44 (id_utilisateur), PRIMARY KEY(date_creation, type_statistiques)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE statistiques_date_temps (temps_debut DATETIME NOT NULL, temps_fin DATETIME NOT NULL, type_statistiques VARCHAR(50) NOT NULL, id_utilisateur INT DEFAULT NULL, id_discipline SMALLINT NOT NULL, id_federation SMALLINT NOT NULL, valeur INT NOT NULL, INDEX IDX_EE0F613D50EAE44 (id_utilisateur), PRIMARY KEY(temps_debut, temps_fin, type_statistiques)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE statistiques_date ADD CONSTRAINT FK_22E250CB50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES fos_user (id)');
        $this->addSql('ALTER TABLE statistiques_date_temps ADD CONSTRAINT FK_EE0F613D50EAE44 FOREIGN KEY (id_utilisateur) REFERENCES fos_user (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE statistiques_date DROP FOREIGN KEY FK_22E250CB50EAE44');
        $this->addSql('ALTER TABLE statistiques_date_temps DROP FOREIGN KEY FK_EE0F613D50EAE44');
        $this->addSql('DROP TABLE statistiques_date');
        $this->addSql('DROP TABLE statistiques_date_temps');
    }
}
