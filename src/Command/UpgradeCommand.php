<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Command;

use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:v2-upgrade',
    description: 'Does a DB upgrade from GCMS v2',
    hidden: true
)]
class UpgradeCommand extends Command
{
    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em) {
        $this->em = $em;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::REQUIRED, 'The hostname of the old MySQL server.')
            ->addArgument('user', InputArgument::REQUIRED, 'The user of the old MySQL server.')
            ->addArgument('password', InputArgument::REQUIRED, 'The password of the old MySQL server.')
            ->addArgument('dbname', InputArgument::REQUIRED, 'The name of the old DB.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $params = [
            'dbname' => $input->getArgument('dbname'),
            'user' => $input->getArgument('user'),
            'password' => $input->getArgument('password'),
            'host' => $input->getArgument('host'),
            'driver' => 'pdo_mysql',
        ];
        $conn = DriverManager::getConnection($params);
        try {
            $conn->getServerVersion();
        } catch (Exception $e) {
            $output->writeln("Could not connect to database: " . $e->getMessage());
            return Command::FAILURE;
        }
        try {
            $userObj = $this->em->getRepository(User::class)->findOneBy(['id' => 1]);
            $categoryObj = $this->em->getRepository(Category::class)->findOneBy(['slug' => 'general']);
            // import articles and news, now just called blog
            $stmt = $conn->executeQuery(
                "SELECT title, content, pubdate, moddate, published, tags FROM articles UNION SELECT title, content, pubdate, moddate, published, tags FROM news ORDER BY pubdate ASC"
            );
            $this->em->getConnection()->beginTransaction();
            while (($row = $stmt->fetchAssociative()) !== false) {
                $blog = new \App\Entity\Blog();
                $blog->setUser($userObj);
                $blog->setCategory($categoryObj);
                $blog->setTitle(html_entity_decode(strip_tags($row['title'])));
                $blog->setContent($row['content']);
                $blog->setPubdate(new \DateTime($row['pubdate']));
                $blog->setModdate($row['moddate'] && $row['moddate'] != '0000-00-00 00:00:00' ? new \DateTime($row['moddate']) : null);
                $blog->setPublished($row['published']);
                $blog->setSticky(false);
                $seen = [];
                foreach (explode(",", $row['tags'] ?? '') as $tag) {
                    $tag = html_entity_decode(trim($tag));
                    if (!empty($tag) && !in_array($tag, $seen)) {
                        $t = $this->em->getRepository(Tag::class)->findOneBy(['name' => $tag]);
                        if ($t == null) {
                            $blog->addTag((new \App\Entity\Tag())->setName($tag));
                        } else {
                            $blog->addTag($t);
                        }
                        $seen[] = $tag;
                    }
                }
                $this->em->persist($blog);
                $this->em->flush();
            }
            // import reader
            $stmt = $conn->executeQuery("SELECT * FROM reader ORDER BY pubdate ASC");
            while (($row = $stmt->fetchAssociative()) !== false) {
                $reader = new \App\Entity\Reader();
                $reader->setTitle(self::stripReaderTitle(html_entity_decode(strip_tags($row['title']))));
                $reader->setContent($row['summary']);
                $reader->setSource($row['source']);
                $reader->setPubdate(new \DateTime($row['pubdate']));
                $reader->setAnnotation($row['annotation']);
                $reader->setGuid(Uuid::v4());
                $seen = [];
                foreach (explode(",", $row['tags']) as $tag) {
                    $tag = html_entity_decode(trim($tag));
                    if (!empty($tag) && !in_array($tag, $seen)) {
                        $t = $this->em->getRepository(Tag::class)->findOneBy(['name' => $tag]);
                        if ($t == null) {
                            $reader->addTag((new \App\Entity\Tag())->setName($tag));
                        } else {
                            $reader->addTag($t);
                        }
                        $seen[] = $tag;
                    }
                }
                $this->em->persist($reader);
                $this->em->flush();
            }
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }
        $output->writeln("Successfully upgraded");
        return Command::SUCCESS;
    }

    public static function stripReaderTitle(string $title): string
    {
        $patterns = [
            '/ \| Ars Technica$/',
            '/ - Slashdot$/',
            '/ \| TechCrunch$/',
        ];
        return preg_replace($patterns, '', $title);
    }
}
