<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220820200209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE filtros ADD pza_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE filtros ADD CONSTRAINT FK_9E72BE641318D848 FOREIGN KEY (pza_id) REFERENCES piezas_name (id)');
        $this->addSql('CREATE INDEX IDX_9E72BE641318D848 ON filtros (pza_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE filtros DROP FOREIGN KEY FK_9E72BE641318D848');
        $this->addSql('DROP INDEX IDX_9E72BE641318D848 ON filtros');
        $this->addSql('ALTER TABLE filtros DROP pza_id');
    }
}
