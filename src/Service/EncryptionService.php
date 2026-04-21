<?php

namespace App\Service;

class EncryptionService
{
    private const CIPHER = 'aes-256-cbc';
    private string $key;

    public function __construct(string $encryptionKey)
    {
        // Klíč musí mít přesně 32 bajtů pro AES-256
        $this->key = hash('sha256', $encryptionKey, true);
    }

    /**
     * Zašifruje text
     */
    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Kombinujeme IV + šifrovaná data a zakódujeme do base64
        return base64_encode($iv . $encrypted);
    }

    /**
     * Dešifruje text
     */
    public function decrypt(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return $ciphertext;
        }

        $data = base64_decode($ciphertext, true);
        if ($data === false) {
            return $ciphertext;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        
        if (strlen($data) <= $ivLength) {
            return $ciphertext;
        }

        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);

        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            // Pokud dešifrování selže, vrátíme původní hodnotu (zpětná kompatibilita)
            return $ciphertext;
        }

        return $decrypted;
    }
}
