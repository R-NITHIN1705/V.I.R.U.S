<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926132000 extends AbstractMigration
{
    public function getDescription(): string { return 'Backfill article images already present in stored publisher feed content.'; }
    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE article SET image_url = (regexp_match(content_raw, '<img[^>]*src=[\"''](https?://[^\"'']+)[\"'']', 'i'))[1] WHERE image_url IS NULL AND content_raw ~* '<img[^>]*src=[\"'']https?://'");
    }
    public function down(Schema $schema): void { /* Existing article content remains intact; extracted URLs are safe to retain. */ }
}
