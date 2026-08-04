<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Command;

use App\Repository\BlogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:check-links',
    description: 'Scan published Blog content for links and images and report any that are broken',
    hidden: false
)]
class CheckLinksCommand extends Command
{
    private const TIMEOUT = 10;

    public function __construct(
        private readonly BlogRepository $blogRepo,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $defaultUri = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Write results to this CSV file instead of printing a table')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Only check the blog post with this slug (published or not)')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Per-request timeout in seconds', self::TIMEOUT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeout = (float) $input->getOption('timeout');
        $csvPath = $input->getOption('csv');
        $slug = $input->getOption('slug');

        if ($slug !== null) {
            $blog = $this->blogRepo->findOneBy(['slug' => $slug]);
            if ($blog === null) {
                $io->error(sprintf('No blog post found with slug "%s".', $slug));
                return Command::FAILURE;
            }
            $blogs = [$blog];
        } else {
            $blogs = $this->blogRepo->findBy(['published' => true]);
        }

        // Collect every unique URL (keyed by the original, unnormalized reference) and
        // remember which slugs/types reference it
        $urls = [];
        foreach ($blogs as $blog) {
            $references = $this->extractReferences($blog->getContent());
            foreach ($references as $reference) {
                $urls[$reference['url']]['type'] ??= $reference['type'];
                $urls[$reference['url']]['checkUrl'] ??= $reference['checkUrl'];
                $urls[$reference['url']]['slugs'][$blog->getSlug()] = true;
            }
        }

        if (empty($urls)) {
            $io->success('No links or images found in blog content.');
            return Command::SUCCESS;
        }

        $io->progressStart(count($urls));
        $broken = [];
        foreach ($urls as $url => $info) {
            if ($info['checkUrl'] === null) {
                $result = 'Relative URL, cannot verify without a site base URL';
            } else {
                $result = $this->checkUrl($info['checkUrl'], $timeout);
            }
            if ($result !== null) {
                foreach (array_keys($info['slugs']) as $slug) {
                    $broken[] = [$slug, $info['type'], $url, $result];
                }
            }
            $io->progressAdvance();
        }
        $io->progressFinish();

        usort($broken, fn($a, $b) => $a[0] <=> $b[0]);

        if ($csvPath !== null) {
            $this->writeCsv($csvPath, $broken);
            $io->writeln(sprintf('Wrote %d broken reference(s) to %s.', count($broken), $csvPath));
        } elseif (empty($broken)) {
            $io->success(sprintf('Checked %d unique URL(s). No broken links or images found.', count($urls)));
            return Command::SUCCESS;
        } else {
            $io->table(['Slug', 'Type', 'URL', 'Status'], $broken);
            $io->warning(sprintf('%d broken reference(s) found across %d unique URL(s).', count($broken), count($urls)));
        }

        return empty($broken) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array<int, array{type: string, url: string, checkUrl: ?string}>
     */
    private function extractReferences(?string $content): array
    {
        if (empty(trim((string) $content))) {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        libxml_clear_errors();

        $references = [];

        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            $checkUrl = $this->normalizeUrl($href);
            if ($checkUrl !== false) {
                $references[] = ['type' => 'link', 'url' => $href, 'checkUrl' => $checkUrl];
            }
        }

        foreach ($dom->getElementsByTagName('img') as $img) {
            $src = trim($img->getAttribute('src'));
            $checkUrl = $this->normalizeUrl($src);
            if ($checkUrl !== false) {
                $references[] = ['type' => 'image', 'url' => $src, 'checkUrl' => $checkUrl];
            }
        }

        return $references;
    }

    /**
     * Returns the URL to actually request, null if it's a relative URL that can't be
     * verified without a known site base, or false if it should be skipped entirely
     * (anchors, mailto/tel/javascript/data links, etc).
     */
    private function normalizeUrl(string $url): string|false|null
    {
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, 'mailto:') || str_starts_with($url, 'tel:') || str_starts_with($url, 'data:') || str_starts_with($url, 'javascript:')) {
            return false;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Protocol-relative URL, e.g. //static.example.com/image.jpg
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if ($this->defaultUri === '') {
            // Root-relative or otherwise relative URL; no configured site base URL to resolve against
            return null;
        }

        return rtrim($this->defaultUri, '/') . '/' . ltrim($url, '/');
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string, 3: string}> $rows
     */
    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Could not open "%s" for writing.', $path));
        }

        fputcsv($handle, ['Slug', 'Type', 'URL', 'Status']);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    private function checkUrl(string $url, float $timeout): ?string
    {
        try {
            $response = $this->httpClient->request('HEAD', $url, ['timeout' => $timeout]);
            $statusCode = $response->getStatusCode();

            // Some servers reject HEAD; fall back to GET before declaring it broken
            if ($statusCode >= 400) {
                $response = $this->httpClient->request('GET', $url, ['timeout' => $timeout]);
                $statusCode = $response->getStatusCode();
            }

            if ($statusCode >= 400) {
                return (string) $statusCode;
            }

            return null;
        } catch (ExceptionInterface $e) {
            return $e->getMessage();
        }
    }
}
