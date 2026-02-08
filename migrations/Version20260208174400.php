<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208174400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE unguessable_code (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, code VARCHAR(64) NOT NULL, purpose VARCHAR(255) NOT NULL, encrypted_context LONGTEXT NOT NULL, expires_at DATETIME DEFAULT NULL, remaining_hits INT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX code_idx (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE unguessable_code');
    }
}
