<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\DataFixtures;

use App\Entity\Config;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Class SetupFixtures
 * @package App\DataFixtures
 * @see https://symfony.com/doc/current/bundles/DoctrineFixturesBundle/index.html
 *
 * This class loads some initial data into the database for testing
 */
class SetupFixtures extends Fixture
{
    public const EMPTY_DATA = [
        'meta' => [
            'author' => 'Webmaster',
            'desc' => 'Gatherer Blog',
            'email' => 'webmaster@example.com',
            'keywords' => 'cms,blog',
            'name' => 'My Gatherer Website',
        ],
        'ingestion' => [
            'uri' => '',
        ],
        'follow' => [
            'bluesky_uri' => '',
            'discord_uri' => '',
            'facebook_uri' => '',
            'github_uri' => '',
            'instagram_uri' => '',
            'linkedin_uri' => '',
            'mastodon_uri' => '',
            'pinterest_uri' => '',
            'reddit_uri' => '',
            'snapchat_uri' => '',
            'spotify_uri' => '',
            'threads_uri' => '',
            'tiktok_uri' => '',
            'twitch_uri' => '',
            'twitter-x_uri' => '',
            'youtube_uri' => '',
        ],
        'disqus' => [
            'enabled' => False,
            'domain' => '',
        ]
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (SetupFixtures::EMPTY_DATA as $k => $v) {
            $config = new Config();
            $config->setNamespace($k);
            $config->setValue($v);
            $manager->persist($config);
        }

        $manager->flush();
    }
}
