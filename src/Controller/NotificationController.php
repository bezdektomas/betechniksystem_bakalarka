<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    /**
     * Vrátí posledních 15 notifikací + počet nepřečtených.
     * Volá bell dropdown při otevření.
     */
    #[Route('', name: 'app_notifications_list', methods: ['GET'])]
    public function list(NotificationRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'notifications' => array_map(
                fn($n) => $n->toArray(),
                $repo->findRecentByUser($user, 15),
            ),
            'unreadCount' => $repo->countUnreadByUser($user),
        ]);
    }

    /** Označí všechny notifikace uživatele jako přečtené (tlačítko „Označit vše"). */
    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function readAll(NotificationRepository $repo): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $repo->markAllReadByUser($user);

        return $this->json(['ok' => true]);
    }
}
