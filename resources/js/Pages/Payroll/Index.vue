<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AuditHistoryDrawer from '@/Components/AuditHistoryDrawer.vue';
import PayslipModal from './Components/PayslipModal.vue';
import {
    Wallet,
    Calendar,
    Lock,
    Unlock,
    RefreshRight,
    Warning,
    CircleCheck,
    Document,
    Clock,
    User,
    Money,
    List,
    Check,
    Plus,
    Edit,
    Delete,
    Search,
    Printer
} from '@element-plus/icons-vue';
import { ElMessageBox, ElMessage } from 'element-plus';
import { useSyncedTab } from '@/Composables/useNavigationHistory';

interface PayrollRecord {
    id: number;
    employee_id: number;
    month_year: string;
    gross_salary: string | number;
    position_allowance_amount: string | number;
    child_allowance_amount: string | number;
    certificate_allowance_amount: string | number;
    tax_exempt_ot_amount: string | number;
    taxable_ot_amount: string | number;
    social_insurance_employee: string | number;
    health_insurance_employee: string | number;
    unemployment_insurance_employee: string | number;
    pit_tax_amount: string | number;
    net_salary: string | number;
    salary_breakdown?: any;
    status: string;
    employee?: {
        id: number;
        user_code: string;
        full_name: string;
        status: string;
        current_timeline?: {
            department?: { current_version?: { name: string } };
            position?: { name: string };
        };
    };
}

interface UnapprovedEmployee {
    employee_id: number;
    user_code: string;
    full_name: string;
    status: string;
    has_timesheet: boolean;
    is_approved: boolean;
    total_days: number;
    reason: string;
}

interface EmployeeSalaryItem {
    id: number;
    tenant_id: number;
    employee_id: number;
    base_salary: string | number;
    insurance_salary: string | number | null;
    contract_type: string;
    effective_date: string;
    end_date: string | null;
    notes: string | null;
    is_locked: boolean;
    employee?: {
        id: number;
        user_code: string;
        full_name: string;
        current_timeline?: {
            department?: { current_version?: { name: string } };
            position?: { name: string };
        };
    };
}

interface PayrollPeriod {
    id: number;
    month_year: string;
    status: string;
    is_locked: boolean;
    locked_at?: string;
    locked_by?: {
        full_name: string;
    };
    policy_version?: {
        version_code: string;
    };
}

const props = defineProps<{
    monthYear: string;
    payrollPeriod: PayrollPeriod | null;
    records: PayrollRecord[];
    unapprovedEmployees: UnapprovedEmployee[];
    employeeSalaries: EmployeeSalaryItem[];
    allEmployees: Array<{ id: number; user_code: string; full_name: string; status: string }>;
    totalEmployeesCount: number;
    calculatedCount: number;
    unapprovedCount: number;
}>();

const selectedMonth = ref(props.monthYear);
const activeTab = useSyncedTab('records', 'tab');
const isCalculating = ref(false);
const isLocking = ref(false);

// Phiếu lương chi tiết (Payslip Modal)
const isPayslipModalVisible = ref(false);
const selectedPayslipRecord = ref<any>(null);

const openPayslip = (record: any) => {
    selectedPayslipRecord.value = record;
    isPayslipModalVisible.value = true;
};

// Quản lý Mức lương nhân viên (Employee Salary Dialog)
const salaryDialogVisible = ref(false);
const editingSalary = ref<EmployeeSalaryItem | null>(null);
const salaryForm = useForm({
    employee_id: null as number | null,
    base_salary: 10000000,
    insurance_salary: null as number | null,
    contract_type: 'OFFICIAL',
    effective_date: new Date().toISOString().substring(0, 10),
    end_date: null as string | null,
    notes: '',
});

const openCreateSalary = () => {
    editingSalary.value = null;
    salaryForm.reset();
    salaryForm.effective_date = new Date().toISOString().substring(0, 10);
    salaryDialogVisible.value = true;
};

const openEditSalary = (item: EmployeeSalaryItem) => {
    if (item.is_locked) {
        ElMessage.warning('Mức lương này đã được áp dụng trong kỳ lương đã khóa sổ, không thể chỉnh sửa.');
        return;
    }
    editingSalary.value = item;
    salaryForm.employee_id = item.employee_id;
    salaryForm.base_salary = Number(item.base_salary);
    salaryForm.insurance_salary = item.insurance_salary ? Number(item.insurance_salary) : null;
    salaryForm.contract_type = item.contract_type;
    salaryForm.effective_date = item.effective_date;
    salaryForm.end_date = item.end_date;
    salaryForm.notes = item.notes || '';
    salaryDialogVisible.value = true;
};

const submitSalaryForm = () => {
    if (editingSalary.value) {
        salaryForm.put(`/payroll/salaries/${editingSalary.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Đã cập nhật mức lương nhân sự thành công!');
                salaryDialogVisible.value = false;
            },
            onError: (errs) => {
                ElMessage.error(Object.values(errs)[0] as string || 'Có lỗi khi lưu mức lương.');
            }
        });
    } else {
        salaryForm.post('/payroll/salaries', {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Đã thêm mức lương mới thành công!');
                salaryDialogVisible.value = false;
            },
            onError: (errs) => {
                ElMessage.error(Object.values(errs)[0] as string || 'Có lỗi khi thêm mức lương.');
            }
        });
    }
};

const deleteSalaryItem = (item: EmployeeSalaryItem) => {
    if (item.is_locked) {
        ElMessage.warning('Mức lương này đã được áp dụng trong kỳ lương đã khóa sổ, không thể xóa.');
        return;
    }

    ElMessageBox.confirm(
        `Bạn có chắc chắn muốn xóa bản ghi mức lương của nhân sự ${item.employee?.full_name}?`,
        'Xác nhận xóa mức lương',
        { confirmButtonText: 'Xóa vĩnh viễn', cancelButtonText: 'Hủy', type: 'warning' }
    ).then(() => {
        router.delete(`/payroll/salaries/${item.id}`, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Đã xóa mức lương thành công.'),
        });
    });
};

// Tự động đồng bộ selectedMonth khi người dùng bấm Back / Forward trình duyệt
watch(() => props.monthYear, (newVal) => {
    if (newVal) {
        selectedMonth.value = newVal;
    }
});

// Audit History Drawer
const isAuditDrawerVisible = ref(false);
const auditEntityId = ref<number | undefined>(props.payrollPeriod?.id);

const isPeriodLocked = computed(() => !!props.payrollPeriod?.is_locked);

// Thống kê tổng hợp quỹ lương
const totalGross = computed(() => {
    return props.records.reduce((acc, r) => acc + Number(r.gross_salary || 0), 0);
});

const totalNet = computed(() => {
    return props.records.reduce((acc, r) => acc + Number(r.net_salary || 0), 0);
});

const totalPitTax = computed(() => {
    return props.records.reduce((acc, r) => acc + Number(r.pit_tax_amount || 0), 0);
});

const totalInsurance = computed(() => {
    return props.records.reduce((acc, r) => {
        return acc + Number(r.social_insurance_employee || 0)
            + Number(r.health_insurance_employee || 0)
            + Number(r.unemployment_insurance_employee || 0);
    }, 0);
});

const formatCurrency = (val: number | string | undefined): string => {
    if (val === undefined || val === null) return '0 ₫';
    return Number(val).toLocaleString('vi-VN') + ' ₫';
};

const handleMonthChange = (val: string) => {
    if (!val) return;
    router.get('/payroll', { month_year: val }, { preserveState: true });
};

const handleCalculate = () => {
    if (isPeriodLocked.value) {
        ElMessage.warning('Kỳ lương đã khóa sổ, không thể tính toán lại.');
        return;
    }

    ElMessageBox.confirm(
        `Bạn có chắc chắn muốn chạy động cơ tính lương cho kỳ ${selectedMonth.value}? ` +
        `Quy chuẩn: Những nhân sự chưa được Quản lý duyệt bảng chấm công sẽ tự động bị bỏ qua.`,
        'Xác nhận tính lương',
        {
            confirmButtonText: 'Bắt đầu tính lương',
            cancelButtonText: 'Hủy',
            type: 'primary',
        }
    ).then(() => {
        isCalculating.value = true;
        router.post('/payroll/calculate', { month_year: selectedMonth.value }, {
            onFinish: () => {
                isCalculating.value = false;
            },
        });
    });
};

const handleLockPeriod = () => {
    ElMessageBox.confirm(
        `CẢNH BÁO KIỂM TOÁN: Sau khi khóa sổ kỳ lương ${selectedMonth.value}, toàn bộ dữ liệu công, lương và các mức lương cá nhân có hiệu lực trong kỳ này sẽ trở thành BẤT BIẾN, không thể sửa đổi hay hồi tố. Bạn có chắc chắn muốn tiếp tục?`,
        'Khóa sổ kỳ lương bất biến',
        {
            confirmButtonText: 'Khóa sổ vĩnh viễn',
            cancelButtonText: 'Xem lại',
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    ).then(() => {
        isLocking.value = true;
        router.post('/payroll/lock-period', { month_year: selectedMonth.value }, {
            onFinish: () => {
                isLocking.value = false;
            },
        });
    });
};

const handleApproveTimesheet = (empId: number) => {
    router.post(`/payroll/approve-timesheet/${empId}`, { month_year: selectedMonth.value }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success('Đã duyệt bảng chấm công. Bạn có thể bấm Tính lương lại để bổ sung nhân sự này.');
        },
    });
};

const openAuditHistory = () => {
    auditEntityId.value = props.payrollPeriod?.id;
    isAuditDrawerVisible.value = true;
};
</script>

<template>
    <AppLayout>
        <Head title="Bảng Lương & Chu Trình Tính Lương - Smart-HR" />

        <div class="space-y-6 pb-12">
            <!-- Header & Action Bar -->
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div>
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <el-icon :size="24"><Wallet /></el-icon>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                                Bảng Lương & Chu Trình Tính Lương
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Tính lương Gross-to-Net, Biểu thuế TNCN 5 bậc, OT miễn thuế NĐ 145 & Khóa sổ bất biến
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Chọn kỳ lương -->
                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800/80 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1">
                            <el-icon><Calendar /></el-icon> Kỳ lương:
                        </span>
                        <el-date-picker
                            v-model="selectedMonth"
                            type="month"
                            value-format="YYYY-MM"
                            format="MM/YYYY"
                            placeholder="Chọn tháng"
                            class="!w-32"
                            size="small"
                            :clearable="false"
                            @change="handleMonthChange"
                        />
                    </div>

                    <!-- Nút tính toán lương -->
                    <el-button
                        type="primary"
                        :icon="RefreshRight"
                        :loading="isCalculating"
                        :disabled="isPeriodLocked"
                        @click="handleCalculate"
                    >
                        Tính Lương Kỳ Này
                    </el-button>

                    <!-- Nút khóa sổ kỳ lương -->
                    <el-button
                        v-if="!isPeriodLocked"
                        type="danger"
                        plain
                        :icon="Lock"
                        :loading="isLocking"
                        @click="handleLockPeriod"
                    >
                        Khóa Sổ Kỳ Lương
                    </el-button>

                    <!-- Nút Lịch sử kiểm toán -->
                    <el-button
                        v-if="payrollPeriod"
                        :icon="Clock"
                        plain
                        @click="openAuditHistory"
                    >
                        Kiểm Toán
                    </el-button>
                </div>
            </div>

            <!-- Cảnh báo quy tắc nghiệp vụ: Bỏ qua nhân sự chưa duyệt chấm công -->
            <el-alert
                title="Quy chuẩn Kiểm toán & Tính Lương (Zero-Hardcode & Temporal Integrity)"
                type="info"
                show-icon
                :closable="false"
                class="!rounded-xl border border-blue-200 dark:border-blue-900/60"
            >
                <template #default>
                    <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1">
                        <div>
                            • <strong>Quy tắc loại trừ bắt buộc:</strong> Bất kỳ nhân sự nào <strong>chưa được Quản lý duyệt bảng chấm công tháng</strong> (<code>is_approved = false</code>) sẽ <strong>tự động bị bỏ qua</strong> không tính lương để bảo vệ ngân sách doanh nghiệp.
                        </div>
                        <div>
                            • <strong>Bảo vệ Bất biến:</strong> Khi kỳ lương đã Khóa sổ (<code>is_locked = true</code>), mọi thao tác sửa đổi công, lương, phụ cấp hay mức lương cá nhân có hiệu lực trong kỳ đều bị phong tỏa hoàn toàn.
                        </div>
                    </div>
                </template>
            </el-alert>

            <!-- Trạng thái Khóa sổ Banner -->
            <div
                v-if="isPeriodLocked"
                class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-center justify-between"
            >
                <div class="flex items-center gap-3 text-amber-800 dark:text-amber-300 text-xs">
                    <el-icon :size="20" class="text-amber-600 dark:text-amber-400"><Lock /></el-icon>
                    <div>
                        <div class="font-bold text-sm">Kỳ lương này ĐÃ ĐƯỢC KHÓA SỔ BẤT BIẾN</div>
                        <div>
                            Đã khóa lúc: {{ payrollPeriod?.locked_at || 'N/A' }} 
                            <span v-if="payrollPeriod?.locked_by">• Người khóa: {{ payrollPeriod?.locked_by?.full_name }}</span>
                        </div>
                    </div>
                </div>
                <el-tag type="danger" effect="dark" round>LOCKED</el-tag>
            </div>

            <!-- Tổng quan các chỉ số quỹ lương -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Đã Tính Lương</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <el-icon :size="16"><CircleCheck /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ calculatedCount }}</span>
                        <span class="text-xs text-slate-400">/ {{ totalEmployeesCount }} nhân sự</span>
                    </div>
                    <div class="mt-2 text-[11px] text-emerald-600 font-medium">Đủ điều kiện & đã duyệt công</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Bị Bỏ Qua (Chưa Duyệt)</span>
                        <div class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <el-icon :size="16"><Warning /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-rose-600 dark:text-rose-400">{{ unapprovedCount }}</span>
                        <span class="text-xs text-slate-400">nhân sự</span>
                    </div>
                    <div class="mt-2 text-[11px] text-rose-600 font-medium">Bảng công chưa được approve</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tổng Quỹ Lương Gross</span>
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <el-icon :size="16"><Money /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-xl font-black text-slate-900 dark:text-white">{{ formatCurrency(totalGross) }}</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-400">Bao gồm phụ cấp & OT</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Thực Chi (Tổng Net)</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <el-icon :size="16"><Wallet /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-xl font-black text-indigo-600 dark:text-indigo-400">{{ formatCurrency(totalNet) }}</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-400">Thuế: {{ formatCurrency(totalPitTax) }} • BH: {{ formatCurrency(totalInsurance) }}</div>
                </div>
            </div>

            <!-- Tabs: Bảng lương đã tính vs Danh sách bị bỏ qua vs Mức lương nhân viên -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
                <el-tabs v-model="activeTab">
                    <!-- Tab 1: Danh sách bảng lương chi tiết -->
                    <el-tab-pane name="records">
                        <template #label>
                            <span class="flex items-center gap-1.5 font-bold text-sm">
                                <el-icon><List /></el-icon>
                                Bảng Lương Đã Tính ({{ records.length }})
                            </span>
                        </template>

                        <div class="mt-3 overflow-x-auto">
                            <el-table
                                :data="records"
                                stripe
                                border
                                style="width: 100%"
                                empty-text="Chưa có bảng lương nào được tính trong kỳ này. Vui lòng bấm 'Tính Lương Kỳ Này'."
                            >
                                <el-table-column prop="employee.user_code" label="Mã NV" width="100" fixed>
                                    <template #default="{ row }">
                                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ row.employee?.user_code }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="employee.full_name" label="Họ và Tên" min-width="160" fixed>
                                    <template #default="{ row }">
                                        <div class="font-medium text-slate-800 dark:text-slate-200">{{ row.employee?.full_name }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase">{{ row.employee?.status }}</div>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Lương Gross" width="140" align="right">
                                    <template #default="{ row }">
                                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ formatCurrency(row.gross_salary) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="PC Chức Vụ" width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs">{{ formatCurrency(row.position_allowance_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="TC Con Nhỏ" width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs">{{ formatCurrency(row.child_allowance_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="OT Miễn Thuế" width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs text-emerald-600 font-semibold">{{ formatCurrency(row.tax_exempt_ot_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="BHXH/YT/TN" width="130" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs text-slate-600 dark:text-slate-300">
                                            {{ formatCurrency(Number(row.social_insurance_employee) + Number(row.health_insurance_employee) + Number(row.unemployment_insurance_employee)) }}
                                        </span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Thuế TNCN" width="130" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">{{ formatCurrency(row.pit_tax_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Thực Nhận (Net)" width="150" align="right">
                                    <template #default="{ row }">
                                        <span class="font-black text-indigo-600 dark:text-indigo-400 text-sm">{{ formatCurrency(row.net_salary) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Phiếu Lương" width="120" align="center" fixed="right">
                                    <template #default="{ row }">
                                        <el-button
                                            size="small"
                                            type="primary"
                                            plain
                                            :icon="Document"
                                            @click="openPayslip(row)"
                                        >
                                            Phiếu Lương
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 2: Danh sách nhân sự bị bỏ qua do chưa duyệt bảng chấm công -->
                    <el-tab-pane name="unapproved">
                        <template #label>
                            <span class="flex items-center gap-1.5 font-bold text-sm text-rose-600 dark:text-rose-400">
                                <el-icon><Warning /></el-icon>
                                Nhân Sự Bị Bỏ Qua ({{ unapprovedEmployees.length }})
                            </span>
                        </template>

                        <div class="mt-3">
                            <div class="p-3 bg-rose-50 dark:bg-rose-950/30 rounded-xl border border-rose-200 dark:border-rose-900/40 text-xs text-rose-700 dark:text-rose-300 mb-4 flex items-center justify-between">
                                <div>
                                    Những nhân sự dưới đây chưa đủ điều kiện tính lương do bảng chấm công tháng chưa được Quản lý bộ phận phê duyệt. 
                                    Sau khi bảng công được duyệt, bấm <strong>"Tính Lương Kỳ Này"</strong> để hệ thống tự động bổ sung vào bảng lương.
                                </div>
                            </div>

                            <el-table
                                :data="unapprovedEmployees"
                                stripe
                                border
                                style="width: 100%"
                                empty-text="Tuyệt vời! Toàn bộ nhân sự trong kỳ đều đã được duyệt bảng chấm công."
                            >
                                <el-table-column prop="user_code" label="Mã NV" width="110">
                                    <template #default="{ row }">
                                        <span class="font-bold text-slate-700 dark:text-slate-300">{{ row.user_code }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="full_name" label="Họ và Tên" min-width="180">
                                    <template #default="{ row }">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ row.full_name }}</div>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="status" label="Hợp Đồng" width="120">
                                    <template #default="{ row }">
                                        <el-tag size="small" :type="row.status === 'PROBATION' ? 'warning' : 'info'">{{ row.status }}</el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Bảng Công Tháng" width="150">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.has_timesheet" type="warning" size="small">Chờ duyệt (Pending)</el-tag>
                                        <el-tag v-else type="danger" size="small">Chưa tổng hợp</el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="reason" label="Lý Do Bỏ Qua" min-width="250">
                                    <template #default="{ row }">
                                        <span class="text-xs text-rose-600 dark:text-rose-400 font-medium">
                                            {{ row.reason }}
                                        </span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Hành Động" width="140" align="center">
                                    <template #default="{ row }">
                                        <el-button
                                            v-if="!isPeriodLocked"
                                            size="small"
                                            type="success"
                                            plain
                                            :icon="Check"
                                            @click="handleApproveTimesheet(row.employee_id)"
                                        >
                                            Duyệt Công
                                        </el-button>
                                        <span v-else class="text-xs text-slate-400">Đã khóa</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 3: Quản lý Mức Lương Nhân Viên (Effective Dating) -->
                    <el-tab-pane name="salaries">
                        <template #label>
                            <span class="flex items-center gap-1.5 font-bold text-sm">
                                <el-icon><Money /></el-icon>
                                Mức Lương Nhân Viên ({{ employeeSalaries.length }})
                            </span>
                        </template>

                        <div class="mt-3 space-y-4">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-xs text-slate-500">
                                    Quản lý mức lương cơ bản thỏa thuận theo dòng thời gian (Effective Dating). Mức lương trong kỳ đã chốt sổ sẽ tự động bị khóa chống sửa đổi hồi tố.
                                </div>
                                <el-button
                                    type="primary"
                                    size="small"
                                    :icon="Plus"
                                    @click="openCreateSalary"
                                >
                                    Thiết Lập Mức Lương Mới
                                </el-button>
                            </div>

                            <el-table
                                :data="employeeSalaries"
                                stripe
                                border
                                style="width: 100%"
                                empty-text="Chưa có bản ghi mức lương cá nhân nào."
                            >
                                <el-table-column prop="employee.user_code" label="Mã NV" width="100" fixed>
                                    <template #default="{ row }">
                                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ row.employee?.user_code }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="employee.full_name" label="Họ và Tên" min-width="170" fixed>
                                    <template #default="{ row }">
                                        <div class="font-medium text-slate-800 dark:text-slate-200">{{ row.employee?.full_name }}</div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ row.employee?.current_timeline?.position?.name || 'Nhân viên' }}
                                        </div>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Lương Cơ Bản" width="150" align="right">
                                    <template #default="{ row }">
                                        <span class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(row.base_salary) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Lương Đóng BH" width="150" align="right">
                                    <template #default="{ row }">
                                        <span v-if="row.insurance_salary" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                            {{ formatCurrency(row.insurance_salary) }}
                                        </span>
                                        <span v-else class="text-xs text-slate-400 italic">Theo lương cơ bản</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Loại HĐ" width="110" align="center">
                                    <template #default="{ row }">
                                        <el-tag size="small" :type="row.contract_type === 'OFFICIAL' ? 'success' : 'warning'">
                                            {{ row.contract_type }}
                                        </el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Thời Gian Áp Dụng" min-width="180">
                                    <template #default="{ row }">
                                        <div class="text-xs">
                                            Từ: <strong>{{ row.effective_date }}</strong>
                                            <span v-if="row.end_date"> &rarr; <strong>{{ row.end_date }}</strong></span>
                                            <span v-else class="text-emerald-600 font-semibold"> (Hiện tại)</span>
                                        </div>
                                        <div v-if="row.notes" class="text-[11px] text-slate-400 italic truncate max-w-xs">
                                            "{{ row.notes }}"
                                        </div>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Khóa Sổ" width="90" align="center">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.is_locked" type="danger" size="small" effect="plain">LOCKED</el-tag>
                                        <el-tag v-else type="info" size="small" effect="plain">Mở</el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Thao Tác" width="140" align="center" fixed="right">
                                    <template #default="{ row }">
                                        <div class="flex items-center justify-center gap-1">
                                            <el-button
                                                size="small"
                                                type="primary"
                                                link
                                                :disabled="row.is_locked"
                                                :icon="Edit"
                                                @click="openEditSalary(row)"
                                            >
                                                Sửa
                                            </el-button>
                                            <el-button
                                                size="small"
                                                type="danger"
                                                link
                                                :disabled="row.is_locked"
                                                :icon="Delete"
                                                @click="deleteSalaryItem(row)"
                                            >
                                                Xóa
                                            </el-button>
                                        </div>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- DIALOG THIẾT LẬP MỨC LƯƠNG NHÂN SỰ -->
        <el-dialog
            v-model="salaryDialogVisible"
            :title="editingSalary ? 'Chỉnh Sửa Mức Lương Nhân Sự' : 'Thiết Lập Mức Lương Mới Cho Nhân Sự'"
            width="560px"
        >
            <el-form :model="salaryForm" label-position="top" class="space-y-3 text-xs">
                <el-form-item label="Nhân sự áp dụng" required>
                    <el-select
                        v-model="salaryForm.employee_id"
                        placeholder="Chọn nhân viên"
                        filterable
                        :disabled="!!editingSalary"
                        class="w-full"
                    >
                        <el-option
                            v-for="emp in allEmployees"
                            :key="emp.id"
                            :label="`${emp.full_name} (${emp.user_code})`"
                            :value="emp.id"
                        />
                    </el-select>
                </el-form-item>

                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item label="Mức lương cơ bản thỏa thuận (VND)" required>
                            <el-input-number
                                v-model="salaryForm.base_salary"
                                :min="0"
                                :step="500000"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="Lương đóng bảo hiểm (VND)">
                            <el-input-number
                                v-model="salaryForm.insurance_salary"
                                :min="0"
                                :step="500000"
                                placeholder="Mặc định bằng lương cơ bản"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item label="Loại hợp đồng" required>
                            <el-select v-model="salaryForm.contract_type" class="w-full">
                                <el-option label="Chính thức" value="OFFICIAL" />
                                <el-option label="Thử việc (85%)" value="PROBATION" />
                                <el-option label="Thời vụ / Vãng lai" value="SEASONAL" />
                                <el-option label="Thực tập sinh" value="INTERN" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="Ngày bắt đầu hiệu lực" required>
                            <el-date-picker
                                v-model="salaryForm.effective_date"
                                type="date"
                                value-format="YYYY-MM-DD"
                                format="DD/MM/YYYY"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="Ngày hết hạn (tùy chọn)">
                            <el-date-picker
                                v-model="salaryForm.end_date"
                                type="date"
                                value-format="YYYY-MM-DD"
                                format="DD/MM/YYYY"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="Ghi chú điều chỉnh">
                    <el-input
                        v-model="salaryForm.notes"
                        placeholder="Lý do điều chỉnh (kết thúc thử việc, tăng lương định kỳ, bổ nhiệm...)"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="salaryDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="salaryForm.processing" @click="submitSalaryForm">
                        Lưu Mức Lương
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- PAYSLIP MODAL CHI TIẾT -->
        <PayslipModal
            v-model="isPayslipModalVisible"
            :record="selectedPayslipRecord"
        />

        <!-- Audit History Drawer -->
        <AuditHistoryDrawer
            v-model="isAuditDrawerVisible"
            entity-type="PayrollPeriod"
            :entity-id="auditEntityId"
            title="Lịch Sử Kiểm Toán Kỳ Lương"
        />
    </AppLayout>
</template>

<style scoped>
:deep(.el-table) {
    --el-table-header-bg-color: #f8fafc;
}
.dark :deep(.el-table) {
    --el-table-header-bg-color: #0f172a;
}
</style>
