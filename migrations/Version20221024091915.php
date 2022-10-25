<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221024091915 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE file (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, font_id INTEGER NOT NULL, style VARCHAR(255) NOT NULL, weight SMALLINT NOT NULL, url CLOB NOT NULL, format VARCHAR(255) NOT NULL, subsets CLOB NOT NULL --(DC2Type:array)
        , unicode_range CLOB DEFAULT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , CONSTRAINT FK_8C9F3610D7F7F9EB FOREIGN KEY (font_id) REFERENCES font (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_8C9F3610D7F7F9EB ON file (font_id)');
        $this->addSql('CREATE TABLE font (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, family VARCHAR(255) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, category VARCHAR(255) NOT NULL, modified_at DATE NOT NULL --(DC2Type:date_immutable)
        , added_at DATE NOT NULL --(DC2Type:date_immutable)
        , updated_at DATE NOT NULL --(DC2Type:date_immutable)
        , designers CLOB DEFAULT NULL --(DC2Type:array)
        , subsets CLOB NOT NULL --(DC2Type:array)
        , variants CLOB NOT NULL --(DC2Type:array)
        , axes CLOB DEFAULT NULL --(DC2Type:json)
        , popularity SMALLINT NOT NULL)');
        $this->addSql('CREATE TABLE _symfony_scheduler_tasks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, task_name VARCHAR(255) NOT NULL, body CLOB NOT NULL)');
        $this->addSql('CREATE INDEX _symfony_scheduler_tasks_name ON _symfony_scheduler_tasks (task_name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE file');
        $this->addSql('DROP TABLE font');
        $this->addSql('DROP TABLE _symfony_scheduler_tasks');
    }
}
