<?php
/*
 * Copyright (c) 2021 Eric Hokanson
 * Licensed under the Open Software License version 3.0
 */

namespace App\EventSubscriber;

use App\Repository\UserRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class InitSubscriber
 * @package App\EventSubscriber
 *
 * Performs some sanity checks to make sure the GCMS system is good to go
 */
class InitSubscriber implements EventSubscriberInterface
{
    private bool $hasAdmin;

    public function __construct(UserRepository $userRepo)
    {
        if ($userRepo->findUsersByRole('ROLE_ADMIN') != null)
            $this->hasAdmin = true;
        else
            $this->hasAdmin = false;
    }

    /**
     * Checks to see if we have at least one admin user and redirects to the setup page if not
     * @param RequestEvent $event
     */
    public function checkAdmin(RequestEvent $event): void
    {
        $event->getRequest()->attributes->set('has_administrator', $this->hasAdmin);
        if (!$this->hasAdmin && !str_starts_with($event->getRequest()->attributes->get('_controller'), 'App\Controller\SecurityController')) {
            $event->getRequest()->attributes->set('_controller', 'App\Controller\SecurityController::setup');
        }
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'checkAdmin',
        ];
    }
}