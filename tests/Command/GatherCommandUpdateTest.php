<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Command;

use App\Command\GatherCommand;
use App\Entity\Reader as ReaderEntity;
use App\Repository\ReaderRepository;
use App\Service\ConfigService;
use App\Service\SummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Laminas\Feed\Reader\Reader;
use Laminas\Http\Client;
use Laminas\Http\Client\Adapter\Test as HttpTestAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GatherCommandUpdateTest extends TestCase
{
    private const FEED_URI = 'http://test.local/feed';
    private const ENTRY_GUID = '550e8400-e29b-41d4-a716-446655440000';

    protected function tearDown(): void
    {
        Reader::reset();
    }

    private function atomFeed(string $published, string $updated): string
    {
        return <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <feed xmlns="http://www.w3.org/2005/Atom">
            <title>Test Feed</title>
            <link href="http://test.local/"/>
            <id>http://test.local/feed</id>
            <updated>$updated</updated>
            <entry>
                <id>550e8400-e29b-41d4-a716-446655440000</id>
                <title>Test Entry</title>
                <link href="http://test.local/entry/1"/>
                <published>$published</published>
                <updated>$updated</updated>
                <content type="html">&lt;p&gt;Updated content from feed.&lt;/p&gt;</content>
            </entry>
        </feed>
        XML;
    }

    private function queueFeedResponse(string $xml): void
    {
        $adapter = new HttpTestAdapter();
        $adapter->setResponse(
            "HTTP/1.1 200 OK\r\nContent-Type: application/atom+xml\r\n\r\n" . $xml
        );
        $httpClient = new Client();
        $httpClient->setAdapter($adapter);
        Reader::setHttpClient($httpClient);
    }

    private function runCommandWithExisting(ReaderEntity $existing): ReaderEntity
    {
        $repo = $this->createMock(ReaderRepository::class);
        $repo->method('findOneBy')->willReturn($existing);

        $config = $this->createMock(ConfigService::class);
        $config->method('get')->willReturn(self::FEED_URI);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $command = new GatherCommand($repo, $config, $em, new SummaryService());
        $app = new Application();
        $app->addCommand($command);
        (new CommandTester($command))->execute([]);

        return $existing;
    }

    private function makeExisting(string $pubdate, ?string $moddate): ReaderEntity
    {
        $entity = (new ReaderEntity())
            ->setTitle('Test Entry')
            ->setContent('<p>Original content.</p>')
            ->setSource('http://test.local/entry/1')
            ->setGuid(self::ENTRY_GUID)
            ->setPubdate(new \DateTime($pubdate));
        $entity->setModdate($moddate !== null ? new \DateTime($moddate) : null);
        return $entity;
    }

    public function testUpdatesContentWhenFeedGainsAModdate(): void
    {
        // No stored moddate; feed now reports updated > pubdate
        $this->queueFeedResponse($this->atomFeed('2024-01-01T00:00:00Z', '2024-06-01T00:00:00Z'));
        $existing = $this->makeExisting('2024-01-01T00:00:00Z', null);

        $this->runCommandWithExisting($existing);

        $this->assertStringContainsString('Updated content from feed', $existing->getContent());
        $this->assertEquals(
            (new \DateTime('2024-06-01T00:00:00Z'))->getTimestamp(),
            $existing->getModdate()->getTimestamp()
        );
    }

    public function testUpdatesContentWhenFeedModdateExceedsStoredModdate(): void
    {
        // Feed reports a moddate newer than what we already stored
        $this->queueFeedResponse($this->atomFeed('2024-01-01T00:00:00Z', '2024-09-01T00:00:00Z'));
        $existing = $this->makeExisting('2024-01-01T00:00:00Z', '2024-06-01T00:00:00Z');

        $this->runCommandWithExisting($existing);

        $this->assertStringContainsString('Updated content from feed', $existing->getContent());
        $this->assertEquals(
            (new \DateTime('2024-09-01T00:00:00Z'))->getTimestamp(),
            $existing->getModdate()->getTimestamp()
        );
    }

    public function testSkipsUpdateWhenFeedModdateMatchesStoredModdate(): void
    {
        // Feed moddate equals the stored moddate — nothing has changed
        $this->queueFeedResponse($this->atomFeed('2024-01-01T00:00:00Z', '2024-06-01T00:00:00Z'));
        $existing = $this->makeExisting('2024-01-01T00:00:00Z', '2024-06-01T00:00:00Z');

        $this->runCommandWithExisting($existing);

        $this->assertEquals('<p>Original content.</p>', $existing->getContent());
        $this->assertEquals(
            (new \DateTime('2024-06-01T00:00:00Z'))->getTimestamp(),
            $existing->getModdate()->getTimestamp()
        );
    }

    public function testSkipsUpdateWhenFeedModdatePrecedesStoredModdate(): void
    {
        // Feed reports an older moddate than what we stored — stale/rewound feed
        $this->queueFeedResponse($this->atomFeed('2024-01-01T00:00:00Z', '2024-03-01T00:00:00Z'));
        $existing = $this->makeExisting('2024-01-01T00:00:00Z', '2024-06-01T00:00:00Z');

        $this->runCommandWithExisting($existing);

        $this->assertEquals('<p>Original content.</p>', $existing->getContent());
        $this->assertEquals(
            (new \DateTime('2024-06-01T00:00:00Z'))->getTimestamp(),
            $existing->getModdate()->getTimestamp()
        );
    }

    public function testSkipsUpdateWhenFeedModdateMatchesPubdate(): void
    {
        // No stored moddate; feed updated == pubdate, so referenceDate = pubdate, not strictly greater
        $this->queueFeedResponse($this->atomFeed('2024-01-01T00:00:00Z', '2024-01-01T00:00:00Z'));
        $existing = $this->makeExisting('2024-01-01T00:00:00Z', null);

        $this->runCommandWithExisting($existing);

        $this->assertEquals('<p>Original content.</p>', $existing->getContent());
        $this->assertNull($existing->getModdate());
    }
}
