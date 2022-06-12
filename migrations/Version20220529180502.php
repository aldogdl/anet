<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220529180502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE orden_resps (id INT AUTO_INCREMENT NOT NULL, orden_id INT NOT NULL, pieza_id INT NOT NULL, own_id INT NOT NULL, costo VARCHAR(10) NOT NULL, observs VARCHAR(255) NOT NULL, fotos LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', status LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DAAC4EBD9750851F (orden_id), INDEX IDX_DAAC4EBD269DAD0C (pieza_id), INDEX IDX_DAAC4EBD416BBCE1 (own_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE orden_resps ADD CONSTRAINT FK_DAAC4EBD9750851F FOREIGN KEY (orden_id) REFERENCES ordenes (id)');
        $this->addSql('ALTER TABLE orden_resps ADD CONSTRAINT FK_DAAC4EBD269DAD0C FOREIGN KEY (pieza_id) REFERENCES orden_piezas (id)');
        $this->addSql('ALTER TABLE orden_resps ADD CONSTRAINT FK_DAAC4EBD416BBCE1 FOREIGN KEY (own_id) REFERENCES ng2_contactos (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE orden_resps');
    }
}
