<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926133000 extends AbstractMigration
{
    public function getDescription(): string { return 'Classify text extraction attempts on publisher video pages as skipped.'; }
    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE article SET full_text_status = 'skipped' WHERE full_text_status = 'failed' AND url ~* '/(video|iplayer)/'");
    }
    public function down(Schema $schema): void { /* These pages remain non-text content and should not be retried as articles. */ }
}
