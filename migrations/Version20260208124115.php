<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208124115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fake_call (id INT AUTO_INCREMENT NOT NULL, from_number VARCHAR(32) NOT NULL, to_number VARCHAR(32) NOT NULL, type VARCHAR(16) NOT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fake_sms (id INT AUTO_INCREMENT NOT NULL, from_number VARCHAR(32) NOT NULL, to_number VARCHAR(32) NOT NULL, message LONGTEXT NOT NULL, direction VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE twilio_call (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, direction VARCHAR(16) NOT NULL, message LONGTEXT DEFAULT NULL, from_number VARCHAR(32) NOT NULL, to_number VARCHAR(16) NOT NULL, sid VARCHAR(64) DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, context LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, error VARCHAR(255) DEFAULT NULL, started_at DATETIME DEFAULT NULL, ended_at DATETIME DEFAULT NULL, duration INT DEFAULT NULL, INDEX sid_idx (sid), UNIQUE INDEX uuid_idx (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE twilio_message (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, direction VARCHAR(16) NOT NULL, message LONGTEXT DEFAULT NULL, from_number VARCHAR(32) NOT NULL, to_number VARCHAR(16) NOT NULL, sid VARCHAR(64) DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, context LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, error VARCHAR(255) DEFAULT NULL, INDEX sid_idx (sid), UNIQUE INDEX uuid_idx (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE twilio_status (id INT AUTO_INCREMENT NOT NULL, sid VARCHAR(64) NOT NULL, status VARCHAR(64) NOT NULL, received_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE fake_call');
        $this->addSql('DROP TABLE fake_sms');
        $this->addSql('DROP TABLE twilio_call');
        $this->addSql('DROP TABLE twilio_message');
        $this->addSql('DROP TABLE twilio_status');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
