<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250604131139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE us_com (id INT AUTO_INCREMENT NOT NULL, own_app VARCHAR(45) NOT NULL, us_wa_id VARCHAR(15) NOT NULL, us_name VARCHAR(50) NOT NULL, role VARCHAR(15) NOT NULL, topic VARCHAR(10) NOT NULL, stt INT NOT NULL, last_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', tkfb VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE us_com');
    }
}
