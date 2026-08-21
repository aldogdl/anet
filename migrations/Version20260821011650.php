<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260821011650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE next_seller (id INT AUTO_INCREMENT NOT NULL, seller_id INT NOT NULL, registerd_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_use_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', stt INT NOT NULL, cant_use INT NOT NULL, UNIQUE INDEX UNIQ_A2CFD6408DE820D9 (seller_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE next_seller ADD CONSTRAINT FK_A2CFD6408DE820D9 FOREIGN KEY (seller_id) REFERENCES sys_com (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE next_seller DROP FOREIGN KEY FK_A2CFD6408DE820D9');
        $this->addSql('DROP TABLE next_seller');
    }
}
