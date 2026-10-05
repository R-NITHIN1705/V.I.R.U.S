<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional publisher country metadata to news sources';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD country VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP country');
    }
}
