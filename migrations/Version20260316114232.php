<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260316114232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_reakce (id INT AUTO_INCREMENT NOT NULL, emoji VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, zprava_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_7536DB61FB8348A3 (zprava_id), INDEX IDX_7536DB61A76ED395 (user_id), UNIQUE INDEX uniq_chat_reakce (zprava_id, user_id, emoji), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_reakce ADD CONSTRAINT FK_7536DB61FB8348A3 FOREIGN KEY (zprava_id) REFERENCES chat_zprava (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_reakce ADD CONSTRAINT FK_7536DB61A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_reakce DROP FOREIGN KEY FK_7536DB61FB8348A3');
        $this->addSql('ALTER TABLE chat_reakce DROP FOREIGN KEY FK_7536DB61A76ED395');
        $this->addSql('DROP TABLE chat_reakce');
    }
}
