<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { useThemeAndLocale, type SupportedLocale } from '@/Composables/useThemeAndLocale';
import {
    House,
    User,
    Operation,
    Clock,
    Calendar,
    Briefcase,
    CircleCheck,
    Wallet,
    Warning,
    Bell,
    Key,
    DArrowRight,
    DArrowLeft,
    Sunny,
    Moon,
    OfficeBuilding,
    Check,
    ChatDotRound,
    Lock,
    ArrowDown,
    Switch,
    SwitchButton,
    Avatar,
    Female,
    QuestionFilled,
    ArrowLeft,
    ArrowRight,
} from '@element-plus/icons-vue';
import { useBrowserNavigation } from '@/Composables/useNavigationHistory';

const page = usePage();
const isSidebarCollapsed = ref(false);

const { isDark, toggleTheme, currentLang, currentElLocale, setLanguage, t } = useThemeAndLocale();
const { goBack, goForward } = useBrowserNavigation();

// Auth User từ Inertia Shared Props
const authUser = computed(() => page.props.auth?.user);

interface SubNavigationItem {
    key: string;
    label: string;
    route: string;
}

interface NavigationItem {
    key: string;
    icon: any;
    route?: string;
    children?: SubNavigationItem[];
}

const navigationItems: NavigationItem[] = [
    { key: 'dashboard', icon: House, route: '/' },
    {
        key: 'core_hr',
        icon: User,
        children: [
            { key: 'core_hr_tree', label: 'Sơ đồ Tổ chức & Nhân sự', route: '/core-hr' },
            { key: 'employee_children', label: 'Con nhỏ & Phúc lợi Gia đình', route: '/employee-children' },
        ],
    },
    {
        key: 'policy',
        icon: Operation,
        children: [
            { key: 'policy_rules', label: 'Quy chế Chấm công & Lương', route: '/policies' },
            { key: 'welfare_schemes', label: 'Chế độ Phúc lợi Doanh nghiệp', route: '/policies?tab=welfare_schemes' },
            { key: 'allowance_catalog', label: 'Danh mục Phụ cấp & Trợ cấp', route: '/policies?tab=allowances' },
        ],
    },
    { key: 'attendance', icon: Clock, route: '/attendance' },
    { key: 'access_control', icon: Lock, route: '/access-control' },
    {
        key: 'rostering',
        icon: Calendar,
        children: [
            { key: 'rostering_matrix', label: 'Ma trận Phân ca Tuần/Tháng', route: '/rosters' },
            { key: 'rostering_shifts', label: 'Danh mục Ca làm việc', route: '/rosters/shifts' },
            { key: 'rostering_swaps', label: 'Yêu cầu Đổi ca & Bắt ca thay', route: '/rosters/swaps' },
        ],
    },
    {
        key: 'leaves',
        icon: Briefcase,
        children: [
            { key: 'leave_balances', label: 'Quỹ Phép & Nghỉ Bù', route: '/leave/balances' },
            { key: 'leave_ot_caps', label: 'Giám Sát Trần OT (40h/200h)', route: '/leave/ot-accumulations' },
        ],
    },
    { key: 'workflow', icon: CircleCheck, route: '/workflow' },
    { key: 'payroll', icon: Wallet, route: '/payroll' },
    { key: 'compliance', icon: Warning, route: '/compliance' },
    { key: 'licensing', icon: Key, route: '/licensing' },
];

// Quản lý trạng thái đóng/mở submenu
const expandedMenus = ref<Record<string, boolean>>({
    core_hr: true,
    policy: true,
    rostering: true,
    leaves: true,
});

const toggleSubmenu = (key: string) => {
    expandedMenus.value[key] = !expandedMenus.value[key];
};

// Kiểm tra route con có đang active hay không
const isParentActive = (item: NavigationItem): boolean => {
    if (item.route && page.url === item.route) return true;
    if (item.children) {
        return item.children.some(child => page.url.split('?')[0] === child.route.split('?')[0]);
    }
    return false;
};

// Kiểm tra chính xác mục submenu con có đang active hay không (hỗ trợ cả query param như ?tab=welfare_schemes)
const isSubItemActive = (subRoute: string): boolean => {
    const currentUrl = page.url;

    // 1. Nếu subRoute có query param (vd: /policies?tab=welfare_schemes)
    if (subRoute.includes('?')) {
        const [subBase, subQuery] = subRoute.split('?');
        const [currentBase, currentQuery] = currentUrl.split('?');
        if (subBase !== currentBase) return false;

        const subParams = new URLSearchParams(subQuery);
        const currentParams = new URLSearchParams(currentQuery || '');
        for (const [key, value] of subParams.entries()) {
            if (currentParams.get(key) !== value) {
                return false;
            }
        }
        return true;
    }

    // 2. Nếu subRoute không chứa query param (vd: /policies)
    const [subBase] = subRoute.split('?');
    const [currentBase, currentQuery] = currentUrl.split('?');
    if (subBase !== currentBase) return false;

    if (currentQuery) {
        const currentParams = new URLSearchParams(currentQuery);
        const tab = currentParams.get('tab');
        if (tab && tab !== 'general' && tab !== 'attendance') {
            return false;
        }
    }

    return true;
};

// Lọc Menu Sidebar tự động theo Vai trò Người dùng (RBAC)
const visibleNavigationItems = computed(() => {
    if (!authUser.value) return navigationItems;
    if (authUser.value.role === 'SUPER_ADMIN') return navigationItems;
    const allowed = authUser.value.allowed_modules || [];
    return navigationItems.filter(item => {
        if (item.key === 'access_control') return authUser.value?.role === 'SUPER_ADMIN';
        return allowed.includes(item.key);
    });
});

// Demo Roles Switcher
const demoRoles = [
    { role: 'SUPER_ADMIN', label: 'Super Admin', desc: 'Quản trị viên Hệ thống' },
    { role: 'HR_ADMIN', label: 'HR Admin', desc: 'Chuyên viên HR Cấp cao' },
    { role: 'ACCOUNTANT', label: 'Kế toán Lương', desc: 'Tính lương & Thuế TNCN' },
    { role: 'DEPARTMENT_MANAGER', label: 'Trưởng bộ phận', desc: 'Duyệt đơn & Phân ca' },
    { role: 'EMPLOYEE', label: 'Nhân viên', desc: 'Chấm công & Đơn từ' },
];

const handleSwitchRole = (role: string) => {
    router.get(`/auth/switch-role/${role}`);
};

const handleLogout = () => {
    router.post('/logout');
};

const getUserInitials = computed(() => {
    if (!authUser.value?.name) return 'AD';
    const parts = authUser.value.name.trim().split(' ');
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

// In-app Notifications State
interface NotificationItem {
    id: number;
    title: string;
    content: string;
    time: string;
    type: 'info' | 'warning' | 'success';
    read: boolean;
}

const notifications = ref<NotificationItem[]>([
    {
        id: 1,
        title: 'Chế độ Bảo hộ Điều 137',
        content: 'Nhân viên EMP002 (Trần Thị Mai) đủ điều kiện áp dụng chế độ con dưới 12 tháng tuổi.',
        time: '10 phút trước',
        type: 'info',
        read: false,
    },
    {
        id: 2,
        title: 'Đồng bộ từ Edge Collector',
        content: 'Chi nhánh Kho Phú Quốc vừa đồng bộ thành công 14 lượt quẹt thẻ HMAC-SHA256.',
        time: '35 phút trước',
        type: 'success',
        read: false,
    },
    {
        id: 3,
        title: 'Rà soát Tuân thủ Hợp đồng',
        content: '01 nhân sự sắp hết hạn hợp đồng xác định thời hạn trong vòng 30 ngày tới.',
        time: '2 giờ trước',
        type: 'warning',
        read: false,
    },
]);

const unreadCount = ref(notifications.value.filter(n => !n.read).length);

const markAllNotificationsAsRead = () => {
    notifications.value.forEach(n => { n.read = true; });
    unreadCount.value = 0;
};
</script>

<template>
    <el-config-provider :locale="currentElLocale">
        <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col font-sans transition-colors duration-200">
            <div class="flex flex-1 overflow-hidden">
                <!-- Sidebar -->
                <aside
                    :class="[
                        'bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 transition-all duration-300 flex flex-col z-30',
                        isSidebarCollapsed ? 'w-20' : 'w-64'
                    ]"
                >
                    <!-- Brand Header -->
                    <div class="h-16 flex items-center justify-between px-4 border-b border-slate-100 dark:border-slate-800">
                        <div class="flex items-center space-x-3 overflow-hidden">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 flex items-center justify-center text-white font-bold text-xl shadow-md shrink-0">
                                SH
                            </div>
                            <div v-show="!isSidebarCollapsed" class="flex flex-col">
                                <span class="font-bold text-slate-800 dark:text-white leading-tight tracking-tight text-lg">Smart-HR</span>
                                <span class="text-[11px] text-slate-400 font-medium tracking-wide uppercase">Enterprise HRMS</span>
                            </div>
                        </div>
                        <button
                            @click="isSidebarCollapsed = !isSidebarCollapsed"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition"
                        >
                            <el-icon :size="16">
                                <component :is="isSidebarCollapsed ? DArrowRight : DArrowLeft" />
                            </el-icon>
                        </button>
                    </div>

                    <!-- Navigation List (Mỗi module là menu cấp 1, chức năng là menu cấp 2/3) -->
                    <nav class="flex-1 overflow-y-auto p-3 space-y-1">
                        <template v-for="item in visibleNavigationItems" :key="item.key">
                            <!-- Menu có Submenu (Module cấp 1 có chức năng cấp 2) -->
                            <div v-if="item.children && item.children.length > 0" class="space-y-1">
                                <button
                                    type="button"
                                    @click="toggleSubmenu(item.key)"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition group"
                                    :class="[
                                        isParentActive(item)
                                            ? 'bg-indigo-50/80 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 font-semibold'
                                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                                    ]"
                                    :title="isSidebarCollapsed ? t(item.key) : undefined"
                                >
                                    <div class="flex items-center space-x-3 truncate">
                                        <el-icon :size="18" class="shrink-0 transition-transform group-hover:scale-110">
                                            <component :is="item.icon" />
                                        </el-icon>
                                        <span v-show="!isSidebarCollapsed" class="truncate">{{ t(item.key) }}</span>
                                    </div>
                                    <el-icon v-show="!isSidebarCollapsed" :size="12" class="text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': expandedMenus[item.key] }">
                                        <ArrowDown />
                                    </el-icon>
                                </button>

                                <!-- Submenu list (Cấp 2) -->
                                <div
                                    v-show="!isSidebarCollapsed && expandedMenus[item.key]"
                                    class="pl-3.5 pr-1 space-y-0.5 border-l-2 border-indigo-200 dark:border-indigo-900/60 ml-4 my-1"
                                >
                                    <Link
                                        v-for="sub in item.children"
                                        :key="sub.key"
                                        :href="sub.route"
                                        class="flex items-center space-x-2.5 px-3 py-1.5 rounded-lg text-xs font-medium transition"
                                        :class="[
                                            isSubItemActive(sub.route)
                                                ? 'text-indigo-600 dark:text-indigo-400 bg-indigo-50/60 dark:bg-indigo-950/40 font-semibold'
                                                : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 hover:bg-slate-100/60 dark:hover:bg-slate-800/40'
                                        ]"
                                    >
                                        <span
                                            class="w-1.5 h-1.5 rounded-full shrink-0"
                                            :class="isSubItemActive(sub.route) ? 'bg-indigo-600 dark:bg-indigo-400' : 'bg-slate-300 dark:bg-slate-700'"
                                        ></span>
                                        <span class="truncate">{{ sub.label }}</span>
                                    </Link>
                                </div>
                            </div>

                            <!-- Menu Đơn lẻ (Không có Submenu) -->
                            <Link
                                v-else
                                :href="item.route!"
                                class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group"
                                :class="[
                                    page.url === item.route
                                        ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 shadow-xs font-semibold'
                                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                                ]"
                                :title="isSidebarCollapsed ? t(item.key) : undefined"
                            >
                                <el-icon :size="18" class="shrink-0 transition-transform group-hover:scale-110">
                                    <component :is="item.icon" />
                                </el-icon>
                                <span v-show="!isSidebarCollapsed" class="truncate">{{ t(item.key) }}</span>
                            </Link>
                        </template>
                    </nav>

                    <!-- License Status Footer (Giao diện Thân thiện Doanh nghiệp) -->
                    <div class="p-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <div
                            class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 flex items-center space-x-2.5"
                            title="Hệ thống đã xác thực bản quyền Ed25519 & Mã hóa dữ liệu CSDL"
                        >
                            <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <el-icon :size="16"><Lock /></el-icon>
                            </div>
                            <div v-show="!isSidebarCollapsed" class="flex flex-col truncate">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-slate-700 dark:text-slate-200 truncate">{{ t('enterprise_licensed') }}</span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                </div>
                                <span class="text-[10px] text-slate-400 truncate">{{ t('data_anchored') }}</span>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Main Content Area -->
                <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                    <!-- Topbar -->
                    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 z-20">
                        <div class="flex items-center space-x-3">
                            <!-- Cụm nút Điều Hướng Trình Duyệt Back / Forward -->
                            <div class="flex items-center space-x-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
                                <button
                                    @click="goBack"
                                    type="button"
                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 hover:text-indigo-600 dark:hover:text-indigo-400 hover:shadow-xs transition active:scale-95"
                                    title="Quay lại trang trước (Alt + ←)"
                                    aria-label="Quay lại trang trước"
                                >
                                    <el-icon :size="14"><ArrowLeft /></el-icon>
                                </button>
                                <button
                                    @click="goForward"
                                    type="button"
                                    class="w-7 h-7 rounded-lg flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 hover:text-indigo-600 dark:hover:text-indigo-400 hover:shadow-xs transition active:scale-95"
                                    title="Tiến tới trang sau (Alt + →)"
                                    aria-label="Tiến tới trang sau"
                                >
                                    <el-icon :size="14"><ArrowRight /></el-icon>
                                </button>
                            </div>

                            <div class="flex items-center space-x-2 text-xs bg-indigo-50/70 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 px-3 py-1.5 rounded-full border border-indigo-200/50 dark:border-indigo-800/50">
                                <el-icon :size="14"><OfficeBuilding /></el-icon>
                                <span class="font-medium">{{ t('headquarters') }}</span>
                            </div>
                            <div class="text-xs text-slate-400 hidden sm:block">
                                {{ t('compliance_badge') }}
                            </div>
                        </div>

                        <div class="flex items-center space-x-2.5">
                            <!-- Quick Switch Role Dropdown (Hỗ trợ Manual Test) -->
                            <el-dropdown trigger="click" @command="handleSwitchRole">
                                <button class="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 transition border border-indigo-200 dark:border-indigo-800/60">
                                    <el-icon :size="13"><Switch /></el-icon>
                                    <span class="hidden md:inline">Vai trò:</span>
                                    <span class="font-bold">{{ authUser?.role_label || 'Quản trị viên' }}</span>
                                    <el-icon :size="11" class="ml-0.5"><ArrowDown /></el-icon>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <div class="px-3 py-1.5 text-[11px] font-semibold text-slate-400 border-b border-slate-100 dark:border-slate-800">
                                            Chuyển vai trò thử nghiệm (Demo)
                                        </div>
                                        <el-dropdown-item
                                            v-for="item in demoRoles"
                                            :key="item.role"
                                            :command="item.role"
                                        >
                                            <div class="flex items-center justify-between w-full min-w-[180px] py-0.5">
                                                <div>
                                                    <div :class="authUser?.role === item.role ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-slate-800 dark:text-slate-200'">
                                                        {{ item.label }}
                                                    </div>
                                                    <div class="text-[10px] text-slate-400">{{ item.desc }}</div>
                                                </div>
                                                <el-icon v-if="authUser?.role === item.role" :size="14" class="text-indigo-600 dark:text-indigo-400 ml-2"><Check /></el-icon>
                                            </div>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>

                            <!-- Multi-language Switcher (i18n) -->
                            <el-dropdown trigger="click" @command="(lang: SupportedLocale) => setLanguage(lang)">
                                <button class="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition border border-slate-200/80 dark:border-slate-700/60">
                                    <span>{{ currentLang === 'vi' ? 'Tiếng Việt' : 'Tiếng Anh' }}</span>
                                    <el-icon :size="11" class="text-slate-400 ml-0.5"><ArrowDown /></el-icon>
                                </button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item command="vi">
                                            <div class="flex items-center justify-between w-full min-w-[110px]">
                                                <span :class="currentLang === 'vi' ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-200'">Tiếng Việt</span>
                                                <el-icon v-if="currentLang === 'vi'" :size="14" class="text-indigo-600 dark:text-indigo-400 ml-2"><Check /></el-icon>
                                            </div>
                                        </el-dropdown-item>
                                        <el-dropdown-item command="en">
                                            <div class="flex items-center justify-between w-full min-w-[110px]">
                                                <span :class="currentLang === 'en' ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-200'">Tiếng Anh</span>
                                                <el-icon v-if="currentLang === 'en'" :size="14" class="text-indigo-600 dark:text-indigo-400 ml-2"><Check /></el-icon>
                                            </div>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>

                            <!-- Dark / Light Mode Toggle -->
                            <button
                                @click="toggleTheme"
                                class="p-2 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition"
                                :title="isDark ? t('light_mode') : t('dark_mode')"
                            >
                                <el-icon :size="18">
                                    <component :is="isDark ? Sunny : Moon" />
                                </el-icon>
                            </button>

                            <!-- Help Guide Link -->
                            <Link
                                href="/help"
                                class="p-2 rounded-xl text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 dark:text-slate-400 dark:hover:text-indigo-400 dark:hover:bg-slate-800 transition flex items-center justify-center"
                                title="Hướng dẫn sử dụng & Trung tâm trợ giúp"
                            >
                                <el-icon :size="18"><QuestionFilled /></el-icon>
                            </Link>

                            <!-- In-app Notification Popover -->
                            <el-popover placement="bottom-end" :width="360" trigger="click">
                                <template #reference>
                                    <button
                                        class="relative p-2 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition"
                                        :title="t('system_notifications')"
                                    >
                                        <el-icon :size="18"><Bell /></el-icon>
                                        <span
                                            v-if="unreadCount > 0"
                                            class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white dark:ring-slate-900"
                                        ></span>
                                    </button>
                                </template>

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                                        <div class="flex items-center space-x-2">
                                            <span class="font-bold text-sm text-slate-800 dark:text-slate-100">{{ t('system_notifications') }}</span>
                                            <span v-if="unreadCount > 0" class="text-[10px] px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 font-bold">
                                                {{ unreadCount }}
                                            </span>
                                        </div>
                                        <button
                                            v-if="unreadCount > 0"
                                            @click="markAllNotificationsAsRead"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium"
                                        >
                                            {{ t('mark_all_read') }}
                                        </button>
                                    </div>

                                    <div v-if="notifications.length > 0" class="divide-y divide-slate-100 dark:divide-slate-800 max-h-80 overflow-y-auto">
                                        <div
                                            v-for="item in notifications"
                                            :key="item.id"
                                            class="py-2.5 px-1 hover:bg-slate-50 dark:hover:bg-slate-800/50 rounded-lg transition"
                                            :class="item.read ? 'opacity-60' : ''"
                                        >
                                            <div class="flex items-start space-x-2.5">
                                                <span
                                                    class="w-2 h-2 rounded-full mt-1.5 shrink-0"
                                                    :class="{
                                                        'bg-indigo-500': item.type === 'info',
                                                        'bg-emerald-500': item.type === 'success',
                                                        'bg-amber-500': item.type === 'warning',
                                                    }"
                                                ></span>
                                                <div class="flex-1 min-w-0 text-xs">
                                                    <div class="font-semibold text-slate-800 dark:text-slate-200 truncate">{{ item.title }}</div>
                                                    <div class="text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-2 leading-relaxed">{{ item.content }}</div>
                                                    <span class="text-[10px] text-slate-400 mt-1 block">{{ item.time }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-else class="text-center py-6 text-slate-400 text-xs">
                                        <el-icon :size="28" class="text-slate-300 mb-1"><ChatDotRound /></el-icon>
                                        <p>{{ t('no_new_notifications') }}</p>
                                    </div>
                                </div>
                            </el-popover>

                            <!-- User Avatar & Profile Dropdown Menu -->
                            <el-dropdown trigger="click">
                                <div class="flex items-center space-x-3 pl-2 border-l border-slate-200 dark:border-slate-800 cursor-pointer">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-bold flex items-center justify-center text-xs shadow-xs border border-indigo-400/40">
                                        {{ getUserInitials }}
                                    </div>
                                    <div class="hidden md:flex flex-col text-left">
                                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-100 leading-tight">
                                            {{ authUser?.name || 'Admin Quản trị' }}
                                        </span>
                                        <span class="text-[11px] text-slate-400 truncate max-w-[140px]">
                                            {{ authUser?.role_label || 'Quản trị viên' }}
                                        </span>
                                    </div>
                                    <el-icon :size="12" class="text-slate-400"><ArrowDown /></el-icon>
                                </div>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-800">
                                            <p class="text-xs font-bold text-slate-800 dark:text-white">{{ authUser?.name }}</p>
                                            <p class="text-[11px] text-slate-400">{{ authUser?.email }}</p>
                                            <p class="text-[10px] mt-1 px-1.5 py-0.5 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 inline-block font-semibold">
                                                {{ authUser?.role_label }}
                                            </p>
                                        </div>
                                        <el-dropdown-item @click="handleLogout" divided>
                                            <div class="flex items-center space-x-2 text-rose-600 dark:text-rose-400 font-medium py-0.5">
                                                <el-icon :size="14"><SwitchButton /></el-icon>
                                                <span>Đăng xuất hệ thống</span>
                                            </div>
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </div>
                    </header>

                    <!-- Page Content Body -->
                    <main scroll-region class="flex-1 overflow-y-auto p-6 bg-slate-50/60 dark:bg-slate-950/60 text-slate-800 dark:text-slate-100">
                        <slot />
                    </main>
                </div>
            </div>
        </div>
    </el-config-provider>
</template>
