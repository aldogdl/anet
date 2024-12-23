<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241223140934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fcm (id INT AUTO_INCREMENT NOT NULL, waid VARCHAR(20) NOT NULL, slug VARCHAR(20) NOT NULL, device VARCHAR(10) NOT NULL, tkfcm VARCHAR(255) NOT NULL, nvm JSON NOT NULL COMMENT \'(DC2Type:json)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('DROP TABLE product');
        $this->addSql('ALTER TABLE items CHANGE fotos fotos JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE img_wa img_wa JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE generik generik JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE matchs matchs JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE ng2_contactos CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, src VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, title VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, token VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, permalink VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, thumbnail VARCHAR(190) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, fotos JSON NOT NULL COMMENT \'(DC2Type:json)\', detalles LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, price DOUBLE PRECISION NOT NULL, original_price DOUBLE PRECISION NOT NULL, seller_id VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, seller_slug VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, attrs JSON NOT NULL COMMENT \'(DC2Type:json)\', is_vendida INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX token_idx (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('DROP TABLE fcm');
        $this->addSql('ALTER TABLE items CHANGE fotos fotos JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE img_wa img_wa JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE generik generik JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE matchs matchs JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE ng2_contactos CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
