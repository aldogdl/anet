<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250612013438 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE sols (id INT AUTO_INCREMENT NOT NULL, iku_id INT NOT NULL, code VARCHAR(150) NOT NULL, detalle VARCHAR(150) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_1B01585EB53AF5E1 (iku_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sols ADD CONSTRAINT FK_1B01585EB53AF5E1 FOREIGN KEY (iku_id) REFERENCES us_com (id)');
        $this->addSql('DROP INDEX IKU ON us_com');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sols DROP FOREIGN KEY FK_1B01585EB53AF5E1');
        $this->addSql('DROP TABLE sols');
        $this->addSql('CREATE UNIQUE INDEX IKU ON us_com (iku(12))');
    }
}
