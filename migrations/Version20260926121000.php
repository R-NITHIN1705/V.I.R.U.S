<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926121000 extends AbstractMigration
{
    public function getDescription(): string { return 'Align auth table defaults and index naming with the ORM mapping.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ALTER full_name DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_auth_token_user RENAME TO idx_9315f04ea76ed395');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX idx_9315f04ea76ed395 RENAME TO idx_auth_token_user');
        $this->addSql('ALTER TABLE "user" ALTER full_name SET DEFAULT \'\'');
    }
}
