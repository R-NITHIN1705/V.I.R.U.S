<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add source coverage region and default-source metadata.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE source ADD region VARCHAR(24) DEFAULT NULL'); $this->addSql('ALTER TABLE source ADD default_source BOOLEAN DEFAULT FALSE NOT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE source DROP region'); $this->addSql('ALTER TABLE source DROP default_source'); }
}
