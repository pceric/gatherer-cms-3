<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Controller;

use App\Entity\Blog;
use App\Entity\Reader;
use App\Repository\ViewArchiveRepository;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class BlogController extends AbstractController
{
    #[Route(path: '/blog/archives', name: 'blog_archives')]
    #[Route(path: '/blog', name: 'blog')]
    public function index(ViewArchiveRepository $viewRepo, Request $request): Response
    {
        $year = $request->query->getInt('year') ?: null;
        $query = $viewRepo->createQueryBuilder('u');
        if ($year) {
            $query->andWhere('u.pubdate >= :start')->setParameter('start', new \DateTimeImmutable("$year-01-01"))
                  ->andWhere('u.pubdate < :end')->setParameter('end', new \DateTimeImmutable(($year + 1) . '-01-01'));
        }
        $adapter = new QueryAdapter($query);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(50);
        if ($pagerfanta->haveToPaginate()) {
            $pagerfanta->setCurrentPage((int)$request->query->get('page', 1));
        }

        return $this->render('blog/index.html.twig', [
            'union' => $pagerfanta->getCurrentPageResults(),
            'pager' => $pagerfanta,
            'years' => $viewRepo->getAvailableYears(),
            'year' => $year,
        ]);
    }

    #[Route(path: '/blog/gathered', name: 'gathered')]
    public function gathered(ViewArchiveRepository $viewRepo, Request $request): Response
    {
        $year = $request->query->getInt('year') ?: null;
        $query = $viewRepo->createQueryBuilder('u')
            ->andWhere('u.type = :type')
            ->setParameter('type', 'reader');
        if ($year) {
            $query->andWhere('u.pubdate >= :start')->setParameter('start', new \DateTimeImmutable("$year-01-01"))
                  ->andWhere('u.pubdate < :end')->setParameter('end', new \DateTimeImmutable(($year + 1) . '-01-01'));
        }
        $adapter = new QueryAdapter($query);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(50);
        if ($pagerfanta->haveToPaginate()) {
            $pagerfanta->setCurrentPage((int)$request->query->get('page', 1));
        }

        return $this->render('blog/index.html.twig', [
            'union' => $pagerfanta->getCurrentPageResults(),
            'pager' => $pagerfanta,
            'years' => $viewRepo->getAvailableYears('reader'),
            'year' => $year,
        ]);
    }

    #[Route(path: '/blog/gathered/{slug}', name: 'reader_show')]
    public function readerShow(#[MapEntity(mapping: ['slug' => 'slug'])] Reader $reader): Response
    {
        return $this->render('blog/show.html.twig', [
            'blog' => $reader,
            'content' => $reader->getContent(),
            'pager' => new Pagerfanta(new ArrayAdapter([]))
        ]);
    }

    #[Route(path: '/blog/article', name: 'article')]
    public function article(ViewArchiveRepository $viewRepo, Request $request): Response
    {
        $year = $request->query->getInt('year') ?: null;
        $query = $viewRepo->createQueryBuilder('u')
            ->andWhere('u.type = :type')
            ->setParameter('type', 'blog');
        if ($year) {
            $query->andWhere('u.pubdate >= :start')->setParameter('start', new \DateTimeImmutable("$year-01-01"))
                  ->andWhere('u.pubdate < :end')->setParameter('end', new \DateTimeImmutable(($year + 1) . '-01-01'));
        }
        $adapter = new QueryAdapter($query);
        $pagerfanta = new Pagerfanta($adapter);
        $pagerfanta->setMaxPerPage(50);
        if ($pagerfanta->haveToPaginate()) {
            $pagerfanta->setCurrentPage((int)$request->query->get('page', 1));
        }

        return $this->render('blog/index.html.twig', [
            'union' => $pagerfanta->getCurrentPageResults(),
            'pager' => $pagerfanta,
            'years' => $viewRepo->getAvailableYears('blog'),
            'year' => $year,
        ]);
    }

    #[Route(path: '/blog/article/{slug}', name: 'blog_show')]
    public function blogShow(#[MapEntity(mapping: ['slug' => 'slug'])] Blog $blog, Request $request): Response
    {
        if (!$blog->getPublished()) {
            throw new NotFoundHttpException();
        }

        $content = preg_split('#(<p><!--\s?pagebreak(.*?)--></p>)|(<!--\s?pagebreak(.*?)-->)#i', $blog->getContent());
        if ($content === false) {
            $content = [$blog->getContent()];
        }
        $pagerfanta = new Pagerfanta(new ArrayAdapter($content));
        $pagerfanta->setMaxPerPage(1);
        if ($pagerfanta->haveToPaginate()) {
            $pagerfanta->setCurrentPage((int)$request->query->get('page', 1));
        }

        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
            'content' => $pagerfanta->getCurrentPageResults()[0],
            'pager' => $pagerfanta
        ]);
    }

    // recent blogs twig fragment
    public function recentBlogs(ViewArchiveRepository $archiveRepo, int $limit = 5): Response
    {
        $blogs = $archiveRepo->findBy([], ['pubdate' => 'DESC'], $limit);
        return $this->render('blog/_recent.html.twig', [
            'blogs' => $blogs,
        ]);
    }

    // recent articles twig fragment
    public function recentArticles(ViewArchiveRepository $archiveRepo, int $limit = 5): Response
    {
        $blogs = $archiveRepo->findBy(['type' => 'blog'], ['pubdate' => 'DESC'], $limit);
        return $this->render('blog/_recent.html.twig', [
            'blogs' => $blogs,
        ]);
    }
}
