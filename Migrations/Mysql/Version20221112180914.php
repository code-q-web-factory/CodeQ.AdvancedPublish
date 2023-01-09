<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20221112180914 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP FOREIGN KEY FK_85B8E50E8D940019');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP FOREIGN KEY FK_85B8E50ECCF1F1BA');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP FOREIGN KEY FK_85B8E50EE0472730');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication CHANGE approved resolved DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50E8D940019 FOREIGN KEY (workspace) REFERENCES neos_contentrepository_domain_model_workspace (name) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50ECCF1F1BA FOREIGN KEY (editor) REFERENCES neos_neos_domain_model_user (persistence_object_identifier) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50EE0472730 FOREIGN KEY (reviewer) REFERENCES neos_neos_domain_model_user (persistence_object_identifier) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP FOREIGN KEY FK_85B8E50ECCF1F1BA');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP FOREIGN KEY FK_85B8E50EE0472730');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication DROP FOREIGN KEY FK_85B8E50E8D940019');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication CHANGE resolved approved DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50ECCF1F1BA FOREIGN KEY (editor) REFERENCES neos_neos_domain_model_user (persistence_object_identifier)');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50EE0472730 FOREIGN KEY (reviewer) REFERENCES neos_neos_domain_model_user (persistence_object_identifier)');
        $this->addSql('ALTER TABLE codeq_advancedpublish_domain_model_publication ADD CONSTRAINT FK_85B8E50E8D940019 FOREIGN KEY (workspace) REFERENCES neos_contentrepository_domain_model_workspace (name)');
    }
}
