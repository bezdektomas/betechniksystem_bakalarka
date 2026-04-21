<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316122007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_konverzace ADD pinned_zprava_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE chat_konverzace ADD CONSTRAINT FK_21CBAAA0B1ABD9ED FOREIGN KEY (pinned_zprava_id) REFERENCES chat_zprava (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_21CBAAA0B1ABD9ED ON chat_konverzace (pinned_zprava_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_konverzace DROP FOREIGN KEY FK_21CBAAA0B1ABD9ED');
        $this->addSql('DROP INDEX IDX_21CBAAA0B1ABD9ED ON chat_konverzace');
        $this->addSql('ALTER TABLE chat_konverzace DROP pinned_zprava_id');
    }
}
