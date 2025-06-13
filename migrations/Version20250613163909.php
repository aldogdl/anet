<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250613163909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pubs DROP FOREIGN KEY FK_8686FC98D1CA435D');
        $this->addSql('DROP INDEX IDX_8686FC98D1CA435D ON pubs');
        $this->addSql('ALTER TABLE pubs ADD iku_own VARCHAR(25) NOT NULL, DROP iku_own_id');
        $this->addSql('ALTER TABLE sols DROP FOREIGN KEY FK_1B01585ED1CA435D');
        $this->addSql('DROP INDEX IDX_1B01585ED1CA435D ON sols');
        $this->addSql('ALTER TABLE sols ADD iku_own VARCHAR(25) NOT NULL, DROP iku_own_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pubs ADD iku_own_id INT NOT NULL, DROP iku_own');
        $this->addSql('ALTER TABLE pubs ADD CONSTRAINT FK_8686FC98D1CA435D FOREIGN KEY (iku_own_id) REFERENCES us_com (id)');
        $this->addSql('CREATE INDEX IDX_8686FC98D1CA435D ON pubs (iku_own_id)');
        $this->addSql('ALTER TABLE sols ADD iku_own_id INT NOT NULL, DROP iku_own');
        $this->addSql('ALTER TABLE sols ADD CONSTRAINT FK_1B01585ED1CA435D FOREIGN KEY (iku_own_id) REFERENCES us_com (id)');
        $this->addSql('CREATE INDEX IDX_1B01585ED1CA435D ON sols (iku_own_id)');
    }
}
