<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316193537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_fcm_token (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(512) NOT NULL, platform VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_1958071DA76ED395 (user_id), UNIQUE INDEX UNIQ_1958071DA76ED3955F37A13B (user_id, token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_fcm_token ADD CONSTRAINT FK_1958071DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_fcm_token DROP FOREIGN KEY FK_1958071DA76ED395');
        $this->addSql('DROP TABLE user_fcm_token');
    }
}
