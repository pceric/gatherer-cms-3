<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Controller;

use App\Controller\FeedController;
use App\Entity\Blog;
use App\Entity\Reader;
use App\Repository\BlogRepository;
use App\Repository\ReaderRepository;
use App\Service\ConfigService;
use App\Service\SummaryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class FeedControllerTest extends TestCase
{
    private function makeController(): FeedController
    {
        $controller = $this->getMockBuilder(FeedController::class)
            ->onlyMethods(['generateUrl'])
            ->getMock();
        $controller->method('generateUrl')->willReturn('http://example.com/');
        return $controller;
    }

    private function makeReader(string $source): Reader
    {
        $reader = new Reader();
        $reader->setGuid('urn:test:' . uniqid());
        $reader->setTitle('Test Item');
        $reader->setContent('<p>Test content</p>');
        $reader->setSource($source);
        $reader->setPubdate(new \DateTime('2026-01-01'));
        $reader->setSlug('test-item-' . uniqid());
        $reader->setModdate(null);
        return $reader;
    }

    private function makeRepos(array $readers = [], array $blogs = []): array
    {
        $blogRepo = $this->createMock(BlogRepository::class);
        $blogRepo->method('findBy')->willReturn($blogs);

        $readerRepo = $this->createMock(ReaderRepository::class);
        $readerRepo->method('findBy')->willReturn($readers);

        $configService = $this->createMock(ConfigService::class);
        $configService->method('get')->willReturnArgument(2);

        $summaryService = $this->createMock(SummaryService::class);
        $summaryService->method('summarize')->willReturnArgument(0);

        return [$blogRepo, $readerRepo, $configService, $summaryService];
    }

    public function testFeedWithHostlessSourceDoesNotThrow(): void
    {
        [$blogRepo, $readerRepo, $configService, $summaryService] = $this->makeRepos(
            readers: [$this->makeReader('/path/with/no/host')]
        );

        $response = $this->makeController()->index(
            $blogRepo, $readerRepo, $configService, $summaryService, new ArrayAdapter(), 'rss'
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<rss', $response->getContent());
    }

    public function testFeedUsesFullSourceAsAuthorWhenHostMissing(): void
    {
        $source = '/path/with/no/host';
        [$blogRepo, $readerRepo, $configService, $summaryService] = $this->makeRepos(
            readers: [$this->makeReader($source)]
        );

        $response = $this->makeController()->index(
            $blogRepo, $readerRepo, $configService, $summaryService, new ArrayAdapter(), 'rss'
        );

        $this->assertStringContainsString(htmlspecialchars($source), $response->getContent());
    }

    public function testFeedExtractsHostFromNormalSourceUrl(): void
    {
        [$blogRepo, $readerRepo, $configService, $summaryService] = $this->makeRepos(
            readers: [$this->makeReader('https://example.com/article/123')]
        );

        $response = $this->makeController()->index(
            $blogRepo, $readerRepo, $configService, $summaryService, new ArrayAdapter(), 'rss'
        );

        $this->assertStringContainsString('example.com', $response->getContent());
    }

    private function makeBlogMock(?string $author, string $content = '<p>Content</p>'): Blog
    {
        $blog = $this->createMock(Blog::class);
        $blog->method('getGuid')->willReturn('urn:test:' . uniqid());
        $blog->method('getTitle')->willReturn('Test Blog Post');
        $blog->method('getSlug')->willReturn('test-blog-post');
        $blog->method('getEntity')->willReturn('blog');
        $blog->method('getAuthor')->willReturn($author);
        $blog->method('getContent')->willReturn($content);
        $blog->method('getPubdate')->willReturn(new \DateTime('2026-01-01'));
        $blog->method('getModdate')->willReturn(null);
        return $blog;
    }

    public function testBlogWithUsernameAuthorFallbackDoesNotThrow(): void
    {
        [$blogRepo, $readerRepo, $configService, $summaryService] = $this->makeRepos(
            blogs: [$this->makeBlogMock('fallback_user')]
        );

        $response = $this->makeController()->index(
            $blogRepo, $readerRepo, $configService, $summaryService, new ArrayAdapter(), 'rss'
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('fallback_user', $response->getContent());
    }

    public function testAtomFeedIsAlsoValid(): void
    {
        [$blogRepo, $readerRepo, $configService, $summaryService] = $this->makeRepos(
            readers: [$this->makeReader('https://example.com/feed')]
        );

        $response = $this->makeController()->index(
            $blogRepo, $readerRepo, $configService, $summaryService, new ArrayAdapter(), 'atom'
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('<feed', $response->getContent());
    }
}
