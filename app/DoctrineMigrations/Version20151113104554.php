<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20151113104554 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE discipline (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(50) NOT NULL, federation VARCHAR(50) NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE entite (id INT AUTO_INCREMENT NOT NULL, id_discipline INT DEFAULT NULL, id_utilisateur INT NOT NULL, type_entite VARCHAR(50) NOT NULL, nom VARCHAR(50) NOT NULL, prenom VARCHAR(50) NOT NULL, adresse VARCHAR(100) NOT NULL, code_postal VARCHAR(5) NOT NULL, ville VARCHAR(50) NOT NULL, longitude DOUBLE PRECISION NOT NULL, latitude DOUBLE PRECISION NOT NULL, projection VARCHAR(50) NOT NULL, type_equipement VARCHAR(50) NOT NULL, nombre_equipement INT NOT NULL, capacite_rencontre TINYINT(1) NOT NULL, capacite_phase_finale TINYINT(1) NOT NULL, participants INT NOT NULL, licencies INT NOT NULL, lieu_rencontre_possible TINYINT(1) NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, INDEX id_discipline_idx (id_discipline), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE groupe (id INT AUTO_INCREMENT NOT NULL, id_utilisateur INT NOT NULL, nom VARCHAR(50) NOT NULL, description LONGTEXT NOT NULL, poules INT NOT NULL, interdiction TINYINT(1) NOT NULL, repartition_homogene TINYINT(1) NOT NULL, nbr_min_match_accueillir TINYINT(1) NOT NULL, nb_min_match_accueillir INT NOT NULL, nb_exclusion_zone INT NOT NULL, nb_participants INT NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE lieu_rencontre (id INT AUTO_INCREMENT NOT NULL, id_entite INT DEFAULT NULL, kilometres INT NOT NULL, duree INT NOT NULL, co2 DOUBLE PRECISION NOT NULL, cout DOUBLE PRECISION NOT NULL, INDEX id_entite_idx (id_entite), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE poule (id INT AUTO_INCREMENT NOT NULL, id_scenario INT DEFAULT NULL, nom VARCHAR(50) NOT NULL, numero VARCHAR(50) NOT NULL, kilometres INT NOT NULL, duree INT NOT NULL, co2 DOUBLE PRECISION NOT NULL, cout DOUBLE PRECISION NOT NULL, kilometres_moyens INT NOT NULL, duree_moyenne INT NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, INDEX id_scenario_idx (id_scenario), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reference (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, code VARCHAR(50) NOT NULL, valeur DOUBLE PRECISION NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scenario (id INT AUTO_INCREMENT NOT NULL, id_groupe INT DEFAULT NULL, nom VARCHAR(50) NOT NULL, kilometres INT NOT NULL, duree INT NOT NULL, co2 DOUBLE PRECISION NOT NULL, cout DOUBLE PRECISION NOT NULL, date_creation DATE NOT NULL, date_modification DATE NOT NULL, INDEX id_groupe_idx (id_groupe), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE entite ADD CONSTRAINT FK_1A291827D0346EE8 FOREIGN KEY (id_discipline) REFERENCES discipline (id)');
        $this->addSql('ALTER TABLE lieu_rencontre ADD CONSTRAINT FK_296BAE5A3C1EADCA FOREIGN KEY (id_entite) REFERENCES entite (id)');
        $this->addSql('ALTER TABLE poule ADD CONSTRAINT FK_FA1FEB406E9E244D FOREIGN KEY (id_scenario) REFERENCES scenario (id)');
        $this->addSql('ALTER TABLE scenario ADD CONSTRAINT FK_3E45C8D8228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE entite DROP FOREIGN KEY FK_1A291827D0346EE8');
        $this->addSql('ALTER TABLE lieu_rencontre DROP FOREIGN KEY FK_296BAE5A3C1EADCA');
        $this->addSql('ALTER TABLE scenario DROP FOREIGN KEY FK_3E45C8D8228E39CC');
        $this->addSql('ALTER TABLE poule DROP FOREIGN KEY FK_FA1FEB406E9E244D');
        $this->addSql('DROP TABLE discipline');
        $this->addSql('DROP TABLE entite');
        $this->addSql('DROP TABLE groupe');
        $this->addSql('DROP TABLE lieu_rencontre');
        $this->addSql('DROP TABLE poule');
        $this->addSql('DROP TABLE reference');
        $this->addSql('DROP TABLE scenario');
    }
}
