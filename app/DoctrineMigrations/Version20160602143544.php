<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160602143544 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("INSERT INTO villes_france_free (ville_id, ville_departement, ville_slug, ville_nom, ville_nom_simple, ville_nom_reel, ville_nom_soundex, ville_nom_metaphone, ville_code_postal, ville_commune, ville_code_commune, ville_arrondissement, ville_canton, ville_amdi, ville_population_2010, ville_population_1999, ville_population_2012, ville_densite_2010, ville_surface, ville_longitude_deg, ville_latitude_deg, ville_longitude_grd, ville_latitude_grd, ville_longitude_dms, ville_latitude_dms, ville_zmin, ville_zmax)
                        VALUES 
                        (40129, '68', 'saint-louis-68', 'SAINT-LOUIS', 'saint louis', 'Saint-Louis', 'S5342', 'SNTLS', '68300', '297', '68297', '4', '11', '6', '20127', '19973', '19900', '1194', '16.85', '7.56667', '47.5833', '5809', '52873', '73354', '473507', '237', '278')");

    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("DELETE FROM villes_france_free WHERE ville_id = 40129");

    }
}
