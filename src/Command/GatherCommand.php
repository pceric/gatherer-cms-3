<?php
/*
 * Copyright (c) 2024 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Command;

use App\Entity\Tag;
use App\Repository\ReaderRepository;
use App\Service\ConfigService;
use App\Service\SummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Feed\Reader\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:gather',
    description: 'Gather feeds from the configured external source',
    aliases: ['app:ingest'],
    hidden: false
)]
class GatherCommand extends Command
{
    use LockableTrait;

    private const MAX_TAGS = 5;

    protected ReaderRepository $readerRepo;
    protected ConfigService $config;
    protected EntityManagerInterface $em;
    protected SummaryService $summaryService;

    public function __construct(ReaderRepository $readerRepository, ConfigService $configService, EntityManagerInterface $em, SummaryService $summaryService)
    {
        $this->readerRepo = $readerRepository;
        $this->config = $configService;
        $this->em = $em;
        $this->summaryService = $summaryService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->lock()) {
            $io->error('The command is already running in another process.');
            return Command::FAILURE;
        }

        if (empty($this->config->get('uri', 'ingestion'))) {
            $io->error('External feed URI is not set');
            return Command::FAILURE;
        }

        $feed = Reader::import($this->config->get('uri', 'ingestion'));
        $tagCache = [];
        $io->progressStart(count($feed));
        foreach ($feed as $entry) {
            try {
                // See if we have a proper UUID, otherwise turn it into one
                if (Uuid::isValid($entry->getId())) {
                    $entryId = $entry->getId();
                } else {
                    $entryId = Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_OID), $feed->getId() . $entry->getId());
                }

                // Check for existing
                $existing = $this->readerRepo->findOneBy(['guid' => $entryId]);
                if ($existing !== null) {
                    $feedModified = $entry->getDateModified();
                    $referenceDate = $existing->getModdate() ?? $existing->getPubdate();
                    if ($feedModified !== null && $feedModified > $referenceDate) {
                        $source = $entry->getPermaLink() ?? $entry->getLink();
                        $sourceHost = parse_url($source, PHP_URL_HOST) ?? '';
                        $content = $this->summaryService->summarize($entry->getContent(), $entry->getDescription());
                        [, $content] = self::filterByDomain($sourceHost, $entry->getTitle(), $content);
                        $existing->setContent($content);
                        $existing->setModdate($feedModified);
                    }
                    continue;
                }

                $article = new \App\Entity\Reader();
                $source = $entry->getPermaLink() ?? $entry->getLink();
                $scheme = strtolower(parse_url($source, PHP_URL_SCHEME) ?? '');
                if (!in_array($scheme, ['http', 'https'], true)) {
                    $io->caution(sprintf('Skipping entry with non-HTTP source URL: %s', $source));
                    continue;
                }
                $sourceHost = parse_url($source, PHP_URL_HOST) ?? '';
                $title = html_entity_decode(strip_tags($entry->getTitle()));
                if (empty($title)) {
                    $io->caution(sprintf('Skipping entry with empty title (source: %s)', $source));
                    continue;
                }
                $content = $this->summaryService->summarize($entry->getContent(), $entry->getDescription());
                [$title, $content] = self::filterByDomain($sourceHost, $title, $content);
                $article->setTitle($title);
                $article->setContent($content);
                $article->setPubdate($entry->getDateCreated() ?? $entry->getDateModified() ?? new \DateTime());
                $article->setModdate($article->getPubdate() == $entry->getDateModified() ? null : $entry->getDateModified());
                $article->setSource($source);
                $article->setGuid($entryId);
                $tag_count = 0;
                foreach ($entry->getCategories() as $cat) {
                    $cat = empty($cat['label']) ? $cat['term'] : $cat['label'];
                    $cat = self::sanitizeTag($cat);
                    if (empty($cat) || mb_strlen($cat) > 32) { continue; }
                    if (!isset($tagCache[strtolower($cat)])) {
                        $tag = $this->em->getRepository(Tag::class)->findOneByNameInsensitive($cat);
                        $tagCache[strtolower($cat)] = $tag ?? (new Tag())->setName($cat);
                    }
                    $article->addTag($tagCache[strtolower($cat)]);
                    $tag_count++;
                    if ($tag_count >= self::MAX_TAGS) {
                        break;
                    }
                }
                $this->em->persist($article);
            } catch (\Exception $e) {
                $io->warning($e);
            } finally {
                $io->progressAdvance();
            }
        }

        try {
            $this->em->flush();
        } catch (\Exception $e) {
            $io->error($e);
            return Command::FAILURE;
        } finally {
            $this->release();
        }

        $io->success('Feeds successfully gathered.');
        return Command::SUCCESS;
    }

    public static function sanitizeTag(string $cat): string
    {
        return preg_replace('/[^\p{L}\p{N}\-_.&\s]/u', '', html_entity_decode(trim($cat)));
    }

    public static function filterByDomain(string $host, string $title, string $content): array
    {
        $parts = explode('.', ltrim($host, '.'));
        $rootDomain = count($parts) >= 2 ? implode('.', array_slice($parts, -2)) : $host;
        return match ($rootDomain) {
            'slashdot.org' => [
                $title,
                preg_replace('@\n?.*Read more of this story at Slashdot\.\s*$@m', '', $content),
            ],
            'techpowerup.com' => [
                preg_replace('@^\(PR\)\s*@', '', $title),
                $content,
            ],
            'lifehacker.com' => [
                $title,
                trim(preg_replace(
                    ['@<a[^>]*>(.*?)</a>@si', '@ style="[^"]*"@', '@ border="[^"]*"@', '@\s*More ».*@s', '@>\s+@'],
                    ['$1', '', '', '', '>'],
                    $content
                )),
            ],
            default => [
                $title,
                (static function(string $c): string {
                    // Strip trailing block whose first content IS "read more" (no real text before it)
                    $c = preg_replace(
                        '@\s*<[a-z][^>]*>\s*(?:<[a-z][^>]*>\s*)*read\s+more.*$@si',
                        '',
                        $c
                    );
                    // Strip trailing "read more" text, restoring any closing tags that followed it
                    return preg_replace(
                        '@\s+read\s+more[.…]*(\s*(?:</[a-z][^>]*>\s*)*)$@si',
                        '$1',
                        $c
                    );
                })($content),
            ],
        };
    }
}
