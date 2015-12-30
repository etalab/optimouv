<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20151230151510 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE scenario DROP FOREIGN KEY FK_3E45C8D8228E39CC');
        $this->addSql('DROP INDEX id_groupe_idx ON scenario');
        $this->addSql('ALTER TABLE scenario ADD co2_voiture DOUBLE PRECISION NOT NULL, ADD co2_covoiturage DOUBLE PRECISION NOT NULL, ADD co2_minibus DOUBLE PRECISION NOT NULL, ADD cout_voiture DOUBLE PRECISION NOT NULL, ADD cout_covoiturage DOUBLE PRECISION NOT NULL, ADD cout_minibus DOUBLE PRECISION NOT NULL, DROP co2, DROP cout, CHANGE id_groupe id_rapport INT DEFAULT NULL');
        $this->addSql('ALTER TABLE scenario ADD CONSTRAINT FK_id_rapport FOREIGN KEY (id_rapport) REFERENCES rapport (id)');
        $this->addSql('CREATE INDEX id_rapport_idx ON scenario (id_rapport)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE scenario DROP FOREIGN KEY FK_id_rapport');
        $this->addSql('DROP INDEX id_rapport_idx ON scenario');
        $this->addSql('ALTER TABLE scenario ADD co2 DOUBLE PRECISION NOT NULL, ADD cout DOUBLE PRECISION NOT NULL, DROP co2_voiture, DROP co2_covoiturage, DROP co2_minibus, DROP cout_voiture, DROP cout_covoiturage, DROP cout_minibus, CHANGE id_rapport id_groupe INT DEFAULT NULL');
        $this->addSql('ALTER TABLE scenario ADD CONSTRAINT FK_3E45C8D8228E39CC FOREIGN KEY (id_groupe) REFERENCES groupe (id)');
        $this->addSql('CREATE INDEX id_groupe_idx ON scenario (id_groupe)');
    }
}
