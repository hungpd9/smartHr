<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
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
} from '@element-plus/icons-vue';

const props = defineProps<{
    metrics: {
        totalEmployees: number;
        todayPunchesCount: number;
        pendingRequestsCount: number;
        activeComplianceAlertsCount: number;
    };
    analytics?: {
        burnoutData: Array<{
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
}>();

// Dynamic metrics from backend
const stats = ref([
    { title: 'Tổng số Nhân sự', value: props.metrics.totalEmployees.toLocaleString(), change: 'Cơ sở dữ liệu thực tế', icon: User, color: 'indigo' },
    { title: 'Lượt quẹt thẻ hôm nay', value: props.metrics.todayPunchesCount.toLocaleString(), change: 'Đồng bộ từ Edge Daemon', icon: CircleCheck, color: 'emerald' },
    { title: 'Đơn chờ phê duyệt', value: props.metrics.pendingRequestsCount.toLocaleString(), change: 'Quy trình đa cấp', icon: MessageBox, color: 'amber' },
    { title: 'Cảnh báo Tuân thủ (BLLĐ)', value: props.metrics.activeComplianceAlertsCount.toLocaleString(), change: 'Giám sát trần OT & HĐLĐ', icon: Warning, color: 'rose' },
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
        ElMessage.error('Người lao động bắt buộc phải tích cam kết tự nguyện!');
        return;
    }
    isConsentModalOpen.value = false;
    ElMessage.success('Đã ghi nhận cam kết tự nguyện tuân thủ Điều 137 BLLĐ 2019.');
};
</script>

<template>
    <AppLayout>
        <Head title="Tổng quan Điều hành Nhân sự" />

        <div class="space-y-6">
            <!-- Hero Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white p-6 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="space-y-2 relative z-10">
                    <div class="inline-flex items-center space-x-2 bg-white/10 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-indigo-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>Smart-HR Enterprise • Quản trị Nhân sự & Phân ca Thông minh</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Trung tâm Điều hành Nguồn Nhân lực</h1>
                    <p class="text-sm text-indigo-200/90 max-w-2xl leading-relaxed">
                        Chấm công lũy tiến tự động, phân ca thông minh bảo đảm tuyệt đối quy định BLLĐ 2019 và biểu thuế TNCN 5 bậc mới.
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
                        Ký cam kết Điều 137 (Demo)
                    </el-button>
                    <el-button
                        type="primary"
                        class="!bg-indigo-500 hover:!bg-indigo-600 !border-none !rounded-xl !shadow-md font-semibold"
                        :icon="Lightning"
                    >
                        Phân ca Tự động
                    </el-button>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div
                    v-for="stat in stats"
                    :key="stat.title"
                    class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md transition"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ stat.title }}</span>
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center"
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
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">{{ stat.value }}</div>
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
                            Phân tích Điều hành & Giám sát Tuân thủ Lao động
                        </h2>
                        <p class="text-xs text-slate-400">
                            Số liệu trực quan tổng hợp theo tháng & chu kỳ thực tế, hỗ trợ ra quyết định phân bổ nhân sự và phòng ngừa rủi ro
                        </p>
                    </div>
                    <el-tag type="info" size="small" round effect="plain">Kỳ phân tích: 09/2026</el-tag>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-5">
                    <!-- Chart 1: Burnout Fatigue Risk Monitor -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-rose-500"><Odometer /></el-icon>
                                    Nguy cơ Quá tải Lao động
                                </span>
                                <el-tag size="small" type="danger" round effect="light">Trần 40h/th</el-tag>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                Giám sát sát ngưỡng trần làm thêm giờ theo Điều 107 Khoản 2 Điểm b BLLĐ 2019
                            </p>

                            <div v-if="burnoutList.length === 0" class="py-6 text-center text-xs text-slate-400">
                                Không có nhân sự phát sinh làm thêm giờ trong kỳ
                            </div>

                            <div v-else class="space-y-3.5">
                                <div
                                    v-for="item in burnoutList"
                                    :key="item.user_code"
                                    class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-100 dark:border-slate-800/80"
                                >
                                    <div class="flex items-center justify-between text-xs mb-1.5">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ item.employee_name }}</span>
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
                                        <span>Đã dùng {{ item.percentage }}% định mức</span>
                                        <span v-if="item.status === 'BREACH'" class="text-rose-600 dark:text-rose-400 font-medium">⚠️ Vượt trần 40h</span>
                                        <span v-else-if="item.status === 'ALERT'" class="text-amber-600 dark:text-amber-400 font-medium">Cận trần (&gt;35h)</span>
                                        <span v-else class="text-emerald-600 dark:text-emerald-400">An toàn</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                            <span>Bảo vệ sức khỏe & Luật lao động</span>
                            <span class="font-medium text-indigo-600 dark:text-indigo-400">Điều 107 BLLĐ</span>
                        </div>
                    </div>

                    <!-- Chart 2: Turnover Trend (Biến động Nhân sự 2026) -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-indigo-500"><Histogram /></el-icon>
                                    Biến động Nhân sự 2026
                                </span>
                                <div class="flex items-center gap-2 text-[10px]">
                                    <span class="flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                                        <span class="w-2 h-2 rounded-sm bg-emerald-500 inline-block"></span> Vào
                                    </span>
                                    <span class="flex items-center gap-1 text-rose-500">
                                        <span class="w-2 h-2 rounded-sm bg-rose-500 inline-block"></span> Nghỉ
                                    </span>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                Tương quan Tuyển mới (Onboarding) và Thôi việc (Offboarding) 9 tháng đầu năm
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
                                        {{ item.month }}: +{{ item.onboarding }} / -{{ item.offboarding }} (Ròng: {{ item.net_growth >= 0 ? '+' + item.net_growth : item.net_growth }})
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
                            <span>Tăng trưởng quy mô nhân sự</span>
                            <span class="font-medium text-emerald-600 dark:text-emerald-400">+25% ròng năm 2026</span>
                        </div>
                    </div>

                    <!-- Chart 3: Headcount Distribution (Cơ cấu Nhân sự theo Khối) -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-indigo-500"><OfficeBuilding /></el-icon>
                                    Cơ cấu Nhân sự theo Khối
                                </span>
                                <el-tag size="small" type="primary" round effect="light">{{ totalHeadcount }} NV</el-tag>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                Phân bổ tỷ trọng định biên giữa các Khối chức năng và Chi nhánh
                            </p>

                            <!-- Horizontal Multi-segment Bar -->
                            <div class="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 flex overflow-hidden mb-4 shadow-inner">
                                <div
                                    v-for="(dept, idx) in headcountList"
                                    :key="dept.name"
                                    class="h-full transition-all"
                                    :class="departmentColors[idx % departmentColors.length].bg"
                                    :style="{ width: `${(dept.count / totalHeadcount) * 100}%` }"
                                    :title="`${dept.name}: ${dept.count} người`"
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
                            <span>Mô hình phân cấp 3 tầng</span>
                            <span class="font-medium text-slate-600 dark:text-slate-300">Khối &gt; Phòng &gt; Team</span>
                        </div>
                    </div>

                    <!-- Chart 4: Attendance Health Index (Chỉ số Sức khỏe Kỷ luật) -->
                    <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-bold text-slate-700 dark:text-slate-200 flex items-center gap-1.5">
                                    <el-icon class="text-emerald-500"><PieChart /></el-icon>
                                    Chỉ số Sức khỏe Kỷ luật
                                </span>
                                <el-tag size="small" type="success" round effect="light">Hạng A (Tốt)</el-tag>
                            </div>
                            <p class="text-[11px] text-slate-400 leading-tight mb-4">
                                Tỷ lệ tuân thủ giờ giấc làm việc & kỷ luật công tác toàn đơn vị
                            </p>

                            <!-- Visual Rings / Multi-segment circle or metric bars -->
                            <div class="grid grid-cols-2 gap-3 mb-3">
                                <div class="p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/40 text-center">
                                    <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">{{ health.on_time }}%</div>
                                    <div class="text-[10px] font-medium text-emerald-700 dark:text-emerald-300 mt-0.5">Đúng giờ chuẩn</div>
                                </div>
                                <div class="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/40 text-center">
                                    <div class="text-xl font-bold text-amber-600 dark:text-amber-400 font-mono">{{ health.late_early }}%</div>
                                    <div class="text-[10px] font-medium text-amber-700 dark:text-amber-300 mt-0.5">Muộn / Về sớm</div>
                                </div>
                                <div class="p-3 rounded-xl bg-indigo-50/70 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/40 text-center">
                                    <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ health.leave }}%</div>
                                    <div class="text-[10px] font-medium text-indigo-700 dark:text-indigo-300 mt-0.5">Phép có duyệt</div>
                                </div>
                                <div class="p-3 rounded-xl bg-rose-50/70 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/40 text-center">
                                    <div class="text-xl font-bold text-rose-600 dark:text-rose-400 font-mono">{{ health.absent }}%</div>
                                    <div class="text-[10px] font-medium text-rose-700 dark:text-rose-300 mt-0.5">Vắng mặt</div>
                                </div>
                            </div>

                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-2">
                                <el-icon class="text-emerald-500 flex-shrink-0"><SuccessFilled /></el-icon>
                                <span>Tỷ lệ vi phạm giờ công tác duy trì dưới mức cảnh báo quy chế 15%.</span>
                            </div>
                        </div>

                        <div class="pt-3 mt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-[11px] text-slate-400">
                            <span>Đánh giá mức độ chấp hành</span>
                            <span class="font-medium text-emerald-600 dark:text-emerald-400">Đạt chuẩn ISO/HR</span>
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
                            <h2 class="text-base font-bold text-slate-800 dark:text-white">Điểm danh & Quẹt thẻ Hôm nay</h2>
                            <p class="text-xs text-slate-400">Dữ liệu thời gian thực từ Edge Collector Daemon qua HMAC-SHA256</p>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 font-medium">
                            Live Sync Active
                        </span>
                    </div>

                    <el-table :data="todayAttendances" size="small" style="width: 100%" stripe>
                        <el-table-column prop="code" label="Mã NV" width="100">
                            <template #default="{ row }">
                                <span class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ row.code }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Họ và Tên">
                            <template #default="{ row }">
                                <span class="font-medium text-slate-800 dark:text-slate-200 text-sm">{{ row.name }}</span>
                                <span class="block text-[11px] text-slate-400">{{ row.dept }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="in" label="Giờ Vào" width="90">
                            <template #default="{ row }">
                                <span class="font-mono text-xs">{{ row.in }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="out" label="Giờ Ra" width="90">
                            <template #default="{ row }">
                                <span class="font-mono text-xs">{{ row.out }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Trạng thái" width="140">
                            <template #default="{ row }">
                                <el-tag v-if="row.status === 'ON_TIME'" type="success" size="small" round>Đúng giờ</el-tag>
                                <el-tag v-else-if="row.status === 'LATE'" type="warning" size="small" round>Đi muộn (12p)</el-tag>
                                <el-tag v-else-if="row.status === 'WORKING'" type="primary" size="small" round>Đang làm việc</el-tag>
                                <el-tag v-else-if="row.status === 'OVERTIME'" type="danger" size="small" round>OT 2.5h</el-tag>
                                <el-tag v-else type="info" size="small" round>{{ row.status }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>

                <!-- Career Timeline Preview (1 Col) -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs p-5">
                    <div class="mb-4">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Cây Timeline Sự nghiệp</h2>
                        <p class="text-xs text-slate-400">Effective Dating: Quản lý lịch sử nhân sự đa thời điểm</p>
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
            title="Đơn Thỏa thuận Đồng thuận Tự nguyện (Điều 137 BLLĐ 2019)"
            width="560px"
        >
            <div class="space-y-4 text-sm text-slate-600 dark:text-slate-300">
                <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-200 text-xs leading-relaxed">
                    <p class="font-semibold mb-1">CĂN CỨ ĐIỀU 137 KHOẢN 1 ĐIỂM B BỘ LUẬT LAO ĐỘNG 2019:</p>
                    Người sử dụng lao động không được bố trí làm việc ban đêm (22:00 - 06:00) hoặc làm thêm giờ đối với người lao động đang nuôi con dưới 12 tháng tuổi, <strong>TRỪ TRƯỜNG HỢP ĐƯỢC NGƯỜI LAO ĐỘNG ĐỒNG Ý</strong>.
                </div>

                <div class="space-y-2">
                    <el-checkbox v-model="isConsented">
                        <span class="text-xs leading-relaxed text-slate-800 dark:text-slate-200 font-medium whitespace-normal">
                            Tôi xác nhận tự nguyện đồng thuận làm thêm giờ / làm việc ban đêm trong thời gian nuôi con dưới 12 tháng tuổi theo quy định tại Điều 137 BLLĐ 2019.
                        </span>
                    </el-checkbox>
                </div>

                <div class="space-y-1 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Đường dẫn URL văn bản thỏa thuận (Tùy chọn - Giảm tải lưu trữ):
                    </label>
                    <el-input
                        v-model="consentUrl"
                        placeholder="https://drive.google.com/file/d/... hoặc link OneDrive nội bộ"
                        size="small"
                    />
                    <span class="text-[11px] text-slate-400">
                        Hỗ trợ đính kèm URL Google Drive/OneDrive hoặc tải tệp trực tiếp để người duyệt đối soát.
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end space-x-2">
                    <el-button @click="isConsentModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" :disabled="!isConsented" @click="submitConsent">
                        Xác nhận & Gửi cam kết
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>

