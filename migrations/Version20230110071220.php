<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230110071220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE font ADD COLUMN version VARCHAR(12) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__font AS SELECT id, slug, family, display_name, category, modified_at, added_at, updated_at, designers, subsets, variants, axes, popularity FROM font');
        $this->addSql('DROP TABLE font');
        $this->addSql('CREATE TABLE font (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, family VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, category VARCHAR(255) NOT NULL, modified_at DATE NOT NULL --(DC2Type:date_immutable)
        , added_at DATE NOT NULL --(DC2Type:date_immutable)
        , updated_at DATE NOT NULL --(DC2Type:date_immutable)
        , designers CLOB DEFAULT NULL --(DC2Type:array)
        , subsets CLOB NOT NULL --(DC2Type:array)
        , variants CLOB NOT NULL --(DC2Type:array)
        , axes CLOB DEFAULT NULL --(DC2Type:json)
        , popularity SMALLINT NOT NULL)');
        $this->addSql('INSERT INTO font (id, slug, family, display_name, category, modified_at, added_at, updated_at, designers, subsets, variants, axes, popularity) SELECT id, slug, family, display_name, category, modified_at, added_at, updated_at, designers, subsets, variants, axes, popularity FROM __temp__font');
        $this->addSql('DROP TABLE __temp__font');
    }
}
