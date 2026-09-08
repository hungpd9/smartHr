<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';
import {
    Briefcase,
    Calendar,
    Clock,
    Refresh,
    Document,
    Search,
    Edit,
    Histogram,
    Collection,
    Coin,
    Timer,
} from '@element-plus/icons-vue';

interface Employee {
    id: number;
    user_code: string;
    full_name: string;
    gender: string;
    join_date: string | null;
    seniority_text?: string;
    current_timeline?: {
        department?: { id: number; name: string };
        position?: { id: number; name: string };
    } | null;
}

interface LeaveTransaction {
    id: number;
    transaction_type: string;
    days: number;
    description: string;
    created_at: string;
}

interface LeaveBalanceItem {
    id: number;
    tenant_id: number;
    employee_id: number;
    year: number;
    leave_type: 'ANNUAL' | 'COMPENSATION';
    carried_over_days: number;
    carried_over_expiry_date: string | null;
    statutory_allocated_days: number;
    seniority_bonus_days: number;
    welfare_bonus_days: number;
    total_entitled_days: number;
    accrued_days_to_date: number;
    used_days: number;
    pending_days: number;
    remaining_days: number;
    comp_off_hours: number;
    employee: Employee;
    transactions?: LeaveTransaction[];
}

interface DepartmentItem {
    id: number;
    name: string;
}

const props = defineProps<{
    balances: LeaveBalanceItem[];
    kpis: LeaveKpis;
    departments: DepartmentItem[];
    employeesList: { id: number; name: string; user_code: string }[];
    filters: {
        year: number;
        department_id: number | null;
        search: string | null;
    };
}>();

const { t } = useThemeAndLocale();

// Filter State
const selectedYear = ref<number>(props.filters.year);
const selectedDepartment = ref<number | null>(props.filters.department_id);
const searchKeyword = ref<string>(props.filters.search || '');

// Tự động đồng bộ bộ lọc khi người dùng bấm Back / Forward trên trình duyệt
watch(() => props.filters, (newFilters) => {
    if (newFilters) {
        selectedYear.value = newFilters.year;
        selectedDepartment.value = newFilters.department_id;
        searchKeyword.value = newFilters.search || '';
    }
}, { deep: true });

const applyFilters = () => {
    router.get(
        '/leave/balances',
        {
            year: selectedYear.value,
            department_id: selectedDepartment.value || undefined,
            search: searchKeyword.value || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const handleYearChange = () => {
    applyFilters();
};

// Modal Điều chỉnh Quỹ phép
const adjustDialogVisible = ref(false);
const activeBalanceForAdjust = ref<LeaveBalanceItem | null>(null);

const adjustForm = useForm({
    leave_balance_id: 0,
    days: 1.0,
    transaction_type: 'ADJUSTMENT',
    description: '',
});

const openAdjustDialog = (balance: LeaveBalanceItem) => {
    activeBalanceForAdjust.value = balance;
    adjustForm.leave_balance_id = balance.id;
    adjustForm.days = 1.0;
    adjustForm.transaction_type = 'ADJUSTMENT';
    adjustForm.description = '';
    adjustDialogVisible.value = true;
};

const submitAdjust = () => {
    if (!adjustForm.description) {
        ElMessage.warning(t('leave.adjust_reason_required'));
        return;
    }

    adjustForm.post('/leave/adjust', {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('leave.adjust_success'));
            adjustDialogVisible.value = false;
        },
        onError: (err) => {
            ElMessage.error(Object.values(err)[0] as string || t('leave.adjust_error'));
        },
    });
};

// Drawer Lịch sử Giao dịch
const historyDrawerVisible = ref(false);
const activeBalanceForHistory = ref<LeaveBalanceItem | null>(null);

const openHistoryDrawer = (balance: LeaveBalanceItem) => {
    activeBalanceForHistory.value = balance;
    historyDrawerVisible.value = true;
};

// Modal Quy đổi OT sang Nghỉ bù
const convertOtDialogVisible = ref(false);
const convertForm = useForm({
    employee_id: null as number | null,
    year: props.filters.year,
    ot_hours: 8.0,
    conversion_rate: 8.0,
    description: '',
});

const openConvertOtDialog = (empId?: number) => {
    convertForm.employee_id = empId || (props.employeesList[0]?.id ?? null);
    convertForm.year = selectedYear.value;
    convertForm.ot_hours = 8.0;
    convertForm.conversion_rate = 8.0;
    convertForm.description = '';
    convertOtDialogVisible.value = true;
};

const estimatedCompOffDays = computed(() => {
    const hours = Number(convertForm.ot_hours) || 0;
    const rate = Number(convertForm.conversion_rate) || 8.0;
    if (rate <= 0) return 0;
    return (hours / rate).toFixed(2);
});

const submitConvertOt = () => {
    if (!convertForm.employee_id) {
        ElMessage.warning(t('leave.convert_employee_required'));
        return;
    }

    convertForm.post('/leave/convert-ot', {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('leave.convert_success'));
            convertOtDialogVisible.value = false;
        },
        onError: (err) => {
            ElMessage.error(Object.values(err)[0] as string || t('leave.convert_error'));
        },
    });
};

// Hành động Tính toán lại Quỹ phép
const isRecalculating = ref(false);
const handleRecalculate = () => {
    ElMessageBox.confirm(
        t('leave.confirm_recalc_msg', { year: selectedYear.value }),
        t('leave.confirm_recalc_title'),
        {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            type: 'warning',
        }
    ).then(() => {
        isRecalculating.value = true;
        router.post(
            '/leave/recalculate',
            { year: selectedYear.value },
            {
                preserveScroll: true,
                onSuccess: () => {
                    ElMessage.success(t('leave.recalc_success'));
                    isRecalculating.value = false;
                },
                onError: () => {
                    ElMessage.error(t('common.error'));
                    isRecalculating.value = false;
                },
            }
        );
    });
};

// Format tag loại giao dịch
const getTransactionTagType = (type: string): string => {
    switch (type) {
        case 'ACCRUAL':
            return 'success';
        case 'USAGE':
            return 'warning';
        case 'EXPIRATION':
            return 'danger';
        case 'COMPENSATION_CONVERT':
            return 'primary';
        default:
            return 'info';
    }
};

const formatTransactionName = (type: string): string => {
    switch (type) {
        case 'ACCRUAL':
            return t('leave.txn_accrual');
        case 'USAGE':
            return t('leave.txn_usage');
        case 'EXPIRATION':
            return t('leave.txn_expiration');
        case 'ADJUSTMENT':
            return t('leave.txn_adjustment');
        case 'COMPENSATION_CONVERT':
            return t('leave.txn_comp_convert');
        default:
            return type;
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="t('leave.page_title')" />

        <div class="space-y-6">
            <!-- Header Banner Doanh nghiệp Chuyên nghiệp -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-emerald-700 via-teal-800 to-slate-900 p-6 sm:p-8 text-white shadow-xl">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-white/10 backdrop-blur-md border border-white/20">
                                <el-icon :size="26" class="text-emerald-300"><Briefcase /></el-icon>
                            </div>
                            <div>
                                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                                    {{ t('leave.title') }}
                                </h1>
                                <p class="mt-1 text-sm text-emerald-100/80">
                                    {{ t('leave.subtitle') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <el-button
                            type="success"
                            plain
                            size="large"
                            :icon="Refresh"
                            :loading="isRecalculating"
                            @click="handleRecalculate"
                            class="!rounded-xl"
                        >
                            {{ t('leave.recalculate_btn', { year: selectedYear }) }}
                        </el-button>

                        <el-button
                            type="warning"
                            size="large"
                            :icon="Coin"
                            @click="openConvertOtDialog()"
                            class="!rounded-xl !text-slate-900 !font-semibold"
                        >
                            {{ t('leave.convert_ot_btn') }}
                        </el-button>
                    </div>
                </div>

                <!-- Decorative overlay -->
                <div class="absolute -right-10 -bottom-10 h-48 w-48 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
            </div>

            <!-- KPI Cards Grid -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>{{ t('leave.kpi_statutory') }}</span>
                        <el-icon class="text-blue-500"><Document /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-800 dark:text-slate-100">
                        {{ kpis.total_statutory_allocated }} <span class="text-xs font-normal text-slate-400">{{ t('leave.days_unit') }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-400">{{ t('leave.statutory_range') }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>{{ t('leave.kpi_seniority') }}</span>
                        <el-icon class="text-emerald-500"><Timer /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">
                        +{{ kpis.total_seniority_bonus }} <span class="text-xs font-normal text-slate-400">{{ t('leave.days_unit') }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-emerald-600/80 dark:text-emerald-400/80">{{ t('leave.seniority_rule') }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>{{ t('leave.kpi_welfare') }}</span>
                        <el-icon class="text-indigo-500"><Collection /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
                        +{{ kpis.total_welfare_bonus }} <span class="text-xs font-normal text-slate-400">{{ t('leave.days_unit') }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-indigo-600/80 dark:text-indigo-400/80">{{ t('leave.from_welfare') }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>{{ t('leave.kpi_carried') }}</span>
                        <el-icon class="text-amber-500"><Calendar /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-amber-600 dark:text-amber-400">
                        {{ kpis.total_carried_over }} <span class="text-xs font-normal text-slate-400">{{ t('leave.days_unit') }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-amber-600/80 dark:text-amber-400/80">{{ t('leave.deadline_march') }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>{{ t('leave.kpi_used_available') }}</span>
                        <el-icon class="text-cyan-500"><Histogram /></el-icon>
                    </div>
                    <div class="mt-2 text-xl font-extrabold text-slate-800 dark:text-slate-100">
                        <span class="text-rose-500">{{ kpis.total_used }}</span> / <span class="text-emerald-600">{{ kpis.total_remaining }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-400">{{ t('leave.annual_leave_balance') }}</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>{{ t('leave.kpi_comp_off') }}</span>
                        <el-icon class="text-purple-500"><Coin /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-purple-600 dark:text-purple-400">
                        {{ kpis.total_comp_off_remaining }} <span class="text-xs font-normal text-slate-400">{{ t('leave.days_unit') }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-purple-600/80 dark:text-purple-400/80">{{ t('leave.ot_accumulated') }}</div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ t('ot_caps.filter_year') }}</span>
                            <el-select
                                v-model="selectedYear"
                                @change="handleYearChange"
                                class="!w-28"
                            >
                                <el-option :value="2024" label="2024" />
                                <el-option :value="2025" label="2025" />
                                <el-option :value="2026" label="2026" />
                                <el-option :value="2027" label="2027" />
                            </el-select>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ t('ot_caps.filter_dept') }}</span>
                            <el-select
                                v-model="selectedDepartment"
                                :placeholder="t('ot_caps.all_departments')"
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
                    </div>

                    <div class="flex items-center gap-3">
                        <el-input
                            v-model="searchKeyword"
                            :placeholder="t('leave.search_placeholder')"
                            :prefix-icon="Search"
                            clearable
                            @clear="applyFilters"
                            @keyup.enter="applyFilters"
                            class="!w-64"
                        />
                        <el-button type="primary" :icon="Search" @click="applyFilters" class="!rounded-xl">
                            {{ t('leave.search_btn') }}
                        </el-button>
                    </div>
                </div>
            </div>

            <!-- Balances Data Table -->
            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden shadow-sm">
                <el-table
                    :data="balances"
                    stripe
                    style="width: 100%"
                    :empty-text="t('leave.empty_balances')"
                >
                    <el-table-column :label="t('leave.col_employee')" min-width="220">
                        <template #default="{ row }">
                            <div class="flex items-center gap-3 py-1">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-sm border border-slate-200 dark:border-slate-700">
                                    {{ row.employee?.full_name?.charAt(0) || 'N' }}
                                </div>
                                <div>
                                    <div class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ row.employee?.full_name }}
                                    </div>
                                    <div class="text-xs text-slate-400 font-mono">
                                        {{ row.employee?.user_code }}
                                    </div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_dept_position')" min-width="180">
                        <template #default="{ row }">
                            <div class="text-xs text-slate-700 dark:text-slate-200 font-medium">
                                {{ row.employee?.current_timeline?.department?.name || t('leave.unassigned') }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ row.employee?.current_timeline?.position?.name || t('leave.position_default') }}
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_seniority')" min-width="130">
                        <template #default="{ row }">
                            <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {{ row.employee?.seniority_text || t('leave.seniority_unknown') }}
                            </div>
                            <div v-if="row.seniority_bonus_days > 0" class="mt-0.5">
                                <el-tag size="small" type="success" effect="plain" class="!text-[10px] !px-1.5">
                                    +{{ row.seniority_bonus_days }}d {{ t('leave.col_seniority') }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_fund_type')" width="140">
                        <template #default="{ row }">
                            <el-tag
                                :type="row.leave_type === 'ANNUAL' ? 'primary' : 'warning'"
                                effect="light"
                                class="!rounded-lg font-semibold"
                            >
                                {{ row.leave_type === 'ANNUAL' ? t('leave.fund_annual') : t('leave.fund_comp_off') }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_allocation')" min-width="210">
                        <template #default="{ row }">
                            <div v-if="row.leave_type === 'ANNUAL'" class="text-xs space-y-0.5">
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                    <span>{{ t('leave.statutory_abbr') }}</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ row.statutory_allocated_days }}d</span>
                                </div>
                                <div v-if="row.seniority_bonus_days > 0" class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                                    <span>{{ t('leave.seniority_abbr') }}</span>
                                    <span class="font-medium">+{{ row.seniority_bonus_days }}d</span>
                                </div>
                                <div v-if="row.welfare_bonus_days > 0" class="flex items-center justify-between text-indigo-600 dark:text-indigo-400">
                                    <span>{{ t('leave.welfare_abbr') }}</span>
                                    <span class="font-medium">+{{ row.welfare_bonus_days }}d</span>
                                </div>
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-0.5 flex items-center justify-between font-bold text-slate-900 dark:text-white">
                                    <span>{{ t('leave.total_entitled_abbr') }}</span>
                                    <span>{{ row.total_entitled_days }}d</span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-purple-600 dark:text-purple-400 font-semibold">
                                {{ row.comp_off_hours }} {{ t('leave.hours_unit') }} OT = {{ row.total_entitled_days }} {{ t('leave.days_unit') }}
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_carried_over')" min-width="150">
                        <template #default="{ row }">
                            <div v-if="row.carried_over_days > 0">
                                <div class="font-bold text-amber-600 dark:text-amber-400">
                                    {{ row.carried_over_days }} {{ t('leave.days_unit') }}
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    {{ t('leave.expiry_label') }} {{ row.carried_over_expiry_date || '31/03' }}
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400">
                                0.00
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_used')" width="100">
                        <template #default="{ row }">
                            <span class="font-semibold text-rose-500">
                                {{ row.used_days }}
                            </span>
                            <span class="text-xs text-slate-400"> / {{ Number(row.total_entitled_days) + Number(row.carried_over_days) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_remaining')" width="140" align="right">
                        <template #default="{ row }">
                            <div class="text-base font-extrabold text-emerald-600 dark:text-emerald-400">
                                {{ row.remaining_days }} <span class="text-xs font-normal text-slate-400">{{ t('leave.days_unit') }}</span>
                            </div>
                            <div v-if="row.pending_days > 0" class="text-[11px] text-amber-500">
                                ({{ t('leave.pending_review') }} {{ row.pending_days }}d)
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('leave.col_actions')" width="180" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="flex items-center justify-center gap-1">
                                <el-tooltip :content="t('leave.tooltip_adjust')" placement="top">
                                    <el-button
                                        size="small"
                                        type="primary"
                                        plain
                                        :icon="Edit"
                                        @click="openAdjustDialog(row)"
                                        class="!p-2"
                                    />
                                </el-tooltip>

                                <el-tooltip :content="t('leave.tooltip_history')" placement="top">
                                    <el-button
                                        size="small"
                                        type="info"
                                        plain
                                        :icon="Clock"
                                        @click="openHistoryDrawer(row)"
                                        class="!p-2"
                                    />
                                </el-tooltip>

                                <el-tooltip :content="t('leave.tooltip_convert')" placement="top">
                                    <el-button
                                        size="small"
                                        type="warning"
                                        plain
                                        :icon="Coin"
                                        @click="openConvertOtDialog(row.employee_id)"
                                        class="!p-2"
                                    />
                                </el-tooltip>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>

        <!-- Dialog: Điều chỉnh Quỹ Phép Thủ Công -->
        <el-dialog
            v-model="adjustDialogVisible"
            :title="t('leave.adjust_dialog_title')"
            width="520px"
            destroy-on-close
            class="!rounded-2xl"
        >
            <div v-if="activeBalanceForAdjust" class="space-y-4">
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">{{ t('leave.label_employee') }}:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ activeBalanceForAdjust.employee?.full_name }} ({{ activeBalanceForAdjust.employee?.user_code }})</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-slate-500">{{ t('leave.fund_year_label') }}</span>
                        <span class="font-semibold text-emerald-600">{{ activeBalanceForAdjust.leave_type === 'ANNUAL' ? t('leave.fund_annual') : t('leave.fund_comp_off') }} - {{ t('leave.year_prefix') }} {{ activeBalanceForAdjust.year }}</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-slate-500">{{ t('leave.current_balance_label') }}</span>
                        <span class="font-bold text-base text-slate-900 dark:text-white">{{ activeBalanceForAdjust.remaining_days }} {{ t('leave.days_unit') }}</span>
                    </div>
                </div>

                <el-form label-position="top">
                    <el-form-item :label="t('leave.tx_type_label')">
                        <el-select v-model="adjustForm.transaction_type" class="w-full">
                            <el-option value="ADJUSTMENT" :label="t('leave.tx_option_adjustment')" />
                            <el-option value="ACCRUAL" :label="t('leave.tx_option_accrual')" />
                            <el-option value="USAGE" :label="t('leave.tx_option_usage')" />
                            <el-option value="EXPIRATION" :label="t('leave.tx_option_expiration')" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('leave.adjust_days_label')">
                        <el-input-number
                            v-model="adjustForm.days"
                            :step="0.5"
                            :precision="2"
                            class="!w-full"
                        />
                        <div class="mt-1 text-xs text-slate-400">
                            {{ t('leave.adjust_days_helper') }}
                        </div>
                    </el-form-item>

                    <el-form-item :label="t('leave.adjust_reason_label')" required>
                        <el-input
                            v-model="adjustForm.description"
                            type="textarea"
                            :rows="3"
                            :placeholder="t('leave.adjust_reason_placeholder')"
                        />
                    </el-form-item>
                </el-form>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="adjustDialogVisible = false">{{ t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="adjustForm.processing" @click="submitAdjust">
                        {{ t('leave.btn_confirm_adjust') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Drawer: Lịch sử Biến động Quỹ Phép -->
        <el-drawer
            v-model="historyDrawerVisible"
            :title="t('leave.history_drawer_title')"
            size="480px"
            destroy-on-close
        >
            <div v-if="activeBalanceForHistory" class="space-y-4">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                    <div class="font-bold text-slate-800 dark:text-slate-100">
                        {{ activeBalanceForHistory.employee?.full_name }} ({{ activeBalanceForHistory.employee?.user_code }})
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        {{ t('leave.history_summary', { pool: activeBalanceForHistory.leave_type, year: activeBalanceForHistory.year, balance: activeBalanceForHistory.remaining_days, days: t('leave.days_unit') }) }}
                    </div>
                </div>

                <div v-if="activeBalanceForHistory.transactions && activeBalanceForHistory.transactions.length > 0" class="mt-4">
                    <el-timeline>
                        <el-timeline-item
                            v-for="trans in activeBalanceForHistory.transactions"
                            :key="trans.id"
                            :type="getTransactionTagType(trans.transaction_type)"
                            :timestamp="trans.created_at"
                            placement="top"
                        >
                            <div class="p-3 rounded-xl border border-slate-200/80 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-xs">
                                <div class="flex items-center justify-between">
                                    <el-tag :type="getTransactionTagType(trans.transaction_type)" size="small" class="font-semibold">
                                        {{ formatTransactionName(trans.transaction_type) }}
                                    </el-tag>
                                    <span class="font-bold font-mono text-sm" :class="Number(trans.days) >= 0 ? 'text-emerald-600' : 'text-rose-500'">
                                        {{ Number(trans.days) >= 0 ? '+' : '' }}{{ trans.days }} {{ t('leave.days_unit') }}
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                    {{ trans.description || t('leave.no_description') }}
                                </div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </div>
                <div v-else class="text-center py-8 text-slate-400 text-sm">
                    {{ t('leave.no_transactions') }}
                </div>
            </div>
        </el-drawer>

        <!-- Dialog: Quy đổi OT sang Nghỉ bù -->
        <el-dialog
            v-model="convertOtDialogVisible"
            :title="t('leave.convert_dialog_title')"
            width="500px"
            destroy-on-close
            class="!rounded-2xl"
        >
            <div class="space-y-4">
                <el-form label-position="top">
                    <el-form-item :label="t('leave.label_employee')" required>
                        <el-select
                            v-model="convertForm.employee_id"
                            filterable
                            :placeholder="t('leave.select_employee_placeholder')"
                            class="w-full"
                        >
                            <el-option
                                v-for="emp in employeesList"
                                :key="emp.id"
                                :value="emp.id"
                                :label="`${emp.full_name} (${emp.user_code})`"
                            />
                        </el-select>
                    </el-form-item>

                    <div class="grid grid-cols-2 gap-3">
                        <el-form-item :label="t('leave.convert_year_label')" required>
                            <el-input-number v-model="convertForm.year" :min="2020" :max="2030" class="!w-full" />
                        </el-form-item>

                        <el-form-item :label="t('leave.convert_rate_label')" required>
                            <el-input-number v-model="convertForm.conversion_rate" :min="1" :max="24" class="!w-full" />
                        </el-form-item>
                    </div>

                    <el-form-item :label="t('leave.convert_hours_label')" required>
                        <el-input-number
                            v-model="convertForm.ot_hours"
                            :step="1.0"
                            :min="0.5"
                            :precision="1"
                            class="!w-full"
                        />
                    </el-form-item>

                    <!-- Kết quả xem trước -->
                    <div class="p-3 rounded-xl bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800 text-purple-900 dark:text-purple-200 text-sm flex items-center justify-between">
                        <span>{{ t('leave.convert_preview_label') }}</span>
                        <span class="text-lg font-bold text-purple-700 dark:text-purple-300">+{{ estimatedCompOffDays }} {{ t('leave.workdays_unit') }}</span>
                    </div>

                    <el-form-item :label="t('leave.convert_note_label')">
                        <el-input
                            v-model="convertForm.description"
                            :placeholder="t('leave.convert_note_placeholder')"
                        />
                    </el-form-item>
                </el-form>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="convertOtDialogVisible = false">{{ t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="convertForm.processing" @click="submitConvertOt">
                        {{ t('leave.btn_submit_convert') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
