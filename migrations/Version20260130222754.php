<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130222754 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE faktura (id INT AUTO_INCREMENT NOT NULL, adresa LONGTEXT DEFAULT NULL, file VARCHAR(255) DEFAULT NULL, original_filename VARCHAR(255) DEFAULT NULL, cena_bez_dph NUMERIC(12, 2) DEFAULT NULL, cena_sdph NUMERIC(12, 2) DEFAULT NULL, cena_bez_dane_zprijmu NUMERIC(12, 2) DEFAULT NULL, datum DATE DEFAULT NULL, poznamka LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, status_id INT NOT NULL, zakazka_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_C99BEBC86BF700BD (status_id), INDEX IDX_C99BEBC8B778F7C3 (zakazka_id), INDEX IDX_C99BEBC8B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE status_faktura (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(255) NOT NULL, sort_order INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE faktura ADD CONSTRAINT FK_C99BEBC86BF700BD FOREIGN KEY (status_id) REFERENCES status_faktura (id)');
        $this->addSql('ALTER TABLE faktura ADD CONSTRAINT FK_C99BEBC8B778F7C3 FOREIGN KEY (zakazka_id) REFERENCES zakazka (id)');
        $this->addSql('ALTER TABLE faktura ADD CONSTRAINT FK_C99BEBC8B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL, CHANGE permissions permissions JSON DEFAULT NULL, CHANGE is_active is_active TINYINT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE last_login_at last_login_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE zakazka CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE zakazka RENAME INDEX idx_5a66e3f1b03a8386 TO IDX_9E7E54FAB03A8386');
        $this->addSql('ALTER TABLE zakazka RENAME INDEX idx_5a66e3f16bf700bd TO IDX_9E7E54FA6BF700BD');
        $this->addSql('ALTER TABLE zakazka_user RENAME INDEX idx_zakazka TO IDX_27F09495B778F7C3');
        $this->addSql('ALTER TABLE zakazka_user RENAME INDEX idx_user TO IDX_27F09495A76ED395');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE faktura DROP FOREIGN KEY FK_C99BEBC86BF700BD');
        $this->addSql('ALTER TABLE faktura DROP FOREIGN KEY FK_C99BEBC8B778F7C3');
        $this->addSql('ALTER TABLE faktura DROP FOREIGN KEY FK_C99BEBC8B03A8386');
        $this->addSql('DROP TABLE faktura');
        $this->addSql('DROP TABLE status_faktura');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles JSON NOT NULL COMMENT \'(DC2Type:json)\', CHANGE permissions permissions JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', CHANGE is_active is_active TINYINT DEFAULT 1 NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE last_login_at last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE zakazka CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE zakazka RENAME INDEX idx_9e7e54fab03a8386 TO IDX_5A66E3F1B03A8386');
        $this->addSql('ALTER TABLE zakazka RENAME INDEX idx_9e7e54fa6bf700bd TO IDX_5A66E3F16BF700BD');
        $this->addSql('ALTER TABLE zakazka_user RENAME INDEX idx_27f09495a76ed395 TO IDX_USER');
        $this->addSql('ALTER TABLE zakazka_user RENAME INDEX idx_27f09495b778f7c3 TO IDX_ZAKAZKA');
    }
}
