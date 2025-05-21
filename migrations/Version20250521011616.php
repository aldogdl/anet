<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250521011616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mmentity (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, variants JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', id_mrk INT DEFAULT NULL, scrape JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', extras JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE items CHANGE fotos fotos JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE img_wa img_wa JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE generik generik JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE matchs matchs JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE ng2_contactos CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mmentity');
        $this->addSql('ALTER TABLE items CHANGE fotos fotos JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE img_wa img_wa JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE generik generik JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE matchs matchs JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE ng2_contactos CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
