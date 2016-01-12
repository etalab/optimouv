<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160106101915 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("INSERT INTO villes_france_free (ville_departement, ville_slug, ville_nom, ville_nom_simple, ville_nom_reel, ville_code_postal,ville_population_2012, ville_longitude_deg, ville_latitude_deg)
                       VALUES ('98', 'monaco', 'Monaco', 'monaco', 'Monaco', '98000', '37579', '7.420816', '43.737411');
");

    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM villes_france_free WHERE ville_nom=\'Monaco\' ");
    }
}
