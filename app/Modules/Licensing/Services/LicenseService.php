<?php

declare(strict_types=1);

namespace App\Modules\Licensing\Services;

use Exception;
use Illuminate\Support\Facades\File;

class LicenseService
{
    /**
     * Khóa công khai của Tác giả (Author Ed25519 Public Key) được nhúng cứng vào mã nguồn lõi.
     * Khách hàng dù có toàn bộ mã nguồn cũng không thể tự sinh chữ ký hợp lệ nếu không có Private Key.
     */
    public const AUTHOR_PUBLIC_KEY = 'd26114cc88643c69ae8534b63442f35808b6d59cc02da54d8dbbe49ebd63ee80';

    protected ?array $cachedLicense = null;

    /**
     * Xóa bộ nhớ đệm giấy phép
     */
    public function clearCache(): void
    {
        $this->cachedLicense = null;
    }

    /**
     * Tác giả tạo tệp bản quyền duy nhất smart-hr.lic cho khách hàng (dành cho kiểm thử nội bộ/test fixtures)
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
     * Xác thực tính toàn vẹn của tệp giấy phép bằng Public Key Ed25519
     */
    public function verifyLicense(string $licenseContent, ?string $publicKeyHex = null): ?array
    {
        try {
            // Hằng số AUTHOR_PUBLIC_KEY là nguồn chân lý bất biến (Source of Truth)
            // Chỉ cho phép ghi đè qua config trong môi trường kiểm thử tự động (testing)
            $publicKeyHex = $publicKeyHex 
                ?? (app()->environment('testing') ? config('licensing.author_public_key') : null)
                ?? self::AUTHOR_PUBLIC_KEY;

            $data = json_decode($licenseContent, true);
            if (!isset($data['payload'], $data['signature']) || ($data['format'] ?? '') !== 'SMARTHR-LIC-V1') {
                return null;
            }

            $payloadJson = base64_decode($data['payload']);
            $signature = hex2bin($data['signature']);
            $publicKey = hex2bin($publicKeyHex);

            if (!$signature || !$publicKey || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                return null;
            }

            $isValid = sodium_crypto_sign_verify_detached($signature, $payloadJson, $publicKey);
            if (!$isValid) {
                return null;
            }

            $payload = json_decode($payloadJson, true);
            if (!is_array($payload)) {
                return null;
            }

            $now = time();
            $expiresTime = isset($payload['expires_at']) ? strtotime($payload['expires_at'] . ' 23:59:59') : 0;
            $payload['is_expired'] = ($expiresTime > 0 && $expiresTime < $now);
            $payload['days_remaining'] = $expiresTime > $now ? (int)ceil(($expiresTime - $now) / 86400) : 0;
            $payload['raw_signature'] = $data['signature'];
            $payload['format'] = $data['format'];

            return $payload;
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Đọc và xác thực giấy phép từ các vị trí lưu trữ tiêu chuẩn
     */
    public function loadLicense(?string $customPath = null): ?array
    {
        if ($this->cachedLicense !== null && $customPath === null) {
            return $this->cachedLicense;
        }

        $candidates = array_filter([
            $customPath,
            config('licensing.license_path', base_path('smart-hr.lic')),
            config('licensing.storage_license_path', storage_path('license/smart-hr.lic')),
        ]);

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                $content = File::get($path);
                $verified = $this->verifyLicense($content);
                if ($verified !== null) {
                    $this->cachedLicense = $verified;
                    return $verified;
                }
            }
        }

        return null;
    }

    /**
     * Lấy thông tin bản quyền hiện tại phục vụ giao diện và bảo mật
     */
    public function getActiveLicense(): array
    {
        $license = $this->loadLicense();

        if (!$license) {
            return [
                'is_valid' => false,
                'status' => 'NOT_INSTALLED',
                'customer_name' => 'Chưa đăng ký bản quyền',
                'allowed_domain' => 'None',
                'expires_at' => null,
                'is_expired' => true,
                'days_remaining' => 0,
                'shared_hmac_secret' => null,
                'shared_hmac_secret_masked' => null,
                'signature_preview' => null,
            ];
        }

        $secret = $license['shared_hmac_secret'] ?? '';
        $maskedSecret = strlen($secret) > 16 
            ? substr($secret, 0, 8) . '••••••••••••••••' . substr($secret, -8)
            : '••••••••••••••••';

        $sig = $license['raw_signature'] ?? '';
        $sigPreview = strlen($sig) > 20
            ? substr($sig, 0, 10) . '...' . substr($sig, -10)
            : $sig;

        return [
            'is_valid' => !$license['is_expired'],
            'status' => $license['is_expired'] ? 'EXPIRED' : 'ACTIVE',
            'customer_name' => $license['customer_name'] ?? 'Doanh nghiệp Smart-HR',
            'allowed_domain' => $license['allowed_domain'] ?? '',
            'expires_at' => $license['expires_at'] ?? null,
            'is_expired' => $license['is_expired'],
            'days_remaining' => $license['days_remaining'],
            'issued_at' => $license['issued_at'] ?? null,
            'shared_hmac_secret' => $secret,
            'shared_hmac_secret_masked' => $maskedSecret,
            'signature_preview' => $sigPreview,
            'raw_signature' => $sig,
        ];
    }

    /**
     * Dẫn xuất khóa mã hóa CSDL (DEK) từ Signature/Secret + Domain bằng HKDF-SHA256
     */
    public function deriveDataEncryptionKey(string $keyMaterial, string $domain): string
    {
        $ikm = (ctype_xdigit($keyMaterial) && strlen($keyMaterial) % 2 === 0) 
            ? (hex2bin($keyMaterial) ?: $keyMaterial) 
            : $keyMaterial;

        $salt = strtolower(trim($domain));
        $info = config('licensing.dek_hkdf_info', 'SmartHR_DEK_v1');

        return hash_hkdf('sha256', $ikm, 32, $info, $salt);
    }

    /**
     * Lấy khóa DEK hiện hành dựa trên giấy phép hợp lệ và tên miền hệ thống
     */
    public function getSystemDek(?string $domain = null): ?string
    {
        $license = $this->loadLicense();
        if (!$license || ($license['is_expired'] ?? true)) {
            return null;
        }

        $targetDomain = $domain 
            ?? config('licensing.domain_override')
            ?? ($license['allowed_domain'] ?? 'smart-hr.test');

        // Móc neo bằng chữ ký số Ed25519
        $keyMaterial = $license['raw_signature'] ?? $license['shared_hmac_secret'] ?? '';
        if (empty($keyMaterial)) {
            return null;
        }

        return $this->deriveDataEncryptionKey($keyMaterial, $targetDomain);
    }

    /**
     * Mã hóa trường nhạy cảm bằng AES-256-GCM
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
            $data = base64_decode($encryptedPayload, true);
            if ($data === false || strlen($data) < 28) {
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

    /**
     * Mã hóa CCCD nhân viên với DEK tự động dẫn xuất từ License
     */
    public function encryptCitizenId(string $citizenId, ?string $domain = null): string
    {
        $dek = $this->getSystemDek($domain);
        if (!$dek) {
            $salt = strtolower(trim($domain ?? 'fallback_domain'));
            $dek = hash_hkdf('sha256', (string)config('app.key'), 32, 'SmartHR_Fallback', $salt);
        }

        return $this->encryptField($citizenId, $dek);
    }

    /**
     * Giải mã CCCD nhân viên
     */
    public function decryptCitizenId(string $encryptedPayload, ?string $domain = null): ?string
    {
        $dek = $this->getSystemDek($domain);
        if (!$dek) {
            $salt = strtolower(trim($domain ?? 'fallback_domain'));
            $dek = hash_hkdf('sha256', (string)config('app.key'), 32, 'SmartHR_Fallback', $salt);
        }

        return $this->decryptField($encryptedPayload, $dek);
    }

    /**
     * Mã hóa thông tin chi tiết lương (Base salary, allowances, breakdown)
     */
    public function encryptSalaryDetails(array $salaryDetails, ?string $domain = null): string
    {
        $dek = $this->getSystemDek($domain);
        if (!$dek) {
            $salt = strtolower(trim($domain ?? 'fallback_domain'));
            $dek = hash_hkdf('sha256', (string)config('app.key'), 32, 'SmartHR_Fallback', $salt);
        }

        $json = json_encode($salaryDetails, JSON_UNESCAPED_UNICODE);

        return $this->encryptField($json, $dek);
    }

    /**
     * Giải mã thông tin chi tiết lương
     */
    public function decryptSalaryDetails(string $encryptedPayload, ?string $domain = null): ?array
    {
        $dek = $this->getSystemDek($domain);
        if (!$dek) {
            $salt = strtolower(trim($domain ?? 'fallback_domain'));
            $dek = hash_hkdf('sha256', (string)config('app.key'), 32, 'SmartHR_Fallback', $salt);
        }

        $json = $this->decryptField($encryptedPayload, $dek);
        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Xác thực chữ ký số HMAC-SHA256 gửi từ Golang Edge Collector Daemon
     */
    public function verifySyncHmac(string $rawPayload, string $providedSignature, ?string $secretHex = null): bool
    {
        if (empty($providedSignature)) {
            return false;
        }

        if ($secretHex === null) {
            $license = $this->loadLicense();
            $secretHex = $license['shared_hmac_secret'] ?? null;
        }

        if (empty($secretHex)) {
            return false;
        }

        $secretBytes = (ctype_xdigit($secretHex) && strlen($secretHex) % 2 === 0)
            ? (hex2bin($secretHex) ?: $secretHex)
            : $secretHex;

        $calculatedHmac = hash_hmac('sha256', $rawPayload, $secretBytes);

        return hash_equals(strtolower($calculatedHmac), strtolower($providedSignature));
    }
}
