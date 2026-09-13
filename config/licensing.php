<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Author Ed25519 Public Key (Khóa Công Khai Tác Giả)
    |--------------------------------------------------------------------------
    | Khóa công khai của tác giả được nhúng cố định trong hệ thống để xác thực
    | chữ ký số của file smart-hr.lic. Tuyệt đối không thể giả mạo nếu không
    | có Private Key tương ứng của tác giả.
    */
    'author_public_key' => env(
        'SMARTHR_AUTHOR_PUBLIC_KEY',
        \App\Modules\Licensing\Services\LicenseService::AUTHOR_PUBLIC_KEY
    ),

    /*
    |--------------------------------------------------------------------------
    | Đường dẫn lưu trữ tệp giấy phép (License File Path)
    |--------------------------------------------------------------------------
    | Hệ thống ưu tiên đọc file smart-hr.lic tại thư mục gốc, nếu không có sẽ
    | tìm kiếm trong thư mục storage/license/smart-hr.lic.
    */
    'license_path' => env('SMARTHR_LICENSE_PATH', base_path('smart-hr.lic')),
    'storage_license_path' => storage_path('license/smart-hr.lic'),

    /*
    |--------------------------------------------------------------------------
    | Domain Override cho Môi Trường Phát Triển Cục Bộ
    |--------------------------------------------------------------------------
    */
    'domain_override' => env('SMARTHR_LICENSE_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Thuật toán & Info chuỗi dẫn xuất DEK HKDF
    |--------------------------------------------------------------------------
    */
    'dek_hkdf_info' => 'SmartHR_DEK_v1',
    'cipher_algo' => 'aes-256-gcm',
];
