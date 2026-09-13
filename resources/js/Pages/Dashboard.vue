<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';
import {
    User,
    CircleCheck,
    MessageBox,
    Warning,
    EditPen,
    Lightning,
    TrendCharts,
    Odometer,
    PieChart,
    Histogram,
    OfficeBuilding,
    CircleCloseFilled,
    SuccessFilled,
    InfoFilled,
    WarningFilled,
    Right,
} from '@element-plus/icons-vue';

const props = defineProps<{
    metrics: {
        totalEmployees: number;
        todayPunchesCount: number;
        pendingRequestsCount: number;
        activeComplianceAlertsCount: number;
        urls?: {
            employees: string;
            attendance: string;
            requests: string;
            compliance: string;
        };
    };
    analytics?: {
        burnoutData: Array<{
            employee_id?: number;
            employee_name: string;
            user_code: string;
            ot_hours: number;
            max_threshold: number;
            status: string;
            badge: string;
            percentage: number;
        }>;
        turnoverData: Array<{
            month: string;
            onboarding: number;
            offboarding: number;
            net_growth: number;
        }>;
        headcountData: Array<{
            name: string;
            count: number;
        }>;
        healthIndex: {
            on_time: number;
            late_early: number;
            leave: number;
            absent: number;
        };
    };
    todayAttendances: Array<{
        employee_id?: number;
        code: string;
        name: string;
        dept: string;
        shift: string;
        in: string;
        out: string;
        status: string;
        ot: string;
    }>;
    careerEvents: Array<{
        date: string;
        status: string;
        title: string;
        desc: string;
    }>;
    policyVersion: string;
    activeAlertsList?: Array<{
        id: number;
        alert_type: string;
        severity: string;
        title: string;
        description: string;
        employee_name: string;
        employee_id?: number;
        user_code?: string;
        created_at: string;
    }>;
}>();

const { t } = useThemeAndLocale();

// Dynamic metrics with navigation URLs
const stats = computed(() => [
    {
        id: 'employees',
        title: t('dashboard.kpi_total_employees'),
        value: props.metrics.totalEmployees.toLocaleString(),
        change: t('dashboard.actual_db'),
        icon: User,
        color: 'indigo',
        url: props.metrics.urls?.employees || '/core-hr',
        isAlert: false,
    },
    {
        id: 'attendance',
        title: t('dashboard.kpi_active_today'),
        value: props.metrics.todayPunchesCount.toLocaleString(),
        change: t('dashboard.realtime_synced'),
        icon: CircleCheck,
        color: 'emerald',
        url: props.metrics.urls?.attendance || '/attendance',
        isAlert: false,
    },
    {
        id: 'requests',
        title: t('dashboard.kpi_pending_approvals'),
        value: props.metrics.pendingRequestsCount.toLocaleString(),
        change: t('dashboard.multi_level_approval'),
        icon: MessageBox,
        color: 'amber',
        url: props.metrics.urls?.requests || '/workflow/requests',
        isAlert: false,
    },
    {
        id: 'alerts',
        title: t('dashboard.system_alerts'),
        value: props.metrics.activeComplianceAlertsCount.toLocaleString(),
        change: t('dashboard.ot_contract_monitor'),
        icon: Warning,
        color: 'rose',
        url: props.metrics.urls?.compliance || '/compliance',
        isAlert: true,
    },
]);

// Attendance & Career data from backend
const todayAttendances = ref(props.todayAttendances);
const careerEvents = ref(props.careerEvents);

// Analytics computations
const burnoutList = computed(() => props.analytics?.burnoutData || []);
const turnoverList = computed(() => props.analytics?.turnoverData || []);
const headcountList = computed(() => props.analytics?.headcountData || []);
const health = computed(() => props.analytics?.healthIndex || {
    on_time: 85,
    late_early: 10,
    leave: 4,
    absent: 1,
});

// Navigation helper
const navigateTo = (url: string) => {
    router.visit(url);
};

const navigateToAttendance = (empId?: number) => {
    if (empId) {
        router.visit(`/attendance?employee_id=${empId}`);
    } else {
        router.visit('/attendance');
    }
};

// Compliance Alerts Explanation Modal
const isAlertsModalOpen = ref(false);

const openAlertsModal = (e?: Event) => {
    if (e) e.stopPropagation();
    isAlertsModalOpen.value = true;
};

// Max value for turnover chart scaling
const maxTurnoverVal = computed(() => {
    if (!turnoverList.value.length) return 5;
    const maxVal = Math.max(
        ...turnoverList.value.map(item => Math.max(item.onboarding, item.offboarding))
    );
    return Math.max(maxVal, 4);
});

// Total Headcount from departments
const totalHeadcount = computed(() => {
    return headcountList.value.reduce((sum, item) => sum + item.count, 0) || props.metrics.totalEmployees || 1;
});

// Palette for Headcount chart
const departmentColors = [
    { bg: 'bg-indigo-500', text: 'text-indigo-500', fill: '#6366f1' },
    { bg: 'bg-emerald-500', text: 'text-emerald-500', fill: '#10b981' },
    { bg: 'bg-amber-500', text: 'text-amber-500', fill: '#f59e0b' },
    { bg: 'bg-sky-500', text: 'text-sky-500', fill: '#0ea5e9' },
    { bg: 'bg-purple-500', text: 'text-purple-500', fill: '#a855f7' },
    { bg: 'bg-rose-500', text: 'text-rose-500', fill: '#f43f5e' },
];

// Helper for burnout bar colors
const getBurnoutProgressColor = (percentage: number) => {
    if (percentage >= 90) return '#f43f5e'; // Rose-500
    if (percentage >= 70) return '#f59e0b'; // Amber-500
    if (percentage >= 40) return '#3b82f6'; // Blue-500
    return '#10b981'; // Emerald-500
};

// Consent Modal for Article 137 Labor Code
const isConsentModalOpen = ref(false);
const isConsented = ref(false);
const consentUrl = ref('');

const openConsentModal = () => {
    isConsentModalOpen.value = true;
};

const submitConsent = () => {
    if (!isConsented.value) {
        ElMessage.error(t('dashboard.consent_required_error'));
        return;
    }
    isConsentModalOpen.value = false;
    ElMessage.success(t('dashboard.consent_saved_success'));
};
</script>

<template>
    <AppLayout>
        <Head :title="t('dashboard.title')" />

        <div class="space-y-6">
            <!-- Hero Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white p-6 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="space-y-2 relative z-10">
                    <div class="inline-flex items-center space-x-2 bg-white/10 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-indigo-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>{{ t('dashboard.hero_tag') }}</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight">{{ t('dashboard.hero_title') }}</h1>
                    <p class="text-sm text-indigo-200/90 max-w-2xl leading-relaxed">
                        {{ t('dashboard.hero_subtitle') }}
                    </p>
                </div>
                <div class="flex items-center space-x-3 relative z-10">
                    <el-button
                        type="info"
                        plain
                        class="!bg-white/10 !border-white/30 !text-white hover:!bg-white/20 !rounded-xl"
                        :icon="EditPen"
                        @click="openConsentModal"
                    >
                        {{ t('dashboard.consent_btn') }}
                    </el-button>
                    <el-button
                        type="primary"
                        class="!bg-indigo-500 hover:!bg-indigo-600 !border-none !rounded-xl !shadow-md font-semibold"
                        :icon="Lightning"
                    >
                        {{ t('dashboard.auto_roster_btn') }}
                    </el-button>
                </div>
            </div>

            <!-- Stats Grid (Clickable Cards with Deep Navigation) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div
                    v-for="stat in stats"
                    :key="stat.title"
                    @click="navigateTo(stat.url)"
                    class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs hover:border-indigo-500/80 dark:hover:border-indigo-500/80 hover:shadow-md transition-all cursor-pointer group flex flex-col justify-between"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition">{{ stat.title }}</span>
                        <div class="flex items-center space-x-1.5">
                            <button
                                v-if="stat.isAlert"
                                @click="openAlertsModal"
                                class="text-[11px] px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300 hover:bg-rose-200 dark:hover:bg-rose-900 transition font-semibold flex items-center gap-1 shrink-0"
                                :title="t('dashboard.explain_alert_btn')"
                            >
                                <el-icon :size="12"><InfoFilled /></el-icon>
                                <span>Giải thích</span>
                            </button>
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center transition group-hover:scale-105"
                                :class="{
                                    'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400': stat.color === 'indigo',
                                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400': stat.color === 'emerald',
                                    'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400': stat.color === 'amber',
                                    'bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400': stat.color === 'rose',
                                }"
                            >
                                <el-icon :size="20">
                                    <component :is="stat.icon" />
                                </el-icon>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight flex items-baseline justify-between">
                            <span>{{ stat.value }}</span>
                            <span class="text-xs text-indigo-600 dark:text-indigo-400 opacity-0 group-hover:opacity-100 transition font-medium flex items-center gap-0.5">
                                Xem ngay <el-icon :size="12"><Right /></el-icon>
                            </span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1 flex items-center space-x-1">
                            <span>{{ stat.change }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Executive Analytics & Compliance Insights Section -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <el-icon class="text-indigo-600 dark:text-indigo-400"><TrendCharts /></el-icon>
                            {{ t('dashboard.analytics_title') }}
                        </h2>
                        <p class="text-xs text-slate-400">
                            {{ t('dashboard.analytics_subtitle') }}
                        </p>
                    </div>
                    <el-tag type="info" size="small" round effect="plain">{{ t('dashboard.analytics_period', { period: '09/2026' }) }}</el-tag>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-5">
                    <!-- Chart 1: Burnout Fatigue Risk Monitor -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-rose-500"><Odometer /></el-icon>
                                    {{ t('dashboard.burnout_title') }}
                                </span>
                                <el-tag size="small" type="danger" round effect="light">{{ t('dashboard.burnout_cap_label') }}</el-tag>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                {{ t('dashboard.burnout_desc') }}
                            </p>

                            <div v-if="burnoutList.length === 0" class="py-6 text-center text-xs text-slate-400">
                                {{ t('dashboard.burnout_empty') }}
                            </div>

                            <div v-else class="space-y-3.5">
                                <div
                                    v-for="item in burnoutList"
                                    :key="item.user_code"
                                    @click="navigateToAttendance(item.employee_id)"
                                    class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800/80 hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-xs transition cursor-pointer group/item"
                                    title="Nhấn để xem chi tiết chấm công của nhân sự này"
                                >
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 group-hover/item:text-indigo-600 dark:group-hover/item:text-indigo-400 transition">{{ item.employee_name }}</span>
                                            <span class="text-[10px] font-mono text-slate-400">({{ item.user_code }})</span>
                                        </div>
                                        <span class="font-mono font-bold" :class="item.percentage >= 90 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-700 dark:text-slate-300'">
                                            {{ item.ot_hours }}h / 40h
                                        </span>
                                    </div>
                                    <el-progress
                                        :percentage="item.percentage"
                                        :color="getBurnoutProgressColor(item.percentage)"
                                        :stroke-width="6"
                                        :show-text="false"
                                    />
                                    <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1.5">
                                        <span>{{ t('dashboard.burnout_used_quota', { percentage: item.percentage }) }}</span>
                                        <span v-if="item.status === 'BREACH'" class="text-rose-600 dark:text-rose-400 font-medium">{{ t('dashboard.burnout_status_breach') }}</span>
                                        <span v-else-if="item.status === 'ALERT'" class="text-amber-600 dark:text-amber-400 font-medium">{{ t('dashboard.burnout_status_alert') }}</span>
                                        <span v-else class="text-emerald-600 dark:text-emerald-400">{{ t('dashboard.burnout_status_safe') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ t('dashboard.burnout_footer_health') }}</span>
                            <span class="font-medium text-indigo-600 dark:text-indigo-400">{{ t('dashboard.burnout_footer_article') }}</span>
                        </div>
                    </div>

                    <!-- Chart 2: Turnover Trend 2026 -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-indigo-500"><Histogram /></el-icon>
                                    {{ t('dashboard.turnover_title') }}
                                </span>
                                <div class="flex items-center gap-2 text-[10px]">
                                    <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                        <span class="w-2 h-2 rounded-sm bg-emerald-500 inline-block"></span> {{ t('dashboard.turnover_legend_in') }}
                                    </span>
                                    <span class="flex items-center gap-1 text-rose-500">
                                        <span class="w-2 h-2 rounded-sm bg-rose-500 inline-block"></span> {{ t('dashboard.turnover_legend_out') }}
                                    </span>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                {{ t('dashboard.turnover_desc') }}
                            </p>

                            <!-- Custom CSS Bar Chart -->
                            <div class="h-44 flex items-end justify-between gap-1 pt-6 pb-2 px-1 border-b border-slate-200 dark:border-slate-800">
                                <div
                                    v-for="item in turnoverList"
                                    :key="item.month"
                                    class="flex-1 flex flex-col items-center gap-1 h-full justify-end group relative"
                                >
                                    <!-- Tooltip hover -->
                                    <div class="absolute -top-10 opacity-0 group-hover:opacity-100 transition pointer-events-none z-20 bg-slate-900 text-white text-[10px] px-2 py-1 rounded shadow whitespace-nowrap">
                                        {{ item.month }}: +{{ item.onboarding }} / -{{ item.offboarding }} ({{ t('dashboard.turnover_net_label') }}: {{ item.net_growth >= 0 ? '+' + item.net_growth : item.net_growth }})
                                    </div>

                                    <!-- Bars Container -->
                                    <div class="w-full flex items-end justify-center gap-0.5 h-full">
                                        <!-- Onboarding Bar -->
                                        <div
                                            class="w-1.5 sm:w-2 bg-emerald-500 rounded-t transition-all group-hover:bg-emerald-400"
                                            :style="{ height: `${(item.onboarding / maxTurnoverVal) * 100}%`, minHeight: item.onboarding > 0 ? '4px' : '0' }"
                                        ></div>
                                        <!-- Offboarding Bar -->
                                        <div
                                            class="w-1.5 sm:w-2 bg-rose-500 rounded-t transition-all group-hover:bg-rose-400"
                                            :style="{ height: `${(item.offboarding / maxTurnoverVal) * 100}%`, minHeight: item.offboarding > 0 ? '4px' : '0' }"
                                        ></div>
                                    </div>

                                    <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400 mt-1">
                                        {{ item.month }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ t('dashboard.turnover_footer_growth') }}</span>
                            <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ t('dashboard.turnover_footer_net') }}</span>
                        </div>
                    </div>

                    <!-- Chart 3: Headcount Distribution by Division -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-indigo-500"><OfficeBuilding /></el-icon>
                                    {{ t('dashboard.headcount_title') }}
                                </span>
                                <el-tag size="small" type="primary" round effect="light">{{ t('dashboard.headcount_tag', { count: totalHeadcount }) }}</el-tag>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                {{ t('dashboard.headcount_desc') }}
                            </p>

                            <!-- Horizontal Multi-segment Bar -->
                            <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 flex overflow-hidden mb-4 shadow-inner">
                                <div
                                    v-for="(dept, idx) in headcountList"
                                    :key="dept.name"
                                    class="h-full transition-all"
                                    :class="departmentColors[idx % departmentColors.length].bg"
                                    :style="{ width: `${(dept.count / totalHeadcount) * 100}%` }"
                                    :title="t('dashboard.headcount_bar_tooltip', { name: dept.name, count: dept.count })"
                                ></div>
                            </div>

                            <!-- List breakdown -->
                            <div class="space-y-2.5">
                                <div
                                    v-for="(dept, idx) in headcountList"
                                    :key="dept.name"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <div class="flex items-center gap-2 truncate pr-2">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :class="departmentColors[idx % departmentColors.length].bg"></span>
                                        <span class="text-slate-700 dark:text-slate-300 font-medium truncate">{{ dept.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-shrink-0 font-mono">
                                        <span class="text-slate-800 dark:text-white font-bold">{{ dept.count }}</span>
                                        <span class="text-[10px] text-slate-400 w-9 text-right">
                                            {{ Math.round((dept.count / totalHeadcount) * 100) }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ t('dashboard.headcount_footer_model') }}</span>
                            <span class="font-medium text-slate-600 dark:text-slate-300">{{ t('dashboard.headcount_footer_levels') }}</span>
                        </div>
                    </div>

                    <!-- Chart 4: Attendance Discipline & Health Index -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-emerald-500"><PieChart /></el-icon>
                                    {{ t('dashboard.health_title') }}
                                </span>
                                <el-tag size="small" type="success" round effect="light">{{ t('dashboard.health_grade_tag') }}</el-tag>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                {{ t('dashboard.health_desc') }}
                            </p>

                            <!-- Visual Rings / Multi-segment circle or metric bars -->
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div class="p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 text-center">
                                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ health.on_time }}%</div>
                                    <div class="text-[10px] font-medium text-emerald-700 dark:text-emerald-300 mt-0.5">{{ t('dashboard.health_on_time_label') }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/40 text-center">
                                    <div class="text-xl font-bold text-amber-600 dark:text-amber-400 font-mono">{{ health.late_early }}%</div>
                                    <div class="text-[10px] font-medium text-amber-700 dark:text-amber-300 mt-0.5">{{ t('dashboard.health_late_label') }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-center">
                                    <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ health.leave }}%</div>
                                    <div class="text-[10px] font-medium text-indigo-700 dark:text-indigo-300 mt-0.5">{{ t('dashboard.health_leave_label') }}</div>
                                </div>
                                <div class="p-3 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/40 text-center">
                                    <div class="text-xl font-bold text-rose-600 dark:text-rose-400 font-mono">{{ health.absent }}%</div>
                                    <div class="text-[10px] font-medium text-rose-700 dark:text-rose-300 mt-0.5">{{ t('dashboard.health_absent_label') }}</div>
                                </div>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                <el-icon class="text-emerald-500 flex-shrink-0"><SuccessFilled /></el-icon>
                                <span>{{ t('dashboard.health_notice') }}</span>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                            <span>{{ t('dashboard.health_footer_rating') }}</span>
                            <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ t('dashboard.health_footer_standard') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Layout: Realtime Attendance Table & Career Timeline -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Today Attendance List (2 Cols) -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-800 dark:text-white">{{ t('dashboard.today_title') }}</h2>
                            <p class="text-xs text-slate-400">{{ t('dashboard.today_subtitle') }}</p>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 font-medium">
                            {{ t('dashboard.live_sync_badge') }}
                        </span>
                    </div>

                    <el-table :data="todayAttendances" size="small" style="width: 100%" stripe>
                        <el-table-column prop="code" :label="t('dashboard.col_employee_code')" width="110">
                            <template #default="{ row }">
                                <span
                                    @click="navigateToAttendance(row.employee_id)"
                                    class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline cursor-pointer"
                                    title="Nhấn để xem bảng chấm công của nhân sự này"
                                >
                                    {{ row.code }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('dashboard.col_employee_name')">
                            <template #default="{ row }">
                                <div
                                    @click="navigateToAttendance(row.employee_id)"
                                    class="font-medium text-slate-800 dark:text-slate-200 text-sm hover:text-indigo-600 dark:hover:text-indigo-400 transition cursor-pointer"
                                    title="Nhấn để xem bảng chấm công của nhân sự này"
                                >
                                    {{ row.name }}
                                </div>
                                <span class="block text-[11px] text-slate-400">{{ row.dept }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="in" :label="t('dashboard.col_check_in')" width="90">
                            <template #default="{ row }">
                                <span class="font-mono text-xs">{{ row.in }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="out" :label="t('dashboard.col_check_out')" width="90">
                            <template #default="{ row }">
                                <span class="font-mono text-xs">{{ row.out }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('dashboard.col_status')" width="140">
                            <template #default="{ row }">
                                <el-tag v-if="row.status === 'ON_TIME'" type="success" size="small" round>{{ t('dashboard.attend_status_on_time') }}</el-tag>
                                <el-tag v-else-if="row.status === 'LATE'" type="warning" size="small" round>{{ t('dashboard.attend_status_late') }}</el-tag>
                                <el-tag v-else-if="row.status === 'WORKING'" type="primary" size="small" round>{{ t('dashboard.attend_status_working') }}</el-tag>
                                <el-tag v-else-if="row.status === 'OVERTIME'" type="danger" size="small" round>{{ t('dashboard.attend_status_overtime') }}</el-tag>
                                <el-tag v-else type="info" size="small" round>{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <!-- Career Timeline Preview (1 Col) -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs p-5">
                    <div class="mb-4">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">{{ t('dashboard.career_title') }}</h2>
                        <p class="text-xs text-slate-400">{{ t('dashboard.career_subtitle') }}</p>
                    </div>

                    <el-timeline class="text-xs">
                        <el-timeline-item
                            v-for="(event, index) in careerEvents"
                            :key="index"
                            :timestamp="event.date"
                            placement="top"
                            type="primary"
                        >
                            <div>
                                <div class="font-semibold text-slate-700 dark:text-slate-200 text-xs">{{ event.title }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ event.desc }}</div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </div>
            </div>
        </div>

        <!-- Consent Modal (Article 137.1.b Labor Code 2019) -->
        <el-dialog
            v-model="isConsentModalOpen"
            :title="t('dashboard.consent_dialog_title')"
            width="560px"
        >
            <div class="space-y-4 text-sm text-slate-600 dark:text-slate-300">
                <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-200 text-xs leading-relaxed">
                    <p class="font-semibold mb-1">{{ t('dashboard.consent_legal_heading') }}</p>
                    {{ t('dashboard.consent_legal_body_1') }} <strong>{{ t('dashboard.consent_legal_emphasis') }}</strong>{{ t('dashboard.consent_legal_body_2') }}
                </div>

                <div class="space-y-2">
                    <el-checkbox v-model="isConsented">
                        <span class="text-xs leading-relaxed text-slate-800 dark:text-slate-200 font-medium whitespace-normal">
                            {{ t('dashboard.consent_checkbox_text') }}
                        </span>
                    </el-checkbox>
                </div>

                <div class="space-y-1 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ t('dashboard.consent_url_label') }}
                    </label>
                    <el-input
                        v-model="consentUrl"
                        :placeholder="t('dashboard.consent_url_placeholder')"
                        size="small"
                    />
                    <span class="text-[11px] text-slate-400">
                        {{ t('dashboard.consent_url_hint') }}
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end space-x-2">
                    <el-button @click="isConsentModalOpen = false">{{ t('dashboard.consent_btn_cancel') }}</el-button>
                    <el-button type="primary" :disabled="!isConsented" @click="submitConsent">
                        {{ t('dashboard.consent_btn_submit') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Compliance Alerts Explanation Modal -->
        <el-dialog
            v-model="isAlertsModalOpen"
            :title="t('dashboard.alerts_dialog_title')"
            width="680px"
        >
            <div class="space-y-4">
                <div class="p-3.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-xl text-xs text-rose-800 dark:text-rose-200 leading-relaxed flex items-start space-x-2.5">
                    <el-icon :size="18" class="text-rose-600 shrink-0 mt-0.5"><WarningFilled /></el-icon>
                    <div>
                        <p class="font-bold mb-0.5">Giám sát Tuân thủ & Phòng ngừa Rủi ro Pháp lý</p>
                        <p class="text-[11px] opacity-90">{{ t('dashboard.alerts_dialog_desc') }}</p>
                    </div>
                </div>

                <div v-if="!activeAlertsList || activeAlertsList.length === 0" class="py-8 text-center text-xs text-slate-400">
                    Hiện không có cảnh báo vi phạm nào đang kích hoạt. Toàn hệ thống đang tuân thủ tốt Bộ luật Lao động.
                </div>

                <div v-else class="space-y-3 max-h-[400px] overflow-y-auto pr-1">
                    <div
                        v-for="alert in activeAlertsList"
                        :key="alert.id"
                        class="p-3.5 rounded-xl border transition-all"
                        :class="{
                            'bg-rose-50/60 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800/70': alert.severity === 'CRITICAL',
                            'bg-amber-50/60 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800/70': alert.severity === 'WARNING',
                            'bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-800': alert.severity === 'INFO',
                        }"
                    >
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <div class="flex items-center gap-2">
                                <el-tag
                                    :type="alert.severity === 'CRITICAL' ? 'danger' : (alert.severity === 'WARNING' ? 'warning' : 'info')"
                                    size="small"
                                    round
                                    effect="dark"
                                >
                                    {{ alert.severity }}
                                </el-tag>
                                <span class="font-bold text-xs text-slate-800 dark:text-slate-100">{{ alert.title }}</span>
                            </div>
                            <span class="text-[10px] text-slate-400 shrink-0">{{ alert.created_at }}</span>
                        </div>

                        <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed mb-2">
                            {{ alert.description }}
                        </p>

                        <div class="flex items-center justify-between text-[11px] pt-2 border-t border-slate-200/60 dark:border-slate-800/60">
                            <div class="text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                <span>Đối tượng:</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ alert.employee_name }}</span>
                                <span v-if="alert.user_code" class="font-mono text-slate-400">({{ alert.user_code }})</span>
                            </div>
                            <button
                                v-if="alert.employee_id"
                                @click="navigateToAttendance(alert.employee_id); isAlertsModalOpen = false;"
                                class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold flex items-center gap-0.5"
                            >
                                <span>Xem bảng công</span>
                                <el-icon :size="10"><Right /></el-icon>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-slate-400">Bộ luật Lao động 2019 & NĐ 145/2020/NĐ-CP</span>
                    <div class="flex items-center space-x-2">
                        <el-button @click="isAlertsModalOpen = false">Đóng</el-button>
                        <el-button
                            type="primary"
                            @click="router.visit('/compliance'); isAlertsModalOpen = false;"
                            class="!bg-indigo-600 hover:!bg-indigo-700 !border-none"
                        >
                            {{ t('dashboard.view_compliance_center') }}
                        </el-button>
                    </div>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>

