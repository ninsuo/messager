<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop twilio_status table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE twilio_status');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE twilio_status (
            id INT AUTO_INCREMENT NOT NULL,
            sid VARCHAR(64) NOT NULL,
            status VARCHAR(64) NOT NULL,
            received_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
