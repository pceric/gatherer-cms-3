<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Service;

use App\Service\SummaryService;
use PHPUnit\Framework\TestCase;

class SummaryServiceTest extends TestCase
{
    private SummaryService $service;

    protected function setUp(): void
    {
        $this->service = new SummaryService();
    }

    public function testCleanContent(): void
    {
        $this->assertEquals(
            '<b>text</b>',
            $this->service->cleanContent('<b class="foo" onload=function_xyz>text</b>')
        );
        $this->assertEquals(
            '<small>text</small>',
            $this->service->cleanContent('<small onmessage="javascript:execute()">text</small>')
        );
        $this->assertEquals(
            '<p>text</p>',
            $this->service->cleanContent('<p onfocus="alert(\'hey\')" onclick=foo() disabled>text</p>')
        );
        $this->assertEquals(
            '',
            $this->service->cleanContent('<script>/* stuff here */</script>')
        );
        $this->assertEquals(
            '',
            $this->service->cleanContent('<style>color=#aa11bb</style>')
        );
        $this->assertEquals(
            '<small>small</small>',
            $this->service->cleanContent('<HEAD></HEAD><body><small>small</small></body>')
        );
        $this->assertEquals(
            '',
            $this->service->cleanContent('<p> </p>')
        );
        $this->assertEquals(
            '<br>',
            $this->service->cleanContent('<br><br/><br>')
        );
    }

    public function testSummarize(): void
    {
        $long = str_repeat('x', 150);

        // Extract summary from unstructured content; <img> stripped, "Read more..." link text remains (filterByDomain strips it)
        $this->assertEquals(
            'Big news for W 11 users: M is finally introducing a movable taskbar, faster File Explorer, and plenty of other changes that address the most popular complaints from customers. Read more...',
            $this->service->summarize(
                '<div><img src="https://example.com/example.jpg" alt="" referrerpolicy="no-referrer" loading="lazy"></div>Big news for W 11 users: M is finally introducing a movable taskbar, faster File Explorer, and plenty of other changes that address the most popular complaints from customers. <a href="https://example.com/" rel="noopener noreferrer" target="_blank">Read more...</a>',
                '<p>Big news for W 11 users: M is finally introducing a movable taskbar, faster File Explo...</p>'
            )
        );

        // Long paragraph extracted directly from content
        $this->assertEquals(
            '<p>' . $long . '</p>',
            $this->service->summarize('<p>' . $long . '</p>')
        );

        // Mega paragraph split by breaks, should just take the first chunk
        $this->assertEquals(
            '<p>' . $long . '</p>',
            $this->service->summarize('<p>' . $long . "<br>\n<br>" . $long . '</p>')
        );

        // Short paragraphs (>= MIN_PARAGRAPH_CHARS) accumulate until MIN_SUMMARY_CHARS is reached;
        // the fourth paragraph is not included once 100 chars are satisfied
        $this->assertEquals(
            '<p>' . str_repeat('a', 30) . '</p>'
                . '<p>' . str_repeat('b', 30) . '</p>'
                . '<p>' . str_repeat('c', 30) . '</p>',
            $this->service->summarize(
                '<p>' . str_repeat('a', 30) . '</p>'
                . '<p>' . str_repeat('b', 30) . '</p>'
                . '<p>' . str_repeat('c', 30) . '</p>'
                . '<p>' . str_repeat('d', 30) . '</p>'
            )
        );

        // Super short paragraph followed by long paragraph; the short paragraph is included in the summary
        $this->assertEquals(
            '<p>short</p><p>' . $long . '</p>',
            $this->service->summarize('<p>short</p><p>' . $long . '</p>')
        );

        // Falls back to $fallback when content yields nothing extractable
        $this->assertEquals(
            'Fallback summary content.',
            $this->service->summarize('tiny', 'Fallback summary content.')
        );

        // Returns cleaned original content when all summarization fails and no fallback given
        $this->assertEquals(
            'tiny',
            $this->service->summarize('tiny')
        );

        // cleanContent is applied — attributes are stripped from extracted paragraphs
        $this->assertEquals(
            '<p>' . $long . '</p>',
            $this->service->summarize('<p class="bad" onclick=foo()>' . $long . '</p>')
        );
    }
}
