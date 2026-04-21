<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserFcmTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\WebPushConfig;
use Psr\Log\LoggerInterface;

class FirebaseNotificationService
{
    private ?\Kreait\Firebase\Contract\Messaging $messaging = null;

    public function __construct(
        private readonly string $serviceAccountPath,
        private readonly UserFcmTokenRepository $tokenRepo,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    private function getMessaging(): ?\Kreait\Firebase\Contract\Messaging
    {
        if ($this->messaging !== null) {
            return $this->messaging;
        }
        if (!file_exists($this->serviceAccountPath)) {
            $this->logger->warning('Firebase service account not found: ' . $this->serviceAccountPath);
            return null;
        }
        try {
            $factory = (new Factory())->withServiceAccount($this->serviceAccountPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Throwable $e) {
            $this->logger->error('Firebase init failed: ' . $e->getMessage());
            return null;
        }
        return $this->messaging;
    }

    /**
     * Odešle push notifikaci seznamu uživatelů (s výjimkou $excludeUser).
     * Používá collapse_key, aby offline zařízení obdržela pro každou konverzaci pouze nejnovější notifikaci.
     *
     * @param User[] $recipients
     */
    public function notifyUsers(
        array $recipients,
        string $title,
        string $body,
        array $data = [],
        string $collapseKey = '',
        ?User $excludeUser = null,
    ): void {
        $messaging = $this->getMessaging();
        if (!$messaging) {
            $this->logger->warning('FCM: messaging not available');
            return;
        }

        $targets = array_filter($recipients, fn(User $u) => !$excludeUser || $u->getId() !== $excludeUser->getId());
        $this->logger->info('FCM: targets=' . count($targets) . ' excludeUser=' . ($excludeUser?->getId() ?? 'none'));
        if (empty($targets)) return;

        $tokens = $this->tokenRepo->findTokensByUsers(array_values($targets));
        $this->logger->info('FCM: tokens found=' . count($tokens));
        if (empty($tokens)) return;

        $tag = $collapseKey ?: 'default';

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData(array_map('strval', $data))
            ->withAndroidConfig(AndroidConfig::fromArray([
                'collapse_key' => $tag,
                'notification'  => ['sound' => 'default'],
            ]))
            ->withWebPushConfig(WebPushConfig::fromArray([
                'notification' => [
                    'tag'  => $tag,
                    'icon' => '/img/icons/icon-192x192.png',
                ],
                'fcm_options' => ['link' => $data['url'] ?? '/chat'],
            ]));

        try {
            $report = $messaging->sendMulticast($message, $tokens);
            $this->logger->info('FCM: sent ok=' . $report->successes()->count() . ' failed=' . $report->failures()->count());

            foreach ($report->failures() as $failure) {
                $this->logger->info('FCM token failed: ' . $failure->error()->getMessage());
                if (str_contains($failure->error()->getMessage(), 'NotRegistered')
                    || str_contains($failure->error()->getMessage(), 'InvalidRegistration')) {
                    $invalid = $this->tokenRepo->findOneBy(['token' => $failure->target()->value()]);
                    if ($invalid) {
                        $this->em->remove($invalid);
                        $this->em->flush();
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('FCM send failed: ' . $e->getMessage());
        }
    }
}
