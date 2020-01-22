<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207004348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE blog (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(128) NOT NULL, guid CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, sticky TINYINT NOT NULL, published TINYINT NOT NULL, pubdate DATETIME NOT NULL, moddate DATETIME DEFAULT NULL, user_id INT NOT NULL, category_id INT NOT NULL, UNIQUE INDEX UNIQ_C0155143989D9B62 (slug), UNIQUE INDEX UNIQ_C01551432B6FCFB2 (guid), INDEX IDX_C0155143A76ED395 (user_id), INDEX IDX_C015514312469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE blog_tag (blog_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_6EC3989DAE07E97 (blog_id), INDEX IDX_6EC3989BAD26311 (tag_id), PRIMARY KEY (blog_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(32) NOT NULL, name VARCHAR(32) NOT NULL, UNIQUE INDEX UNIQ_64C19C1989D9B62 (slug), UNIQUE INDEX UNIQ_64C19C15E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE config (id INT AUTO_INCREMENT NOT NULL, namespace VARCHAR(32) NOT NULL, value JSON NOT NULL, UNIQUE INDEX UNIQ_D48A2F7C33E16B56 (namespace), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reader (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(128) NOT NULL, guid CHAR(36) NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, source VARCHAR(2048) NOT NULL, annotation LONGTEXT DEFAULT NULL, pubdate DATETIME NOT NULL, moddate DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_CC3F893C989D9B62 (slug), UNIQUE INDEX UNIQ_CC3F893C2B6FCFB2 (guid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reader_tag (reader_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_D0F76DC81717D737 (reader_id), INDEX IDX_D0F76DC8BAD26311 (tag_id), PRIMARY KEY (reader_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(32) NOT NULL, name VARCHAR(32) NOT NULL, UNIQUE INDEX UNIQ_389B783989D9B62 (slug), UNIQUE INDEX UNIQ_389B7835E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, displayname VARCHAR(180) NOT NULL, UNIQUE INDEX UNIQ_8D93D649F85E0677 (username), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE blog ADD CONSTRAINT FK_C0155143A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE blog ADD CONSTRAINT FK_C015514312469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE blog_tag ADD CONSTRAINT FK_6EC3989DAE07E97 FOREIGN KEY (blog_id) REFERENCES blog (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE blog_tag ADD CONSTRAINT FK_6EC3989BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reader_tag ADD CONSTRAINT FK_D0F76DC81717D737 FOREIGN KEY (reader_id) REFERENCES reader (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reader_tag ADD CONSTRAINT FK_D0F76DC8BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('CREATE VIEW v_archive AS (SELECT id, slug, guid, title, pubdate, \'blog\' as type FROM blog WHERE published = 1) UNION (SELECT id, slug, guid, title, pubdate, \'reader\' as type from reader) ORDER BY pubdate DESC');
        $this->addSql('INSERT INTO category SET slug=\'general\', name=\'General\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE blog DROP FOREIGN KEY FK_C0155143A76ED395');
        $this->addSql('ALTER TABLE blog DROP FOREIGN KEY FK_C015514312469DE2');
        $this->addSql('ALTER TABLE blog_tag DROP FOREIGN KEY FK_6EC3989DAE07E97');
        $this->addSql('ALTER TABLE blog_tag DROP FOREIGN KEY FK_6EC3989BAD26311');
        $this->addSql('ALTER TABLE reader_tag DROP FOREIGN KEY FK_D0F76DC81717D737');
        $this->addSql('ALTER TABLE reader_tag DROP FOREIGN KEY FK_D0F76DC8BAD26311');
        $this->addSql('DROP TABLE blog');
        $this->addSql('DROP TABLE blog_tag');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE config');
        $this->addSql('DROP TABLE reader');
        $this->addSql('DROP TABLE reader_tag');
        $this->addSql('DROP TABLE tag');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP VIEW v_archive');
    }
}
