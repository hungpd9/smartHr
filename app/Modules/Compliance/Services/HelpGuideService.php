<?php

declare(strict_types=1);

namespace App\Modules\Compliance\Services;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class HelpGuideService
{
    protected string $basePath;

    public function __construct()
    {
        $this->basePath = resource_path('docs/guides');
    }

    /**
     * Lấy danh sách tài liệu hướng dẫn được phép xem theo vai trò của người dùng
     */
    public function getGuidesForUser(User $user): array
    {
        $accessibleRoles = $this->getAccessibleRoleFolders($user->role);
        $catalog = [];

        foreach ($accessibleRoles as $roleKey => $roleLabel) {
            $folderPath = "{$this->basePath}/{$roleKey}";
            if (!File::isDirectory($folderPath)) {
                continue;
            }

            $files = File::files($folderPath);
            $guides = [];

            foreach ($files as $file) {
                if ($file->getExtension() !== 'md') {
                    continue;
                }

                $filename = $file->getFilenameWithoutExtension();
                $content = File::get($file->getRealPath());
                $title = $this->extractTitleFromMarkdown($content, $filename);

                $guides[] = [
                    'slug' => $filename,
                    'title' => $title,
                    'role' => $roleKey,
                    'file_path' => $file->getRelativePathname(),
                ];
            }

            // Sắp xếp theo tên file (01, 02, 03...)
            usort($guides, fn($a, $b) => strcmp($a['slug'], $b['slug']));

            if (!empty($guides)) {
                $catalog[] = [
                    'role' => $roleKey,
                    'label' => $roleLabel,
                    'guides' => $guides,
                ];
            }
        }

        return $catalog;
    }

    /**
     * Lấy chi tiết nội dung Markdown của một bài viết cụ thể
     */
    public function getGuideContent(User $user, string $role, string $slug): ?array
    {
        $accessibleRoles = $this->getAccessibleRoleFolders($user->role);
        if (!array_key_exists($role, $accessibleRoles)) {
            return null;
        }

        $filePath = "{$this->basePath}/{$role}/{$slug}.md";
        if (!File::exists($filePath)) {
            return null;
        }

        $content = File::get($filePath);
        $title = $this->extractTitleFromMarkdown($content, $slug);

        return [
            'role' => $role,
            'role_label' => $accessibleRoles[$role],
            'slug' => $slug,
            'title' => $title,
            'content' => $content,
            'updated_at' => date('d/m/Y H:i', File::lastModified($filePath)),
        ];
    }

    /**
     * Kiểm tra vai trò của người dùng có được phép đọc thư mục tài liệu chỉ định hay không
     */
    public function canAccessRoleGuide(UserRole|string $role, string $targetRoleFolder): bool
    {
        $accessibleRoles = $this->getAccessibleRoleFolders($role);
        return array_key_exists($targetRoleFolder, $accessibleRoles);
    }

    /**
     * Ma trận quyền truy cập thư mục tài liệu theo vai trò
     */
    protected function getAccessibleRoleFolders(UserRole|string $role): array
    {
        $roleValue = is_string($role) ? $role : $role->value;

        return match ($roleValue) {
            UserRole::SUPER_ADMIN->value => [
                'employee' => 'Dành cho Nhân viên',
                'manager' => 'Dành cho Quản lý / Trưởng phòng',
                'hr_admin' => 'Dành cho Quản trị Nhân sự (HR Admin)',
                'accountant' => 'Dành cho Kế toán Tiền lương',
                'super_admin' => 'Dành cho Quản trị Tối cao (Super Admin)',
            ],
            UserRole::HR_ADMIN->value => [
                'employee' => 'Dành cho Nhân viên',
                'manager' => 'Dành cho Quản lý / Trưởng phòng',
                'hr_admin' => 'Dành cho Quản trị Nhân sự (HR Admin)',
            ],
            UserRole::ACCOUNTANT->value => [
                'employee' => 'Dành cho Nhân viên',
                'accountant' => 'Dành cho Kế toán Tiền lương',
            ],
            UserRole::DEPARTMENT_MANAGER->value => [
                'employee' => 'Dành cho Nhân viên',
                'manager' => 'Dành cho Quản lý / Trưởng phòng',
            ],
            default => [
                'employee' => 'Dành cho Nhân viên',
            ],
        };
    }

    /**
     * Trích xuất tiêu đề H1 (# ...) từ nội dung Markdown
     */
    protected function extractTitleFromMarkdown(string $content, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }

        return Str::headline($fallback);
    }
}
