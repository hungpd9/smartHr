<script setup lang="ts">
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';

const page = usePage();
const isSidebarCollapsed = ref(false);
const isDark = ref(false);

const toggleDarkMode = () => {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);
};

const navigationItems = [
    { label: 'Bàn làm việc', icon: 'pi pi-home', route: '/' },
    { label: 'Hồ sơ & Tổ chức (CoreHR)', icon: 'pi pi-users', route: '/core-hr' },
    { label: 'Quy chế & Thuế (Policy)', icon: 'pi pi-sliders-h', route: '/policies' },
    { label: 'Chấm công (Attendance)', icon: 'pi pi-clock', route: '/attendance' },
    { label: 'Phân ca (Rostering)', icon: 'pi pi-calendar', route: '/rostering' },
    { label: 'Nghỉ phép & OT', icon: 'pi pi-briefcase', route: '/leaves' },
    { label: 'Phê duyệt (Workflow)', icon: 'pi pi-check-circle', route: '/workflow' },
    { label: 'Bảng lương (Payroll)', icon: 'pi pi-wallet', route: '/payroll' },
    { label: 'Tuân thủ (Compliance)', icon: 'pi pi-shield', route: '/compliance' },
    { label: 'Thông báo', icon: 'pi pi-bell', route: '/notifications' },
    { label: 'Bản quyền & Máy chấm công', icon: 'pi pi-key', route: '/licensing' },
];
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col font-sans transition-colors duration-200">
        <!-- PrimeVue Global Toast & ConfirmDialog -->
        <Toast position="top-right" />
        <ConfirmDialog />

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
                            <span class="text-[11px] text-slate-400 font-medium tracking-wide uppercase">HRMS & Rostering</span>
                        </div>
                    </div>
                    <button
                        @click="isSidebarCollapsed = !isSidebarCollapsed"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 dark:hover:text-slate-200 transition"
                    >
                        <i :class="isSidebarCollapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'" class="text-sm" />
                    </button>
                </div>

                <!-- Navigation List -->
                <nav class="flex-1 overflow-y-auto p-3 space-y-1">
                    <Link
                        v-for="item in navigationItems"
                        :key="item.label"
                        :href="item.route"
                        class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition group"
                        :class="[
                            page.url === item.route
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'
                        ]"
                        v-tooltip.right="isSidebarCollapsed ? item.label : undefined"
                    >
                        <i :class="[item.icon, 'text-base shrink-0 transition-transform group-hover:scale-110']" />
                        <span v-show="!isSidebarCollapsed" class="truncate">{{ item.label }}</span>
                    </Link>
                </nav>

                <!-- License Anchor Status Footer -->
                <div class="p-3 border-t border-slate-100 dark:border-slate-800 text-xs">
                    <div
                        class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200/60 dark:border-slate-700/60 flex items-center space-x-2.5"
                    >
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <div v-show="!isSidebarCollapsed" class="flex flex-col truncate">
                            <span class="font-semibold text-slate-700 dark:text-slate-200 truncate">smart-hr.lic: Hợp lệ</span>
                            <span class="text-[10px] text-slate-400">Ed25519 Verified</span>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
                <!-- Topbar -->
                <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-6 z-20">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-xs bg-indigo-50/70 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 px-3 py-1.5 rounded-full border border-indigo-200/50 dark:border-indigo-800/50">
                            <i class="pi pi-building text-xs"></i>
                            <span class="font-medium">Chi nhánh: Hội sở chính (HN)</span>
                        </div>
                        <div class="text-xs text-slate-400">
                            Tuân thủ BLLĐ 2019 & Thuế 2026
                        </div>
                    </div>

                    <div class="flex items-center space-x-3">
                        <!-- Dark Mode Toggle -->
                        <button
                            @click="toggleDarkMode"
                            class="p-2 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition"
                            v-tooltip.bottom="isDark ? 'Chuyển giao diện Sáng' : 'Chuyển giao diện Tối'"
                        >
                            <i :class="isDark ? 'pi pi-sun' : 'pi pi-moon'" class="text-base" />
                        </button>

                        <!-- In-app Notification Bell -->
                        <button
                            class="relative p-2 rounded-xl text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition"
                            v-tooltip.bottom="'Thông báo hệ thống'"
                        >
                            <i class="pi pi-bell text-base" />
                            <span class="absolute top-1.5 right-1.5 w-2 h-2 bg-rose-500 rounded-full ring-2 ring-white dark:ring-slate-900"></span>
                        </button>

                        <!-- User Avatar Menu -->
                        <div class="flex items-center space-x-3 pl-3 border-l border-slate-200 dark:border-slate-800">
                            <div class="w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-semibold flex items-center justify-center text-sm border border-indigo-200 dark:border-indigo-700">
                                AD
                            </div>
                            <div class="hidden md:flex flex-col text-left">
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100 leading-tight">Admin Quản trị</span>
                                <span class="text-[11px] text-slate-400">Chuyên viên HR Cấp cao</span>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Page Content Body -->
                <main class="flex-1 overflow-y-auto p-6 bg-slate-50/60 dark:bg-slate-950/60">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
