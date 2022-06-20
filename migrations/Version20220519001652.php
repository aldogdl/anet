<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220519001652 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE scm_ordpza');
        $this->addSql('ALTER TABLE campaings ADD slug VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE orden_piezas DROP ruta');
        $this->addSql('ALTER TABLE ordenes DROP ruta');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE scm_ordpza (id INT AUTO_INCREMENT NOT NULL, orden_id INT DEFAULT NULL, pieza_id INT DEFAULT NULL, own_id INT NOT NULL, avo_id INT NOT NULL, prioridad TINYINT(1) NOT NULL, acc INT NOT NULL, msg VARCHAR(3) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, sys VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7009012E416BBCE1 (own_id), INDEX IDX_7009012EFEBD9344 (avo_id), INDEX IDX_7009012E9750851F (orden_id), INDEX IDX_7009012E269DAD0C (pieza_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE scm_ordpza ADD CONSTRAINT FK_7009012EFEBD9344 FOREIGN KEY (avo_id) REFERENCES ng2_contactos (id)');
        $this->addSql('ALTER TABLE scm_ordpza ADD CONSTRAINT FK_7009012E269DAD0C FOREIGN KEY (pieza_id) REFERENCES orden_piezas (id)');
        $this->addSql('ALTER TABLE scm_ordpza ADD CONSTRAINT FK_7009012E9750851F FOREIGN KEY (orden_id) REFERENCES ordenes (id)');
        $this->addSql('ALTER TABLE scm_ordpza ADD CONSTRAINT FK_7009012E416BBCE1 FOREIGN KEY (own_id) REFERENCES ng2_contactos (id)');
        $this->addSql('ALTER TABLE campaings DROP slug');
        $this->addSql('ALTER TABLE orden_piezas ADD ruta VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE ordenes ADD ruta VARCHAR(50) NOT NULL');
    }
}