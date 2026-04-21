<?php

namespace App\Controller;

use App\Entity\ChatKonverzace;
use App\Entity\ChatPriloha;
use App\Entity\ChatReakce;
use App\Entity\ChatZprava;
use App\Entity\User;
use App\Entity\UserFcmToken;
use App\Repository\ChatKonverzaceRepository;
use App\Repository\ChatPrecteniRepository;
use App\Repository\NotificationRepository;
use App\Repository\ChatPrilohaRepository;
use App\Repository\ChatZpravaRepository;
use App\Repository\UserFcmTokenRepository;
use App\Repository\UserRepository;
use App\Repository\ZakazkaRepository;
use App\Service\CentrifugoService;
use App\Service\FirebaseNotificationService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chat')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        #[Autowire(env: 'CENTRIFUGO_WS_URL')]              private readonly string $wsUrl,
        #[Autowire(env: 'FIREBASE_API_KEY')]                private readonly string $fbApiKey,
        #[Autowire(env: 'FIREBASE_AUTH_DOMAIN')]            private readonly string $fbAuthDomain,
        #[Autowire(env: 'FIREBASE_PROJECT_ID')]             private readonly string $fbProjectId,
        #[Autowire(env: 'FIREBASE_STORAGE_BUCKET')]         private readonly string $fbStorageBucket,
        #[Autowire(env: 'FIREBASE_MESSAGING_SENDER_ID')]    private readonly string $fbMessagingSenderId,
        #[Autowire(env: 'FIREBASE_APP_ID')]                 private readonly string $fbAppId,
        #[Autowire(env: 'FIREBASE_VAPID_KEY')]              private readonly string $fbVapidKey,
    ) {}

    #[Route('', name: 'app_chat_index')]
    public function index(
        ChatKonverzaceRepository $repo,
        ChatPrecteniRepository $precteniRepo,
        CentrifugoService $centrifugo,
        UserRepository $userRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $konverzace = $repo->findByUser($user);
        $vsichniUzivatele = $userRepo->findBy(['isActive' => true]);
        $unreadCounts = $precteniRepo->countUnreadByConversations($konverzace, $user);

        $userChannel = $centrifugo->channelForUser($user->getId());

        return $this->render('chat/index.html.twig', [
            'konverzace' => $konverzace,
            'aktivniKonverzace' => null,
            'zpravy' => [],
            'vsichniUzivatele' => $vsichniUzivatele,
            'unreadCounts' => $unreadCounts,
            'connectionToken' => $centrifugo->generateConnectionToken($user),
            'subscriptionToken' => null,
            'userSubscriptionToken' => $centrifugo->generateSubscriptionToken($user, $userChannel),
            'wsUrl' => $this->wsUrl,
            'firebaseConfig' => [
                'apiKey'            => $this->fbApiKey,
                'authDomain'        => $this->fbAuthDomain,
                'projectId'         => $this->fbProjectId,
                'storageBucket'     => $this->fbStorageBucket,
                'messagingSenderId' => $this->fbMessagingSenderId,
                'appId'             => $this->fbAppId,
                'vapidKey'          => $this->fbVapidKey,
            ],
        ]);
    }

    #[Route('/{id}', name: 'app_chat_show', requirements: ['id' => '\d+'])]
    public function show(
        ChatKonverzace $konverzace,
        ChatKonverzaceRepository $repo,
        ChatZpravaRepository $zpravaRepo,
        ChatPrecteniRepository $precteniRepo,
        CentrifugoService $centrifugo,
        UserRepository $userRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if (!$konverzace->maClen($user)) {
            throw $this->createAccessDeniedException('Nemáš přístup do této konverzace.');
        }

        // Mark this conversation as read
        $precteniRepo->markAsRead($konverzace, $user);

        $zpravy = $zpravaRepo->findByKonverzace($konverzace);
        $konverzaceList = $repo->findByUser($user);
        $vsichniUzivatele = $userRepo->findBy(['isActive' => true]);
        $unreadCounts = $precteniRepo->countUnreadByConversations($konverzaceList, $user);
        $channel = $centrifugo->channelForKonverzace($konverzace->getId());

        $userChannel = $centrifugo->channelForUser($user->getId());

        return $this->render('chat/index.html.twig', [
            'konverzace' => $konverzaceList,
            'aktivniKonverzace' => $konverzace,
            'zpravy' => $zpravy,
            'vsichniUzivatele' => $vsichniUzivatele,
            'unreadCounts' => $unreadCounts,
            'connectionToken' => $centrifugo->generateConnectionToken($user),
            'subscriptionToken' => $centrifugo->generateSubscriptionToken($user, $channel),
            'userSubscriptionToken' => $centrifugo->generateSubscriptionToken($user, $userChannel),
            'wsUrl' => $this->wsUrl,
            'firebaseConfig' => [
                'apiKey'            => $this->fbApiKey,
                'authDomain'        => $this->fbAuthDomain,
                'projectId'         => $this->fbProjectId,
                'storageBucket'     => $this->fbStorageBucket,
                'messagingSenderId' => $this->fbMessagingSenderId,
                'appId'             => $this->fbAppId,
                'vapidKey'          => $this->fbVapidKey,
            ],
        ]);
    }

    #[Route('/direct/{userId}', name: 'app_chat_direct', requirements: ['userId' => '\d+'], methods: ['POST'])]
    public function directChat(
        int $userId,
        UserRepository $userRepo,
        ChatKonverzaceRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $target = $userRepo->find($userId);

        if (!$target || $target->getId() === $user->getId()) {
            throw $this->createNotFoundException();
        }

        $konverzace = $repo->findPrimyChat($user, $target);

        if (!$konverzace) {
            $konverzace = (new ChatKonverzace())
                ->setTyp(ChatKonverzace::TYP_PRIMY)
                ->setVytvoril($user)
                ->addClen($user)
                ->addClen($target);

            $em->persist($konverzace);
            $em->flush();
        }

        return $this->redirectToRoute('app_chat_show', ['id' => $konverzace->getId()]);
    }

    #[Route('/zakazka/{zakazkaId}', name: 'app_chat_zakazka', requirements: ['zakazkaId' => '\d+'], methods: ['POST'])]
    public function zakazkaChat(
        int $zakazkaId,
        ZakazkaRepository $zakazkaRepo,
        ChatKonverzaceRepository $repo,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $zakazka = $zakazkaRepo->find($zakazkaId);

        if (!$zakazka || !$zakazka->hasUserAccess($user)) {
            throw $this->createAccessDeniedException();
        }

        $konverzace = $repo->findZakazkaChat($zakazka);

        if (!$konverzace) {
            $konverzace = (new ChatKonverzace())
                ->setTyp(ChatKonverzace::TYP_ZAKAZKA)
                ->setZakazka($zakazka)
                ->setNazev($zakazka->getName())
                ->setVytvoril($user);

            $konverzace->addClen($zakazka->getCreatedBy());
            foreach ($zakazka->getAssignedUsers() as $assignedUser) {
                $konverzace->addClen($assignedUser);
            }

            $em->persist($konverzace);
            $em->flush();
        }

        return $this->redirectToRoute('app_chat_show', ['id' => $konverzace->getId()]);
    }

    #[Route('/skupina', name: 'app_chat_skupina', methods: ['POST'])]
    public function skupinaChat(
        Request $request,
        UserRepository $userRepo,
        EntityManagerInterface $em,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $nazev = trim($request->request->getString('nazev'));
        $clenoveIds = $request->request->all('clenove');

        if (!$nazev) {
            $this->addFlash('danger', 'Název skupiny je povinný.');
            return $this->redirectToRoute('app_chat_index');
        }

        $konverzace = (new ChatKonverzace())
            ->setTyp(ChatKonverzace::TYP_SKUPINA)
            ->setNazev($nazev)
            ->setVytvoril($user)
            ->addClen($user);

        foreach ($clenoveIds as $id) {
            $clen = $userRepo->find((int) $id);
            if ($clen && $clen->getId() !== $user->getId()) {
                $konverzace->addClen($clen);
            }
        }

        $em->persist($konverzace);
        $em->flush();

        return $this->redirectToRoute('app_chat_show', ['id' => $konverzace->getId()]);
    }

    #[Route('/{id}/zpravy', name: 'app_chat_zpravy_older', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function zpravyOlder(
        ChatKonverzace $konverzace,
        Request $request,
        ChatZpravaRepository $zpravaRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$konverzace->maClen($user)) {
            return new JsonResponse(['error' => 'Přístup odepřen'], 403);
        }

        $beforeId = $request->query->getInt('before');
        if (!$beforeId) {
            return new JsonResponse(['error' => 'Chybí parametr before'], 400);
        }

        $zpravy = $zpravaRepo->findOlderThan($konverzace, $beforeId, 20);

        return new JsonResponse([
            'zpravy'  => array_map(fn($z) => $z->toArray(), $zpravy),
            'hasMore' => count($zpravy) >= 20,
        ]);
    }

    #[Route('/{id}/zprava', name: 'app_chat_zprava', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function zprava(
        ChatKonverzace $konverzace,
        Request $request,
        ChatZpravaRepository $zpravaRepo,
        EntityManagerInterface $em,
        CentrifugoService $centrifugo,
        NotificationService $notificationService,
        #[Autowire('%kernel.project_dir%/var/chat_attachments')] string $uploadDir,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$konverzace->maClen($user)) {
            return new JsonResponse(['error' => 'Přístup odepřen'], 403);
        }

        // Support both JSON and multipart (file upload)
        $isMultipart = str_contains($request->headers->get('Content-Type', ''), 'multipart');

        if (!$this->isCsrfTokenValid('chat', $isMultipart
            ? $request->request->get('_csrf_token')
            : $request->headers->get('X-Csrf-Token')
        )) {
            return new JsonResponse(['error' => 'Neplatný CSRF token'], 403);
        }

        if ($isMultipart) {
            $obsah = trim($request->request->getString('obsah'));
            $replyToId = $request->request->get('replyTo');
        } else {
            $data = json_decode($request->getContent(), true);
            $obsah = trim($data['obsah'] ?? '');
            $replyToId = $data['replyTo'] ?? null;
        }

        $uploadedFile = $request->files->get('file');

        if (!$obsah && !$uploadedFile) {
            return new JsonResponse(['error' => 'Prázdná zpráva'], 400);
        }

        $zprava = (new ChatZprava())
            ->setObsah($obsah ?: '')
            ->setKonverzace($konverzace)
            ->setAutor($user);

        if (!empty($replyToId)) {
            $replyZprava = $zpravaRepo->find((int) $replyToId);
            if ($replyZprava && $replyZprava->getKonverzace()->getId() === $konverzace->getId()) {
                $zprava->setReplyTo($replyZprava);
            }
        }

        if ($uploadedFile) {
            $allowedMimes = [
                'application/pdf', 'image/jpeg', 'image/png',
                'application/zip', 'application/x-zip-compressed',
                'text/csv', 'application/xml', 'text/xml',
                'application/json', 'text/json',
            ];
            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'zip', 'csv', 'xml', 'json'];

            $ext = strtolower($uploadedFile->getClientOriginalExtension());
            $mime = $uploadedFile->getMimeType();

            if (!in_array($ext, $allowedExtensions) || !in_array($mime, $allowedMimes)) {
                return new JsonResponse(['error' => 'Nepodporovaný typ souboru'], 400);
            }

            if ($uploadedFile->getSize() > 20 * 1024 * 1024) {
                return new JsonResponse(['error' => 'Soubor je příliš velký (max 20 MB)'], 400);
            }

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $originalName = $uploadedFile->getClientOriginalName();
            $fileSize = $uploadedFile->getSize();
            $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadedFile->move($uploadDir, $storedName);

            $priloha = new ChatPriloha(
                $zprava,
                $originalName,
                $storedName,
                $mime,
                $fileSize,
            );
            $zprava->getPrilohy()->add($priloha);
            $em->persist($priloha);
        }

        $konverzace->setPosledniZpravaAt(new \DateTimeImmutable());

        $em->persist($zprava);
        $em->flush();

        $channel = $centrifugo->channelForKonverzace($konverzace->getId());
        $centrifugo->publish($channel, [
            'type' => 'message',
            'message' => $zprava->toArray(),
        ]);

        $notification = [
            'type' => 'notification',
            'konverzaceId' => $konverzace->getId(),
            'message' => $zprava->toArray(),
        ];
        foreach ($konverzace->getClenove() as $clen) {
            $centrifugo->publish($centrifugo->channelForUser($clen->getId()), $notification);
        }

        // In-app notifikace + Firebase push
        $isPrimy = $konverzace->getTyp() === ChatKonverzace::TYP_PRIMY;
        $notificationService->notify(
            recipients:   $konverzace->getClenove()->toArray(),
            title:        $isPrimy ? $user->getDisplayName() : ($konverzace->getNazev() ?? $konverzace->getZakazka()?->getName() ?? $user->getDisplayName()),
            body:         $isPrimy ? ($obsah ?: '📎 Příloha') : $user->getDisplayName() . ': ' . ($obsah ?: '📎 Příloha'),
            link:         '/chat/' . $konverzace->getId(),
            resourceType: 'konverzace',
            resourceId:   $konverzace->getId(),
            collapseKey:  'chat-conv-' . $konverzace->getId(),
            excludeUser:  $user,
        );

        return new JsonResponse(['ok' => true, 'id' => $zprava->getId()]);
    }

    #[Route('/priloha/{id}', name: 'app_chat_priloha', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadPriloha(
        ChatPriloha $priloha,
        #[Autowire('%kernel.project_dir%/var/chat_attachments')] string $uploadDir,
    ): BinaryFileResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$priloha->getZprava()->getKonverzace()->maClen($user)) {
            throw $this->createAccessDeniedException();
        }

        $filePath = $uploadDir . '/' . $priloha->getStoredName();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('Soubor nenalezen.');
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $priloha->getOriginalName(),
        );
        $response->headers->set('Content-Type', $priloha->getMimeType());

        return $response;
    }

    #[Route('/zprava/{id}/reakce', name: 'app_chat_reakce', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reakce(
        ChatZprava $zprava,
        Request $request,
        EntityManagerInterface $em,
        CentrifugoService $centrifugo,
        NotificationService $notificationService,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $konverzace = $zprava->getKonverzace();

        if (!$konverzace->maClen($user)) {
            return new JsonResponse(['error' => 'Přístup odepřen'], 403);
        }

        $data  = json_decode($request->getContent(), true);
        $emoji = $data['emoji'] ?? '';

        $allowed = ['👍', '❤️', '😂', '😮', '😢', '😡'];
        if (!\in_array($emoji, $allowed, true)) {
            return new JsonResponse(['error' => 'Nepodporovaný emoji'], 400);
        }

        // Toggle: remove if already reacted, add if not
        $existing = null;
        foreach ($zprava->getReakce() as $r) {
            if ($r->getUser()->getId() === $user->getId() && $r->getEmoji() === $emoji) {
                $existing = $r;
                break;
            }
        }

        if ($existing) {
            $zprava->getReakce()->removeElement($existing);
            $em->remove($existing);
        } else {
            $reakce = new ChatReakce($zprava, $user, $emoji);
            $zprava->getReakce()->add($reakce);
            $em->persist($reakce);
        }

        $em->flush();

        $grouped = $zprava->getGroupedReakce();
        $centrifugo->publish($centrifugo->channelForKonverzace($konverzace->getId()), [
            'type'     => 'reaction',
            'zpravaId' => $zprava->getId(),
            'reakce'   => $grouped,
        ]);

        // Notifikovat autora zprávy (pokud nereagoval sám na sebe)
        $zprava_autor = $zprava->getAutor();
        if ($zprava_autor->getId() !== $user->getId() && !$existing) {
            $notificationService->notify(
                recipients:   [$zprava_autor],
                title:        $user->getDisplayName() . ' reagoval/a',
                body:         $emoji . ' na vaši zprávu',
                link:         '/chat/' . $konverzace->getId(),
                resourceType: 'konverzace',
                resourceId:   $konverzace->getId(),
                collapseKey:  'chat-reaction-' . $konverzace->getId(),
            );
        }

        return new JsonResponse(['ok' => true, 'reakce' => $grouped]);
    }

    #[Route('/{id}/prilohy', name: 'app_chat_prilohy', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function prilohy(
        ChatKonverzace $konverzace,
        ChatPrilohaRepository $prilohaRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$konverzace->maClen($user)) {
            return new JsonResponse(['error' => 'Přístup odepřen'], 403);
        }

        return new JsonResponse(array_map(fn($p) => [
            'id'           => $p->getId(),
            'originalName' => $p->getOriginalName(),
            'mimeType'     => $p->getMimeType(),
            'size'         => $p->getSize(),
            'createdAt'    => $p->getCreatedAt()->format('c'),
            'autorName'    => $p->getZprava()->getAutor()->getDisplayName(),
        ], $prilohaRepo->findByKonverzace($konverzace)));
    }

    #[Route('/{id}/pin', name: 'app_chat_pin', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pin(
        ChatKonverzace $konverzace,
        Request $request,
        EntityManagerInterface $em,
        CentrifugoService $centrifugo,
        ChatZpravaRepository $zpravaRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$konverzace->maClen($user)) {
            return new JsonResponse(['error' => 'Přístup odepřen'], 403);
        }

        $data     = json_decode($request->getContent(), true);
        $zpravaId = (int) ($data['zpravaId'] ?? 0);

        $current = $konverzace->getPinnedZprava();
        if ($current && $current->getId() === $zpravaId) {
            $konverzace->setPinnedZprava(null);
            $payload = null;
        } else {
            $zprava = $zpravaRepo->find($zpravaId);
            if (!$zprava || $zprava->getKonverzace()->getId() !== $konverzace->getId()) {
                return new JsonResponse(['error' => 'Zpráva nenalezena'], 404);
            }
            $konverzace->setPinnedZprava($zprava);
            $payload = [
                'id'        => $zprava->getId(),
                'obsah'     => mb_substr($zprava->getObsah(), 0, 120),
                'autorName' => $zprava->getAutor()->getDisplayName(),
            ];
        }

        $em->flush();

        $centrifugo->publish($centrifugo->channelForKonverzace($konverzace->getId()), [
            'type'   => 'pin',
            'zprava' => $payload,
        ]);

        return new JsonResponse(['ok' => true, 'zprava' => $payload]);
    }

    #[Route('/{id}/typing', name: 'app_chat_typing', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function typing(
        ChatKonverzace $konverzace,
        CentrifugoService $centrifugo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$konverzace->maClen($user)) {
            return new JsonResponse(['error' => 'Přístup odepřen'], 403);
        }

        $channel = $centrifugo->channelForKonverzace($konverzace->getId());
        $centrifugo->publish($channel, [
            'type' => 'typing',
            'userId' => $user->getId(),
            'name' => $user->getDisplayName(),
        ]);

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/{id}/precteno', name: 'app_chat_precteno', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function precteno(
        ChatKonverzace $konverzace,
        ChatPrecteniRepository $precteniRepo,
        NotificationRepository $notificationRepo,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$konverzace->maClen($user)) {
            return new JsonResponse(['error' => 'Přístup odepřen'], 403);
        }

        $precteniRepo->markAsRead($konverzace, $user);
        $notificationRepo->markReadByUserAndResource($user, 'konverzace', $konverzace->getId());

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/fcm-token', name: 'app_chat_fcm_token', methods: ['POST'])]
    public function saveFcmToken(
        Request $request,
        UserFcmTokenRepository $tokenRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        /** @var User $user */
        $user  = $this->getUser();
        $data  = json_decode($request->getContent(), true);
        $token = trim($data['token'] ?? '');

        if (!$token) {
            return new JsonResponse(['error' => 'Chybí token'], 400);
        }

        $existing = $tokenRepo->findByUserAndToken($user, $token);
        if ($existing) {
            $existing->touchLastUsed();
        } else {
            $entry = new UserFcmToken($user, $token, 'web');
            $em->persist($entry);
        }
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/firebase-messaging-sw.js', name: 'app_firebase_sw', methods: ['GET'])]
    public function firebaseServiceWorker(): Response
    {
        $config = json_encode([
            'apiKey'            => $this->fbApiKey,
            'authDomain'        => $this->fbAuthDomain,
            'projectId'         => $this->fbProjectId,
            'storageBucket'     => $this->fbStorageBucket,
            'messagingSenderId' => $this->fbMessagingSenderId,
            'appId'             => $this->fbAppId,
        ], JSON_UNESCAPED_SLASHES);

        $js = <<<JS
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js');

firebase.initializeApp({$config});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const { title, body, icon } = payload.notification ?? {};
    const data = payload.data ?? {};

    // If chat is already open for this conversation, skip the notification
    return self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
        const chatOpen = clients.some((c) => {
            const url = new URL(c.url);
            return url.pathname === (data.url ?? '/chat') && c.visibilityState === 'visible';
        });
        if (chatOpen) return;

        return self.registration.showNotification(title ?? 'BeTechnik', {
            body: body ?? '',
            icon: icon ?? '/favicon.ico',
            tag:  data.collapseKey ?? 'default',
            data: { url: data.url ?? '/chat' },
            requireInteraction: false,
        });
    });
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? '/chat';
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
            for (const client of clients) {
                if (new URL(client.url).pathname.startsWith('/chat') && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return self.clients.openWindow(url);
        })
    );
});
JS;

        return new Response($js, 200, [
            'Content-Type'           => 'application/javascript',
            'Cache-Control'          => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }
}
