<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220824150304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE filtros DROP FOREIGN KEY FK_9E72BE64599D1722');
        $this->addSql('DROP INDEX IDX_9E72BE64599D1722 ON filtros');
        $this->addSql('ALTER TABLE filtros ADD emp_id INT DEFAULT NULL, DROP cot_id');
        $this->addSql('ALTER TABLE filtros ADD CONSTRAINT FK_9E72BE647A663008 FOREIGN KEY (emp_id) REFERENCES ng1_empresas (id)');
        $this->addSql('CREATE INDEX IDX_9E72BE647A663008 ON filtros (emp_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE filtros DROP FOREIGN KEY FK_9E72BE647A663008');
        $this->addSql('DROP INDEX IDX_9E72BE647A663008 ON filtros');
        $this->addSql('ALTER TABLE filtros ADD cot_id INT NOT NULL, DROP emp_id');
        $this->addSql('ALTER TABLE filtros ADD CONSTRAINT FK_9E72BE64599D1722 FOREIGN KEY (cot_id) REFERENCES ng1_empresas (id)');
        $this->addSql('CREATE INDEX IDX_9E72BE64599D1722 ON filtros (cot_id)');
    }
}
