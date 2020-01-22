<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Controller;

use App\Entity\Blog;
use Elastica\Query;
use Elastica\Query\MultiMatch;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SearchController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'fos_elastica.finder.blog')]
        private readonly PaginatedFinderInterface $blogFinder,
        #[Autowire(service: 'fos_elastica.finder.reader')]
        private readonly PaginatedFinderInterface $readerFinder,
        #[Autowire(env: 'ELASTICSEARCH_HOSTS')]
        private readonly string $elasticsearchHosts = '',
    ) {}

    #[Route(path: '/search', name: 'search')]
    public function search(Request $request): Response
    {
        if ($this->elasticsearchHosts === '') {
            throw $this->createNotFoundException();
        }

        $q = trim((string) $request->query->get('q', ''));

        if ($q === '') {
            return $this->render('search/index.html.twig', [
                'pager' => new Pagerfanta(new ArrayAdapter([])),
                'query' => '',
            ]);
        }

        $multiMatch = new MultiMatch();
        $multiMatch->setQuery($q);
        $multiMatch->setFields(['title^2', 'content']);

        $query = new Query($multiMatch);
        $query->setSize(100);

        $results = array_filter(
            array_merge(
                $this->blogFinder->find($query),
                $this->readerFinder->find($query),
            ),
            fn($r) => !($r instanceof Blog) || $r->getPublished(),
        );

        usort($results, fn($a, $b) => $b->getPubdate() <=> $a->getPubdate());

        $pager = new Pagerfanta(new ArrayAdapter(array_values($results)));
        $pager->setMaxPerPage(25);
        if ($pager->haveToPaginate()) {
            $pager->setCurrentPage((int) $request->query->get('page', 1));
        }

        return $this->render('search/index.html.twig', [
            'pager' => $pager,
            'query' => $q,
        ]);
    }
}
