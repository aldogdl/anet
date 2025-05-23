<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250522195052 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE item_pub (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, thumb VARCHAR(255) NOT NULL, img_big VARCHAR(255) NOT NULL, price DOUBLE PRECISION NOT NULL, place VARCHAR(50) NOT NULL, link VARCHAR(255) NOT NULL, is_active TINYINT(1) NOT NULL, created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', extras JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', id_src VARCHAR(150) NOT NULL, src VARCHAR(5) NOT NULL, own_slug VARCHAR(25) NOT NULL, own_wa_id VARCHAR(20) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE item_pub');
    }
}
