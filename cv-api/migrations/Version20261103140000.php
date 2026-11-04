<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add languages, education and professional links to CVs, preserving existing data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cv ADD languages JSON DEFAULT '[]' NOT NULL, ADD education JSON DEFAULT '[]' NOT NULL, ADD github VARCHAR(2048) DEFAULT NULL, ADD linkedin VARCHAR(2048) DEFAULT NULL, ADD portfolio VARCHAR(2048) DEFAULT NULL");
        $this->addSql('ALTER TABLE cv ALTER languages DROP DEFAULT, ALTER education DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv DROP languages, DROP education, DROP github, DROP linkedin, DROP portfolio');
    }
}
