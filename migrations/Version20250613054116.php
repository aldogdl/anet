<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250613054116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pubs (id INT AUTO_INCREMENT NOT NULL, iku_own_id INT NOT NULL, iku VARCHAR(25) NOT NULL, code VARCHAR(150) NOT NULL, detalle VARCHAR(150) NOT NULL, price DOUBLE PRECISION NOT NULL, costo DOUBLE PRECISION NOT NULL, fto_ref VARCHAR(50) NOT NULL, fotos JSON NOT NULL COMMENT \'(DC2Type:json)\', app_slug VARCHAR(25) NOT NULL, app_wa_id VARCHAR(15) NOT NULL, is_df TINYINT(1) NOT NULL, stt INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8686FC98D1CA435D (iku_own_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE pubs ADD CONSTRAINT FK_8686FC98D1CA435D FOREIGN KEY (iku_own_id) REFERENCES us_com (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pubs DROP FOREIGN KEY FK_8686FC98D1CA435D');
        $this->addSql('DROP TABLE pubs');
    }
}
