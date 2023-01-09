<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221031165730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE codeq_advancedpublish_domain_model_publication_events_join');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE codeq_advancedpublish_domain_model_publication_events_join (advancedpublish_publication VARCHAR(40) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, eventlog_event INT UNSIGNED NOT NULL, UNIQUE INDEX UNIQ_143C3C6573FC4D4A (eventlog_event), INDEX IDX_143C3C6564D1D52A (advancedpublish_publication), PRIMARY KEY(advancedpublish_publication, eventlog_event)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication_events_join ADD CONSTRAINT FK_143C3C6564D1D52A FOREIGN KEY (advancedpublish_publication) REFERENCES codeq_advancedpublish_domain_model_publication (persistence_object_identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication_events_join ADD CONSTRAINT FK_143C3C6573FC4D4A FOREIGN KEY (eventlog_event) REFERENCES neos_neos_eventlog_domain_model_event (uid) ON DELETE CASCADE');
    }
}
