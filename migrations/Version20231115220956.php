<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20231115220956 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(50) NOT NULL, src VARCHAR(10) NOT NULL, title VARCHAR(190) NOT NULL, token VARCHAR(190) NOT NULL, permalink VARCHAR(190) NOT NULL, thumbnail VARCHAR(190) NOT NULL, fotos LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', detalles LONGTEXT NOT NULL, price DOUBLE PRECISION NOT NULL, original_price DOUBLE PRECISION NOT NULL, seller_id INT NOT NULL, seller_slug VARCHAR(150) NOT NULL, attrs LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', is_vendida TINYINT(1) NOT NULL, INDEX token_idx (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE product');
    }
}
