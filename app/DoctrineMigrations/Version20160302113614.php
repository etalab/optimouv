<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160302113614 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE scenario DROP FOREIGN KEY FK_id_rapport');
        $this->addSql('CREATE TABLE parametres (id INT AUTO_INCREMENT NOT NULL, id_groupe INT DEFAULT NULL, nom VARCHAR(100) NOT NULL, type_action VARCHAR(50) NOT NULL, valeur_exclusion INT NOT NULL, date_creation DATE NOT NULL, params LONGTEXT DEFAULT NULL, statut TINYINT(1) DEFAULT NULL, INDEX IDX_1A79799D228E39CC (id_groupe), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE resultats (id INT AUTO_INCREMENT NOT NULL, id_rapport INT DEFAULT NULL, nom VARCHAR(50) NOT NULL, kilometres INT NOT NULL, duree INT NOT NULL, co2_voiture DOUBLE PRECISION NOT NULL, co2_covoiturage DOUBLE PRECISION NOT NULL, co2_minibus DOUBLE PRECISION NOT NULL, cout_voiture DOUBLE PRECISION NOT NULL, cout_covoiturage DOUBLE PRECISION NOT NULL, cout_minibus DOUBLE PRECISION NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, details_calcul LONGTEXT DEFAULT NULL, INDEX id_rapport_idx (id_rapport), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE parametres ADD CONSTRAINT FK_1A79799D228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id)');
        $this->addSql('ALTER TABLE resultats ADD CONSTRAINT FK_55ED970260A909EC FOREIGN KEY (id_rapport) REFERENCES parametres (id)');
        $this->addSql('DROP TABLE rapport');
        $this->addSql('set foreign_key_checks=0');
        $this->addSql('DROP TABLE scenario');
//        $this->addSql('ALTER TABLE poule ADD CONSTRAINT FK_FA1FEB406E9E244D FOREIGN KEY (id_scenario) REFERENCES resultats (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE resultats DROP FOREIGN KEY FK_55ED970260A909EC');
//        $this->addSql('ALTER TABLE poule DROP FOREIGN KEY FK_FA1FEB406E9E244D');
        $this->addSql('CREATE TABLE rapport (id INT AUTO_INCREMENT NOT NULL, id_groupe INT DEFAULT NULL, nom VARCHAR(100) NOT NULL COLLATE utf8_unicode_ci, type_action VARCHAR(50) NOT NULL COLLATE utf8_unicode_ci, valeur_exclusion INT NOT NULL, date_creation DATE NOT NULL, params LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci, statut TINYINT(1) DEFAULT NULL, INDEX IDX_BE34A09C228E39CC (id_groupe), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scenario (id INT AUTO_INCREMENT NOT NULL, id_rapport INT DEFAULT NULL, nom VARCHAR(50) NOT NULL COLLATE utf8_unicode_ci, kilometres INT NOT NULL, duree INT NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, co2_voiture DOUBLE PRECISION NOT NULL, co2_covoiturage DOUBLE PRECISION NOT NULL, co2_minibus DOUBLE PRECISION NOT NULL, cout_voiture DOUBLE PRECISION NOT NULL, cout_covoiturage DOUBLE PRECISION NOT NULL, cout_minibus DOUBLE PRECISION NOT NULL, details_calcul LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci, INDEX id_rapport_idx (id_rapport), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rapport ADD CONSTRAINT FK_BE34A09C228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE scenario ADD CONSTRAINT FK_id_rapport FOREIGN KEY (id_rapport) REFERENCES rapport (id)');
        $this->addSql('set foreign_key_checks=0');
        $this->addSql('DROP TABLE parametres');
        $this->addSql('DROP TABLE resultats');
    }
}
