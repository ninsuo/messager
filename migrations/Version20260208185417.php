<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208185417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE book (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uuid_idx (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_contact (book_id INT NOT NULL, contact_id INT NOT NULL, INDEX IDX_DF42CEAE16A2B381 (book_id), INDEX IDX_DF42CEAEE7A1254A (contact_id), PRIMARY KEY (book_id, contact_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, phone_number LONGTEXT NOT NULL, blind_phone_number VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX blind_phone_number_idx (blind_phone_number), UNIQUE INDEX uuid_idx (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, error VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, trigger_id INT NOT NULL, contact_id INT NOT NULL, INDEX IDX_B6BD307F5FDDDCD6 (trigger_id), INDEX IDX_B6BD307FE7A1254A (contact_id), UNIQUE INDEX uuid_idx (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `trigger` (id INT AUTO_INCREMENT NOT NULL, uuid VARCHAR(36) NOT NULL, type VARCHAR(10) NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_1A6B0F5DA76ED395 (user_id), UNIQUE INDEX uuid_idx (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE trigger_contact (trigger_id INT NOT NULL, contact_id INT NOT NULL, INDEX IDX_4822E1015FDDDCD6 (trigger_id), INDEX IDX_4822E101E7A1254A (contact_id), PRIMARY KEY (trigger_id, contact_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE book_contact ADD CONSTRAINT FK_DF42CEAE16A2B381 FOREIGN KEY (book_id) REFERENCES book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE book_contact ADD CONSTRAINT FK_DF42CEAEE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F5FDDDCD6 FOREIGN KEY (trigger_id) REFERENCES `trigger` (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FE7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE `trigger` ADD CONSTRAINT FK_1A6B0F5DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE trigger_contact ADD CONSTRAINT FK_4822E1015FDDDCD6 FOREIGN KEY (trigger_id) REFERENCES `trigger` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trigger_contact ADD CONSTRAINT FK_4822E101E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE book_contact DROP FOREIGN KEY FK_DF42CEAE16A2B381');
        $this->addSql('ALTER TABLE book_contact DROP FOREIGN KEY FK_DF42CEAEE7A1254A');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F5FDDDCD6');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FE7A1254A');
        $this->addSql('ALTER TABLE `trigger` DROP FOREIGN KEY FK_1A6B0F5DA76ED395');
        $this->addSql('ALTER TABLE trigger_contact DROP FOREIGN KEY FK_4822E1015FDDDCD6');
        $this->addSql('ALTER TABLE trigger_contact DROP FOREIGN KEY FK_4822E101E7A1254A');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_contact');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE `trigger`');
        $this->addSql('DROP TABLE trigger_contact');
    }
}
