<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315225459 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_precteni (id INT AUTO_INCREMENT NOT NULL, precteno_at DATETIME NOT NULL, konverzace_id INT NOT NULL, uzivatel_id INT NOT NULL, INDEX IDX_655D6A6A7D268FF5 (konverzace_id), INDEX IDX_655D6A6A9B3651C6 (uzivatel_id), UNIQUE INDEX uniq_precteni (konverzace_id, uzivatel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_precteni ADD CONSTRAINT FK_655D6A6A7D268FF5 FOREIGN KEY (konverzace_id) REFERENCES chat_konverzace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_precteni ADD CONSTRAINT FK_655D6A6A9B3651C6 FOREIGN KEY (uzivatel_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_precteni DROP FOREIGN KEY FK_655D6A6A7D268FF5');
        $this->addSql('ALTER TABLE chat_precteni DROP FOREIGN KEY FK_655D6A6A9B3651C6');
        $this->addSql('DROP TABLE chat_precteni');
    }
}
