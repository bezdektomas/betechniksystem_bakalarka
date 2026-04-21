<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CentrifugoService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'CENTRIFUGO_API_URL')] private readonly string $centrifugoUrl,
        #[Autowire(env: 'CENTRIFUGO_API_KEY')] private readonly string $apiKey,
        #[Autowire(env: 'CENTRIFUGO_TOKEN_HMAC_SECRET_KEY')] private readonly string $hmacSecret,
    ) {}

    public function publish(string $channel, array $data): void
    {
        $this->httpClient->request('POST', $this->centrifugoUrl . '/api/publish', [
            'headers' => [
                'Authorization' => 'apikey ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'channel' => $channel,
                'data' => $data,
            ],
        ]);
    }

    /**
     * JWT pro navázání WebSocket připojení (identifikuje uživatele).
     */
    public function generateConnectionToken(User $user): string
    {
        return $this->createJwt([
            'sub' => (string) $user->getId(),
            'exp' => time() + 3600,
            'info' => [
                'name' => $user->getDisplayName(),
            ],
        ]);
    }

    /**
     * JWT, který opravňuje k odběru jednoho konkrétního kanálu.
     */
    public function generateSubscriptionToken(User $user, string $channel): string
    {
        return $this->createJwt([
            'sub' => (string) $user->getId(),
            'exp' => time() + 3600,
            'channel' => $channel,
        ]);
    }

    public function channelForKonverzace(int $konverzaceId): string
    {
        return 'chat:konverzace_' . $konverzaceId;
    }

    public function channelForUser(int $userId): string
    {
        return 'chat:user_' . $userId;
    }

    private function createJwt(array $payload): string
    {
        $header = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body   = $this->base64url(json_encode($payload));
        $sig    = $this->base64url(hash_hmac('sha256', $header . '.' . $body, $this->hmacSecret, true));

        return $header . '.' . $body . '.' . $sig;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
