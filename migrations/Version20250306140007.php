<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250306140007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fcm ADD logged_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD use_app TINYINT(1) NOT NULL, ADD use_app_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD user_cat VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fcm DROP logged_at, DROP use_app, DROP use_app_at, DROP user_cat');
    }
}
