<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Controller;

use App\Repository\TagRepository;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TagController extends AbstractController
{
    #[Route(path: ['/tag/{tag}', '/tag/{slug}'], name: 'tag')]
    public function index(string $tag, TagRepository $tagRepository, Request $request): Response
    {
        $matches = [];
        foreach ($tagRepository->findBy(['name' => $tag], [], 100) as $tr) {
            $union = $tr->getBlogs(true)->toArray() + $tr->getReaders()->toArray();
            foreach ($union as $u) {
                $matches[] = ['entity' => $u->getEntity(), 'slug' => $u->getSlug(), 'title' => $u->getTitle(), 'pubdate' => $u->getPubdate()];
            }
        }
        usort($matches, fn($a, $b) => $b['pubdate'] <=> $a['pubdate']);

        $pager = new Pagerfanta(new ArrayAdapter($matches));
        $pager->setMaxPerPage(50);
        if ($pager->haveToPaginate()) {
            $pager->setCurrentPage((int) $request->query->get('page', 1));
        }

        return $this->render('tag/index.html.twig', [
            'tag' => $tag,
            'matches' => $pager->getCurrentPageResults(),
            'pager' => $pager,
        ]);
    }
}
