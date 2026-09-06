<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Clock,
    Refresh,
    Search,
    Histogram,
    TrendCharts,
} from '@element-plus/icons-vue';

interface Employee {
    id: number;
    user_code: string;
    full_name: string;
    current_timeline?: {
        department?: { id: number; name: string };
        position?: { id: number; name: string };
    } | null;
}

interface OvertimeAccumulationItem {
    id: number;
    tenant_id: number;
    employee_id: number;
    year: number;
    total_ot_hours_year: number;
    monthly_ot_json: Record<string, number> | null;
    is_exceeded_200h: boolean;
    is_exceeded_300h: boolean;
    employee: Employee;
}

interface DepartmentItem {
    id: number;
    name: string;
}

interface ComplianceSummary {
    year: number;
    total_employees_tracked: number;
    total_ot_hours: number;
    exceeded_200h_count: number;
    exceeded_300h_count: number;
    warning_monthly_count: number;
    breach_monthly_count: number;
    compliance_rate: number;
}

const props = defineProps<{
    accumulations: OvertimeAccumulationItem[];
    summary: ComplianceSummary;
    departments: DepartmentItem[];
    filters: {
        year: number;
        department_id: number | null;
        warning_filter: string | null;
    };
}>();

const selectedYear = ref<number>(props.filters.year);
const selectedDepartment = ref<number | null>(props.filters.department_id);
const selectedWarningFilter = ref<string>(props.filters.warning_filter || 'all');
const isSyncing = ref<boolean>(false);

const applyFilters = () => {
    router.get(
        '/leave/ot-accumulations',
        {
            year: selectedYear.value,
            department_id: selectedDepartment.value || undefined,
            warning_filter: selectedWarningFilter.value === 'all' ? undefined : selectedWarningFilter.value,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const handleYearChange = () => {
    applyFilters();
};

const handleSync = () => {
    isSyncing.value = true;
    router.post(
        '/leave/sync-ot',
        { year: selectedYear.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success(`Đồng bộ giờ làm thêm năm ${selectedYear.value} thành công!`);
                isSyncing.value = false;
            },
            onError: () => {
                ElMessage.error('Có lỗi xảy ra khi đồng bộ giờ OT.');
                isSyncing.value = false;
            },
        }
    );
};

const monthKeys = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12'];

// Tính % đạt trần 200h
const calculatePercentage = (hours: number): number => {
    return Math.min(100, Math.round((hours / 200) * 100));
};

const getProgressStatus = (hours: number): '' | 'success' | 'warning' | 'exception' => {
    if (hours > 200) return 'exception';
    if (hours >= 160) return 'warning';
    return 'success';
};

// Xác định màu cell tháng theo nấc tuân thủ
const getMonthCellStyle = (hours: number) => {
    if (hours > 40.0) {
        return 'bg-rose-50 text-rose-700 font-bold border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900';
    }
    if (hours >= 35.0) {
        return 'bg-amber-50 text-amber-700 font-semibold border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900';
    }
    if (hours > 0) {
        return 'text-slate-800 dark:text-slate-200 font-medium';
    }
    return 'text-slate-300 dark:text-slate-600 font-normal';
};
</script>

<template>
    <AppLayout>
        <Head title="Giám Sát Trần Làm Thêm Giờ (OT) - Smart-HR Enterprise" />

        <div class="space-y-6">
            <!-- Header Banner Doanh nghiệp Chuyên nghiệp -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-950 p-6 sm:p-8 text-white shadow-xl">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 backdrop-blur-md border border-white/20">
                                <el-icon :size="26" class="text-blue-300"><Clock /></el-icon>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                                    Giám Sát Trần Làm Thêm Giờ (OT Compliance)
                                </h1>
                                <p class="mt-1 text-sm text-blue-100/80">
                                    Smart-HR Enterprise • Kiểm soát 3 tầng trần: Ngày 50%, Tháng 40h, Năm 200h/300h theo Điều 107 BLLĐ & Nghị định 145/2020
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <el-button
                            type="primary"
                            size="large"
                            :icon="Refresh"
                            :loading="isSyncing"
                            @click="handleSync"
                            class="!rounded-xl !bg-blue-600 hover:!bg-blue-500 !border-none"
                        >
                            Đồng bộ lại Giờ OT Năm {{ selectedYear }}
                        </el-button>
                    </div>
                </div>

                <!-- Decorative blur overlay -->
                <div class="absolute -right-12 -bottom-12 h-48 w-48 rounded-full bg-blue-500/10 blur-3xl pointer-events-none"></div>
            </div>

            <!-- KPI Cards Tuân Thủ Pháp Luật -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-medium">
                        <span>Tổng Giờ OT Toàn Cty</span>
                        <el-icon class="text-blue-500"><Histogram /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900 dark:text-slate-100">
                        {{ summary.total_ot_hours }} <span class="text-xs font-normal text-slate-400">h</span>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-400">{{ summary.total_employees_tracked }} nhân sự theo dõi</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-medium">
                        <span>Vượt Trần 200h Năm</span>
                        <el-icon class="text-rose-500"><Warning /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold" :class="summary.exceeded_200h_count > 0 ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100'">
                        {{ summary.exceeded_200h_count }} <span class="text-xs font-normal text-slate-400">người</span>
                    </div>
                    <div class="mt-1 text-[11px] text-rose-500">Giới hạn luật định</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-medium">
                        <span>Vượt Trần 300h Đặc Thù</span>
                        <el-icon class="text-rose-700"><CircleClose /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold" :class="summary.exceeded_300h_count > 0 ? 'text-rose-700' : 'text-slate-800 dark:text-slate-100'">
                        {{ summary.exceeded_300h_count }} <span class="text-xs font-normal text-slate-400">người</span>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-400">Ngành nghề đặc biệt</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-medium">
                        <span>Vượt 40h Tháng Hiện Tại</span>
                        <el-icon class="text-rose-500"><Warning /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold" :class="summary.breach_monthly_count > 0 ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100'">
                        {{ summary.breach_monthly_count }} <span class="text-xs font-normal text-slate-400">người</span>
                    </div>
                    <div class="mt-1 text-[11px] text-rose-500">Vi phạm Điều 107</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-medium">
                        <span>Cảnh Báo Chạm (35h-40h)</span>
                        <el-icon class="text-amber-500"><TrendCharts /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-amber-600">
                        {{ summary.warning_monthly_count }} <span class="text-xs font-normal text-slate-400">người</span>
                    </div>
                    <div class="mt-1 text-[11px] text-amber-600/80">Tiệm cận trần tháng</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-medium">
                        <span>Tỷ Lệ Tuân Thủ</span>
                        <el-icon class="text-emerald-500"><CircleCheck /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-600">
                        {{ summary.compliance_rate }}%
                    </div>
                    <div class="mt-1 text-[11px] text-emerald-600/80">Chỉ số BLLĐ</div>
                </div>
            </div>

            <!-- Toolbar & Filter -->
            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Năm:</span>
                            <el-select v-model="selectedYear" @change="handleYearChange" class="!w-28">
                                <el-option :value="2024" label="2024" />
                                <el-option :value="2025" label="2025" />
                                <el-option :value="2026" label="2026" />
                                <el-option :value="2027" label="2027" />
                            </el-select>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Phòng ban:</span>
                            <el-select
                                v-model="selectedDepartment"
                                placeholder="Tất cả phòng ban"
                                clearable
                                @change="applyFilters"
                                class="!w-48"
                            >
                                <el-option
                                    v-for="dept in departments"
                                    :key="dept.id"
                                    :label="dept.name"
                                    :value="dept.id"
                                />
                            </el-select>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Trạng thái:</span>
                            <el-radio-group v-model="selectedWarningFilter" size="small" @change="applyFilters">
                                <el-radio-button value="all">Tất cả</el-radio-button>
                                <el-radio-button value="warning">Tiệm cận (35h-40h)</el-radio-button>
                                <el-radio-button value="breached">Vi phạm trần</el-radio-button>
                            </el-radio-group>
                        </div>
                    </div>

                    <!-- Ghi chú phân loại màu sắc -->
                    <div class="flex items-center gap-4 text-xs text-slate-500">
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                            <span>&le; 30h (An toàn)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                            <span>35h - 40h (Cảnh báo)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="inline-block h-2.5 w-2.5 rounded-full bg-rose-500"></span>
                            <span>&gt; 40h (Vi phạm luật)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Overtime Grid Matrix Table -->
            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                <el-table
                    :data="accumulations"
                    stripe
                    style="width: 100%"
                    empty-text="Chưa có dữ liệu làm thêm giờ cho năm này. Vui lòng bấm 'Đồng bộ lại Giờ OT'."
                >
                    <el-table-column label="Nhân sự" min-width="200" fixed="left">
                        <template #default="{ row }">
                            <div class="flex items-center gap-2.5 py-1">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold text-xs border border-slate-200 dark:border-slate-700">
                                    {{ row.employee?.full_name?.charAt(0) || 'N' }}
                                </div>
                                <div>
                                    <div class="font-semibold text-xs text-slate-900 dark:text-slate-100">
                                        {{ row.employee?.full_name }}
                                    </div>
                                    <div class="text-[11px] text-slate-400 font-mono">
                                        {{ row.employee?.user_code }} • {{ row.employee?.current_timeline?.department?.name || 'Chưa rõ' }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Tổng OT Năm / Trần 200h" min-width="210">
                        <template #default="{ row }">
                            <div class="space-y-1">
                                <div class="flex justify-between items-center text-xs">
                                    <span class="font-extrabold text-sm" :class="row.is_exceeded_200h ? 'text-rose-600' : 'text-slate-800 dark:text-slate-100'">
                                        {{ row.total_ot_hours_year }}h
                                    </span>
                                    <span class="text-[11px] text-slate-400">
                                        {{ calculatePercentage(row.total_ot_hours_year) }}% của 200h
                                    </span>
                                </div>
                                <el-progress
                                    :percentage="calculatePercentage(row.total_ot_hours_year)"
                                    :status="getProgressStatus(row.total_ot_hours_year)"
                                    :stroke-width="6"
                                    :show-text="false"
                                />
                            </div>
                        </template>
                    </el-table-column>

                    <!-- 12 Tháng Chi Tiết -->
                    <el-table-column
                        v-for="m in monthKeys"
                        :key="m"
                        :label="`T${m}`"
                        width="68"
                        align="center"
                    >
                        <template #default="{ row }">
                            <div
                                class="py-1 px-0.5 rounded text-xs transition-colors"
                                :class="getMonthCellStyle(row.monthly_ot_json?.[m] || 0)"
                            >
                                {{ row.monthly_ot_json?.[m] ? `${row.monthly_ot_json[m]}h` : '-' }}
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Tuân thủ BLLĐ" width="150" align="center" fixed="right">
                        <template #default="{ row }">
                            <div v-if="row.is_exceeded_300h">
                                <el-tag type="danger" effect="dark" size="small" class="font-bold">
                                    VƯỢT TRẦN 300H
                                </el-tag>
                            </div>
                            <div v-else-if="row.is_exceeded_200h">
                                <el-tag type="danger" effect="dark" size="small" class="font-bold">
                                    VƯỢT TRẦN 200H
                                </el-tag>
                            </div>
                            <div v-else-if="Object.values(row.monthly_ot_json || {}).some((h: any) => Number(h) > 40.0)">
                                <el-tag type="danger" effect="plain" size="small" class="font-semibold">
                                    Vượt 40h tháng
                                </el-tag>
                            </div>
                            <div v-else-if="Object.values(row.monthly_ot_json || {}).some((h: any) => Number(h) >= 35.0)">
                                <el-tag type="warning" effect="plain" size="small" class="font-semibold">
                                    Tiệm cận 40h
                                </el-tag>
                            </div>
                            <div v-else>
                                <el-tag type="success" effect="plain" size="small">
                                    Chuẩn tuân thủ
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    </AppLayout>
</template>
