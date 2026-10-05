<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist article image type and publisher attribution metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE article ADD image_type VARCHAR(30) DEFAULT 'PLACEHOLDER' NOT NULL");
        $this->addSql('ALTER TABLE article ADD image_attribution VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD image_alt VARCHAR(512) DEFAULT NULL');
        $this->addSql("UPDATE article a SET image_type = 'SOURCE', image_attribution = s.name, image_alt = a.title FROM source s WHERE a.source_id = s.id AND a.image_url IS NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE article DROP image_alt');
        $this->addSql('ALTER TABLE article DROP image_attribution');
        $this->addSql('ALTER TABLE article DROP image_type');
    }
}
