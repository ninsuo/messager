<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop twilio_message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE twilio_message');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE twilio_message (
            id INT AUTO_INCREMENT NOT NULL,
            uuid VARCHAR(36) NOT NULL,
            direction VARCHAR(16) NOT NULL,
            message LONGTEXT DEFAULT NULL,
            from_number LONGTEXT DEFAULT NULL,
            blind_from_number VARCHAR(64) DEFAULT NULL,
            to_number LONGTEXT DEFAULT NULL,
            blind_to_number VARCHAR(64) DEFAULT NULL,
            sid VARCHAR(64) DEFAULT NULL,
            status VARCHAR(20) DEFAULT NULL,
            context LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            error VARCHAR(255) DEFAULT NULL,
            UNIQUE INDEX uuid_idx (uuid),
            INDEX sid_idx (sid),
            INDEX blind_from_number_idx (blind_from_number),
            INDEX blind_to_number_idx (blind_to_number),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
