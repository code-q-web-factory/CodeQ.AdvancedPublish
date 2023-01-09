<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221026212650 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE codeq_advancedpublish_domain_model_publication (persistence_object_identifier VARCHAR(40) NOT NULL, editor VARCHAR(40) DEFAULT NULL, reviewer VARCHAR(40) DEFAULT NULL, workspace VARCHAR(255) DEFAULT NULL, created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', approved DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(255) NOT NULL, editoripaddress VARCHAR(255) NOT NULL, revieweripaddress VARCHAR(255) NOT NULL, comment LONGTEXT NOT NULL, INDEX IDX_85B8E50ECCF1F1BA (editor), INDEX IDX_85B8E50EE0472730 (reviewer), UNIQUE INDEX UNIQ_85B8E50E8D940019 (workspace), PRIMARY KEY(persistence_object_identifier)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50ECCF1F1BA FOREIGN KEY (editor) REFERENCES neos_neos_domain_model_user (persistence_object_identifier)');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50EE0472730 FOREIGN KEY (reviewer) REFERENCES neos_neos_domain_model_user (persistence_object_identifier)');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50E8D940019 FOREIGN KEY (workspace) REFERENCES neos_contentrepository_domain_model_workspace (name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE codeq_advancedpublish_domain_model_publication');
    }
}
