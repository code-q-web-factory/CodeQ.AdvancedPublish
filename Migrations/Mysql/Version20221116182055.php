<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221116182055 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD revision VARCHAR(40) DEFAULT NULL');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50E6D6315CC FOREIGN KEY (revision) REFERENCES codeq_revisions_domain_model_revision (persistence_object_identifier)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_85B8E50E6D6315CC ON codeq_advancedpublish_domain_model_publication (revision)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP FOREIGN KEY FK_85B8E50E6D6315CC');
        $this->addSql('DROP INDEX UNIQ_85B8E50E6D6315CC ON codeq_advancedpublish_domain_model_publication');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP revision');
    }
}
