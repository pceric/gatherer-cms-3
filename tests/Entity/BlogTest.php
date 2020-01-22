<?php
/*
 * Copyright (c) 2026 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Tests\Entity;

use App\Entity\Blog;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class BlogTest extends TestCase
{
    private function makeUser(?string $displayname, string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        if ($displayname !== null) $user->setDisplayname($displayname);
        return $user;
    }

    private function makeBlog(User $user): Blog
    {
        $blog = new Blog();
        $blog->setUser($user);
        return $blog;
    }

    public function testGetAuthorReturnsDisplayname(): void
    {
        $blog = $this->makeBlog($this->makeUser('Jane Doe', 'janedoe'));
        $this->assertSame('Jane Doe', $blog->getAuthor());
    }

    public function testGetAuthorFallsBackToUsernameWhenDisplaynameIsNull(): void
    {
        $blog = $this->makeBlog($this->makeUser(null, 'janedoe'));
        $this->assertSame('janedoe', $blog->getAuthor());
    }
}
