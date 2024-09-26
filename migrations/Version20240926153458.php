<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240926153458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE items (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(15) NOT NULL, pieza VARCHAR(100) NOT NULL, lado VARCHAR(20) NOT NULL, poss VARCHAR(20) DEFAULT NULL, marca VARCHAR(25) NOT NULL, model VARCHAR(25) NOT NULL, anios LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', condicion VARCHAR(100) NOT NULL, id_item VARCHAR(100) NOT NULL, id_cot VARCHAR(100) NOT NULL, price DOUBLE PRECISION NOT NULL, costo DOUBLE PRECISION NOT NULL, origen VARCHAR(25) NOT NULL, fotos LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\', own_wa_id VARCHAR(20) NOT NULL, own_slug VARCHAR(50) NOT NULL, place VARCHAR(50) NOT NULL, stt SMALLINT NOT NULL, img_wa LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ng1_empresas (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(150) NOT NULL, domicilio VARCHAR(150) NOT NULL, cp INT NOT NULL, is_local TINYINT(1) NOT NULL, tel_fijo VARCHAR(20) NOT NULL, lat_lng VARCHAR(100) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE ng2_contactos (id INT AUTO_INCREMENT NOT NULL, empresa_id INT NOT NULL, curc VARCHAR(180) NOT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) NOT NULL, nombre VARCHAR(100) NOT NULL, is_cot TINYINT(1) NOT NULL, cargo VARCHAR(50) NOT NULL, celular VARCHAR(15) NOT NULL, key_cel LONGTEXT NOT NULL, key_web LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_4DFDDC98935F06DC (curc), INDEX IDX_4DFDDC98521E1991 (empresa_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(50) NOT NULL, src VARCHAR(10) NOT NULL, title VARCHAR(190) NOT NULL, token VARCHAR(190) NOT NULL, permalink VARCHAR(190) NOT NULL, thumbnail VARCHAR(190) NOT NULL, fotos LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', detalles LONGTEXT NOT NULL, price DOUBLE PRECISION NOT NULL, original_price DOUBLE PRECISION NOT NULL, seller_id VARCHAR(50) NOT NULL, seller_slug VARCHAR(150) NOT NULL, attrs LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', is_vendida INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX token_idx (token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE ng2_contactos ADD CONSTRAINT FK_4DFDDC98521E1991 FOREIGN KEY (empresa_id) REFERENCES ng1_empresas (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ng2_contactos DROP FOREIGN KEY FK_4DFDDC98521E1991');
        $this->addSql('DROP TABLE items');
        $this->addSql('DROP TABLE ng1_empresas');
        $this->addSql('DROP TABLE ng2_contactos');
        $this->addSql('DROP TABLE product');
    }
}
