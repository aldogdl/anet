<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405182409 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Agregar columna como nullable primero
        $this->addSql('ALTER TABLE item_pub ADD updated_at DATETIME NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        // Actualizar registros existentes con la fecha actual
        $this->addSql('UPDATE item_pub SET updated_at = NOW() WHERE updated_at IS NULL');
        // Cambiar a NOT NULL después de actualizar
        $this->addSql('ALTER TABLE item_pub MODIFY updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_pub DROP updated_at');
    }
}
