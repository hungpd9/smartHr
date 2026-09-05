<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
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
} from '@element-plus/icons-vue';

interface EmployeeItem {
    id: number;
    user_code: string;
    full_name: string;
    gender: string;
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
    status: 'PRESENT' | 'LATE_EARLY' | 'ABSENT' | 'LEAVE_PAID' | 'LEAVE_UNPAID' | 'HOLIDAY';
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

const props = defineProps<{
    employees: EmployeeItem[];
    selectedEmployee?: EmployeeItem;
    monthYear: string;
    mode: 'official' | 'preview';
    isLocked: boolean;
    days: TimesheetDay[];
    summary: TimesheetSummary;
    canCalculate: boolean;
    canLock: boolean;
}>();

// State lọc
const currentMonth = ref(props.monthYear);
const selectedEmpId = ref(props.selectedEmployee?.id || props.employees[0]?.id || null);
const currentMode = ref(props.mode);

// Xử lý đổi tháng hoặc đổi nhân viên hoặc đổi chế độ
const handleFilterChange = () => {
    router.get('/attendance', {
        month_year: currentMonth.value,
        employee_id: selectedEmpId.value,
        mode: currentMode.value,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
};

// Form Tính toán lại công
const isCalculateDialogVisible = ref(false);
const calculateForm = useForm({
    from_date: `${props.monthYear}-01`,
    to_date: `${props.monthYear}-10`,
    employee_id: selectedEmpId.value,
});

const handleRunCalculate = () => {
    calculateForm.post('/attendance/calculate', {
        onSuccess: () => {
            isCalculateDialogVisible.value = false;
            ElMessage.success('Đã hoàn thành tính toán lại bảng công qua 7-Pipe Pipeline!');
        },
    });
};

// Khóa / Mở khóa tháng
const handleToggleLock = () => {
    const nextLockState = !props.isLocked;
    const confirmText = nextLockState
        ? `Bạn có chắc chắn muốn KHÓA kỳ công tháng ${props.monthYear}? Sau khi khóa, dữ liệu công sẽ không thể tính toán lại.`
        : `Bạn có chắc chắn muốn MỞ KHÓA kỳ công tháng ${props.monthYear}?`;

    ElMessageBox.confirm(confirmText, 'Xác nhận thay đổi trạng thái kỳ công', {
        confirmButtonText: 'Đồng ý',
        cancelButtonText: 'Hủy',
        type: nextLockState ? 'warning' : 'info',
    }).then(() => {
        router.post('/attendance/lock-month', {
            month_year: props.monthYear,
            lock: nextLockState,
        }, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success(nextLockState ? 'Đã khóa kỳ công!' : 'Đã mở khóa kỳ công!'),
        });
    }).catch(() => {});
};

// Dialog xem log thô
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
        ElMessage.error('Không thể nạp dữ liệu log thô từ máy chấm công.');
    } finally {
        rawLogsLoading.value = false;
    }
};

// Helper format tiền tệ VNĐ
const formatVND = (value: number | string | null | undefined) => {
    if (!value) return '0 đ';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
};

// Helper format ngày
const formatDateStr = (dateStr: string) => {
    const parts = dateStr.split('-');
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
};

// Helper badge trạng thái công
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'PRESENT':
            return { text: 'Đúng giờ', type: 'success', class: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' };
        case 'LATE_EARLY':
            return { text: 'Muộn / Sớm', type: 'warning', class: 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400' };
        case 'ABSENT':
            return { text: 'Vắng mặt', type: 'danger', class: 'bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400' };
        case 'LEAVE_PAID':
            return { text: 'Nghỉ phép (Có lương)', type: 'primary', class: 'bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400' };
        case 'LEAVE_UNPAID':
            return { text: 'Nghỉ không lương', type: 'info', class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' };
        case 'HOLIDAY':
            return { text: 'Nghỉ Lễ / Tết (Đ.112)', type: 'success', class: 'bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-400 font-bold' };
        default:
            return { text: status, type: 'info', class: 'bg-slate-100 text-slate-600' };
    }
};
</script>

<template>
    <AppLayout>
        <Head title="Bảng Chấm công & Quản lý Ngày công - Smart-HR" />

        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 shadow-xs transition-colors">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                                <Clock class="w-3.5 h-3.5" />
                                7-Pipe Progressive Engine
                            </span>
                            <span v-if="isLocked" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border border-rose-200/60 dark:border-rose-800/60">
                                <Lock class="w-3 h-3" />
                                Kỳ công đã khóa
                            </span>
                            <span v-else class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                <Unlock class="w-3 h-3" />
                                Kỳ công đang mở
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-800 dark:text-white tracking-tight">
                            Bảng Chấm công Tháng {{ monthYear }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                            Nhân viên: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ selectedEmployee?.full_name }}</span>
                            ({{ selectedEmployee?.user_code }})
                            • Chức danh: <span class="font-medium text-slate-700 dark:text-slate-200">{{ selectedEmployee?.current_timeline?.job_title || 'Nhân sự' }}</span>
                        </p>
                    </div>

                    <!-- Bộ lọc & Thao tác -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Chọn tháng -->
                        <el-date-picker
                            v-model="currentMonth"
                            type="month"
                            placeholder="Chọn tháng"
                            format="YYYY-MM"
                            value-format="YYYY-MM"
                            @change="handleFilterChange"
                            class="!w-36"
                            size="default"
                        />

                        <!-- Chọn nhân viên -->
                        <el-select
                            v-model="selectedEmpId"
                            @change="handleFilterChange"
                            placeholder="Chọn nhân viên"
                            class="w-56"
                            size="default"
                        >
                            <el-option
                                v-for="emp in employees"
                                :key="emp.id"
                                :label="`${emp.user_code} - ${emp.full_name}`"
                                :value="emp.id"
                            />
                        </el-select>

                        <!-- Nút tính công lại -->
                        <el-button
                            v-if="canCalculate"
                            type="primary"
                            :icon="Refresh"
                            @click="isCalculateDialogVisible = true"
                            class="!rounded-xl shadow-xs"
                        >
                            Tính toán công
                        </el-button>

                        <!-- Nút khóa tháng -->
                        <el-button
                            v-if="canLock"
                            :type="isLocked ? 'success' : 'danger'"
                            :icon="isLocked ? Unlock : Lock"
                            @click="handleToggleLock"
                            plain
                            class="!rounded-xl shadow-xs"
                        >
                            {{ isLocked ? 'Mở khóa kỳ' : 'Khóa kỳ công' }}
                        </el-button>
                    </div>
                </div>
            </div>

            <!-- KPI Cards Thống kê Tháng -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Tổng ngày công chuẩn</div>
                    <div class="text-xl font-bold text-slate-800 dark:text-white mt-1">
                        {{ summary.total_standard_days }} <span class="text-xs font-normal text-slate-400">công ({{ summary.total_standard_hours }}h)</span>
                    </div>
                    <div class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">Quy đổi 8h/ngày công</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Làm thêm giờ (OT)</div>
                    <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                        {{ summary.total_ot_hours }} <span class="text-xs font-normal text-slate-400">giờ</span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Ngày thường / Cuối tuần / Lễ</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Tổng phút vi phạm</div>
                    <div class="text-xl font-bold text-amber-600 dark:text-amber-400 mt-1">
                        {{ summary.total_late_minutes }} <span class="text-xs font-normal text-slate-400">phút</span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Sau khi trừ miễn trừ con nhỏ & grace</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Tiền phạt lũy tiến</div>
                    <div class="text-xl font-bold text-rose-600 dark:text-rose-400 mt-1">
                        {{ formatVND(summary.total_penalty_amount) }}
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">Khấu trừ lương kỳ này</div>
                </div>

                <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Chế độ đang xem</div>
                    <div class="mt-1 flex items-center gap-1.5">
                        <span
                            class="px-2.5 py-0.5 rounded-full text-xs font-bold"
                            :class="currentMode === 'preview'
                                ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200'
                                : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200'"
                        >
                            {{ currentMode === 'preview' ? 'Tạm tính (Preview)' : 'Chính thức (Official)' }}
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-400 mt-0.5">
                        <span v-if="currentMode === 'preview'">Áp dụng {{ summary.pending_requests_count }} đơn chờ</span>
                        <span v-else>Dữ liệu chốt từ máy quẹt thẻ</span>
                    </div>
                </div>
            </div>

            <!-- Tab Switcher: Bảng công Đã duyệt vs Tạm tính (Preview) -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="flex items-center gap-2">
                        <el-radio-group v-model="currentMode" @change="handleFilterChange" size="default">
                            <el-radio-button value="official">
                                <span class="flex items-center gap-1.5">
                                    <Check class="w-3.5 h-3.5 text-emerald-500" />
                                    Bảng công Chính thức (Đã duyệt)
                                </span>
                            </el-radio-button>
                            <el-radio-button value="preview">
                                <span class="flex items-center gap-1.5">
                                    <View class="w-3.5 h-3.5 text-amber-500" />
                                    Bảng công Tạm tính (Preview Đơn chờ)
                                </span>
                            </el-radio-button>
                        </el-radio-group>
                    </div>

                    <div v-if="currentMode === 'preview'" class="text-xs text-amber-700 dark:text-amber-400 flex items-center gap-1.5 bg-amber-50 dark:bg-amber-950/40 px-3 py-1.5 rounded-xl border border-amber-200/60">
                        <InfoFilled class="w-4 h-4 shrink-0" />
                        Chế độ xem trước: Tự động giả lập kết quả nếu các đơn xin nghỉ, sửa công, làm thêm giờ được duyệt.
                    </div>
                </div>

                <!-- Bảng Lưới Chấm công (Timesheet Grid) -->
                <el-table :data="days" stripe style="width: 100%" class="rounded-xl overflow-hidden">
                    <!-- Ngày -->
                    <el-table-column label="Ngày công" prop="work_date" width="150">
                        <template #default="{ row }">
                            <div class="flex flex-col">
                                <span class="font-mono font-bold text-slate-800 dark:text-white">
                                    {{ formatDateStr(row.work_date) }}
                                </span>
                                <span class="text-[11px] text-slate-400 capitalize">
                                    {{ row.day_name }}
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Ca làm việc -->
                    <el-table-column label="Ca phân công" width="160">
                        <template #default="{ row }">
                            <div v-if="row.roster?.shift">
                                <span class="font-semibold text-xs text-slate-800 dark:text-slate-200 block truncate">
                                    {{ row.roster.shift.name }}
                                </span>
                                <span class="text-[10px] text-slate-400 font-mono">
                                    {{ row.roster.shift.start_time.substring(0, 5) }} - {{ row.roster.shift.end_time.substring(0, 5) }}
                                </span>
                            </div>
                            <span v-else class="text-xs text-slate-400">
                                {{ row.is_weekend ? 'Nghỉ tuần' : 'Chưa xếp ca' }}
                            </span>
                        </template>
                    </el-table-column>

                    <!-- Giờ vào / ra thực tế & nút xem log thô -->
                    <el-table-column label="Vào / Ra thực tế" width="160">
                        <template #default="{ row }">
                            <div class="flex items-center justify-between">
                                <div class="font-mono text-xs">
                                    <span v-if="row.actual_in" class="text-slate-700 dark:text-slate-300 font-semibold">{{ row.actual_in.substring(0, 5) }}</span>
                                    <span v-else class="text-slate-300 dark:text-slate-600">--:--</span>
                                    <span class="text-slate-400 mx-1">→</span>
                                    <span v-if="row.actual_out" class="text-slate-700 dark:text-slate-300 font-semibold">{{ row.actual_out.substring(0, 5) }}</span>
                                    <span v-else class="text-slate-300 dark:text-slate-600">--:--</span>
                                </div>
                                <button
                                    @click="handleViewRawLogs(row.work_date)"
                                    title="Xem log quẹt thẻ từ máy"
                                    class="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition ml-1"
                                >
                                    <Document class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Giờ công chuẩn -->
                    <el-table-column label="Giờ chuẩn" prop="standard_hours" width="100" align="center">
                        <template #default="{ row }">
                            <span
                                class="font-bold text-xs"
                                :class="row.standard_hours >= 8.0 ? 'text-emerald-600 dark:text-emerald-400' : (row.standard_hours > 0 ? 'text-indigo-600' : 'text-slate-400')"
                            >
                                {{ row.standard_hours }}h
                            </span>
                        </template>
                    </el-table-column>

                    <!-- Muộn / Sớm -->
                    <el-table-column label="Muộn / Sớm" width="130" align="center">
                        <template #default="{ row }">
                            <div v-if="row.late_minutes > 0 || row.early_minutes > 0" class="flex flex-col text-[11px]">
                                <span v-if="row.late_minutes > 0" class="text-rose-600 font-bold">
                                    Muộn {{ row.late_minutes }}'
                                </span>
                                <span v-if="row.early_minutes > 0" class="text-amber-600 font-medium">
                                    Sớm {{ row.early_minutes }}'
                                </span>
                            </div>
                            <span v-else class="text-xs text-slate-400">0'</span>
                        </template>
                    </el-table-column>

                    <!-- Tiền phạt vi phạm -->
                    <el-table-column label="Phạt vi phạm" width="120">
                        <template #default="{ row }">
                            <span v-if="row.late_penalty_amount > 0" class="font-semibold text-xs text-rose-600 dark:text-rose-400 font-mono">
                                {{ formatVND(row.late_penalty_amount) }}
                            </span>
                            <span v-else class="text-xs text-slate-400">0 đ</span>
                        </template>
                    </el-table-column>

                    <!-- Giờ OT -->
                    <el-table-column label="OT Làm thêm" width="120">
                        <template #default="{ row }">
                            <div class="text-xs">
                                <span v-if="row.ot_holiday_hours > 0" class="font-bold text-purple-600">
                                    {{ row.ot_holiday_hours }}h (Lễ 3x)
                                </span>
                                <span v-else-if="row.ot_weekend_hours > 0" class="font-bold text-indigo-600">
                                    {{ row.ot_weekend_hours }}h (Tuần 2x)
                                </span>
                                <span v-else-if="row.ot_day_hours > 0 || row.ot_night_hours > 0" class="font-semibold text-blue-600">
                                    {{ row.ot_day_hours + row.ot_night_hours }}h (1.5x)
                                </span>
                                <span v-else class="text-slate-400">—</span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Trạng thái -->
                    <el-table-column label="Trạng thái" width="170">
                        <template #default="{ row }">
                            <span
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold inline-block"
                                :class="getStatusBadge(row.status).class"
                            >
                                {{ getStatusBadge(row.status).text }}
                            </span>
                        </template>
                    </el-table-column>

                    <!-- Ghi chú / Tạm tính -->
                    <el-table-column label="Ghi chú & Đơn từ">
                        <template #default="{ row }">
                            <div v-if="row.is_preview_modified" class="flex items-center gap-1.5 text-xs text-amber-600 dark:text-amber-400 font-medium">
                                <Warning class="w-3.5 h-3.5 shrink-0" />
                                <span>{{ row.preview_note }}</span>
                            </div>
                            <span v-else class="text-xs text-slate-400">
                                {{ row.status === 'HOLIDAY' ? 'Nghỉ lễ theo BLLĐ 2019' : '—' }}
                            </span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>

        <!-- Dialog Tính toán lại công -->
        <el-dialog
            v-model="isCalculateDialogVisible"
            title="Tính toán Bảng công (7-Pipe Pipeline)"
            width="500px"
            destroy-on-close
        >
            <el-form :model="calculateForm" label-position="top" class="space-y-3">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item label="Từ ngày" required>
                        <el-date-picker
                            v-model="calculateForm.from_date"
                            type="date"
                            placeholder="Chọn ngày"
                            format="YYYY-MM-DD"
                            value-format="YYYY-MM-DD"
                            class="!w-full"
                        />
                    </el-form-item>
                    <el-form-item label="Đến ngày" required>
                        <el-date-picker
                            v-model="calculateForm.to_date"
                            type="date"
                            placeholder="Chọn ngày"
                            format="YYYY-MM-DD"
                            value-format="YYYY-MM-DD"
                            class="!w-full"
                        />
                    </el-form-item>
                </div>
                <el-form-item label="Nhân viên áp dụng">
                    <el-select v-model="calculateForm.employee_id" placeholder="Tất cả nhân viên" clearable class="w-full">
                        <el-option
                            v-for="emp in employees"
                            :key="emp.id"
                            :label="`${emp.user_code} - ${emp.full_name}`"
                            :value="emp.id"
                        />
                    </el-select>
                </el-form-item>
                <div class="p-3 bg-indigo-50 dark:bg-indigo-950/40 rounded-xl text-xs text-indigo-700 dark:text-indigo-300 border border-indigo-200/60">
                    <InfoFilled class="w-4 h-4 inline-block mr-1" />
                    Hệ thống sẽ chạy chuỗi 7 Pipes độc lập: Ghép cặp log thô $\to$ Khớp ca $\to$ Nạp quy chế $\to$ Phúc lợi con nhỏ $\to$ Tính muộn/sớm $\to$ Phạt lũy tiến $\to$ Tính OT.
                </div>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isCalculateDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="calculateForm.processing" @click="handleRunCalculate">
                        Chạy tính toán
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog xem log quẹt thẻ thô từ máy -->
        <el-dialog
            v-model="isRawLogsDialogVisible"
            :title="`Log quẹt thẻ thô ngày ${currentLogDate}`"
            width="550px"
        >
            <div v-loading="rawLogsLoading" class="space-y-3">
                <div v-if="rawLogsList.length === 0" class="text-center py-6 text-slate-400 text-sm">
                    Không tìm thấy lượt quẹt thẻ nào trên máy chấm công trong ngày này.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="log in rawLogsList"
                        :key="log.id"
                        class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/60 flex items-center justify-between text-xs"
                    >
                        <div class="space-y-0.5">
                            <div class="font-mono font-bold text-slate-800 dark:text-white text-sm">
                                {{ log.punch_time }}
                            </div>
                            <div class="text-slate-400">
                                Máy: <b class="text-slate-600 dark:text-slate-300">{{ log.device_sn }}</b>
                                • Phương thức: <b class="text-indigo-600">{{ log.verification_mode }}</b>
                            </div>
                        </div>
                        <div>
                            <span
                                class="px-2.5 py-1 rounded-full text-xs font-semibold"
                                :class="log.punch_state === 'CHECK_IN' ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'"
                            >
                                {{ log.punch_state }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>
    </AppLayout>
</template>
