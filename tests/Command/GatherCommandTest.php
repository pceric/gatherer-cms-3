<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Command;

use App\Command\GatherCommand;
use App\Service\SummaryService;
use PHPUnit\Framework\TestCase;

class GatherCommandTest extends TestCase
{
    public function testSanitizeTag(): void
    {
        // Allowed characters pass through unchanged
        $this->assertEquals('hello-world',  GatherCommand::sanitizeTag('hello-world'));
        $this->assertEquals('tag_name',     GatherCommand::sanitizeTag('tag_name'));
        $this->assertEquals('v1.0',         GatherCommand::sanitizeTag('v1.0'));
        $this->assertEquals('foo & bar',    GatherCommand::sanitizeTag('foo & bar'));
        $this->assertEquals('tag name',     GatherCommand::sanitizeTag('tag name'));

        // Surrounding whitespace is trimmed
        $this->assertEquals('tag',          GatherCommand::sanitizeTag('  tag  '));

        // Unicode letters and digits are preserved
        $this->assertEquals('café',         GatherCommand::sanitizeTag('café'));
        $this->assertEquals('日本語',        GatherCommand::sanitizeTag('日本語'));

        // Disallowed symbols are stripped
        $this->assertEquals('tag',          GatherCommand::sanitizeTag('tag!@#$%^*'));
        $this->assertEquals('tag',          GatherCommand::sanitizeTag('tag!'));

        // HTML injection stripped (XSS via raw tag content)
        $this->assertEquals('img srcx onerroralert1', GatherCommand::sanitizeTag('<img src=x onerror=alert(1)>'));

        // HTML injection stripped after entity decoding (XSS via encoded payload within 32-char limit)
        $this->assertEquals('img srcx onerroralert1', GatherCommand::sanitizeTag('&lt;img src=x onerror=alert(1)&gt;'));

        // Quote breakout attempt stripped
        $this->assertEquals('fooonclickalert1', GatherCommand::sanitizeTag('foo"onclick="alert(1)"'));
    }

    public function testFilterByDomain(): void
    {
        // Unknown domain — no changes
        $this->assertEquals(
            ['My Title', '<p>Some content.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Some content.</p>')
        );

        // Default — strips plain "Read more" at end
        $this->assertEquals(
            ['My Title', '<p>Content.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Content.</p> Read more')
        );

        // Default — strips "Read More" (capital) with trailing dots
        $this->assertEquals(
            ['My Title', '<p>Content.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Content.</p> Read More...')
        );

        // Default — strips "Read more" with ellipsis character
        $this->assertEquals(
            ['My Title', '<p>Content.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Content.</p> Read more…')
        );

        // Default — strips "Read More" wrapped in a <p> tag
        $this->assertEquals(
            ['My Title', '<p>Content.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Content.</p><p>Read More</p>')
        );

        // Default — strips "Read more" wrapped in nested tags
        $this->assertEquals(
            ['My Title', '<p>Content.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Content.</p><p><a href="https://example.com/post">Read more</a></p>')
        );

        // Default — strips "Read More" without touching content before it, even if it's in the same <p> tag
        $this->assertEquals(
            ['My Title', '<p>Article body.</p><p>Blah Blah.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Article body.</p><p>Blah Blah. Read More</p>')
        );

        // Default — strips trailing block element with boilerplate "read more" and tracking content
        $this->assertEquals(
            ['My Title', '<p>Keep this.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Keep this.</p><p><a href="http://example.com">read more</a> | <a href="http://example.com/ad/">.</a><img src="http://example.com/ad/adserve.php?o=image" height="0" width="0" /></p>')
        );

        // Default — strips block element with only "Read More" content, leaving just text
        $this->assertEquals(
            ['My Title', '<p>Blah Blah.</p>'],
            GatherCommand::filterByDomain('example.com', 'My Title', '<p>Blah Blah. Read More</p>')
        );

        // Slashdot — strips trailing boilerplate from content
        $this->assertEquals(
            ['Slashdot Post', ''],
            GatherCommand::filterByDomain('slashdot.org', 'Slashdot Post', 'Blah Blah. Read more of this story at Slashdot.')
        );

        // Slashdot — only strips the trailing sentence, preserving preceding content
        $this->assertEquals(
            ['Slashdot Post', '<p>Keep this.</p>'],
            GatherCommand::filterByDomain('slashdot.org', 'Slashdot Post', "<p>Keep this.</p>\nRead more of this story at Slashdot.")
        );

        // TechPowerUp — strips leading "(PR) " from title
        $this->assertEquals(
            ['Some Product Launch', '<p>Content.</p>'],
            GatherCommand::filterByDomain('techpowerup.com', '(PR) Some Product Launch', '<p>Content.</p>')
        );

        // TechPowerUp — title without (PR) prefix is untouched
        $this->assertEquals(
            ['Normal Title', '<p>Content.</p>'],
            GatherCommand::filterByDomain('techpowerup.com', 'Normal Title', '<p>Content.</p>')
        );

        // TechPowerUp — content is untouched
        $this->assertEquals(
            ['Review Title', '<p>Blah Blah. Content.</p>'],
            GatherCommand::filterByDomain('techpowerup.com', 'Review Title', '<p>Blah Blah. Content.</p>')
        );

        // Lifehacker - messy
        $this->assertEquals(
            ['Discover Your New Favorite Linux Flavor', '<img height="120" width="190" title="Click here" alt="Click here" src="http://example.com/example.jpg">So you\'ve gotten started with Linux, but you\'re looking for a new flavor besides Ubuntu to try out.'],
            GatherCommand::filterByDomain('lifehacker.com', 'Discover Your New Favorite Linux Flavor', '<a title="Click here" href="http://example.com"><img style="border-color:#B3B3B3;border-width:0 1px 1px;border-style:none solid solid" height="120" width="190" title="Click here" alt="Click here" src="http://example.com/example.jpg"></a>
			So you\'ve <a href="http://example.com">gotten started with Linux</a>, but you\'re looking for a new flavor besides Ubuntu to try out. <a href="http://example.com" title="Click here">More »</a><br style="clear:both"><a href="http://example.org">
            <img src="http://example.org/example.png" border="0"></a> <a href="http://example.org"><img src="http://example.org/example.png" border="0"></a>')
        );
    }

    public function testGatherPipeline(): void
    {
        $summaryService = new SummaryService();

        $rawContent = '<div><img src="https://example.com/example.jpg" alt="" referrerpolicy="no-referrer" loading="lazy"></div>Big news for W 11 users: M is finally introducing a movable taskbar, faster FE, and plenty of other changes that address the most popular complaints from customers. <a href="https://example.com/" rel="noopener noreferrer" target="_blank">Read more...</a>';

        $content = $summaryService->summarize($rawContent);
        [, $content] = GatherCommand::filterByDomain('example.com', 'My Title', $content);

        $this->assertEquals(
            'Big news for W 11 users: M is finally introducing a movable taskbar, faster FE, and plenty of other changes that address the most popular complaints from customers.',
            $content
        );
    }
}
