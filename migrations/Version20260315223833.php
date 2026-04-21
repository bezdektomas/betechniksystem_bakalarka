<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260315223833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_konverzace (id INT AUTO_INCREMENT NOT NULL, typ VARCHAR(20) NOT NULL, nazev VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, posledni_zprava_at DATETIME DEFAULT NULL, zakazka_id INT DEFAULT NULL, vytvoril_id INT NOT NULL, INDEX IDX_21CBAAA0B778F7C3 (zakazka_id), INDEX IDX_21CBAAA0F7FA5CA3 (vytvoril_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chat_konverzace_uzivatel (chat_konverzace_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_EF0C4754E767D72A (chat_konverzace_id), INDEX IDX_EF0C4754A76ED395 (user_id), PRIMARY KEY (chat_konverzace_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE chat_zprava (id INT AUTO_INCREMENT NOT NULL, obsah LONGTEXT NOT NULL, created_at DATETIME NOT NULL, edited_at DATETIME DEFAULT NULL, konverzace_id INT NOT NULL, autor_id INT NOT NULL, reply_to_id INT DEFAULT NULL, INDEX IDX_4E861E547D268FF5 (konverzace_id), INDEX IDX_4E861E5414D45BBE (autor_id), INDEX IDX_4E861E54FFDF7169 (reply_to_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chat_konverzace ADD CONSTRAINT FK_21CBAAA0B778F7C3 FOREIGN KEY (zakazka_id) REFERENCES zakazka (id)');
        $this->addSql('ALTER TABLE chat_konverzace ADD CONSTRAINT FK_21CBAAA0F7FA5CA3 FOREIGN KEY (vytvoril_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE chat_konverzace_uzivatel ADD CONSTRAINT FK_EF0C4754E767D72A FOREIGN KEY (chat_konverzace_id) REFERENCES chat_konverzace (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_konverzace_uzivatel ADD CONSTRAINT FK_EF0C4754A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chat_zprava ADD CONSTRAINT FK_4E861E547D268FF5 FOREIGN KEY (konverzace_id) REFERENCES chat_konverzace (id)');
        $this->addSql('ALTER TABLE chat_zprava ADD CONSTRAINT FK_4E861E5414D45BBE FOREIGN KEY (autor_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE chat_zprava ADD CONSTRAINT FK_4E861E54FFDF7169 FOREIGN KEY (reply_to_id) REFERENCES chat_zprava (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_konverzace DROP FOREIGN KEY FK_21CBAAA0B778F7C3');
        $this->addSql('ALTER TABLE chat_konverzace DROP FOREIGN KEY FK_21CBAAA0F7FA5CA3');
        $this->addSql('ALTER TABLE chat_konverzace_uzivatel DROP FOREIGN KEY FK_EF0C4754E767D72A');
        $this->addSql('ALTER TABLE chat_konverzace_uzivatel DROP FOREIGN KEY FK_EF0C4754A76ED395');
        $this->addSql('ALTER TABLE chat_zprava DROP FOREIGN KEY FK_4E861E547D268FF5');
        $this->addSql('ALTER TABLE chat_zprava DROP FOREIGN KEY FK_4E861E5414D45BBE');
        $this->addSql('ALTER TABLE chat_zprava DROP FOREIGN KEY FK_4E861E54FFDF7169');
        $this->addSql('DROP TABLE chat_konverzace');
        $this->addSql('DROP TABLE chat_konverzace_uzivatel');
        $this->addSql('DROP TABLE chat_zprava');
    }
}
