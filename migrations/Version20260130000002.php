<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260130000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Status table
        $this->addSql('CREATE TABLE status (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(100) NOT NULL,
            color VARCHAR(100) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Zakazka table
        $this->addSql('CREATE TABLE zakazka (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id INT NOT NULL,
            status_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            price NUMERIC(12, 2) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            url VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            realizace DATE DEFAULT NULL,
            INDEX IDX_5A66E3F1B03A8386 (created_by_id),
            INDEX IDX_5A66E3F16BF700BD (status_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Zakazka-User pivot table (assigned users)
        $this->addSql('CREATE TABLE zakazka_user (
            zakazka_id INT NOT NULL,
            user_id INT NOT NULL,
            INDEX IDX_ZAKAZKA (zakazka_id),
            INDEX IDX_USER (user_id),
            PRIMARY KEY(zakazka_id, user_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Foreign keys
        $this->addSql('ALTER TABLE zakazka ADD CONSTRAINT FK_ZAKAZKA_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE zakazka ADD CONSTRAINT FK_ZAKAZKA_STATUS FOREIGN KEY (status_id) REFERENCES status (id)');
        $this->addSql('ALTER TABLE zakazka_user ADD CONSTRAINT FK_ZU_ZAKAZKA FOREIGN KEY (zakazka_id) REFERENCES zakazka (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE zakazka_user ADD CONSTRAINT FK_ZU_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // Insert default statuses (Tailwind CSS classes)
        $this->addSql("INSERT INTO status (name, color, sort_order) VALUES 
            ('Nový', 'bg-blue-100 text-blue-800', 1),
            ('Čeká na schválení', 'bg-yellow-100 text-yellow-800', 2),
            ('V realizaci', 'bg-[rgba(241,97,1,0.15)] text-[rgb(241,97,1)]', 3),
            ('Pozastaveno', 'bg-slate-100 text-slate-800', 4),
            ('Dokončeno', 'bg-green-100 text-green-800', 5),
            ('Zrušeno', 'bg-red-100 text-red-800', 6)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE zakazka_user DROP FOREIGN KEY FK_ZU_ZAKAZKA');
        $this->addSql('ALTER TABLE zakazka_user DROP FOREIGN KEY FK_ZU_USER');
        $this->addSql('ALTER TABLE zakazka DROP FOREIGN KEY FK_ZAKAZKA_CREATED_BY');
        $this->addSql('ALTER TABLE zakazka DROP FOREIGN KEY FK_ZAKAZKA_STATUS');
        $this->addSql('DROP TABLE zakazka_user');
        $this->addSql('DROP TABLE zakazka');
        $this->addSql('DROP TABLE status');
    }
}
