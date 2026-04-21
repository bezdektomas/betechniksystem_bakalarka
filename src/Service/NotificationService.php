<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centrální místo pro odesílání notifikací.
 * Uloží záznam do DB (bell centrum) + odešle Firebase push.
 */
class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notificationRepo,
        private readonly FirebaseNotificationService $firebaseService,
    ) {}

    /**
     * Upozorní příjemce: uloží in-app notifikaci a odešle push.
     *
     * @param User[]  $recipients    Všichni potenciální příjemci
     * @param ?User   $excludeUser   Odesílatel – vynechán z příjemců
     * @param string  $resourceType  Typ resource (konverzace, zakazka, …)
     * @param int     $resourceId    ID resource (pro auto-read v NotificationReadListener)
     */
    public function notify(
        array $recipients,
        string $title,
        string $body,
        string $link,
        string $resourceType,
        int $resourceId,
        string $collapseKey = '',
        ?User $excludeUser = null,
    ): void {
        $targets = array_values(array_filter(
            $recipients,
            fn(User $u) => !$excludeUser || $u->getId() !== $excludeUser->getId(),
        ));

        if (empty($targets)) {
            return;
        }

        // Uložit in-app notifikaci pro každého příjemce
        foreach ($targets as $user) {
            $notification = (new Notification())
                ->setUser($user)
                ->setTitle($title)
                ->setBody($body)
                ->setLink($link)
                ->setResourceType($resourceType)
                ->setResourceId($resourceId);

            $this->em->persist($notification);
        }

        $this->em->flush();

        // Firebase push (fire and forget, nezávislé na in-app)
        $this->firebaseService->notifyUsers(
            recipients:  $targets,
            title:       $title,
            body:        $body,
            data:        ['url' => $link],
            collapseKey: $collapseKey,
        );
    }
}
