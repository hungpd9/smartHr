<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';
import {
    Clock,
    Calendar,
    User,
    Check,
    Close,
    Warning,
    Lock,
    Unlock,
    Refresh,
    Document,
    InfoFilled,
    View,
    ChatDotRound,
    Search,
    Filter,
    ArrowRight,
    CircleCheck,
    CircleClose,
    TopRight,
    OfficeBuilding,
} from '@element-plus/icons-vue';

interface EmployeeItem {
    id: number;
    user_code: string;
    full_name: string;
    gender: string;
    current_timeline?: {
        job_title?: string;
        department?: {
            name?: string;
            code?: string;
        };
    };
}

interface TimesheetDay {
    work_date: string;
    day_name: string;
    is_weekend: boolean;
    roster?: {
        id: number;
        shift?: {
            code: string;
            name: string;
            start_time: string;
            end_time: string;
        };
    } | null;
    actual_in: string | null;
    actual_out: string | null;
    standard_hours: number;
    late_minutes: number;
    early_minutes: number;
    late_penalty_amount: number;
    ot_day_hours: number;
    ot_night_hours: number;
    ot_weekend_hours: number;
    ot_holiday_hours: number;
    status: 'PRESENT' | 'LATE_EARLY' | 'ABSENT' | 'LEAVE_PAID' | 'LEAVE_UNPAID' | 'HOLIDAY' | string;
    is_preview_modified: boolean;
    pending_request_type: string | null;
    preview_note: string | null;
}

interface TimesheetSummary {
    total_standard_days: number;
    total_standard_hours: number;
    total_ot_hours: number;
    total_late_minutes: number;
    total_penalty_amount: number;
    pending_requests_count: number;
}

interface CalendarDay {
    date: string;
    day_number: number;
    day_name: string;
    is_weekend: boolean;
}

interface MatrixCell {
    symbol: string;
    std_hours: number;
    ot_hours: number;
    late_minutes: number;
    is_late: boolean;
    actual_in: string | null;
    actual_out: string | null;
}

interface MatrixRow {
    employee_id: number;
    user_code: string;
    attendance_code: string;
    full_name: string;
    department_name: string;
    branch_name: string;
    position_title: string;
    attendance_id?: number | null;
    submission_status: 'EMPLOYEE_REVIEW' | 'SUBMITTED' | 'MANAGER_APPROVED' | 'HIGHER_MANAGER_APPROVED' | 'REJECTED' | 'HR_LOCKED' | string;
    submission_type: 'MANUAL' | 'AUTO_SUBMITTED' | string;
    submitted_at?: string | null;
    rejection_reason?: string | null;
    is_locked: boolean;
    total_standard_days: number;
    total_ot_hours: number;
    total_late_minutes: number;
    total_penalty_amount: number;
    days: Record<string, MatrixCell>;
}

interface WorkDateRow {
    employee_id: number;
    user_code: string;
    attendance_code: string;
    full_name: string;
    department_name: string;
    branch_name: string;
    position_title: string;
    shift_name: string;
    shift_time: string;
    actual_in: string | null;
    actual_out: string | null;
    standard_hours: number;
    late_minutes: number;
    early_minutes: number;
    ot_hours: number;
    late_penalty_amount: number;
    status: string;
}

const props = defineProps<{
    view: 'workmonth' | 'workdate' | 'detail';
    monthYear: string;
    workDate: string;
    mode: 'official' | 'preview';
    isLocked: boolean;
    filters: {
        branch_id: number | null;
        department_id: number | null;
        search: string | null;
    };
    branches: Array<{ id: number; name: string; code: string }>;
    departments: Array<{ id: number; name: string; code: string; branch_id: number }>;
    lifecyclePhase: {
        target_month: string;
        processing_month: string;
        current_day: number;
        is_processing_month: boolean;
        configs: {
            enable_auto_submit: boolean;
            emp_review_deadline_day: number;
            auto_submit_day: number;
            manager_approval_deadline_day: number;
            higher_manager_deadline_day: number;
            hr_lock_deadline_day: number;
            payroll_payment_day: number;
        };
        current_step: number;
        phase_key: string;
        phase_title: string;
        phase_desc: string;
        can_employee_submit: boolean;
        can_manager_approve: boolean;
        can_hr_lock: boolean;
    };
    workMonthData?: {
        calendar_days: CalendarDay[];
        rows: MatrixRow[];
        total_count: number;
    } | null;
    workDateData?: {
        work_date: string;
        day_name: string;
        is_weekend: boolean;
        stats: {
            total_employees: number;
            present_count: number;
            late_count: number;
            early_count: number;
            absent_count: number;
            ot_count: number;
        };
        rows: WorkDateRow[];
    } | null;
    employees: EmployeeItem[];
    selectedEmployee?: EmployeeItem;
    days: TimesheetDay[];
    summary: TimesheetSummary;
    canCalculate: boolean;
    canLock: boolean;
    canApprove: boolean;
}>();

const { t } = useThemeAndLocale();

// State điều hướng View
const currentView = ref(props.view || 'workmonth');
const currentMonth = ref(props.monthYear);
const currentWorkDate = ref(props.workDate);
const selectedEmpId = ref(props.selectedEmployee?.id || props.employees[0]?.id || null);
const currentMode = ref(props.mode);

// Bộ lọc
const filterBranchId = ref<number | null>(props.filters?.branch_id || null);
const filterDepartmentId = ref<number | null>(props.filters?.department_id || null);
const searchQuery = ref(props.filters?.search || '');

// Đồng bộ khi URL thay đổi (Browser Back/Forward)
watch(() => props.view, (val) => { if (val) currentView.value = val; });
watch(() => props.monthYear, (val) => { if (val) currentMonth.value = val; });
watch(() => props.workDate, (val) => { if (val) currentWorkDate.value = val; });
watch(() => props.filters?.branch_id, (val) => { filterBranchId.value = val ?? null; });
watch(() => props.filters?.department_id, (val) => { filterDepartmentId.value = val ?? null; });
watch(() => props.filters?.search, (val) => { searchQuery.value = val || ''; });

// Xử lý chuyển view và lọc
const handleNavigate = (overrideParams: Record<string, any> = {}) => {
    router.get('/attendance', {
        view: currentView.value,
        month_year: currentMonth.value,
        work_date: currentWorkDate.value,
        mode: currentMode.value,
        employee_id: selectedEmpId.value,
        branch_id: filterBranchId.value,
        department_id: filterDepartmentId.value,
        search: searchQuery.value ? searchQuery.value : undefined,
        ...overrideParams,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const changeView = (newView: 'workmonth' | 'workdate' | 'detail') => {
    currentView.value = newView;
    handleNavigate({ view: newView });
};

// Chọn hàng loạt trong Ma trận WorkMonth
const selectedRowIds = ref<number[]>([]);
const isAllSelected = computed(() => {
    const rows = props.workMonthData?.rows || [];
    if (!rows.length) return false;
    const validRows = rows.filter(r => r.attendance_id);
    return validRows.length > 0 && selectedRowIds.value.length === validRows.length;
});

const handleToggleSelectAll = (checked: boolean) => {
    if (!checked) {
        selectedRowIds.value = [];
        return;
    }
    const rows = props.workMonthData?.rows || [];
    selectedRowIds.value = rows.filter(r => r.attendance_id).map(r => r.attendance_id as number);
};

const toggleSelectRow = (attendanceId?: number | null) => {
    if (!attendanceId) return;
    const idx = selectedRowIds.value.indexOf(attendanceId);
    if (idx > -1) {
        selectedRowIds.value.splice(idx, 1);
    } else {
        selectedRowIds.value.push(attendanceId);
    }
};

// Xem chi tiết một nhân viên cụ thể từ bảng
const viewEmployeeDetail = (employeeId: number) => {
    selectedEmpId.value = employeeId;
    currentView.value = 'detail';
    handleNavigate({ view: 'detail', employee_id: employeeId });
};

// Phê duyệt đơn lẻ
const handleApproveSingle = (attendanceId?: number | null) => {
    if (!attendanceId) return;
    router.post(`/attendance/approve/${attendanceId}`, {}, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success(t('attendance.batch_approve_success')),
    });
};

// Phê duyệt hàng loạt
const handleBulkApprove = () => {
    if (!selectedRowIds.value.length) return;
    ElMessageBox.confirm(
        t('attendance.batch_approve_confirm_msg', { count: selectedRowIds.value.length }),
        t('attendance.batch_approve_confirm_title'),
        {
            type: 'warning',
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
        }
    ).then(() => {
        router.post('/attendance/bulk-approve', {
            ids: selectedRowIds.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                selectedRowIds.value = [];
                ElMessage.success(t('attendance.batch_approve_success'));
            },
        });
    }).catch(() => {});
};

// Từ chối bảng công
const isRejectDialogVisible = ref(false);
const rejectingAttendanceId = ref<number | null>(null);
const rejectForm = useForm({
    reason: '',
});

const openRejectDialog = (attendanceId?: number | null) => {
    if (!attendanceId) return;
    rejectingAttendanceId.value = attendanceId;
    rejectForm.reason = '';
    isRejectDialogVisible.value = true;
};

const handleConfirmReject = () => {
    if (!rejectingAttendanceId.value) return;
    rejectForm.post(`/attendance/reject/${rejectingAttendanceId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            isRejectDialogVisible.value = false;
            rejectForm.reset();
            ElMessage.success(t('attendance.dialog_reject_success'));
        },
    });
};

// Nộp bảng công cá nhân
const handleSubmitTimesheet = (empId?: number | null) => {
    router.post('/attendance/submit', {
        month_year: props.monthYear,
        employee_id: empId || selectedEmpId.value,
    }, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success(t('attendance.submit_confirm_msg', { month: props.monthYear })),
    });
};

// Khóa / Mở khóa tháng
const handleToggleLock = () => {
    const nextLockState = !props.isLocked;
    const confirmText = nextLockState
        ? t('attendance.lock_confirm_text', { month: props.monthYear })
        : t('attendance.unlock_confirm_text', { month: props.monthYear });

    ElMessageBox.confirm(confirmText, t('attendance.lock_confirm_title'), {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        type: nextLockState ? 'warning' : 'info',
    }).then(() => {
        router.post('/attendance/lock-month', {
            month_year: props.monthYear,
            lock: nextLockState,
        }, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success(nextLockState ? t('attendance.lock_success') : t('attendance.unlock_success')),
        });
    }).catch(() => {});
};

// Tính toán lại công (Pipeline 7-Pipe)
const isCalculateDialogVisible = ref(false);
const calculateForm = useForm({
    from_date: `${props.monthYear}-01`,
    to_date: `${props.monthYear}-15`,
    employee_id: selectedEmpId.value,
});

const handleRunCalculate = () => {
    calculateForm.post('/attendance/calculate', {
        onSuccess: () => {
            isCalculateDialogVisible.value = false;
            ElMessage.success(t('attendance.dialog_calculate_success'));
        },
    });
};

// Log thô từ máy chấm công
const isRawLogsDialogVisible = ref(false);
const rawLogsLoading = ref(false);
const currentLogDate = ref('');
const rawLogsList = ref<any[]>([]);

const handleViewRawLogs = async (dateStr: string) => {
    if (!selectedEmpId.value) return;
    currentLogDate.value = dateStr;
    isRawLogsDialogVisible.value = true;
    rawLogsLoading.value = true;

    try {
        const res = await axios.get('/attendance/raw-logs', {
            params: {
                employee_id: selectedEmpId.value,
                date: dateStr,
            },
        });
        rawLogsList.value = res.data.logs || [];
    } catch (e) {
        ElMessage.error(t('attendance.raw_logs_empty'));
    } finally {
        rawLogsLoading.value = false;
    }
};

// Helpers format
const formatVND = (val: number | string | null | undefined) => {
    if (!val) return `0 ${t('common.currency_vnd')}`;
    const num = typeof val === 'string' ? parseFloat(val) : val;
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
};

const formatDateStr = (dateStr: string) => {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
};

const getSubmissionBadge = (status: string) => {
    switch (status) {
        case 'EMPLOYEE_REVIEW':
            return { label: t('attendance.badge_review'), type: 'info', class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' };
        case 'SUBMITTED':
            return { label: t('attendance.badge_submitted'), type: 'warning', class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 font-semibold' };
        case 'MANAGER_APPROVED':
            return { label: t('attendance.badge_manager_approved'), type: 'success', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 font-medium' };
        case 'HIGHER_MANAGER_APPROVED':
            return { label: t('attendance.badge_higher_approved'), type: 'success', class: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400 font-bold' };
        case 'REJECTED':
            return { label: t('attendance.badge_rejected'), type: 'danger', class: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400 font-bold' };
        case 'HR_LOCKED':
            return { label: t('attendance.badge_locked'), type: 'primary', class: 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400' };
        default:
            return { label: status || t('status.draft'), type: 'info', class: 'bg-slate-100 text-slate-600' };
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'PRESENT':
            return { text: t('attendance.badge_on_time'), class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' };
        case 'LATE_EARLY':
            return { text: t('attendance.badge_late_early'), class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' };
        case 'ABSENT':
            return { text: t('attendance.badge_absent'), class: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400' };
        case 'LEAVE_PAID':
            return { text: t('attendance.badge_paid_leave'), class: 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400' };
        case 'LEAVE_UNPAID':
            return { text: t('attendance.badge_unpaid_leave'), class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' };
        case 'HOLIDAY':
            return { text: t('attendance.badge_holiday'), class: 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400 font-bold' };
        default:
            return { text: status, class: 'bg-slate-100 text-slate-600' };
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`${$t('attendance.title')} - Smart-HR Enterprise`" />

        <div class="space-y-6">
            <!-- Header & Banner Tiến trình Chu trình Đóng công (Monthly Lifecycle Banner) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 shadow-xs space-y-5 transition-colors">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                                <Clock class="w-3.5 h-3.5" />
                                Smart Timekeeper & Rostering Platform
                            </span>
                            <span v-if="isLocked" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                                <Lock class="w-3 h-3" />
                                {{ $t('status.locked') }}
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                <Unlock class="w-3 h-3" />
                                {{ $t('status.unlocked') }}
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-800 dark:text-white tracking-tight">
                            {{ $t('attendance.title') }} - {{ monthYear }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                            {{ $t('attendance.subtitle') }}
                        </p>
                    </div>

                    <!-- Hành động chính -->
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <!-- Nộp bảng công cá nhân -->
                        <el-button
                            v-if="lifecyclePhase?.can_employee_submit && !isLocked"
                            type="primary"
                            plain
                            :icon="TopRight"
                            @click="handleSubmitTimesheet()"
                            class="!rounded-xl"
                        >
                            {{ $t('attendance.submit_month_btn') }}
                        </el-button>

                        <!-- Tính toán công -->
                        <el-button
                            v-if="canCalculate"
                            type="primary"
                            :icon="Refresh"
                            @click="isCalculateDialogVisible = true"
                            class="!rounded-xl shadow-xs"
                        >
                            {{ $t('attendance.calculate_btn') }}
                        </el-button>

                        <!-- Khóa / Mở khóa -->
                        <el-button
                            v-if="canLock"
                            :type="isLocked ? 'success' : 'danger'"
                            :icon="isLocked ? Unlock : Lock"
                            @click="handleToggleLock"
                            plain
                            class="!rounded-xl shadow-xs"
                        >
                            {{ isLocked ? $t('attendance.unlock_month_btn') : $t('attendance.lock_month_btn') }}
                        </el-button>
                    </div>
                </div>

                <!-- Banner 6 Bước Chu Trình Đóng Công Hàng Tháng (Lifecycle Stepper) -->
                <div class="p-4 rounded-xl border border-slate-200/70 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></span>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                                {{ $t('attendance.lifecycle_stepper_title', { month: lifecyclePhase?.target_month }) }}
                            </span>
                            <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                {{ lifecyclePhase?.phase_title }}
                            </span>
                        </div>
                        <span class="text-[11px] text-slate-400">
                            {{ lifecyclePhase?.phase_desc }}
                        </span>
                    </div>

                    <!-- 6 Bước -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 pt-1">
                        <!-- Bước 1 -->
                        <div
                            class="p-2.5 rounded-lg border text-xs transition-all"
                            :class="lifecyclePhase?.current_step === 1
                                ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-bold shadow-xs'
                                : (lifecyclePhase?.current_step > 1 ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500')"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <span>{{ $t('attendance.step_1_title') }}</span>
                                <span class="font-mono text-[10px]">1-{{ lifecyclePhase?.configs?.emp_review_deadline_day }}</span>
                            </div>
                            <div class="text-[10px] mt-1 truncate">{{ $t('attendance.step_1_sub') }}</div>
                        </div>

                        <!-- Bước 2 -->
                        <div
                            class="p-2.5 rounded-lg border text-xs transition-all"
                            :class="lifecyclePhase?.current_step === 2
                                ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-bold shadow-xs'
                                : (lifecyclePhase?.current_step > 2 ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500')"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <span>{{ $t('attendance.step_2_title') }}</span>
                                <span class="font-mono text-[10px]">{{ $t('common.date') }} {{ lifecyclePhase?.configs?.auto_submit_day }}</span>
                            </div>
                            <div class="text-[10px] mt-1 truncate">{{ $t('attendance.step_2_sub') }}</div>
                        </div>

                        <!-- Bước 3 -->
                        <div
                            class="p-2.5 rounded-lg border text-xs transition-all"
                            :class="lifecyclePhase?.current_step === 3
                                ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-bold shadow-xs'
                                : (lifecyclePhase?.current_step > 3 ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500')"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <span>{{ $t('attendance.step_3_title') }}</span>
                                <span class="font-mono text-[10px]">≤ {{ lifecyclePhase?.configs?.manager_approval_deadline_day }}</span>
                            </div>
                            <div class="text-[10px] mt-1 truncate">{{ $t('attendance.step_3_sub') }}</div>
                        </div>

                        <!-- Bước 4 -->
                        <div
                            class="p-2.5 rounded-lg border text-xs transition-all"
                            :class="lifecyclePhase?.current_step === 4
                                ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-bold shadow-xs'
                                : (lifecyclePhase?.current_step > 4 ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500')"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <span>{{ $t('attendance.step_4_title') }}</span>
                                <span class="font-mono text-[10px]">≤ {{ lifecyclePhase?.configs?.higher_manager_deadline_day }}</span>
                            </div>
                            <div class="text-[10px] mt-1 truncate">{{ $t('attendance.step_4_sub') }}</div>
                        </div>

                        <!-- Bước 5 -->
                        <div
                            class="p-2.5 rounded-lg border text-xs transition-all"
                            :class="lifecyclePhase?.current_step === 5
                                ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 font-bold shadow-xs'
                                : (lifecyclePhase?.current_step > 5 ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400' : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500')"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <span>{{ $t('attendance.step_5_title') }}</span>
                                <span class="font-mono text-[10px]">{{ $t('common.date') }} {{ lifecyclePhase?.configs?.hr_lock_deadline_day }}</span>
                            </div>
                            <div class="text-[10px] mt-1 truncate">{{ $t('attendance.step_5_sub') }}</div>
                        </div>

                        <!-- Bước 6 -->
                        <div
                            class="p-2.5 rounded-lg border text-xs transition-all"
                            :class="lifecyclePhase?.current_step === 6
                                ? 'bg-emerald-50 dark:bg-emerald-950/60 border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 font-bold shadow-xs'
                                : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-500'"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <span>{{ $t('attendance.step_6_title') }}</span>
                                <span class="font-mono text-[10px]">{{ $t('common.date') }} {{ lifecyclePhase?.configs?.payroll_payment_day }}</span>
                            </div>
                            <div class="text-[10px] mt-1 truncate">{{ $t('attendance.step_6_sub') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Tabs Navigation & Toolbar -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <!-- Segmented View Buttons -->
                    <div class="inline-flex p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/60 dark:border-slate-700/60">
                        <button
                            type="button"
                            @click="changeView('workmonth')"
                            class="px-4 py-2 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5"
                            :class="currentView === 'workmonth'
                                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            <Calendar class="w-4 h-4" />
                            {{ $t('attendance.view_workmonth') }}
                        </button>

                        <button
                            type="button"
                            @click="changeView('workdate')"
                            class="px-4 py-2 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5"
                            :class="currentView === 'workdate'
                                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            <Clock class="w-4 h-4" />
                            {{ $t('attendance.view_workdate') }}
                        </button>

                        <button
                            type="button"
                            @click="changeView('detail')"
                            class="px-4 py-2 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5"
                            :class="currentView === 'detail'
                                ? 'bg-white dark:bg-slate-900 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                        >
                            <User class="w-4 h-4" />
                            {{ $t('attendance.view_detail') }}
                        </button>
                    </div>

                    <!-- Filters Control Bar -->
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <!-- Chọn tháng (cho workmonth & detail) -->
                        <div v-if="currentView !== 'workdate'" class="flex items-center gap-1.5">
                            <span class="text-xs text-slate-500 font-medium">{{ $t('common.month') }}:</span>
                            <el-date-picker
                                v-model="currentMonth"
                                type="month"
                                :placeholder="$t('common.month')"
                                format="YYYY-MM"
                                value-format="YYYY-MM"
                                @change="handleNavigate()"
                                class="!w-32"
                                size="default"
                            />
                        </div>

                        <!-- Chọn ngày (cho workdate) -->
                        <div v-if="currentView === 'workdate'" class="flex items-center gap-1.5">
                            <span class="text-xs text-slate-500 font-medium">{{ $t('common.date') }}:</span>
                            <el-date-picker
                                v-model="currentWorkDate"
                                type="date"
                                :placeholder="$t('common.date')"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                                @change="handleNavigate()"
                                class="!w-36"
                                size="default"
                            />
                        </div>

                        <!-- Lọc Chi nhánh (cho workmonth & workdate) -->
                        <el-select
                            v-if="currentView !== 'detail'"
                            v-model="filterBranchId"
                            :placeholder="$t('attendance.filter_all_branches')"
                            clearable
                            @change="handleNavigate()"
                            class="w-40"
                            size="default"
                        >
                            <el-option
                                v-for="b in branches"
                                :key="b.id"
                                :label="b.name"
                                :value="b.id"
                            />
                        </el-select>

                        <!-- Lọc Phòng ban (cho workmonth & workdate) -->
                        <el-select
                            v-if="currentView !== 'detail'"
                            v-model="filterDepartmentId"
                            :placeholder="$t('attendance.filter_all_departments')"
                            clearable
                            @change="handleNavigate()"
                            class="w-44"
                            size="default"
                        >
                            <el-option
                                v-for="d in departments"
                                :key="d.id"
                                :label="d.name"
                                :value="d.id"
                            />
                        </el-select>

                        <!-- Tìm kiếm tên / mã NV -->
                        <el-input
                            v-if="currentView !== 'detail'"
                            v-model="searchQuery"
                            :placeholder="$t('attendance.filter_search_placeholder')"
                            :prefix-icon="Search"
                            clearable
                            @keyup.enter="handleNavigate()"
                            @clear="handleNavigate()"
                            class="w-48"
                            size="default"
                        />

                        <!-- Chọn nhân viên khi ở view Detail -->
                        <el-select
                            v-if="currentView === 'detail'"
                            v-model="selectedEmpId"
                            @change="handleNavigate()"
                            :placeholder="$t('common.employee')"
                            class="w-60"
                            size="default"
                        >
                            <el-option
                                v-for="emp in employees"
                                :key="emp.id"
                                :label="`${emp.user_code} - ${emp.full_name}`"
                                :value="emp.id"
                            />
                        </el-select>

                        <!-- Toggle Chế độ xem Official / Preview (khi ở Detail) -->
                        <div v-if="currentView === 'detail'" class="inline-flex p-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                            <button
                                type="button"
                                @click="currentMode = 'official'; handleNavigate({ mode: 'official' })"
                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all"
                                :class="currentMode === 'official' ? 'bg-white dark:bg-slate-900 text-slate-800 dark:text-white shadow-xs' : 'text-slate-500'"
                            >
                                {{ $t('status.approved') }}
                            </button>
                            <button
                                type="button"
                                @click="currentMode = 'preview'; handleNavigate({ mode: 'preview' })"
                                class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all flex items-center gap-1"
                                :class="currentMode === 'preview' ? 'bg-amber-500 text-white shadow-xs font-bold' : 'text-slate-500'"
                            >
                                <span>{{ $t('status.draft') }}</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-200 animate-ping"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 1: WORKMONTH MATRIX ================= -->
            <div v-if="currentView === 'workmonth'" class="space-y-4">
                <!-- Bulk Actions Toolbar -->
                <div v-if="canApprove" class="bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 rounded-xl p-3 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <el-checkbox
                            :model-value="isAllSelected"
                            @change="handleToggleSelectAll"
                        >
                            <span class="text-xs font-semibold text-indigo-950 dark:text-indigo-200">
                                {{ $t('common.select_all') }} ({{ selectedRowIds.length }} / {{ workMonthData?.rows?.length || 0 }})
                            </span>
                        </el-checkbox>

                        <span v-if="selectedRowIds.length > 0" class="text-xs text-indigo-600 dark:text-indigo-400">
                            • {{ $t('common.selected_count', { count: selectedRowIds.length }) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <el-button
                            type="success"
                            size="small"
                            :disabled="selectedRowIds.length === 0"
                            :icon="CircleCheck"
                            @click="handleBulkApprove"
                        >
                            {{ $t('attendance.btn_batch_approve', { count: selectedRowIds.length }) }}
                        </el-button>
                    </div>
                </div>

                <!-- Lưới Ma Trận 31 Ngày -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
                    <div class="overflow-x-auto max-h-[650px] relative">
                        <table class="w-full text-left border-collapse text-xs">
                            <!-- Table Header -->
                            <thead class="bg-slate-50 dark:bg-slate-800/80 sticky top-0 z-20 shadow-xs border-b border-slate-200 dark:border-slate-700">
                                <tr>
                                    <!-- Cột chọn -->
                                    <th class="p-2.5 w-10 text-center sticky left-0 z-30 bg-slate-50 dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700">
                                        <el-checkbox
                                            :model-value="isAllSelected"
                                            @change="handleToggleSelectAll"
                                        />
                                    </th>
                                    <!-- Cột cố định: Nhân viên -->
                                    <th class="p-2.5 min-w-[200px] sticky left-10 z-30 bg-slate-50 dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 font-bold text-slate-700 dark:text-slate-200">
                                        {{ $t('attendance.col_dept_branch') }}
                                    </th>
                                    <!-- Cột tổng kết nhanh -->
                                    <th class="p-2 text-center w-14 border-r border-slate-200 dark:border-slate-700 font-semibold text-slate-600 dark:text-slate-300">
                                        {{ $t('attendance.col_total_std_days') }}
                                    </th>
                                    <th class="p-2 text-center w-14 border-r border-slate-200 dark:border-slate-700 font-semibold text-slate-600 dark:text-slate-300">
                                        {{ $t('attendance.col_total_ot') }}
                                    </th>
                                    <th class="p-2 text-center w-14 border-r border-slate-200 dark:border-slate-700 font-semibold text-slate-600 dark:text-slate-300">
                                        {{ $t('attendance.col_total_late') }}
                                    </th>
                                    <th class="p-2 text-center w-28 border-r border-slate-200 dark:border-slate-700 font-semibold text-slate-600 dark:text-slate-300">
                                        {{ $t('attendance.col_status') }}
                                    </th>
                                    <!-- Các cột ngày trong tháng (28 - 31 cột) -->
                                    <th
                                        v-for="cal in workMonthData?.calendar_days || []"
                                        :key="cal.date"
                                        class="p-1.5 text-center min-w-[34px] border-r border-slate-200/60 dark:border-slate-700/60"
                                        :class="cal.is_weekend ? 'bg-slate-100/70 dark:bg-slate-800/40 text-rose-500 font-semibold' : 'text-slate-700 dark:text-slate-300'"
                                    >
                                        <div class="text-[11px] font-bold">{{ cal.day_number }}</div>
                                        <div class="text-[9px] uppercase text-slate-400">{{ cal.day_name }}</div>
                                    </th>
                                    <!-- Thao tác -->
                                    <th class="p-2.5 text-center min-w-[130px] sticky right-0 z-30 bg-slate-50 dark:bg-slate-800 border-l border-slate-200 dark:border-slate-700 font-bold text-slate-700 dark:text-slate-200">
                                        {{ $t('common.actions') }}
                                    </th>
                                </tr>
                            </thead>

                            <!-- Table Body -->
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr
                                    v-for="row in workMonthData?.rows || []"
                                    :key="row.employee_id"
                                    class="hover:bg-indigo-50/30 dark:hover:bg-indigo-950/20 transition-colors"
                                >
                                    <!-- Checkbox -->
                                    <td class="p-2.5 text-center sticky left-0 z-10 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-700">
                                        <el-checkbox
                                            :model-value="selectedRowIds.includes(row.attendance_id || 0)"
                                            :disabled="!row.attendance_id"
                                            @change="toggleSelectRow(row.attendance_id)"
                                        />
                                    </td>

                                    <!-- Tên NV & Phòng ban -->
                                    <td class="p-2.5 sticky left-10 z-10 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-700">
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <button
                                                    type="button"
                                                    @click="viewEmployeeDetail(row.employee_id)"
                                                    class="font-semibold text-slate-800 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 text-left line-clamp-1 cursor-pointer transition-colors"
                                                >
                                                    {{ row.full_name }}
                                                </button>
                                                <div class="text-[11px] text-slate-400 line-clamp-1">
                                                    <span class="font-mono">{{ row.user_code }}</span> • {{ row.department_name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Tổng công -->
                                    <td class="p-2 text-center border-r border-slate-200 dark:border-slate-700 font-bold text-slate-800 dark:text-white">
                                        {{ row.total_standard_days }}
                                    </td>

                                    <!-- Tổng OT -->
                                    <td class="p-2 text-center border-r border-slate-200 dark:border-slate-700">
                                        <span v-if="row.total_ot_hours > 0" class="font-semibold text-amber-600 dark:text-amber-400 font-mono">
                                            +{{ row.total_ot_hours }}h
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>

                                    <!-- Tổng muộn -->
                                    <td class="p-2 text-center border-r border-slate-200 dark:border-slate-700">
                                        <span v-if="row.total_late_minutes > 0" class="font-semibold text-rose-600 dark:text-rose-400 font-mono">
                                            {{ row.total_late_minutes }}p
                                        </span>
                                        <span v-else class="text-slate-300">-</span>
                                    </td>

                                    <!-- Trạng thái nộp -->
                                    <td class="p-2 text-center border-r border-slate-200 dark:border-slate-700">
                                        <span
                                            class="inline-block px-2 py-0.5 rounded-full text-[10px]"
                                            :class="getSubmissionBadge(row.submission_status).class"
                                        >
                                            {{ getSubmissionBadge(row.submission_status).label }}
                                        </span>
                                        <div v-if="row.submission_type === 'AUTO_SUBMITTED'" class="text-[9px] text-indigo-500 font-semibold mt-0.5">
                                            ({{ $t('attendance.step_2_title') }})
                                        </div>
                                    </td>

                                    <!-- Các ô ngày trong tháng -->
                                    <td
                                        v-for="cal in workMonthData?.calendar_days || []"
                                        :key="cal.date"
                                        class="p-1 text-center border-r border-slate-100 dark:border-slate-800"
                                        :class="cal.is_weekend ? 'bg-slate-50/50 dark:bg-slate-800/20' : ''"
                                    >
                                        <div
                                            v-if="row.days[cal.date]"
                                            class="inline-flex flex-col items-center justify-center min-w-[26px] min-h-[26px] rounded text-[11px] font-medium"
                                            :class="{
                                                'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 font-bold': row.days[cal.date].symbol === 'X',
                                                'bg-sky-50 dark:bg-sky-950/50 text-sky-700 dark:text-sky-400 font-semibold': row.days[cal.date].symbol === '0.5' || row.days[cal.date].symbol === 'P',
                                                'bg-rose-50 dark:bg-rose-950/50 text-rose-600 font-bold': row.days[cal.date].symbol === 'V',
                                                'text-slate-400': row.days[cal.date].symbol === 'OFF' || row.days[cal.date].symbol === '-',
                                            }"
                                            :title="`${cal.date}: ${row.days[cal.date].actual_in || '--:--'} -> ${row.days[cal.date].actual_out || '--:--'}`"
                                        >
                                            <span>{{ row.days[cal.date].symbol }}</span>
                                            <span v-if="row.days[cal.date].is_late" class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-0.5"></span>
                                            <span v-if="row.days[cal.date].ot_hours > 0" class="text-[8px] text-indigo-600 font-bold leading-none">+{{ row.days[cal.date].ot_hours }}</span>
                                        </div>
                                    </td>

                                    <!-- Cột Thao tác -->
                                    <td class="p-2 text-center sticky right-0 z-10 bg-white dark:bg-slate-900 border-l border-slate-200 dark:border-slate-700">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Nút Duyệt -->
                                            <el-button
                                                v-if="canApprove && !row.is_locked && row.submission_status !== 'MANAGER_APPROVED' && row.submission_status !== 'HIGHER_MANAGER_APPROVED'"
                                                type="success"
                                                size="small"
                                                circle
                                                :icon="Check"
                                                :title="$t('common.confirm')"
                                                @click="handleApproveSingle(row.attendance_id)"
                                            />

                                            <!-- Nút Từ chối -->
                                            <el-button
                                                v-if="canApprove && !row.is_locked && row.submission_status === 'SUBMITTED'"
                                                type="danger"
                                                size="small"
                                                circle
                                                :icon="Close"
                                                :title="$t('attendance.btn_reject')"
                                                @click="openRejectDialog(row.attendance_id)"
                                            />

                                            <!-- Nút Xem chi tiết -->
                                            <el-button
                                                type="info"
                                                size="small"
                                                circle
                                                :icon="View"
                                                :title="$t('common.detail')"
                                                @click="viewEmployeeDetail(row.employee_id)"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================= TAB 2: WORKDATE OVERVIEW ================= -->
            <div v-if="currentView === 'workdate'" class="space-y-4">
                <!-- Thẻ thống kê điểm danh ngày -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 font-medium">{{ $t('attendance.kpi_total_employees') }}</div>
                        <div class="text-xl font-bold text-slate-800 dark:text-white mt-1">{{ workDateData?.stats?.total_employees || 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 font-medium">{{ $t('attendance.kpi_present') }}</div>
                        <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ workDateData?.stats?.present_count || 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 font-medium">{{ $t('attendance.kpi_late') }}</div>
                        <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">{{ workDateData?.stats?.late_count || 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 font-medium">{{ $t('attendance.kpi_early') }}</div>
                        <div class="text-xl font-bold text-orange-600 dark:text-orange-400 mt-1">{{ workDateData?.stats?.early_count || 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 font-medium">{{ $t('attendance.kpi_absent') }}</div>
                        <div class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-1">{{ workDateData?.stats?.absent_count || 0 }}</div>
                    </div>
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 font-medium">{{ $t('attendance.kpi_ot') }}</div>
                        <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ workDateData?.stats?.ot_count || 0 }}</div>
                    </div>
                </div>

                <!-- Bảng danh sách điểm danh ngày -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
                    <el-table
                        :data="workDateData?.rows || []"
                        stripe
                        style="width: 100%"
                        size="default"
                    >
                        <el-table-column :label="$t('attendance.col_user_code')" prop="user_code" width="110">
                            <template #default="{ row }">
                                <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ row.user_code }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_full_name')" min-width="180">
                            <template #default="{ row }">
                                <div class="font-medium text-slate-800 dark:text-white">{{ row.full_name }}</div>
                                <div class="text-xs text-slate-400">{{ row.department_name }} • {{ row.position_title }}</div>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_shift')" min-width="160">
                            <template #default="{ row }">
                                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">{{ row.shift_name }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ row.shift_time }}</div>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_actual_in')" width="120">
                            <template #default="{ row }">
                                <span v-if="row.actual_in" class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ row.actual_in }}</span>
                                <span v-else class="text-slate-300 font-mono">--:--:--</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_actual_out')" width="120">
                            <template #default="{ row }">
                                <span v-if="row.actual_out" class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ row.actual_out }}</span>
                                <span v-else class="text-slate-300 font-mono">--:--:--</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_std_hours')" width="90" align="center">
                            <template #default="{ row }">
                                <span class="font-bold text-slate-800 dark:text-white">{{ row.standard_hours }}h</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_late_early')" width="110" align="center">
                            <template #default="{ row }">
                                <span v-if="row.late_minutes > 0 || row.early_minutes > 0" class="text-rose-600 font-semibold font-mono">
                                    {{ row.late_minutes + row.early_minutes }}p
                                </span>
                                <span v-else class="text-slate-300 font-mono">-</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_ot_hours')" width="90" align="center">
                            <template #default="{ row }">
                                <span v-if="row.ot_hours > 0" class="text-amber-600 font-bold font-mono">+{{ row.ot_hours }}h</span>
                                <span v-else class="text-slate-300 font-mono">-</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_status')" width="130" align="center">
                            <template #default="{ row }">
                                <span
                                    class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="getStatusBadge(row.status).class"
                                >
                                    {{ getStatusBadge(row.status).text }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('common.actions')" width="100" align="center">
                            <template #default="{ row }">
                                <el-button
                                    type="primary"
                                    link
                                    size="small"
                                    :icon="View"
                                    @click="viewEmployeeDetail(row.employee_id)"
                                >
                                    {{ $t('common.detail') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>

            <!-- ================= TAB 3: PERSONAL DETAIL ================= -->
            <div v-if="currentView === 'detail'" class="space-y-6">
                <!-- KPI Cards Thống kê Tháng cá nhân -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('attendance.summary_std_days') }}</div>
                        <div class="text-2xl font-bold text-slate-800 dark:text-white mt-1">
                            {{ summary?.total_standard_days || 0 }} <span class="text-xs font-normal text-slate-400">{{ $t('attendance.summary_unit_workday') }}</span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1 font-mono">({{ summary?.total_standard_hours || 0 }} {{ $t('common.hours') }})</div>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('attendance.summary_ot_hours') }}</div>
                        <div class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                            {{ summary?.total_ot_hours || 0 }} <span class="text-xs font-normal text-slate-400">{{ $t('common.hours') }}</span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1">{{ $t('attendance.summary_ot_desc') }}</div>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('attendance.summary_late_mins') }}</div>
                        <div class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                            {{ summary?.total_late_minutes || 0 }} <span class="text-xs font-normal text-slate-400">{{ $t('common.minutes') }}</span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1">{{ $t('attendance.summary_late_desc') }}</div>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('attendance.summary_penalty') }}</div>
                        <div class="text-2xl font-bold text-slate-800 dark:text-white mt-1">
                            {{ formatVND(summary?.total_penalty_amount || 0) }}
                        </div>
                        <div class="text-xs text-slate-400 mt-1">{{ $t('attendance.summary_penalty_desc') }}</div>
                    </div>

                    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('attendance.summary_pending_requests') }}</div>
                        <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                            {{ summary?.pending_requests_count || 0 }}
                        </div>
                        <div class="text-xs text-slate-400 mt-1">{{ $t('attendance.summary_pending_desc') }}</div>
                    </div>
                </div>

                <!-- Bảng Lưới Chấm Công Cá Nhân 31 Ngày -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-slate-800 dark:text-white text-sm">
                                {{ $t('attendance.detail_heading', { name: selectedEmployee?.full_name, code: selectedEmployee?.user_code }) }}
                            </span>
                            <span v-if="mode === 'preview'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                {{ $t('attendance.preview_badge') }}
                            </span>
                        </div>
                        <el-button
                            size="small"
                            type="primary"
                            plain
                            @click="handleSubmitTimesheet(selectedEmployee?.id)"
                        >
                            {{ $t('attendance.single_submit_btn') }}
                        </el-button>
                    </div>

                    <el-table
                        :data="days"
                        stripe
                        style="width: 100%"
                        size="small"
                    >
                        <el-table-column :label="$t('attendance.col_work_date')" width="130">
                            <template #default="{ row }">
                                <div class="font-mono font-semibold text-slate-800 dark:text-white">{{ formatDateStr(row.work_date) }}</div>
                                <div class="text-[11px] text-slate-400">{{ row.day_name }}</div>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_shift')" min-width="150">
                            <template #default="{ row }">
                                <div v-if="row.roster?.shift">
                                    <span class="font-semibold text-slate-700 dark:text-slate-300">{{ row.roster.shift.name }}</span>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ row.roster.shift.start_time }} - {{ row.roster.shift.end_time }}</div>
                                </div>
                                <span v-else class="text-slate-400 italic">{{ $t('attendance.col_no_shift') }}</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_actual_in')" width="110">
                            <template #default="{ row }">
                                <span v-if="row.actual_in" class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ row.actual_in }}</span>
                                <span v-else class="text-slate-300 font-mono">--:--:--</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_actual_out')" width="110">
                            <template #default="{ row }">
                                <span v-if="row.actual_out" class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">{{ row.actual_out }}</span>
                                <span v-else class="text-slate-300 font-mono">--:--:--</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_std_hours')" width="100" align="center">
                            <template #default="{ row }">
                                <span class="font-bold text-slate-800 dark:text-white">{{ row.standard_hours }}h</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_late_early')" width="110" align="center">
                            <template #default="{ row }">
                                <div v-if="row.late_minutes > 0 || row.early_minutes > 0">
                                    <span class="text-rose-600 font-semibold font-mono">{{ row.late_minutes + row.early_minutes }}p</span>
                                    <div class="text-[10px] text-slate-400">(-{{ formatVND(row.late_penalty_amount) }})</div>
                                </div>
                                <span v-else class="text-slate-300">-</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_overtime')" width="110" align="center">
                            <template #default="{ row }">
                                <div v-if="row.ot_day_hours > 0 || row.ot_night_hours > 0 || row.ot_weekend_hours > 0 || row.ot_holiday_hours > 0">
                                    <span class="text-amber-600 font-bold font-mono">+{{ (row.ot_day_hours + row.ot_night_hours + row.ot_weekend_hours + row.ot_holiday_hours).toFixed(1) }}h</span>
                                </div>
                                <span v-else class="text-slate-300">-</span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_status')" width="140" align="center">
                            <template #default="{ row }">
                                <span
                                    class="inline-block px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="getStatusBadge(row.status).class"
                                >
                                    {{ getStatusBadge(row.status).text }}
                                </span>
                            </template>
                        </el-table-column>

                        <el-table-column :label="$t('attendance.col_reconcile')" width="90" align="center">
                            <template #default="{ row }">
                                <el-button
                                    size="small"
                                    link
                                    type="primary"
                                    :icon="View"
                                    @click="handleViewRawLogs(row.work_date)"
                                >
                                    Log
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>

            <!-- Dialog: Từ chối Bảng công -->
            <el-dialog
                v-model="isRejectDialogVisible"
                :title="$t('attendance.dialog_reject_title')"
                width="460px"
            >
                <div class="space-y-4">
                    <p class="text-xs text-slate-500">
                        {{ $t('attendance.dialog_reject_label') }}
                    </p>
                    <el-input
                        v-model="rejectForm.reason"
                        type="textarea"
                        :rows="4"
                        :placeholder="$t('attendance.dialog_reject_placeholder')"
                    />
                </div>
                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <el-button @click="isRejectDialogVisible = false">{{ $t('common.cancel') }}</el-button>
                        <el-button
                            type="danger"
                            :loading="rejectForm.processing"
                            :disabled="!rejectForm.reason.trim()"
                            @click="handleConfirmReject"
                        >
                            {{ $t('attendance.dialog_reject_confirm_btn') }}
                        </el-button>
                    </div>
                </template>
            </el-dialog>

            <!-- Dialog: Tính toán công lại -->
            <el-dialog
                v-model="isCalculateDialogVisible"
                :title="$t('attendance.dialog_calculate_title')"
                width="480px"
            >
                <div class="space-y-4">
                    <div class="p-3 rounded-xl bg-indigo-50/70 text-indigo-900 text-xs leading-relaxed">
                        {{ $t('attendance.dialog_calculate_desc') }}
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-700">{{ $t('common.from_date') }}:</label>
                            <el-date-picker
                                v-model="calculateForm.from_date"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                class="!w-full"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-700">{{ $t('common.to_date') }}:</label>
                            <el-date-picker
                                v-model="calculateForm.to_date"
                                type="date"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                class="!w-full"
                            />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700">{{ $t('attendance.calculate_scope_label') }}:</label>
                        <el-select
                            v-model="calculateForm.employee_id"
                            :placeholder="$t('attendance.dialog_calculate_scope_all')"
                            clearable
                            class="w-full"
                        >
                            <el-option
                                v-for="emp in employees"
                                :key="emp.id"
                                :label="`${emp.user_code} - ${emp.full_name}`"
                                :value="emp.id"
                            />
                        </el-select>
                    </div>
                </div>

                <template #footer>
                    <div class="flex items-center justify-end gap-2">
                        <el-button @click="isCalculateDialogVisible = false">{{ $t('common.cancel') }}</el-button>
                        <el-button
                            type="primary"
                            :loading="calculateForm.processing"
                            @click="handleRunCalculate"
                        >
                            {{ $t('attendance.dialog_calculate_btn') }}
                        </el-button>
                    </div>
                </template>
            </el-dialog>

            <!-- Dialog: Xem Log quẹt thẻ thô từ Máy chấm công -->
            <el-dialog
                v-model="isRawLogsDialogVisible"
                :title="$t('attendance.dialog_raw_logs_date', { date: currentLogDate })"
                width="520px"
            >
                <div v-loading="rawLogsLoading" class="space-y-3 min-h-[160px]">
                    <div v-if="rawLogsList.length === 0 && !rawLogsLoading" class="text-center py-8 text-slate-400 text-xs">
                        {{ $t('attendance.raw_logs_empty') }}
                    </div>

                    <div v-else class="space-y-2">
                        <div
                            v-for="log in rawLogsList"
                            :key="log.id"
                            class="p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-2">
                                <span class="p-1 rounded bg-indigo-50 text-indigo-600">
                                    <Clock class="w-3.5 h-3.5" />
                                </span>
                                <div>
                                    <span class="font-mono font-bold text-slate-800 dark:text-white">{{ log.punch_time }}</span>
                                    <div class="text-[10px] text-slate-400">{{ $t('attendance.raw_logs_device') }}: {{ log.device_id || 'LAN Machine' }}</div>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700">
                                {{ log.verify_type || $t('attendance.raw_logs_verify') }}
                            </span>
                        </div>
                    </div>
                </div>

                <template #footer>
                    <el-button @click="isRawLogsDialogVisible = false">{{ $t('common.close') }}</el-button>
                </template>
            </el-dialog>
        </div>
    </AppLayout>
</template>
