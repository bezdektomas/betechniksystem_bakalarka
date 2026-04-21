<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315233919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_priloha (id INT AUTO_INCREMENT NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL, zprava_id INT NOT NULL, INDEX IDX_4A280E0DFB8348A3 (zprava_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_priloha ADD CONSTRAINT FK_4A280E0DFB8348A3 FOREIGN KEY (zprava_id) REFERENCES chat_zprava (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_priloha DROP FOREIGN KEY FK_4A280E0DFB8348A3');
        $this->addSql('DROP TABLE chat_priloha');
    }
}
