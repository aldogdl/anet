<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224125150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_itempub_mrk_mdl_active_year ON item_pub (mrk_id, mdl_id, is_active, anio_inicio, anio_fin)');
        $this->addSql('CREATE INDEX idx_itempub_mrk_mdl_active_created_id ON item_pub (mrk_id, mdl_id, is_active, created, id)');
        $this->addSql('CREATE INDEX idx_itempub_mrk_mdl_active_lado_poss ON item_pub (mrk_id, mdl_id, is_active, lado, poss)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_itempub_mrk_mdl_active_year ON item_pub');
        $this->addSql('DROP INDEX idx_itempub_mrk_mdl_active_created_id ON item_pub');
        $this->addSql('DROP INDEX idx_itempub_mrk_mdl_active_lado_poss ON item_pub');
    }
}
