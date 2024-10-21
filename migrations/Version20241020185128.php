<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241020185128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mks_mds (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(15) NOT NULL, vars LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', id_mrk INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE items ADD own_ml_id VARCHAR(15) NOT NULL, ADD id_anet INT NOT NULL, ADD pza_id INT NOT NULL, ADD mrk_id INT NOT NULL, ADD mdl_id INT NOT NULL, ADD thumbnail VARCHAR(155) NOT NULL, ADD permalink VARCHAR(155) NOT NULL, ADD source VARCHAR(10) NOT NULL, ADD generik LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', ADD matchs LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', ADD calif INT NOT NULL, CHANGE anios anios LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', CHANGE fotos fotos LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mks_mds');
        $this->addSql('ALTER TABLE items DROP own_ml_id, DROP id_anet, DROP pza_id, DROP mrk_id, DROP mdl_id, DROP thumbnail, DROP permalink, DROP source, DROP generik, DROP matchs, DROP calif, CHANGE anios anios LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', CHANGE fotos fotos LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\'');
    }
}
