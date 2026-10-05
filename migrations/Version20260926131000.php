<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926131000 extends AbstractMigration
{
    public function getDescription(): string { return 'Persist original article image URLs from publisher feeds.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE article ADD image_url VARCHAR(2048) DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE article DROP image_url'); }
}
