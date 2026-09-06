<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
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
    kpis: {
        total_statutory_allocated: number;
        total_seniority_bonus: number;
        total_welfare_bonus: number;
        total_carried_over: number;
        total_used: number;
        total_remaining: number;
        total_comp_off_remaining: number;
    };
    departments: DepartmentItem[];
    employeesList: { id: number; user_code: string; full_name: string }[];
    filters: {
        year: number;
        department_id: number | null;
        search: string | null;
    };
}>();

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
        ElMessage.warning('Vui lòng nhập lý do điều chỉnh để lưu vết kiểm toán.');
        return;
    }

    adjustForm.post('/leave/adjust', {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Điều chỉnh quỹ phép thành công!');
            adjustDialogVisible.value = false;
        },
        onError: (err) => {
            ElMessage.error(Object.values(err)[0] as string || 'Có lỗi xảy ra khi điều chỉnh quỹ phép.');
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
        ElMessage.warning('Vui lòng chọn nhân viên cần quy đổi.');
        return;
    }

    convertForm.post('/leave/convert-ot', {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Quy đổi làm thêm giờ sang nghỉ bù thành công!');
            convertOtDialogVisible.value = false;
        },
        onError: (err) => {
            ElMessage.error(Object.values(err)[0] as string || 'Có lỗi xảy ra khi quy đổi.');
        },
    });
};

// Hành động Tính toán lại Quỹ phép
const isRecalculating = ref(false);
const handleRecalculate = () => {
    ElMessageBox.confirm(
        `Thao tác này sẽ tự động tính lại phép thâm niên, ngày phép phúc lợi và cập nhật hạn dùng phép tồn cho toàn bộ nhân sự năm ${selectedYear.value}. Bạn có chắc chắn muốn thực hiện?`,
        'Xác nhận Tính toán lại Quỹ Phép',
        {
            confirmButtonText: 'Đồng ý',
            cancelButtonText: 'Hủy',
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
                    ElMessage.success('Đã tính toán và cập nhật quỹ phép thành công!');
                    isRecalculating.value = false;
                },
                onError: () => {
                    ElMessage.error('Có lỗi xảy ra khi tính toán quỹ phép.');
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
            return 'Tích lũy phép';
        case 'USAGE':
            return 'Nghỉ phép';
        case 'EXPIRATION':
            return 'Hết hạn phép tồn';
        case 'ADJUSTMENT':
            return 'Điều chỉnh thủ công';
        case 'COMPENSATION_CONVERT':
            return 'Đổi OT sang nghỉ bù';
        default:
            return type;
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Quản trị Quỹ Phép Năm & Nghỉ Bù - Smart-HR Enterprise" />

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
                                    Quản trị Quỹ Phép Năm & Nghỉ Bù
                                </h1>
                                <p class="mt-1 text-sm text-emerald-100/80">
                                    Smart-HR Enterprise • Tự động tăng phép thâm niên (Đ.114 BLLĐ), tích hợp phúc lợi doanh nghiệp & quy đổi làm thêm giờ sang nghỉ bù
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
                            Tính toán & Khởi tạo Năm {{ selectedYear }}
                        </el-button>

                        <el-button
                            type="warning"
                            size="large"
                            :icon="Coin"
                            @click="openConvertOtDialog()"
                            class="!rounded-xl !text-slate-900 !font-semibold"
                        >
                            Quy đổi OT sang Nghỉ bù
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
                        <span>Định mức Chuẩn (Đ.113)</span>
                        <el-icon class="text-blue-500"><Document /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-800 dark:text-slate-100">
                        {{ kpis.total_statutory_allocated }} <span class="text-xs font-normal text-slate-400">ngày</span>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-400">12 - 14 ngày/năm</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>Phép Thâm Niên (Đ.114)</span>
                        <el-icon class="text-emerald-500"><Timer /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-600 dark:text-emerald-400">
                        +{{ kpis.total_seniority_bonus }} <span class="text-xs font-normal text-slate-400">ngày</span>
                    </div>
                    <div class="mt-1 text-[11px] text-emerald-600/80 dark:text-emerald-400/80">+1 ngày / mỗi 5 năm</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>Phép Phúc Lợi Cty</span>
                        <el-icon class="text-indigo-500"><Collection /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
                        +{{ kpis.total_welfare_bonus }} <span class="text-xs font-normal text-slate-400">ngày</span>
                    </div>
                    <div class="mt-1 text-[11px] text-indigo-600/80 dark:text-indigo-400/80">Từ welfare_schemes</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>Phép Tồn Chuyển Sang</span>
                        <el-icon class="text-amber-500"><Calendar /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-amber-600 dark:text-amber-400">
                        {{ kpis.total_carried_over }} <span class="text-xs font-normal text-slate-400">ngày</span>
                    </div>
                    <div class="mt-1 text-[11px] text-amber-600/80 dark:text-amber-400/80">Hạn chót 31/03</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>Đã Dùng / Khả Dụng</span>
                        <el-icon class="text-cyan-500"><Histogram /></el-icon>
                    </div>
                    <div class="mt-2 text-xl font-extrabold text-slate-800 dark:text-slate-100">
                        <span class="text-rose-500">{{ kpis.total_used }}</span> / <span class="text-emerald-600">{{ kpis.total_remaining }}</span>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-400">Số dư phép năm</div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm transition-all hover:shadow-md">
                    <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
                        <span>Quỹ Nghỉ Bù (Comp-off)</span>
                        <el-icon class="text-purple-500"><Coin /></el-icon>
                    </div>
                    <div class="mt-2 text-2xl font-extrabold text-purple-600 dark:text-purple-400">
                        {{ kpis.total_comp_off_remaining }} <span class="text-xs font-normal text-slate-400">ngày</span>
                    </div>
                    <div class="mt-1 text-[11px] text-purple-600/80 dark:text-purple-400/80">Tích lũy từ làm thêm giờ</div>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 p-4 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Năm:</span>
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
                    </div>

                    <div class="flex items-center gap-3">
                        <el-input
                            v-model="searchKeyword"
                            placeholder="Tìm kiếm mã NV, họ tên..."
                            :prefix-icon="Search"
                            clearable
                            @clear="applyFilters"
                            @keyup.enter="applyFilters"
                            class="!w-64"
                        />
                        <el-button type="primary" :icon="Search" @click="applyFilters" class="!rounded-xl">
                            Tìm
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
                    empty-text="Chưa có dữ liệu quỹ phép cho năm này. Vui lòng bấm 'Tính toán & Khởi tạo' phía trên."
                >
                    <el-table-column label="Nhân viên" min-width="220">
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

                    <el-table-column label="Phòng ban / Vị trí" min-width="180">
                        <template #default="{ row }">
                            <div class="text-xs text-slate-700 dark:text-slate-200 font-medium">
                                {{ row.employee?.current_timeline?.department?.name || 'Chưa phân bổ' }}
                            </div>
                            <div class="text-[11px] text-slate-400">
                                {{ row.employee?.current_timeline?.position?.name || 'Nhân viên' }}
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Thâm niên" min-width="130">
                        <template #default="{ row }">
                            <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                {{ row.employee?.seniority_text || 'Chưa rõ' }}
                            </div>
                            <div v-if="row.seniority_bonus_days > 0" class="mt-0.5">
                                <el-tag size="small" type="success" effect="plain" class="!text-[10px] !px-1.5">
                                    +{{ row.seniority_bonus_days }}d thâm niên
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Loại quỹ" width="140">
                        <template #default="{ row }">
                            <el-tag
                                :type="row.leave_type === 'ANNUAL' ? 'primary' : 'warning'"
                                effect="light"
                                class="!rounded-lg font-semibold"
                            >
                                {{ row.leave_type === 'ANNUAL' ? 'Phép Năm' : 'Nghỉ Bù OT' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Cơ cấu định mức" min-width="210">
                        <template #default="{ row }">
                            <div v-if="row.leave_type === 'ANNUAL'" class="text-xs space-y-0.5">
                                <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
                                    <span>Chuẩn (Đ.113):</span>
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ row.statutory_allocated_days }}d</span>
                                </div>
                                <div v-if="row.seniority_bonus_days > 0" class="flex items-center justify-between text-emerald-600 dark:text-emerald-400">
                                    <span>Thâm niên (Đ.114):</span>
                                    <span class="font-medium">+{{ row.seniority_bonus_days }}d</span>
                                </div>
                                <div v-if="row.welfare_bonus_days > 0" class="flex items-center justify-between text-indigo-600 dark:text-indigo-400">
                                    <span>Phúc lợi Cty:</span>
                                    <span class="font-medium">+{{ row.welfare_bonus_days }}d</span>
                                </div>
                                <div class="border-t border-slate-100 dark:border-slate-800 pt-0.5 flex items-center justify-between font-bold text-slate-900 dark:text-white">
                                    <span>Tổng hưởng:</span>
                                    <span>{{ row.total_entitled_days }}d</span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-purple-600 dark:text-purple-400 font-semibold">
                                {{ row.comp_off_hours }} giờ OT quy đổi = {{ row.total_entitled_days }} ngày nghỉ bù
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Phép tồn (Carry-over)" min-width="150">
                        <template #default="{ row }">
                            <div v-if="row.carried_over_days > 0">
                                <div class="font-bold text-amber-600 dark:text-amber-400">
                                    {{ row.carried_over_days }} ngày
                                </div>
                                <div class="text-[11px] text-slate-400">
                                    Hạn: {{ row.carried_over_expiry_date || '31/03' }}
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400">
                                0.00
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Đã dùng" width="100">
                        <template #default="{ row }">
                            <span class="font-semibold text-rose-500">
                                {{ row.used_days }}
                            </span>
                            <span class="text-xs text-slate-400"> / {{ Number(row.total_entitled_days) + Number(row.carried_over_days) }}</span>
                        </template>
                    </el-table-column>

                    <el-table-column label="Khả dụng còn lại" width="140" align="right">
                        <template #default="{ row }">
                            <div class="text-base font-extrabold text-emerald-600 dark:text-emerald-400">
                                {{ row.remaining_days }} <span class="text-xs font-normal text-slate-400">ngày</span>
                            </div>
                            <div v-if="row.pending_days > 0" class="text-[11px] text-amber-500">
                                (Đang duyệt: {{ row.pending_days }}d)
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Thao tác" width="180" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="flex items-center justify-center gap-1">
                                <el-tooltip content="Điều chỉnh số dư quỹ phép" placement="top">
                                    <el-button
                                        size="small"
                                        type="primary"
                                        plain
                                        :icon="Edit"
                                        @click="openAdjustDialog(row)"
                                        class="!p-2"
                                    />
                                </el-tooltip>

                                <el-tooltip content="Xem lịch sử biến động quỹ phép" placement="top">
                                    <el-button
                                        size="small"
                                        type="info"
                                        plain
                                        :icon="Clock"
                                        @click="openHistoryDrawer(row)"
                                        class="!p-2"
                                    />
                                </el-tooltip>

                                <el-tooltip content="Quy đổi giờ làm thêm sang nghỉ bù" placement="top">
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
            title="Điều chỉnh Quỹ Phép Thủ Công"
            width="520px"
            destroy-on-close
            class="!rounded-2xl"
        >
            <div v-if="activeBalanceForAdjust" class="space-y-4">
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-sm">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Nhân viên:</span>
                        <span class="font-bold text-slate-800 dark:text-slate-200">{{ activeBalanceForAdjust.employee?.full_name }} ({{ activeBalanceForAdjust.employee?.user_code }})</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-slate-500">Loại quỹ / Năm:</span>
                        <span class="font-semibold text-emerald-600">{{ activeBalanceForAdjust.leave_type === 'ANNUAL' ? 'Phép Năm' : 'Nghỉ Bù' }} - Năm {{ activeBalanceForAdjust.year }}</span>
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-slate-500">Số dư khả dụng hiện tại:</span>
                        <span class="font-bold text-base text-slate-900 dark:text-white">{{ activeBalanceForAdjust.remaining_days }} ngày</span>
                    </div>
                </div>

                <el-form label-position="top">
                    <el-form-item label="Loại biến động">
                        <el-select v-model="adjustForm.transaction_type" class="w-full">
                            <el-option value="ADJUSTMENT" label="Điều chỉnh thủ công (ADJUSTMENT)" />
                            <el-option value="ACCRUAL" label="Cộng bù tích lũy (ACCRUAL)" />
                            <el-option value="USAGE" label="Trừ ngày phép đã dùng (USAGE)" />
                            <el-option value="EXPIRATION" label="Thu hồi hết hạn phép tồn (EXPIRATION)" />
                        </el-select>
                    </el-form-item>

                    <el-form-item label="Số ngày biến động (+ tăng thêm, - trừ bớt)">
                        <el-input-number
                            v-model="adjustForm.days"
                            :step="0.5"
                            :precision="2"
                            class="!w-full"
                        />
                        <div class="mt-1 text-xs text-slate-400">
                            Ví dụ: Nhập 1.0 để cộng thêm 1 ngày; nhập -0.5 để giảm trừ nửa ngày.
                        </div>
                    </el-form-item>

                    <el-form-item label="Lý do điều chỉnh (Bắt buộc lưu vết kiểm toán Audit Trail)" required>
                        <el-input
                            v-model="adjustForm.description"
                            type="textarea"
                            :rows="3"
                            placeholder="Ghi rõ căn cứ quyết định, đơn từ đính kèm hoặc sai số cần hiệu chỉnh..."
                        />
                    </el-form-item>
                </el-form>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="adjustDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="adjustForm.processing" @click="submitAdjust">
                        Xác nhận Điều chỉnh
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Drawer: Lịch sử Biến động Quỹ Phép -->
        <el-drawer
            v-model="historyDrawerVisible"
            title="Lịch sử Biến động Quỹ Phép"
            size="480px"
            destroy-on-close
        >
            <div v-if="activeBalanceForHistory" class="space-y-4">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                    <div class="font-bold text-slate-800 dark:text-slate-100">
                        {{ activeBalanceForHistory.employee?.full_name }} ({{ activeBalanceForHistory.employee?.user_code }})
                    </div>
                    <div class="text-xs text-slate-500 mt-0.5">
                        Quỹ: {{ activeBalanceForHistory.leave_type }} • Năm: {{ activeBalanceForHistory.year }} • Số dư: {{ activeBalanceForHistory.remaining_days }} ngày
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
                                        {{ Number(trans.days) >= 0 ? '+' : '' }}{{ trans.days }} ngày
                                    </span>
                                </div>
                                <div class="mt-2 text-xs text-slate-600 dark:text-slate-300">
                                    {{ trans.description || 'Không có mô tả' }}
                                </div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </div>
                <div v-else class="text-center py-8 text-slate-400 text-sm">
                    Chưa có giao dịch biến động nào được ghi nhận.
                </div>
            </div>
        </el-drawer>

        <!-- Dialog: Quy đổi OT sang Nghỉ bù -->
        <el-dialog
            v-model="convertOtDialogVisible"
            title="Quy đổi Giờ Làm Thêm (OT) sang Nghỉ Bù"
            width="500px"
            destroy-on-close
            class="!rounded-2xl"
        >
            <div class="space-y-4">
                <el-form label-position="top">
                    <el-form-item label="Nhân viên" required>
                        <el-select
                            v-model="convertForm.employee_id"
                            filterable
                            placeholder="Chọn nhân sự"
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
                        <el-form-item label="Năm áp dụng" required>
                            <el-input-number v-model="convertForm.year" :min="2020" :max="2030" class="!w-full" />
                        </el-form-item>

                        <el-form-item label="Tỷ lệ quy đổi (giờ/ngày)" required>
                            <el-input-number v-model="convertForm.conversion_rate" :min="1" :max="24" class="!w-full" />
                        </el-form-item>
                    </div>

                    <el-form-item label="Số giờ làm thêm (OT) quy đổi" required>
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
                        <span>Số ngày nghỉ bù tích lũy:</span>
                        <span class="text-lg font-bold text-purple-700 dark:text-purple-300">+{{ estimatedCompOffDays }} ngày công</span>
                    </div>

                    <el-form-item label="Ghi chú quy đổi">
                        <el-input
                            v-model="convertForm.description"
                            placeholder="VD: Nhân viên đăng ký nghỉ bù thay vì nhận lương OT..."
                        />
                    </el-form-item>
                </el-form>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="convertOtDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="convertForm.processing" @click="submitConvertOt">
                        Thực hiện Quy đổi
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
