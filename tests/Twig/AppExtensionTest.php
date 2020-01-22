<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Twig;

use App\Service\SummaryService;
use App\Twig\AppExtension;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class AppExtensionTest extends TestCase
{
    private AppExtension $extension;

    protected function setUp(): void
    {
        $request = $this->createMock(Request::class);
        $request->method('getUriForPath')
            ->willReturnCallback(fn(string $path) => 'http://localhost' . $path);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('#none');

        $this->extension = new AppExtension(
            $requestStack,
            $translator,
            [],
            $this->createMock(SummaryService::class)
        );
    }

    private function makeTags(array $names): ArrayObject
    {
        return new ArrayObject(array_map(function (string $name) {
            return new class($name) {
                public function __construct(private string $name) {}
                public function getName(): string { return $this->name; }
            };
        }, $names));
    }

    public function testTagifyEscapesHtmlInName(): void
    {
        $result = $this->extension->tagify($this->makeTags(['<script>alert(1)</script>']));
        $this->assertStringContainsString('&lt;script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testTagifyEscapesAmpersandInName(): void
    {
        $result = $this->extension->tagify($this->makeTags(['foo & bar']));
        $this->assertStringContainsString('foo &amp; bar', $result);
    }

    public function testTagifyEscapesQuotesInName(): void
    {
        $result = $this->extension->tagify($this->makeTags(['"quoted"']));
        $this->assertStringContainsString('&quot;quoted&quot;', $result);
        $this->assertStringNotContainsString('"quoted"', $result);
    }

    public function testTagifyUrlEncodesSpacesInPath(): void
    {
        $result = $this->extension->tagify($this->makeTags(['foo bar']));
        $this->assertStringContainsString('/tag/foo%20bar', $result);
    }

    public function testTagifyUrlEncodesAmpersandInPath(): void
    {
        $result = $this->extension->tagify($this->makeTags(['foo & bar']));
        $this->assertStringContainsString('/tag/foo%20%26%20bar', $result);
    }

    public function testTagifyRendersNormalTag(): void
    {
        $result = $this->extension->tagify($this->makeTags(['php']));
        $this->assertStringContainsString('href="http://localhost/tag/php"', $result);
        $this->assertStringContainsString('>#php<', $result);
    }

    public function testTagifyEmptyCollectionReturnsNone(): void
    {
        $result = $this->extension->tagify(new ArrayObject([]));
        $this->assertStringContainsString('#none', $result);
    }

    public function testTagifyMultipleTags(): void
    {
        $result = $this->extension->tagify($this->makeTags(['php', 'symfony']));
        $this->assertStringContainsString('>#php<', $result);
        $this->assertStringContainsString('>#symfony<', $result);
    }
}
