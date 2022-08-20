<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220820192947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE filtros ADD marca_id INT DEFAULT NULL, ADD modelo_id INT DEFAULT NULL, ADD anio INT NOT NULL, ADD pieza VARCHAR(100) NOT NULL, ADD grupo VARCHAR(1) NOT NULL, DROP restris, DROP exceps, DROP espes, DROP is_fav, DROP plan');
        $this->addSql('ALTER TABLE filtros ADD CONSTRAINT FK_9E72BE6481EF0041 FOREIGN KEY (marca_id) REFERENCES ao1_marcas (id)');
        $this->addSql('ALTER TABLE filtros ADD CONSTRAINT FK_9E72BE64C3A9576E FOREIGN KEY (modelo_id) REFERENCES ao2_modelos (id)');
        $this->addSql('CREATE INDEX IDX_9E72BE6481EF0041 ON filtros (marca_id)');
        $this->addSql('CREATE INDEX IDX_9E72BE64C3A9576E ON filtros (modelo_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE filtros DROP FOREIGN KEY FK_9E72BE6481EF0041');
        $this->addSql('ALTER TABLE filtros DROP FOREIGN KEY FK_9E72BE64C3A9576E');
        $this->addSql('DROP INDEX IDX_9E72BE6481EF0041 ON filtros');
        $this->addSql('DROP INDEX IDX_9E72BE64C3A9576E ON filtros');
        $this->addSql('ALTER TABLE filtros ADD restris LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', ADD exceps LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', ADD espes LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:json)\', ADD is_fav TINYINT(1) NOT NULL, ADD plan VARCHAR(50) NOT NULL, DROP marca_id, DROP modelo_id, DROP anio, DROP pieza, DROP grupo');
    }
}
