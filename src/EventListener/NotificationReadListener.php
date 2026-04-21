<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Automaticky označí notifikace jako přečtené, když uživatel navštíví
 * příslušnou stránku – ať už přišel přes push notifikaci nebo přímo.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -10)]
class NotificationReadListener
{
    private const ROUTE_MAP = [
        'app_chat_show'    => ['konverzace', 'id'],
        'app_zakazka_show' => ['zakazka',    'id'],
        'app_faktura_show' => ['faktura',    'id'],
    ];

    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notificationRepo,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route   = $request->attributes->get('_route');

        if (!isset(self::ROUTE_MAP[$route])) {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        [$resourceType, $routeParam] = self::ROUTE_MAP[$route];
        $resourceId = (int) $request->attributes->get($routeParam);

        if (!$resourceId) {
            return;
        }

        $this->notificationRepo->markReadByUserAndResource($user, $resourceType, $resourceId);
    }
}
