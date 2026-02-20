<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220192929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE pubs');
        $this->addSql('DROP TABLE sols');
        $this->addSql('ALTER TABLE item_pub ADD type INT NOT NULL, ADD iku VARCHAR(100) NOT NULL, ADD costo DOUBLE PRECISION NOT NULL, ADD pieza VARCHAR(150) NOT NULL, ADD mrk_id INT NOT NULL, ADD mdl_id INT NOT NULL, ADD anio_inicio INT NOT NULL, ADD anio_fin INT DEFAULT NULL, ADD lado VARCHAR(50) DEFAULT NULL, ADD poss VARCHAR(50) DEFAULT NULL, ADD detalles VARCHAR(255) DEFAULT NULL, ADD variantes VARCHAR(255) DEFAULT NULL, ADD ta_id INT NOT NULL, DROP place, DROP own_wa_id, CHANGE own_slug wa_id VARCHAR(25) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pubs (id INT AUTO_INCREMENT NOT NULL, iku VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, code VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, detalle VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, price DOUBLE PRECISION NOT NULL, costo DOUBLE PRECISION NOT NULL, fto_ref VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, fotos JSON NOT NULL COMMENT \'(DC2Type:json)\', app_slug VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, app_wa_id VARCHAR(15) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, is_df TINYINT(1) NOT NULL, stt INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ftec VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, iku_own VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE sols (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, detalle VARCHAR(150) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', app_slug VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, app_wa_id VARCHAR(15) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, iku VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, iku_app_src VARCHAR(25) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE item_pub ADD place VARCHAR(50) NOT NULL, ADD own_wa_id VARCHAR(20) NOT NULL, DROP type, DROP iku, DROP costo, DROP pieza, DROP mrk_id, DROP mdl_id, DROP anio_inicio, DROP anio_fin, DROP lado, DROP poss, DROP detalles, DROP variantes, DROP ta_id, CHANGE wa_id own_slug VARCHAR(25) NOT NULL');
    }
}
