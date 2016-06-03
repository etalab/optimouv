<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160603104403 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql("
                        UPDATE villes_france_free SET ville_code_postal='06000' WHERE ville_id='40000';
                        UPDATE villes_france_free SET ville_code_postal='06100' WHERE ville_id='40001';
                        UPDATE villes_france_free SET ville_code_postal='06200' WHERE ville_id='40002';
                        UPDATE villes_france_free SET ville_code_postal='06300' WHERE ville_id='40003';
                        UPDATE villes_france_free SET ville_code_postal='06130' WHERE ville_id='40004';
                        UPDATE villes_france_free SET ville_code_postal='06520' WHERE ville_id='40005';
                        UPDATE villes_france_free SET ville_code_postal='06400' WHERE ville_id='40006';
                        UPDATE villes_france_free SET ville_code_postal='06150' WHERE ville_id='40007';
                        UPDATE villes_france_free SET ville_code_postal='06600' WHERE ville_id='40008';
                        UPDATE villes_france_free SET ville_code_postal='06160' WHERE ville_id='40009';
                        UPDATE villes_france_free SET ville_code_postal='07310' WHERE ville_id='40010';
                        UPDATE villes_france_free SET ville_code_postal='07320' WHERE ville_id='40011';
                        UPDATE villes_france_free SET ville_code_postal='01410' WHERE ville_id='40049';
                      ");

    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
