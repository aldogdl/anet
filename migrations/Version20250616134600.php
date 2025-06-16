<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250616134600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sols CHANGE iku_own iku_app_src VARCHAR(25) NOT NULL');
        $this->addSql('DROP INDEX IKU ON us_com');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE sols CHANGE iku_app_src iku_own VARCHAR(25) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX IKU ON us_com (iku(12))');
    }
}
