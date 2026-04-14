<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260413222005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_itempub_mrk_mdl_active_year ON item_pub');
        $this->addSql('DROP INDEX idx_itempub_mrk_mdl_active_lado_poss ON item_pub');
        $this->addSql('DROP INDEX idx_itempub_mrk_mdl_active_created_id ON item_pub');
        $this->addSql('ALTER TABLE item_pub ADD slug VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_pub DROP slug');
        $this->addSql('CREATE INDEX idx_itempub_mrk_mdl_active_year ON item_pub (mrk_id, mdl_id, is_active, anio_inicio, anio_fin)');
        $this->addSql('CREATE INDEX idx_itempub_mrk_mdl_active_lado_poss ON item_pub (mrk_id, mdl_id, is_active, lado, poss)');
        $this->addSql('CREATE INDEX idx_itempub_mrk_mdl_active_created_id ON item_pub (mrk_id, mdl_id, is_active, created, id)');
    }
}
