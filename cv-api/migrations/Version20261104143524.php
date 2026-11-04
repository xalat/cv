<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104143524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CV locale, defaulting existing CVs to French.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv ADD locale VARCHAR(2) DEFAULT \'fr\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cv DROP locale');
    }
}
