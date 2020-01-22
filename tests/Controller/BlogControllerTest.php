<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Controller;

use App\Controller\BlogController;
use App\Entity\Blog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BlogControllerTest extends TestCase
{
    private function makeBlog(string $content, bool $published = true): Blog
    {
        $blog = new Blog();
        $blog->setTitle('Test Post');
        $blog->setContent($content);
        $blog->setPublished($published);
        $blog->setSticky(false);
        $blog->setPubdate(new \DateTime());
        return $blog;
    }

    /**
     * Mocks render() to capture template params, then calls blogShow.
     *
     * @return array{blog: Blog, content: string, pager: \Pagerfanta\Pagerfanta}
     */
    private function callBlogShow(Blog $blog, Request $request): array
    {
        $captured = [];
        $controller = $this->getMockBuilder(BlogController::class)
            ->onlyMethods(['render'])
            ->getMock();
        $controller->method('render')
            ->willReturnCallback(function (string $template, array $params) use (&$captured) {
                $captured = $params;
                return new Response();
            });
        $controller->blogShow($blog, $request);
        return $captured;
    }

    public function testSinglePageNoPagebreak(): void
    {
        $blog = $this->makeBlog('<p>Only page.</p>');
        $params = $this->callBlogShow($blog, new Request());

        $this->assertSame('<p>Only page.</p>', $params['content']);
        $this->assertSame(1, $params['pager']->getNbPages());
    }

    public function testPagebreakSplitsIntoTwoPages(): void
    {
        $blog = $this->makeBlog('<p>Page one.</p><!--pagebreak--><p>Page two.</p>');

        $page1 = $this->callBlogShow($blog, new Request());
        $this->assertSame(2, $page1['pager']->getNbPages());
        $this->assertSame('<p>Page one.</p>', $page1['content']);

        $page2 = $this->callBlogShow($blog, new Request(['page' => '2']));
        $this->assertSame('<p>Page two.</p>', $page2['content']);
    }

    public function testPagebreakInParagraphTagsAlsoSplits(): void
    {
        $blog = $this->makeBlog('<p>Page one.</p><p><!--pagebreak--></p><p>Page two.</p>');

        $page1 = $this->callBlogShow($blog, new Request());
        $this->assertSame(2, $page1['pager']->getNbPages());
        $this->assertSame('<p>Page one.</p>', $page1['content']);

        $page2 = $this->callBlogShow($blog, new Request(['page' => '2']));
        $this->assertSame('<p>Page two.</p>', $page2['content']);
    }

    public function testMultiplePagebreaksProduceMultiplePages(): void
    {
        $blog = $this->makeBlog('<p>One.</p><!--pagebreak--><p>Two.</p><!--pagebreak--><p>Three.</p>');

        $page1 = $this->callBlogShow($blog, new Request());
        $this->assertSame(3, $page1['pager']->getNbPages());
        $this->assertSame('<p>One.</p>', $page1['content']);

        $page2 = $this->callBlogShow($blog, new Request(['page' => '2']));
        $this->assertSame('<p>Two.</p>', $page2['content']);

        $page3 = $this->callBlogShow($blog, new Request(['page' => '3']));
        $this->assertSame('<p>Three.</p>', $page3['content']);
    }

    public function testPagebreakIsCaseInsensitive(): void
    {
        $blog = $this->makeBlog('<p>Page one.</p><!--Pagebreak--><p>Page two.</p>');

        $params = $this->callBlogShow($blog, new Request());
        $this->assertSame(2, $params['pager']->getNbPages());
    }

    public function testUnpublishedPostThrows404(): void
    {
        $controller = new BlogController();
        $blog = $this->makeBlog('<p>Content.</p>', false);

        $this->expectException(NotFoundHttpException::class);
        $controller->blogShow($blog, new Request());
    }
}
