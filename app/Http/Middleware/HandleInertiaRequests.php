<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role instanceof \App\Enums\UserRole ? $user->role->value : (string) $user->role,
                    'role_label' => $user->role instanceof \App\Enums\UserRole ? $user->role->label($user->locale_preference ?? 'vi') : (string) $user->role,
                    'role_description' => $user->role instanceof \App\Enums\UserRole ? $user->role->description($user->locale_preference ?? 'vi') : '',
                    'avatar' => $user->avatar,
                    'theme_preference' => $user->theme_preference ?? 'system',
                    'locale_preference' => $user->locale_preference ?? 'vi',
                    'allowed_modules' => $user->role instanceof \App\Enums\UserRole ? $user->role->allowedModules() : [],
                    'employee' => $user->employee ? [
                        'id' => $user->employee->id,
                        'user_code' => $user->employee->user_code,
                        'full_name' => $user->employee->full_name,
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'in_app_notifications' => fn () => $user ? app(\App\Modules\Notification\Services\NotificationDispatcherService::class)->getRecentNotificationsForUser($user, 10)->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'severity' => strtolower($n->severity),
                'title' => $n->title,
                'content' => $n->content,
                'action_url' => $n->action_url,
                'is_read' => (bool) $n->is_read,
                'created_at' => $n->created_at?->diffForHumans() ?? 'Vừa xong',
            ]) : [],
            'unread_notifications_count' => fn () => $user ? app(\App\Modules\Notification\Services\NotificationDispatcherService::class)->getUnreadCountForUser($user) : 0,
        ];
    }
}
