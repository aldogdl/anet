<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920183309 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE remate (id INT AUTO_INCREMENT NOT NULL, remate_id VARCHAR(50) DEFAULT NULL, iku VARCHAR(100) NOT NULL, owner_slug VARCHAR(100) NOT NULL, owner_wa_id VARCHAR(50) NOT NULL, owner_ta_id INT DEFAULT 0 NOT NULL, precio_remate DOUBLE PRECISION DEFAULT \'0\' NOT NULL, precio_original DOUBLE PRECISION DEFAULT \'0\' NOT NULL, status INT DEFAULT 0 NOT NULL, pieza VARCHAR(255) NOT NULL, lado VARCHAR(50) DEFAULT \'A\' NOT NULL, poss VARCHAR(50) DEFAULT \'A\' NOT NULL, detalles LONGTEXT DEFAULT NULL, mrk_id INT DEFAULT 0 NOT NULL, marca VARCHAR(100) DEFAULT \'\' NOT NULL, mdl_id INT DEFAULT 0 NOT NULL, modelo VARCHAR(100) DEFAULT \'\' NOT NULL, anio_inicio INT DEFAULT 0 NOT NULL, anio_fin INT DEFAULT 9999 NOT NULL, foto_thumb VARCHAR(255) DEFAULT \'\' NOT NULL, foto_big VARCHAR(255) DEFAULT \'\' NOT NULL, path_img VARCHAR(255) DEFAULT \'\' NOT NULL, pictures JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_remate_owner (owner_slug, owner_wa_id, status), INDEX idx_remate_mrk_mdl (mrk_id, mdl_id, status), INDEX idx_remate_status_dates (status, created_at, expires_at), UNIQUE INDEX uniq_remate_iku_owner (iku, owner_slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE remate');
    }
}
