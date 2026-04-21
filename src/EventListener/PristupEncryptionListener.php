<?php

namespace App\EventListener;

use App\Entity\Pristup;
use App\Service\EncryptionService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Pristup::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Pristup::class)]
#[AsEntityListener(event: Events::postLoad, method: 'postLoad', entity: Pristup::class)]
class PristupEncryptionListener
{
    public function __construct(
        private EncryptionService $encryptionService,
    ) {}

    /**
     * Zašifruje data před prvním uložením
     */
    public function prePersist(Pristup $pristup, PrePersistEventArgs $event): void
    {
        $this->encryptFields($pristup);
    }

    /**
     * Zašifruje data před aktualizací
     */
    public function preUpdate(Pristup $pristup, PreUpdateEventArgs $event): void
    {
        // Zjistíme, která pole se změnila, a zašifrujeme je
        $changeSet = $event->getEntityChangeSet();
        
        if (isset($changeSet['username'])) {
            $pristup->setUsernameRaw($this->encryptionService->encrypt($pristup->getUsernameRaw()));
        }
        if (isset($changeSet['password'])) {
            $pristup->setPasswordRaw($this->encryptionService->encrypt($pristup->getPasswordRaw()));
        }
        if (isset($changeSet['url'])) {
            $pristup->setUrlRaw($this->encryptionService->encrypt($pristup->getUrlRaw()));
        }
        if (isset($changeSet['popis'])) {
            $pristup->setPopisRaw($this->encryptionService->encrypt($pristup->getPopisRaw()));
        }
    }

    /**
     * Dešifruje data po načtení z databáze
     */
    public function postLoad(Pristup $pristup, PostLoadEventArgs $event): void
    {
        $this->decryptFields($pristup);
    }

    private function encryptFields(Pristup $pristup): void
    {
        if ($pristup->getUsernameRaw()) {
            $pristup->setUsernameRaw($this->encryptionService->encrypt($pristup->getUsernameRaw()));
        }
        if ($pristup->getPasswordRaw()) {
            $pristup->setPasswordRaw($this->encryptionService->encrypt($pristup->getPasswordRaw()));
        }
        if ($pristup->getUrlRaw()) {
            $pristup->setUrlRaw($this->encryptionService->encrypt($pristup->getUrlRaw()));
        }
        if ($pristup->getPopisRaw()) {
            $pristup->setPopisRaw($this->encryptionService->encrypt($pristup->getPopisRaw()));
        }
    }

    private function decryptFields(Pristup $pristup): void
    {
        if ($pristup->getUsernameRaw()) {
            $pristup->setUsernameRaw($this->encryptionService->decrypt($pristup->getUsernameRaw()));
        }
        if ($pristup->getPasswordRaw()) {
            $pristup->setPasswordRaw($this->encryptionService->decrypt($pristup->getPasswordRaw()));
        }
        if ($pristup->getUrlRaw()) {
            $pristup->setUrlRaw($this->encryptionService->decrypt($pristup->getUrlRaw()));
        }
        if ($pristup->getPopisRaw()) {
            $pristup->setPopisRaw($this->encryptionService->decrypt($pristup->getPopisRaw()));
        }
    }
}
