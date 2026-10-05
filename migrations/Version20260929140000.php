<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align followed topic preference column with the User mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ALTER followed_topics DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ALTER followed_topics SET DEFAULT '[]'");
    }
}
