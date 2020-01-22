<?php
/*
 * Copyright (c) 2024 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Repository;

use App\Entity\ViewArchive;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ViewArchive>
 */
class ViewArchiveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ViewArchive::class);
    }

    public function getAvailableYears(?string $type = null): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT DISTINCT YEAR(pubdate) AS year FROM v_archive';
        $params = [];
        if ($type !== null) {
            $sql .= ' WHERE type = :type';
            $params['type'] = $type;
        }
        $sql .= ' ORDER BY year DESC';
        return array_map('intval', $conn->fetchFirstColumn($sql, $params));
    }
}
