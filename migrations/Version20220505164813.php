<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220505164813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE campaings (id INT AUTO_INCREMENT NOT NULL, titulo VARCHAR(50) NOT NULL, despec VARCHAR(255) NOT NULL, priority SMALLINT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scm_camp (id INT AUTO_INCREMENT NOT NULL, campaing_id INT NOT NULL, remiter_id INT NOT NULL, emiter_id INT NOT NULL, target VARCHAR(50) NOT NULL, src VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', send_at VARCHAR(100) NOT NULL, INDEX IDX_135AC6D08621DA8F (campaing_id), INDEX IDX_135AC6D0CB5586BB (remiter_id), INDEX IDX_135AC6D03C6AAFC6 (emiter_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE scm_receivers (id INT AUTO_INCREMENT NOT NULL, camp_id INT NOT NULL, receiver_id INT NOT NULL, stt VARCHAR(3) NOT NULL, send_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_608FBB9277075ABB (camp_id), INDEX IDX_608FBB92CD53EDB6 (receiver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE scm_camp ADD CONSTRAINT FK_135AC6D08621DA8F FOREIGN KEY (campaing_id) REFERENCES campaings (id)');
        $this->addSql('ALTER TABLE scm_camp ADD CONSTRAINT FK_135AC6D0CB5586BB FOREIGN KEY (remiter_id) REFERENCES ng2_contactos (id)');
        $this->addSql('ALTER TABLE scm_camp ADD CONSTRAINT FK_135AC6D03C6AAFC6 FOREIGN KEY (emiter_id) REFERENCES ng2_contactos (id)');
        $this->addSql('ALTER TABLE scm_receivers ADD CONSTRAINT FK_608FBB9277075ABB FOREIGN KEY (camp_id) REFERENCES scm_camp (id)');
        $this->addSql('ALTER TABLE scm_receivers ADD CONSTRAINT FK_608FBB92CD53EDB6 FOREIGN KEY (receiver_id) REFERENCES ng2_contactos (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE scm_camp DROP FOREIGN KEY FK_135AC6D08621DA8F');
        $this->addSql('ALTER TABLE scm_receivers DROP FOREIGN KEY FK_608FBB9277075ABB');
        $this->addSql('DROP TABLE campaings');
        $this->addSql('DROP TABLE scm_camp');
        $this->addSql('DROP TABLE scm_receivers');
    }
}
