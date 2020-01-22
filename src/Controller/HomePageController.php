<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Controller;

use App\Repository\BlogRepository;
use App\Repository\ReaderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomePageController extends AbstractController
{
    /**
     * @param BlogRepository $newsRepo
     * @param ReaderRepository $readerRepo
     * @return Response
     */
    #[Route(path: '/', name: 'home_page')]
    public function index(BlogRepository $newsRepo, ReaderRepository $readerRepo): Response
    {
        $sticky = $newsRepo->findBy(['published' => true, 'sticky' => true], ['pubdate' => 'DESC'], 10);
        $blogs = $newsRepo->findBy(['published' => true, 'sticky' => false], ['pubdate' => 'DESC'], 10);
        $reader = $readerRepo->findBy([], ['pubdate' => 'DESC'], 10);

        // Doctrine doesn't have a union
        $feed = BlogRepository::blogMerger($blogs, $reader);

        return $this->render('home_page/index.html.twig', [
            'sticky' => $sticky,
            'feed' => $feed,
        ]);
    }
}
