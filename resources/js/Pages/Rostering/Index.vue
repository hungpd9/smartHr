<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Calendar,
    Clock,
    Plus,
    RefreshRight,
    Check,
    Warning,
    WarningFilled,
    Moon,
    Sunny,
    User,
    ArrowLeft,
    ArrowRight,
    Promotion,
    Setting,
    Switch,
    Star,
    StarFilled,
    InfoFilled,
    DataAnalysis,
    Money,
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';

interface DateColumn {
    date: string;
    day_name: string;
    day_number: string;
    is_weekend: boolean;
}

interface ShiftOption {
    id: number;
    code: string;
    name: string;
    start_time: string;
    end_time: string;
    work_hours: number;
    is_night_shift: boolean;
    is_default?: boolean;
}

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
}

interface RosterDayCell {
    roster_id: number | null;
    shift_id: number | null;
    shift_code: string | null;
    shift_name: string | null;
    start_time: string | null;
    end_time: string | null;
    is_night_shift: boolean;
    work_hours: number;
    is_published: boolean;
    status: string;
    is_default_inherited?: boolean;
    violation_flags: string[];
}

interface EmployeeMatrixRow {
    employee_id: number;
    user_code: string;
    full_name: string;
    department_name: string;
    gender: string;
    has_child_under_12m: boolean;
    is_pregnant: boolean;
    days: Record<string, RosterDayCell>;
}

const props = defineProps<{
    dates: DateColumn[];
    matrix: EmployeeMatrixRow[];
    shifts: ShiftOption[];
    departments: DepartmentOption[];
    defaultShift: ShiftOption | null;
    startDate: string;
    endDate: string;
    selectedDepartmentId: number | null;
}>();

// Bộ lọc
const selectedDept = ref<number | null>(props.selectedDepartmentId);

// Tự động đồng bộ khi người dùng bấm Back / Forward trên trình duyệt
watch(() => props.selectedDepartmentId, (newVal) => {
    selectedDept.value = newVal;
});

const handleDepartmentChange = (val: number | null) => {
    router.get('/rosters', {
        start_date: props.startDate,
        end_date: props.endDate,
        department_id: val,
    }, { preserveState: true, preserveScroll: true });
};

// Điều hướng Tuần (Trước / Sau)
const navigateWeek = (offsetDays: number) => {
    const curStart = new Date(props.startDate);
    curStart.setDate(curStart.getDate() + offsetDays);
    const newStart = curStart.toISOString().split('T')[0];

    const curEnd = new Date(props.endDate);
    curEnd.setDate(curEnd.getDate() + offsetDays);
    const newEnd = curEnd.toISOString().split('T')[0];

    router.get('/rosters', {
        start_date: newStart,
        end_date: newEnd,
        department_id: selectedDept.value,
    }, { preserveState: true, preserveScroll: true });
};

// Dialog Gán ca nhanh
const assignDialogVisible = ref(false);
const selectedCell = ref<{
    employeeId: number;
    employeeName: string;
    date: string;
    currentShiftId: number | null;
} | null>(null);

const assignForm = useForm({
    employee_id: 0,
    roster_date: '',
    shift_id: null as number | null,
    force: false,
});

const openAssignDialog = (row: EmployeeMatrixRow, dateStr: string) => {
    const cell = row.days[dateStr];
    selectedCell.value = {
        employeeId: row.employee_id,
        employeeName: row.full_name,
        date: dateStr,
        currentShiftId: cell?.shift_id ?? null,
    };

    assignForm.employee_id = row.employee_id;
    assignForm.roster_date = dateStr;
    assignForm.shift_id = cell?.is_default_inherited ? null : (cell?.shift_id ?? null);
    assignForm.force = false;
    assignForm.clearErrors();

    assignDialogVisible.value = true;
};

const handleSaveAssignment = () => {
    assignForm.post('/rosters/assign', {
        preserveScroll: true,
        onSuccess: () => {
            assignDialogVisible.value = false;
            ElMessage.success('Đã cập nhật ca làm việc thành công.');
        },
        onError: (err: any) => {
            if (err.shift_id) {
                // Vi phạm ràng buộc BLLĐ
                ElMessageBox.confirm(
                    `Phát hiện cảnh báo tuân thủ pháp luật lao động:\n\n${Array.isArray(err.shift_id) ? err.shift_id.join('\n') : err.shift_id}\n\nBạn có muốn CƯỠNG CHẾ gán ca này không?`,
                    'Cảnh báo Ràng buộc BLLĐ 2019',
                    {
                        confirmButtonText: 'Cưỡng chế gán',
                        cancelButtonText: 'Hủy bỏ',
                        type: 'warning',
                    }
                ).then(() => {
                    assignForm.force = true;
                    handleSaveAssignment();
                }).catch(() => {});
            }
        },
    });
};

// Dialog Solver Phân Ca Tự Động & Mô Phỏng Đối Soát
const solverDialogVisible = ref(false);
const isSimulating = ref(false);
const simulationResult = ref<any | null>(null);

const solverForm = useForm({
    start_date: props.startDate,
    end_date: props.endDate,
    department_id: props.selectedDepartmentId,
    strategy: 'FAIRNESS',
    shift_ids: props.shifts.map(s => s.id),
    preferences: {
        avoid_female_night: false,
        avoid_has_child: false,
        prioritize_low_base_salary: true,
    },
});

const openSolverDialog = () => {
    solverForm.start_date = props.startDate;
    solverForm.end_date = props.endDate;
    solverForm.department_id = selectedDept.value;
    solverForm.shift_ids = props.shifts.map(s => s.id);
    simulationResult.value = null;
    solverDialogVisible.value = true;
};

// Chạy mô phỏng đối soát không ghi CSDL
const runSimulation = async () => {
    if (solverForm.shift_ids.length === 0) {
        ElMessage.warning('Vui lòng chọn ít nhất một ca làm việc.');
        return;
    }

    isSimulating.value = true;
    try {
        const res = await axios.post('/rosters/simulate-auto-generate', {
            start_date: solverForm.start_date,
            end_date: solverForm.end_date,
            department_id: solverForm.department_id,
            strategy: solverForm.strategy,
            shift_ids: solverForm.shift_ids,
            preferences: solverForm.preferences,
            simulate_only: true,
        });

        if (res.data?.status === 'success') {
            simulationResult.value = res.data.data;
            ElMessage.success('Đã hoàn thành phân tích mô phỏng đối soát phân ca!');
        }
    } catch (err: any) {
        ElMessage.error(err.response?.data?.message || 'Có lỗi xảy ra khi thực hiện mô phỏng.');
    } finally {
        isSimulating.value = false;
    }
};

const handleRunSolver = () => {
    solverForm.post('/rosters/auto-generate', {
        preserveScroll: true,
        onSuccess: () => {
            solverDialogVisible.value = false;
            simulationResult.value = null;
        },
    });
};

// Công bố lịch phân ca
const handlePublishRoster = () => {
    ElMessageBox.confirm(
        `Bạn có chắc chắn muốn CÔNG BỐ lịch phân ca từ ngày ${props.startDate} đến ${props.endDate} cho toàn bộ nhân viên xem trên cổng Portal?`,
        'Xác nhận Công bố Lịch',
        {
            confirmButtonText: 'Công bố ngay',
            cancelButtonText: 'Xem lại',
            type: 'success',
        }
    ).then(() => {
        router.post('/rosters/publish', {
            start_date: props.startDate,
            end_date: props.endDate,
            department_id: selectedDept.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Đã công bố lịch phân ca thành công.');
            },
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout>
        <Head title="Ma trận Phân ca Thông minh - Smart-HR" />

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header Card -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <el-icon :size="22"><Calendar /></el-icon>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            Ma Trận Phân Ca Thông Minh (Auto Rostering Matrix)
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Quản lý lịch làm việc tuần/tháng, kiểm soát tự động luật nghỉ 12h, 24h và bảo vệ thai sản/con nhỏ theo BLLĐ 2019
                        </p>
                    </div>
                </div>

                <!-- Action Controls -->
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Điều hướng tuần -->
                    <div class="flex items-center space-x-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl">
                        <el-button size="small" :icon="ArrowLeft" circle @click="navigateWeek(-7)" title="Tuần trước" />
                        <span class="text-xs font-mono font-bold px-2 text-slate-700 dark:text-slate-300">
                            {{ startDate }} ⟶ {{ endDate }}
                        </span>
                        <el-button size="small" :icon="ArrowRight" circle @click="navigateWeek(7)" title="Tuần sau" />
                    </div>

                    <!-- Lọc phòng ban -->
                    <el-select
                        v-model="selectedDept"
                        placeholder="Tất cả phòng ban"
                        clearable
                        style="width: 180px"
                        @change="handleDepartmentChange"
                    >
                        <el-option
                            v-for="dept in departments"
                            :key="dept.id"
                            :label="dept.name"
                            :value="dept.id"
                        />
                    </el-select>

                    <!-- Nút Chạy Solver Phân Ca -->
                    <el-button type="primary" :icon="RefreshRight" class="!rounded-xl !font-semibold" @click="openSolverDialog">
                        Phân Ca Tự Động
                    </el-button>

                    <!-- Nút Công bố -->
                    <el-button type="success" :icon="Promotion" class="!rounded-xl !font-semibold" @click="handlePublishRoster">
                        Công Bố Lịch
                    </el-button>
                </div>
            </div>

            <!-- Banner Ca Tiêu Chuẩn Mặc Định Đang Áp Dụng -->
            <div v-if="defaultShift" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <el-icon :size="20"><StarFilled /></el-icon>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-slate-900 dark:text-white text-sm">Ca Tiêu Chuẩn Áp Dụng Toàn Công Ty:</span>
                            <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/60 px-2 py-0.5 rounded text-xs">
                                {{ defaultShift.code }}
                            </span>
                            <span class="text-slate-800 dark:text-slate-200 text-sm font-semibold">
                                {{ defaultShift.name }} ({{ defaultShift.start_time.substring(0, 5) }} - {{ defaultShift.end_time.substring(0, 5) }})
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Các ngày chưa phân ca riêng sẽ tự động kế thừa ca này (hiển thị nhãn <strong>[Mặc định]</strong>). Người quản lý không cần phải phân ca thủ công cho từng nhân viên.
                        </p>
                    </div>
                </div>
                <div class="shrink-0 flex items-center space-x-2">
                    <Link href="/rosters/shifts" class="el-button el-button--small is-plain">
                        Quản Lý Ca Làm Việc
                    </Link>
                </div>
            </div>

            <!-- Bảng Ma Trận Lưới Phân Ca -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                <!-- Fixed Column Nhân viên -->
                                <th class="p-3 w-56 sticky left-0 bg-slate-50 dark:bg-slate-800 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                    Nhân Viên / Phòng Ban
                                </th>

                                <!-- Các cột ngày trong tuần -->
                                <th
                                    v-for="col in dates"
                                    :key="col.date"
                                    class="p-3 min-w-[130px] text-center border-l border-slate-100 dark:border-slate-800"
                                    :class="col.is_weekend ? 'bg-amber-50/30 dark:bg-amber-950/20 text-amber-800 dark:text-amber-300' : ''"
                                >
                                    <div class="font-bold uppercase tracking-wider">{{ col.day_name }}</div>
                                    <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400 font-normal">{{ col.day_number }}</div>
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <tr
                                v-for="row in matrix"
                                :key="row.employee_id"
                                class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition"
                            >
                                <!-- Cột nhân viên (Sticky) -->
                                <td class="p-3 sticky left-0 bg-white dark:bg-slate-900 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-[10px] text-slate-600 dark:text-slate-300">
                                            {{ row.user_code.substring(0, 3) }}
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="font-bold text-slate-900 dark:text-white truncate" :title="row.full_name">
                                                {{ row.full_name }}
                                            </div>
                                            <div class="text-[11px] text-slate-400 truncate">
                                                {{ row.department_name }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Badges ưu tiên thai sản / con nhỏ -->
                                    <div class="flex flex-wrap gap-1 mt-1.5">
                                        <el-tag v-if="row.has_child_under_12m" type="danger" size="small" effect="plain" class="!text-[10px] !h-4 !px-1">
                                            Con &lt; 12T (Đ.137)
                                        </el-tag>
                                        <el-tag v-if="row.is_pregnant" type="warning" size="small" effect="plain" class="!text-[10px] !h-4 !px-1">
                                            Thai sản
                                        </el-tag>
                                    </div>
                                </td>

                                <!-- Các ô ngày công -->
                                <td
                                    v-for="col in dates"
                                    :key="col.date"
                                    class="p-2 text-center border-l border-slate-100 dark:border-slate-800 align-top cursor-pointer group"
                                    :class="col.is_weekend ? 'bg-amber-50/10 dark:bg-amber-950/10' : ''"
                                    @click="openAssignDialog(row, col.date)"
                                >
                                    <!-- Ô đã có ca -->
                                    <div v-if="row.days[col.date]?.shift_id" class="p-2 rounded-xl transition border"
                                        :class="[
                                            row.days[col.date]?.is_default_inherited
                                                ? 'bg-emerald-50/60 dark:bg-emerald-950/30 border-dashed border-emerald-300 dark:border-emerald-800/80 text-emerald-800 dark:text-emerald-300'
                                                : row.days[col.date]?.is_night_shift
                                                    ? 'bg-purple-50 dark:bg-purple-950/40 border-purple-200 dark:border-purple-800/60 text-purple-700 dark:text-purple-300'
                                                    : 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800/60 text-indigo-700 dark:text-indigo-300',
                                            row.days[col.date]?.violation_flags?.length > 0
                                                ? '!border-red-500 !bg-red-50 dark:!bg-red-950/40 !text-red-700 dark:!text-red-300'
                                                : ''
                                        ]"
                                    >
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="font-bold text-[11px] truncate">
                                                {{ row.days[col.date]?.shift_code }}
                                            </span>
                                            <div class="flex items-center space-x-1">
                                                <el-tag v-if="row.days[col.date]?.is_default_inherited" type="success" size="small" class="!text-[9px] !h-4 !px-1">
                                                    Mặc định
                                                </el-tag>
                                                <el-icon v-if="row.days[col.date]?.is_night_shift" :size="12"><Moon /></el-icon>
                                                <el-icon v-else-if="!row.days[col.date]?.is_default_inherited" :size="12"><Sunny /></el-icon>
                                            </div>
                                        </div>

                                        <div class="font-mono text-[10px] opacity-80">
                                            {{ row.days[col.date]?.start_time?.substring(0, 5) }} - {{ row.days[col.date]?.end_time?.substring(0, 5) }}
                                        </div>

                                        <!-- Cảnh báo vi phạm nếu có -->
                                        <div v-if="row.days[col.date]?.violation_flags?.length > 0" class="mt-1 flex items-center justify-center space-x-1 text-[10px] font-bold text-red-600 dark:text-red-400">
                                            <el-icon :size="12"><WarningFilled /></el-icon>
                                            <span class="truncate" :title="row.days[col.date]?.violation_flags?.join('; ')">Cảnh báo luật</span>
                                        </div>

                                        <!-- Badge đã công bố -->
                                        <div v-if="row.days[col.date]?.is_published && !row.days[col.date]?.is_default_inherited" class="mt-1 text-[9px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center justify-center">
                                            <el-icon :size="10" class="mr-0.5"><Check /></el-icon> Đã công bố
                                        </div>
                                    </div>

                                    <!-- Ô ngày nghỉ (OFF) -->
                                    <div v-else class="h-14 rounded-xl border border-dashed border-slate-200 dark:border-slate-800 flex flex-col items-center justify-center text-slate-300 dark:text-slate-600 group-hover:border-indigo-300 dark:group-hover:border-indigo-700 group-hover:text-indigo-500 transition">
                                        <span class="text-[10px] font-medium">Nghỉ (OFF)</span>
                                        <span class="text-[9px] opacity-0 group-hover:opacity-100 transition">+ Gán ca</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Dialog Gán Ca Nhanh -->
        <el-dialog
            v-model="assignDialogVisible"
            title="Gán Ca Làm Việc Cho Nhân Viên"
            width="460px"
            destroy-on-close
            class="!rounded-2xl"
        >
            <div v-if="selectedCell" class="space-y-4">
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl flex items-center justify-between text-xs">
                    <div>
                        <div class="text-slate-400">Nhân viên:</div>
                        <div class="font-bold text-slate-800 dark:text-white mt-0.5">{{ selectedCell.employeeName }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-slate-400">Ngày làm việc:</div>
                        <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400 mt-0.5">{{ selectedCell.date }}</div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        Chọn Ca Làm Việc:
                    </label>
                    <el-select
                        v-model="assignForm.shift_id"
                        placeholder="Chọn ca hoặc chọn Nghỉ (OFF)"
                        clearable
                        style="width: 100%"
                    >
                        <el-option :value="null" label="-- Đặt làm Ngày Nghỉ (OFF) --" />
                        <el-option
                            v-for="shift in shifts"
                            :key="shift.id"
                            :label="`${shift.name} (${shift.start_time.substring(0, 5)} - ${shift.end_time.substring(0, 5)}${shift.is_night_shift ? ' - Ca Đêm' : ''})`"
                            :value="shift.id"
                        />
                    </el-select>
                    <p class="text-[11px] text-slate-500 mt-1">
                        * Để trống hoặc xóa để nhân viên tự động áp dụng Ca tiêu chuẩn mặc định toàn công ty.
                    </p>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end space-x-2">
                    <el-button @click="assignDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="assignForm.processing" @click="handleSaveAssignment">
                        Lưu Phân Ca
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog Solver Phân Ca Tự Động & Mô Phỏng Đối Soát -->
        <el-dialog
            v-model="solverDialogVisible"
            title="Động Cơ Phân Ca Tự Động & Minh Bạch Chi Phí"
            width="750px"
            destroy-on-close
            class="!rounded-2xl"
        >
            <div class="space-y-4 text-xs">
                <!-- Banner Ràng buộc BLLĐ -->
                <div class="p-3.5 bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-200 dark:border-indigo-800 rounded-xl text-indigo-800 dark:text-indigo-300">
                    <div class="font-bold mb-1 flex items-center space-x-1.5">
                        <el-icon><Check /></el-icon>
                        <span>Tuân thủ Ràng Buộc Cứng Bộ Luật Lao Động 2019:</span>
                    </div>
                    Thuật toán toán học tự động bảo vệ:
                    <div class="grid grid-cols-2 gap-2 mt-1 font-medium">
                        <div>• Nghỉ tối thiểu 12 giờ giữa 2 ca (Đ.110).</div>
                        <div>• Nghỉ 24 giờ liên tục mỗi tuần (Đ.111).</div>
                        <div>• Tối đa 48 giờ làm việc chuẩn/tuần (Đ.105).</div>
                        <div>• Tuyệt đối cấm ca đêm/OT cho thai sản & con &lt; 12T (Đ.137).</div>
                    </div>
                </div>

                <!-- Cấu hình thời gian & phòng ban -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1">Từ ngày:</label>
                        <el-date-picker v-model="solverForm.start_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Đến ngày:</label>
                        <el-date-picker v-model="solverForm.end_date" type="date" value-format="YYYY-MM-DD" style="width: 100%" />
                    </div>
                </div>

                <!-- Chiến lược cốt lõi -->
                <div>
                    <label class="block font-semibold mb-1">Chiến Lược Phân Ca Chủ Đạo:</label>
                    <el-radio-group v-model="solverForm.strategy" class="w-full">
                        <el-radio-button value="FAIRNESS" class="w-1/2">
                            Công bằng Nhân sự (Cân bằng số ca đêm & cuối tuần)
                        </el-radio-button>
                        <el-radio-button value="COST_OPTIMIZATION" class="w-1/2">
                            Tối ưu Chi phí (Hạn chế tối đa phụ cấp ca đêm & OT giá cao)
                        </el-radio-button>
                    </el-radio-group>
                </div>

                <!-- Tiêu chí nâng cao (Configurable Preferences) -->
                <div class="p-3.5 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 space-y-2">
                    <div class="font-bold text-slate-800 dark:text-slate-200">Tiêu Chí Ưu Tiên & Tối Ưu Chi Phí Ca Đêm (Điều 98 BLLĐ):</div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Tối ưu chi phí phụ cấp 30% lương ca đêm:</span>
                            <p class="text-[11px] text-slate-400">Ưu tiên phân ca đêm cho nhân sự có mức lương cơ bản thấp hơn hoặc đang thử việc để giảm tổng chi phí phụ cấp cho doanh nghiệp.</p>
                        </div>
                        <el-switch v-model="solverForm.preferences.prioritize_low_base_salary" />
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                        <div>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Ưu tiên tránh ca đêm cho lao động Nữ:</span>
                            <p class="text-[11px] text-slate-400">Ưu tiên nam giới làm ca đêm (22h - 6h) vì yếu tố an toàn đi lại đêm muộn.</p>
                        </div>
                        <el-switch v-model="solverForm.preferences.avoid_female_night" />
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                        <div>
                            <span class="font-semibold text-slate-700 dark:text-slate-300">Ưu tiên người chưa có con làm ca đêm:</span>
                            <p class="text-[11px] text-slate-400">Tránh xếp ca đêm cho nhân viên đang có con nhỏ (kể cả trên 12 tháng), ưu tiên nhân sự độc thân.</p>
                        </div>
                        <el-switch v-model="solverForm.preferences.avoid_has_child" />
                    </div>
                </div>

                <!-- Ca làm việc sử dụng -->
                <div>
                    <label class="block font-semibold mb-1">Các Ca Mẫu Tham Gia Phân Bổ:</label>
                    <el-checkbox-group v-model="solverForm.shift_ids" class="grid grid-cols-2 gap-1.5">
                        <el-checkbox v-for="shift in shifts" :key="shift.id" :value="shift.id">
                            <span class="font-medium">{{ shift.name }}</span>
                            <span v-if="shift.is_night_shift" class="text-purple-600 font-bold ml-1">(Ca Đêm +30%)</span>
                        </el-checkbox>
                    </el-checkbox-group>
                </div>

                <!-- Bảng Hiển Thị Kết Quả Mô Phỏng & Đối Soát -->
                <div v-if="simulationResult" class="space-y-3 pt-2 border-t border-slate-200 dark:border-slate-700">
                    <div class="flex items-center justify-between">
                        <div class="font-bold text-indigo-600 dark:text-indigo-400 flex items-center space-x-1">
                            <el-icon><DataAnalysis /></el-icon>
                            <span>Bảng Đối Soát Mô Phỏng Phân Ca (Chưa lưu CSDL)</span>
                        </div>
                        <el-tag type="success" size="small">Đối Soát Minh Bạch</el-tag>
                    </div>

                    <!-- KPI Cards -->
                    <div class="grid grid-cols-4 gap-2 text-center">
                        <div class="p-2 bg-slate-100 dark:bg-slate-800 rounded-lg">
                            <div class="text-[10px] text-slate-400">Tổng ca xếp</div>
                            <div class="font-bold text-slate-800 dark:text-white text-base">{{ simulationResult.total_shifts_scheduled }}</div>
                        </div>
                        <div class="p-2 bg-purple-50 dark:bg-purple-950/40 rounded-lg">
                            <div class="text-[10px] text-purple-600 dark:text-purple-400">Số ca đêm</div>
                            <div class="font-bold text-purple-700 dark:text-purple-300 text-base">{{ simulationResult.total_night_shifts }}</div>
                        </div>
                        <div class="p-2 bg-amber-50 dark:bg-amber-950/40 rounded-lg">
                            <div class="text-[10px] text-amber-600 dark:text-amber-400">Dự toán phụ cấp ca đêm</div>
                            <div class="font-bold text-amber-700 dark:text-amber-300 text-sm">
                                {{ Number(simulationResult.estimated_night_allowance).toLocaleString('vi-VN') }} đ
                            </div>
                        </div>
                        <div class="p-2 bg-emerald-50 dark:bg-emerald-950/40 rounded-lg">
                            <div class="text-[10px] text-emerald-600 dark:text-emerald-400">Vi phạm ngăn chặn</div>
                            <div class="font-bold text-emerald-700 dark:text-emerald-300 text-base">{{ simulationResult.violations_prevented }}</div>
                        </div>
                    </div>

                    <!-- Bảng chi tiết từng nhân sự -->
                    <div class="max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-left text-[11px]">
                            <thead class="bg-slate-50 dark:bg-slate-800 sticky top-0 font-bold text-slate-600 dark:text-slate-300">
                                <tr>
                                    <th class="p-2">Nhân viên</th>
                                    <th class="p-2 text-center">Giới tính</th>
                                    <th class="p-2 text-center">Ca Ngày</th>
                                    <th class="p-2 text-center">Ca Đêm</th>
                                    <th class="p-2 text-center">Nghỉ</th>
                                    <th class="p-2 text-right">Phụ Cấp Ca Đêm</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="emp in simulationResult.employee_breakdown" :key="emp.employee_id">
                                    <td class="p-2 font-medium">
                                        {{ emp.full_name }}
                                        <span v-if="emp.has_children" class="text-slate-400 text-[10px]">(Có con)</span>
                                    </td>
                                    <td class="p-2 text-center">{{ emp.gender === 'FEMALE' ? 'Nữ' : 'Nam' }}</td>
                                    <td class="p-2 text-center">{{ emp.day_shifts_count }}</td>
                                    <td class="p-2 text-center font-bold text-purple-600">{{ emp.night_shifts_count }}</td>
                                    <td class="p-2 text-center">{{ emp.rest_days_count }}</td>
                                    <td class="p-2 text-right font-mono text-amber-700 dark:text-amber-400">
                                        {{ Number(emp.estimated_night_allowance).toLocaleString('vi-VN') }} đ
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-between items-center">
                    <el-button :icon="DataAnalysis" :loading="isSimulating" @click="runSimulation">
                        Mô Phỏng & Đối Soát Chi Phí
                    </el-button>
                    <div class="space-x-2">
                        <el-button @click="solverDialogVisible = false">Đóng</el-button>
                        <el-button type="primary" :loading="solverForm.processing" @click="handleRunSolver">
                            {{ simulationResult ? 'Xác Nhận Áp Dụng Lịch Này' : 'Áp Dụng Phân Ca' }}
                        </el-button>
                    </div>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
