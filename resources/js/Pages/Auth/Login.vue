<script setup lang="ts">
import { useForm, Head } from '@inertiajs/vue3';
import { useThemeAndLocale, type SupportedLocale } from '@/Composables/useThemeAndLocale';
import {
    Sunny,
    Moon,
    ArrowDown,
    Check,
    User,
    Lock,
    Key,
    Suitcase,
    TrendCharts,
    Management,
    Avatar,
} from '@element-plus/icons-vue';
import AppBrandLogo from '@/Components/AppBrandLogo.vue';

interface DemoUser {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    role_desc: string;
}

const props = defineProps<{
    demoUsers?: DemoUser[];
}>();

const { isDark, toggleTheme, currentLang, currentElLocale, setLanguage, t } = useThemeAndLocale();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => {
            form.password = '';
        },
    });
};

// 1-Click Auto Fill Demo User
const selectDemoUser = (user: DemoUser) => {
    form.email = user.email;
    form.password = 'password';
    form.remember = true;
    submit();
};

const getRoleIcon = (role: string) => {
    switch (role) {
        case 'SUPER_ADMIN':
            return Key;
        case 'HR_ADMIN':
            return Suitcase;
        case 'ACCOUNTANT':
            return TrendCharts;
        case 'DEPARTMENT_MANAGER':
            return Management;
        default:
            return Avatar;
    }
};

const getRoleBadgeColor = (role: string) => {
    switch (role) {
        case 'SUPER_ADMIN':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'HR_ADMIN':
            return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-200 dark:border-indigo-800';
        case 'ACCOUNTANT':
            return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        case 'DEPARTMENT_MANAGER':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border-amber-200 dark:border-amber-800';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700';
    }
};
</script>

<template>
    <el-config-provider :locale="currentElLocale">
        <Head :title="t('auth.sign_in_title')" />

        <div class="min-h-screen bg-slate-50 dark:bg-slate-950 flex flex-col justify-center items-center p-4 transition-colors duration-200 relative overflow-hidden font-sans">
            <!-- Top Controls (Language & Theme) -->
            <div class="absolute top-6 right-6 flex items-center space-x-3 z-20">
                <el-dropdown trigger="click" @command="(lang: SupportedLocale) => setLanguage(lang)">
                    <button class="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition border border-slate-200 dark:border-slate-800 shadow-xs">
                        <span>{{ currentLang === 'vi' ? t('header.vietnamese') : t('header.english') }}</span>
                        <el-icon :size="11" class="text-slate-400 ml-0.5"><ArrowDown /></el-icon>
                    </button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item command="vi">
                                <div class="flex items-center justify-between w-full min-w-[110px]">
                                    <span :class="currentLang === 'vi' ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-200'">{{ t('header.vietnamese') }}</span>
                                    <el-icon v-if="currentLang === 'vi'" :size="14" class="text-indigo-600 dark:text-indigo-400 ml-2"><Check /></el-icon>
                                </div>
                            </el-dropdown-item>
                            <el-dropdown-item command="en">
                                <div class="flex items-center justify-between w-full min-w-[110px]">
                                    <span :class="currentLang === 'en' ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-slate-700 dark:text-slate-200'">{{ t('header.english') }}</span>
                                    <el-icon v-if="currentLang === 'en'" :size="14" class="text-indigo-600 dark:text-indigo-400 ml-2"><Check /></el-icon>
                                </div>
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>

                <button
                    @click="toggleTheme"
                    class="p-2 rounded-xl bg-white dark:bg-slate-900 text-slate-500 hover:text-slate-700 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-200 dark:hover:bg-slate-800 transition border border-slate-200 dark:border-slate-800 shadow-xs"
                    :title="isDark ? t('header.light_mode') : t('header.dark_mode')"
                >
                    <el-icon :size="18">
                        <component :is="isDark ? Sunny : Moon" />
                    </el-icon>
                </button>
            </div>

            <!-- Login Container -->
            <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-stretch z-10">
                <!-- Left: Form Card (7 cols) -->
                <div class="lg:col-span-7 bg-white dark:bg-slate-900 rounded-3xl border border-slate-200/80 dark:border-slate-800 shadow-xl p-8 sm:p-10 flex flex-col justify-between">
                    <div>
                        <!-- Brand -->
                        <div class="mb-8">
                            <AppBrandLogo size="lg" :show-slogan="true" />
                        </div>

                        <div class="mb-6">
                            <h2 class="text-xl font-bold text-slate-800 dark:text-white tracking-tight">{{ t('auth.sign_in_title') }}</h2>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                {{ t('auth.sign_in_subtitle') }}
                            </p>
                        </div>

                        <!-- Alert Errors -->
                        <div v-if="form.errors.email" class="mb-5 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs flex items-center space-x-2">
                            <span>{{ form.errors.email }}</span>
                        </div>

                        <!-- Form -->
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                    {{ t('auth.email_or_code') }}
                                </label>
                                <el-input
                                    v-model="form.email"
                                    :placeholder="t('auth.email_placeholder')"
                                    :prefix-icon="User"
                                    size="large"
                                    class="!rounded-xl"
                                    clearable
                                />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                    {{ t('auth.password') }}
                                </label>
                                <el-input
                                    v-model="form.password"
                                    type="password"
                                    show-password
                                    :placeholder="t('auth.password_placeholder')"
                                    :prefix-icon="Lock"
                                    size="large"
                                    class="!rounded-xl"
                                />
                            </div>

                            <div class="flex items-center justify-between pt-1">
                                <el-checkbox v-model="form.remember">
                                    <span class="text-xs text-slate-600 dark:text-slate-400">{{ t('auth.remember_me') }}</span>
                                </el-checkbox>
                                <span class="text-xs text-slate-400">{{ t('auth.default_password') }}</span>
                            </div>

                            <div class="pt-2">
                                <el-button
                                    type="primary"
                                    native-type="submit"
                                    size="large"
                                    :loading="form.processing"
                                    class="w-full !bg-indigo-600 hover:!bg-indigo-700 !rounded-xl !h-12 !font-bold !text-sm !shadow-md shadow-indigo-200 dark:shadow-none"
                                >
                                    {{ t('auth.sign_in_button') }}
                                </el-button>
                            </div>
                        </form>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                        <span>{{ t('auth.labor_code_compliant') }}</span>
                        <span>{{ t('auth.aes_encrypted') }}</span>
                    </div>
                </div>

                <!-- Right: 1-Click Demo Accounts (5 cols) -->
                <div class="lg:col-span-5 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md rounded-3xl border border-slate-200/80 dark:border-slate-800 p-6 sm:p-8 flex flex-col justify-between shadow-lg">
                    <div>
                        <div class="flex items-center space-x-2 mb-4">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider">
                                {{ t('auth.demo_accounts_title') }}
                            </h3>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
                            {{ t('auth.demo_accounts_desc') }}
                        </p>

                        <div class="space-y-2.5">
                            <div
                                v-for="user in (demoUsers || [])"
                                :key="user.id"
                                @click="selectDemoUser(user)"
                                class="p-3 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-800/80 hover:border-indigo-500 dark:hover:border-indigo-500 hover:shadow-md transition-all cursor-pointer group flex items-center justify-between"
                            >
                                <div class="flex items-center space-x-3 min-w-0">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 border" :class="getRoleBadgeColor(user.role)">
                                        <el-icon :size="16"><component :is="getRoleIcon(user.role)" /></el-icon>
                                    </div>
                                    <div class="truncate">
                                        <div class="text-xs font-bold text-slate-800 dark:text-slate-100 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition truncate">
                                            {{ user.role_label }}
                                        </div>
                                        <div class="text-[11px] text-slate-400 truncate">{{ user.name }} • {{ user.email }}</div>
                                    </div>
                                </div>
                                <span class="text-[10px] px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-500 dark:text-slate-300 font-semibold group-hover:bg-indigo-600 group-hover:text-white transition shrink-0 ml-2">
                                    {{ t('auth.one_click') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-400 text-center">
                        {{ t('auth.auto_sync_roles') }}
                    </div>
                </div>
            </div>
        </div>
    </el-config-provider>
</template>
