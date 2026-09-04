<?php

declare(strict_types=1);

namespace App\Modules\Licensing\Services;

use Exception;

class LicenseService
{
    /**
     * Sinh cặp khóa Ed25519 (Tác giả giữ Private Key, Public Key nhúng vào Go và Laravel)
     */
    public function generateKeyPair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $publicKey = sodium_crypto_sign_publickey($keypair);
        $secretKey = sodium_crypto_sign_secretkey($keypair);

        return [
            'public_key' => bin2hex($publicKey),
            'private_key' => bin2hex($secretKey),
        ];
    }

    /**
     * Tác giả tạo tệp bản quyền duy nhất smart-hr.lic cho khách hàng
     */
    public function createLicense(
        string $domain,
        string $customerName,
        string $expiresAt,
        string $privateKeyHex
    ): string {
        $privateKey = hex2bin($privateKeyHex);

        $payloadData = [
            'allowed_domain' => strtolower(trim($domain)),
            'customer_name' => trim($customerName),
            'expires_at' => $expiresAt,
            'shared_hmac_secret' => bin2hex(random_bytes(32)),
            'issued_at' => date('Y-m-d H:i:s'),
        ];

        $payloadJson = json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = sodium_crypto_sign_detached($payloadJson, $privateKey);

        $licenseStructure = [
            'format' => 'SMARTHR-LIC-V1',
            'payload' => base64_encode($payloadJson),
            'signature' => bin2hex($signature),
        ];

        return json_encode($licenseStructure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Cả Go Daemon và Laravel dùng Public Key xác thực file smart-hr.lic
     */
    public function verifyLicense(string $licenseContent, string $publicKeyHex): ?array
    {
        try {
            $data = json_decode($licenseContent, true);
            if (!isset($data['payload'], $data['signature'])) {
                return null;
            }

            $payloadJson = base64_decode($data['payload']);
            $signature = hex2bin($data['signature']);
            $publicKey = hex2bin($publicKeyHex);

            $isValid = sodium_crypto_sign_verify_detached($signature, $payloadJson, $publicKey);
            if (!$isValid) {
                return null;
            }

            $payload = json_decode($payloadJson, true);
            
            // Kiểm tra hạn sử dụng
            if (isset($payload['expires_at']) && strtotime($payload['expires_at']) < time()) {
                $payload['is_expired'] = true;
            } else {
                $payload['is_expired'] = false;
            }

            return $payload;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Dẫn xuất khóa mã hóa CSDL (DEK) từ License Secret + Domain bằng HKDF-SHA256
     */
    public function deriveDataEncryptionKey(string $sharedHmacSecret, string $domain): string
    {
        return hash_hkdf('sha256', $sharedHmacSecret, 32, 'smart-hr-database-anchoring', strtolower(trim($domain)));
    }

    /**
     * Mã hóa trường nhạy cảm (Lương, CCCD) bằng AES-256-GCM
     */
    public function encryptField(string $plaintext, string $dek): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $dek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Giải mã trường nhạy cảm bằng AES-256-GCM
     */
    public function decryptField(string $encryptedPayload, string $dek): ?string
    {
        try {
            $data = base64_decode($encryptedPayload);
            if (strlen($data) < 28) {
                return null;
            }

            $iv = substr($data, 0, 12);
            $tag = substr($data, 12, 16);
            $ciphertext = substr($data, 28);

            $decrypted = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $dek,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            return $decrypted !== false ? $decrypted : null;
        } catch (Exception) {
            return null;
        }
    }
}
