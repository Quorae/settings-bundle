<?php

declare(strict_types=1);

namespace Quorae\SettingsBundle\Service;

use Quorae\SettingsBundle\Exception\DecryptException;

final readonly class SettingCryptor
{
    private string $key;

    public function __construct(
        #[\SensitiveParameter] string $appSecret,
        string $hkdfInfo = 'quorae-settings-encryption',
    ) {
        $this->key = hash_hkdf('sha256', $appSecret, \SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $hkdfInfo);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return $this->base64UrlEncode($nonce.$ciphertext);
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = $this->base64UrlDecode($ciphertext);
        if (null === $decoded) {
            throw new DecryptException('Settings cryptor: payload is not valid base64url.');
        }

        $nonceLength = \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (\strlen($decoded) < $nonceLength + 1) {
            throw new DecryptException('Settings cryptor: payload too short to contain a nonce + ciphertext.');
        }

        $nonce = substr($decoded, 0, $nonceLength);
        $cipher = substr($decoded, $nonceLength);

        try {
            $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        } catch (\SodiumException $e) {
            throw new DecryptException('Settings cryptor: sodium failure during decryption.', previous: $e);
        }

        if (false === $plaintext) {
            throw new DecryptException('Settings cryptor: authentication failed (tampered ciphertext or wrong secret).');
        }

        return $plaintext;
    }

    private function base64UrlEncode(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $encoded): ?string
    {
        $remainder = \strlen($encoded) % 4;
        if ($remainder > 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);

        return false === $decoded ? null : $decoded;
    }
}
