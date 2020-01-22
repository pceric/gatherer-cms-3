<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Controller;

use App\Repository\BlogRepository;
use App\Repository\ReaderRepository;
use App\Service\ConfigService;
use App\Service\SummaryService;
use Laminas\Feed\Writer\Feed;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class FeedController extends AbstractController
{
    private const CACHE_SECONDS = 900;

    #[Route(path: '/feed/{type}', name: 'feed', requirements: ['type' => 'atom|rss'])]
    public function index(BlogRepository $newsRepo, ReaderRepository $readerRepo, ConfigService $configService, SummaryService $summaryService, CacheInterface $cache, string $type = 'rss'): Response
    {
        $export = $cache->get("feed.$type", function(ItemInterface $item) use ($newsRepo, $readerRepo, $configService, $summaryService, $type) {
            $item->expiresAfter((int)($_ENV['GCMS_SYNDICATION_CACHE_SECONDS'] ?? $this::CACHE_SECONDS));

            $news = $newsRepo->findBy(['published' => true], ['pubdate' => 'DESC'], 50);
            $reader = $readerRepo->findBy([], ['pubdate' => 'DESC'], 50);

            // Doctrine doesn't have a union
            $items = array_merge($news, $reader);
            usort($items, function ($a, $b) {
                if ($a->getPubdate() < $b->getPubdate())
                    return 1;
                elseif ($a->getPubdate() > $b->getPubdate())
                    return -1;
                return 0;
            });

            $feed = new Feed;
            $feed->setTitle($configService->get('name', 'meta','Feed'));
            $feed->setDescription($configService->get('desc', 'meta', 'Syndication'));
            $feed->setLink($this->generateUrl('home_page',[],UrlGeneratorInterface::ABSOLUTE_URL));
            $feed->setFeedLink($this->generateUrl('feed',['type' => $type],UrlGeneratorInterface::ABSOLUTE_URL), $type);
            $feed->setGenerator($_ENV['ENGINE_NAME']);
            $feed->setDateCreated(new \DateTimeImmutable());
            $feed->setDateModified(count($items) > 0 ? $items[0]->getPubdate() : new \DateTimeImmutable());

            foreach ($items as $item) {
                $entry = $feed->createEntry();
                $entry->setId($item->getGuid());
                $entry->setTitle($item->getTitle());
                if ($item->getEntity() == "blog") {
                    $entry->setLink($this->generateUrl('blog_show', ['slug' => $item->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));
                    $entry->addAuthor(['name' => $item->getAuthor()]);
                    $entry->setContent($summaryService->summarize($item->getContent()));
                } else {
                    $entry->setLink($this->generateUrl('reader_show', ['slug' => $item->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL));
                    $entry->addAuthor(['name' => parse_url($item->getSource(), PHP_URL_HOST) ?: $item->getSource()]);
                    if ($content = $item->getContent()) $entry->setContent($content);
                }
                $entry->setDateCreated($item->getPubdate());
                if ($item->getModdate()) $entry->setDateModified($item->getModdate()); // setting modified to null is buggy
                $feed->addEntry($entry);
            }

            return $feed->export($type, true);
        });

        $response = new Response($export);
        $response->setPublic();
        $response->setMaxAge((int)($_ENV['GCMS_SYNDICATION_CACHE_SECONDS'] ?? $this::CACHE_SECONDS));
        $response->headers->set('Content-Type','application/' . $type . '+xml; charset=utf-8');
        return $response;
    }
}
