<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Command;

use App\Command\UpgradeCommand;
use PHPUnit\Framework\TestCase;

class UpgradeCommandTest extends TestCase
{
    public function testStripReaderTitleLeavesUnknownSourceUntouched(): void
    {
        $this->assertEquals(
            'Some Article Title',
            UpgradeCommand::stripReaderTitle('Some Article Title')
        );
    }

    public function testStripReaderTitlePreservesInternalSpaces(): void
    {
        $this->assertEquals(
            'My Article With Spaces',
            UpgradeCommand::stripReaderTitle('My Article With Spaces')
        );
    }

    public function testStripReaderTitleRemovesArsTechnicaSuffix(): void
    {
        $this->assertEquals(
            'New GPU announced',
            UpgradeCommand::stripReaderTitle('New GPU announced | Ars Technica')
        );
    }

    public function testStripReaderTitleRemovesSlashdotSuffix(): void
    {
        $this->assertEquals(
            'Linux kernel released',
            UpgradeCommand::stripReaderTitle('Linux kernel released - Slashdot')
        );
    }

    public function testStripReaderTitleRemovesTechCrunchSuffix(): void
    {
        $this->assertEquals(
            'Startup raises funding',
            UpgradeCommand::stripReaderTitle('Startup raises funding | TechCrunch')
        );
    }

    public function testStripReaderTitleDoesNotStripMidTitlePipe(): void
    {
        $this->assertEquals(
            'A | B comparison',
            UpgradeCommand::stripReaderTitle('A | B comparison')
        );
    }
}
