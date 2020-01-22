<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Repository;

use App\Entity\Blog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Blog>
 */
class BlogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Blog::class);
    }

    public static function blogMerger(array $blog, array $reader): array
    {
        // Doctrine doesn't have a union
        $feed = array_merge($blog, $reader);
        usort($feed, function ($a, $b) {
            if ($a->getPubdate() < $b->getPubdate())
                return 1;
            elseif ($a->getPubdate() > $b->getPubdate())
                return -1;
            return 0;
        });
        return $feed;
    }
}
