<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260131134808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE dochazka (id INT AUTO_INCREMENT NOT NULL, datum DATE NOT NULL, minuty INT NOT NULL, popis LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, zakazka_id INT DEFAULT NULL, INDEX IDX_D1F921EA76ED395 (user_id), INDEX IDX_D1F921EB778F7C3 (zakazka_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE dochazka_timer (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, paused_at DATETIME DEFAULT NULL, accumulated_minutes INT NOT NULL, user_id INT NOT NULL, zakazka_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_7250F187A76ED395 (user_id), INDEX IDX_7250F187B778F7C3 (zakazka_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE dochazka ADD CONSTRAINT FK_D1F921EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE dochazka ADD CONSTRAINT FK_D1F921EB778F7C3 FOREIGN KEY (zakazka_id) REFERENCES zakazka (id)');
        $this->addSql('ALTER TABLE dochazka_timer ADD CONSTRAINT FK_7250F187A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE dochazka_timer ADD CONSTRAINT FK_7250F187B778F7C3 FOREIGN KEY (zakazka_id) REFERENCES zakazka (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE dochazka DROP FOREIGN KEY FK_D1F921EA76ED395');
        $this->addSql('ALTER TABLE dochazka DROP FOREIGN KEY FK_D1F921EB778F7C3');
        $this->addSql('ALTER TABLE dochazka_timer DROP FOREIGN KEY FK_7250F187A76ED395');
        $this->addSql('ALTER TABLE dochazka_timer DROP FOREIGN KEY FK_7250F187B778F7C3');
        $this->addSql('DROP TABLE dochazka');
        $this->addSql('DROP TABLE dochazka_timer');
    }
}
