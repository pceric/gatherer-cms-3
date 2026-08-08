<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Command;

use App\Repository\BlogRepository;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\TransferStats;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:check-links',
    description: 'Scan published Blog content for links and images and report any that are broken',
    hidden: false
)]
class CheckLinksCommand extends Command
{
    private const TIMEOUT = 10;
    private const CONCURRENCY = 10;
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    private readonly Client $httpClient;

    public function __construct(
        private readonly BlogRepository $blogRepo,
        #[Autowire(env: 'DEFAULT_URI')]
        private readonly string $defaultUri = '',
    ) {
        parent::__construct();

        $this->httpClient = new Client([
            'headers' => ['User-Agent' => self::USER_AGENT],
            'http_errors' => false,
        ]);
    }

    protected function configure(): void
    {
        $this
            ->addOption('csv', null, InputOption::VALUE_REQUIRED, 'Write results to this CSV file instead of printing a table')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Only check the blog post with this slug (published or not)')
            ->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Per-request timeout in seconds', self::TIMEOUT)
            ->addOption('concurrency', null, InputOption::VALUE_REQUIRED, 'Number of requests to run in parallel', self::CONCURRENCY);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeout = (float) $input->getOption('timeout');
        $concurrency = (int) $input->getOption('concurrency');
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
        $ignored = [];

        $checkUrls = [];
        foreach ($urls as $url => $info) {
            if ($info['checkUrl'] === null) {
                foreach (array_keys($info['slugs']) as $slug) {
                    $broken[] = [$slug, $info['type'], $url, 'Relative URL, cannot verify without a site base URL'];
                }
                $io->progressAdvance();
            } else {
                $checkUrls[$url] = $info['checkUrl'];
            }
        }

        $results = $this->checkUrls($checkUrls, $timeout, $concurrency, $io);

        foreach ($results as $url => $result) {
            $info = $urls[$url];
            if (is_int($result)) {
                if ($result >= 600) {
                    $ignored[] = sprintf('Ignoring non-standard HTTP status %d for %s', $result, $info['checkUrl']);
                    continue;
                }
                if ($result < 400) {
                    continue;
                }
                $message = (string) $result;
            } else {
                $message = $result;
            }
            foreach (array_keys($info['slugs']) as $slug) {
                $broken[] = [$slug, $info['type'], $url, $message];
            }
        }

        $io->progressFinish();

        if (!empty($ignored)) {
            $io->note($ignored);
        }

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

    /**
     * Checks every URL concurrently via a HEAD request. URLs whose HEAD request comes back
     * with a >=400 status are retried with a GET, since some servers reject HEAD entirely.
     *
     * @param array<string, string> $checkUrls Original url => checkUrl to request
     * @return array<string, int|string> Original url => status code, or an error message
     */
    private function checkUrls(array $checkUrls, float $timeout, int $concurrency, SymfonyStyle $io): array
    {
        if (empty($checkUrls)) {
            return [];
        }

        $results = [];
        $getNeeded = [];
        $this->sendBatch($checkUrls, 'HEAD', $timeout, $concurrency, function (string $url, int|string $result) use (&$results, &$getNeeded, $checkUrls, $io) {
            if (is_int($result) && $result >= 400) {
                $getNeeded[$url] = $checkUrls[$url];
            } else {
                $results[$url] = $result;
                $io->progressAdvance();
            }
        });

        if (!empty($getNeeded)) {
            $this->sendBatch($getNeeded, 'GET', $timeout, $concurrency, function (string $url, int|string $result) use (&$results, $io) {
                $results[$url] = $result;
                $io->progressAdvance();
            });
        }

        return $results;
    }

    /**
     * Sends the given requests concurrently, capped at $concurrency in flight at a time,
     * invoking $onResult as each individual request completes so callers can report
     * progress in real time rather than waiting for the whole batch to finish.
     *
     * @param array<string, string> $checkUrls Original url => checkUrl to request
     * @param callable(string, int|string): void $onResult
     */
    private function sendBatch(array $checkUrls, string $method, float $timeout, int $concurrency, callable $onResult): void
    {
        // Guzzle's PSR-7 Response rejects any status code outside 100-599, so a server
        // replying with a non-standard code (e.g. 600) never reaches 'fulfilled' as an int -
        // it lands in 'rejected' instead. Track the raw curl http_code per request URI via
        // on_stats (fired before Response construction is attempted, so it still has the
        // real code) so 'rejected' can recover it instead of reporting a generic error
        // message. Correlate by URI rather than Request object identity, since Guzzle's
        // middleware stack clones the request (PSR-7 messages are immutable) before it
        // reaches the handler.
        $rawStatusCodes = [];

        $requests = function () use ($checkUrls, $method) {
            foreach ($checkUrls as $url => $checkUrl) {
                yield $url => new Request($method, $checkUrl);
            }
        };

        $pool = new Pool($this->httpClient, $requests(), [
            'concurrency' => max(1, $concurrency),
            'options' => [
                'timeout' => $timeout,
                'on_stats' => function (TransferStats $stats) use (&$rawStatusCodes) {
                    $httpCode = $stats->getHandlerStat('http_code');
                    if (is_int($httpCode) && $httpCode > 0) {
                        $rawStatusCodes[(string) $stats->getRequest()->getUri()] = $httpCode;
                    }
                },
            ],
            'fulfilled' => function (ResponseInterface $response, string $url) use ($onResult) {
                $onResult($url, $response->getStatusCode());
            },
            'rejected' => function (\Throwable $reason, string $url) use ($onResult, $checkUrls, &$rawStatusCodes) {
                $onResult($url, $rawStatusCodes[$checkUrls[$url]] ?? $reason->getMessage());
            },
        ]);

        $pool->promise()->wait();
    }
}
