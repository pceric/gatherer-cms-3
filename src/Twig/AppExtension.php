<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\Twig;

use App\Service\SummaryService;
use ArrayAccess;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * This class contains our custom Twig extensions
 */
class AppExtension extends AbstractExtension
{
    private RequestStack $request_stack;
    private TranslatorInterface $translator;
    private array $config;
    private SummaryService $summaryService;

    public function __construct(RequestStack $request_stack, TranslatorInterface $translator, array $config, SummaryService $summaryService)
    {
        $this->request_stack = $request_stack;
        $this->translator = $translator;
        $this->config = $config;
        $this->summaryService = $summaryService;
    }

    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML,/. you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('paragraph', $this->paragraph(...)),
            new TwigFilter('tagify', $this->tagify(...), ['is_variadic' => true, 'is_safe' => ['html']]),
            new TwigFilter('summarize', $this->summarize(...), ['is_safe' => ['html']]),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('parse_url', 'parse_url'),
            new TwigFunction('social_names', [$this, 'socialNames']),
        ];
    }

    /**
     * This Twig filter wraps text in \<p\>\</p\> tags if not already wrapped
     * @param string $string
     * @return string text wrapped in paragraph tags
     */
    public function paragraph(string $string): string
    {
        if (str_starts_with(strtolower($string), '<p>')) {
            return $string;
        }
        return sprintf('<p>%s</p>', $string);
    }

    /**
     * This Twig filter summarizes a block of large text into a smaller one
     * @param string $content
     * @return string summarized text
     */
    public function summarize(string $content): string
    {
        return $this->summaryService->summarize($content);
    }

    /**
     * This Twig filter turns an array of tag labels into href strings
     * @param $tags ArrayAccess list of tags
     * @param array $options
     * @return string a html string containing hrefs of tags
     */
    public function tagify(ArrayAccess $tags, array $options = []): string
    {
        $links = [];
        $request = $this->request_stack->getCurrentRequest();
        foreach ($tags as $t) {
            $name = trim($t->getName());
            $path = $request->getUriForPath('/tag/' . rawurlencode($name));
            $links[] = "<a class=\"" . ($options['class'] ?? 'badge bg-info') . "\" href=\"$path\">#" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</a>";
        }
        if (count($links) == 0) {
            $links[] = "<span class=\"" . ($options['class'] ?? 'badge bg-info') . "\">" . $this->translator->trans('#none') . "</span>";
        }
        return implode($options['separator'] ?? ' ', $links);
    }

    /**
     * This Twig function generates an array of social media names from our config schema
     * @return array List of social media names
     */
    public function socialNames(): array
    {
        $names = [];
        foreach ($this->config['follow']['fields'] as $v) {
            $names[] = substr(key($v), 0, -4);
        }
        return $names;
    }
}
