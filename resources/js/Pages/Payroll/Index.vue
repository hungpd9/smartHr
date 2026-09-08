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
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

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

const { t } = useThemeAndLocale();

const selectedMonth = ref(props.monthYear);
const activeTab = useSyncedTab('records', 'tab');
const isCalculating = ref(false);
const isLocking = ref(false);

// Detailed payslip (Payslip Modal)
const isPayslipModalVisible = ref(false);
const selectedPayslipRecord = ref<any>(null);

const openPayslip = (record: any) => {
    selectedPayslipRecord.value = record;
    isPayslipModalVisible.value = true;
};

// Employee Salary management (Salary Dialog)
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
        ElMessage.warning(t('payroll.salary_locked_edit_msg'));
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
                ElMessage.success(t('payroll.salary_update_success'));
                salaryDialogVisible.value = false;
            },
            onError: (errs) => {
                ElMessage.error(Object.values(errs)[0] as string || t('payroll.salary_update_error'));
            }
        });
    } else {
        salaryForm.post('/payroll/salaries', {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success(t('payroll.salary_create_success'));
                salaryDialogVisible.value = false;
            },
            onError: (errs) => {
                ElMessage.error(Object.values(errs)[0] as string || t('payroll.salary_create_error'));
            }
        });
    }
};

const deleteSalaryItem = (item: EmployeeSalaryItem) => {
    if (item.is_locked) {
        ElMessage.warning(t('payroll.salary_locked_delete_msg'));
        return;
    }

    ElMessageBox.confirm(
        t('payroll.salary_delete_confirm_msg', { name: item.employee?.full_name as string }),
        t('payroll.salary_delete_confirm_title'),
        {
            confirmButtonText: t('payroll.salary_delete_confirm_btn'),
            cancelButtonText: t('common.cancel'),
            type: 'warning',
        }
    ).then(() => {
        router.delete(`/payroll/salaries/${item.id}`, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success(t('payroll.salary_delete_success')),
        });
    });
};

// Auto-sync selectedMonth when the user navigates Back / Forward in the browser
watch(() => props.monthYear, (newVal) => {
    if (newVal) {
        selectedMonth.value = newVal;
    }
});

// Audit History Drawer
const isAuditDrawerVisible = ref(false);
const auditEntityId = ref<number | undefined>(props.payrollPeriod?.id);

const isPeriodLocked = computed(() => !!props.payrollPeriod?.is_locked);

// Payroll fund aggregate statistics
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
        ElMessage.warning(t('payroll.period_locked_recalc_msg'));
        return;
    }

    ElMessageBox.confirm(
        t('payroll.calculate_run_confirm_msg', { month: selectedMonth.value }),
        t('payroll.calculate_run_confirm_title'),
        {
            confirmButtonText: t('payroll.calculate_run_confirm_btn'),
            cancelButtonText: t('common.cancel'),
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
        t('payroll.lock_period_confirm_msg', { month: selectedMonth.value }),
        t('payroll.lock_period_confirm_title'),
        {
            confirmButtonText: t('payroll.lock_period_confirm_btn'),
            cancelButtonText: t('payroll.lock_period_cancel_btn'),
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
            ElMessage.success(t('payroll.approve_timesheet_success'));
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
        <Head :title="t('payroll.page_title')" />

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
                                {{ $t('payroll.page_heading') }}
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $t('payroll.page_subtitle') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Select payroll period -->
                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-800/80 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700">
                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 flex items-center gap-1">
                            <el-icon><Calendar /></el-icon> {{ $t('payroll.period_label') }}
                        </span>
                        <el-date-picker
                            v-model="selectedMonth"
                            type="month"
                            value-format="YYYY-MM"
                            format="MM/YYYY"
                            :placeholder="$t('payroll.month_placeholder')"
                            class="!w-32"
                            size="small"
                            :clearable="false"
                            @change="handleMonthChange"
                        />
                    </div>

                    <!-- Calculate payroll button -->
                    <el-button
                        type="primary"
                        :icon="RefreshRight"
                        :loading="isCalculating"
                        :disabled="isPeriodLocked"
                        @click="handleCalculate"
                    >
                        {{ $t('payroll.calculate_period_btn') }}
                    </el-button>

                    <!-- Lock payroll period button -->
                    <el-button
                        v-if="!isPeriodLocked"
                        type="danger"
                        plain
                        :icon="Lock"
                        :loading="isLocking"
                        @click="handleLockPeriod"
                    >
                        {{ $t('payroll.lock_period_btn') }}
                    </el-button>

                    <!-- Audit history button -->
                    <el-button
                        v-if="payrollPeriod"
                        :icon="Clock"
                        plain
                        @click="openAuditHistory"
                    >
                        {{ $t('payroll.audit_btn') }}
                    </el-button>
                </div>
            </div>

            <!-- Business rule warning: skip employees with unapproved timesheets -->
            <el-alert
                :title="$t('payroll.alert_rules_title')"
                type="info"
                show-icon
                :closable="false"
                class="!rounded-xl border border-blue-200 dark:border-blue-900/60"
            >
                <template #default>
                    <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1">
                        <div>
                            • <strong>{{ $t('payroll.alert_exclusion_lead') }}</strong> {{ $t('payroll.alert_exclusion_body') }}
                        </div>
                        <div>
                            • <strong>{{ $t('payroll.alert_immutable_lead') }}</strong> {{ $t('payroll.alert_immutable_body') }}
                        </div>
                    </div>
                </template>
            </el-alert>

            <!-- Locked banner -->
            <div
                v-if="isPeriodLocked"
                class="p-4 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-center justify-between"
            >
                <div class="flex items-center gap-3 text-amber-800 dark:text-amber-300 text-xs">
                    <el-icon :size="20" class="text-amber-600 dark:text-amber-400"><Lock /></el-icon>
                    <div>
                        <div class="font-bold text-sm">{{ $t('payroll.locked_banner_title') }}</div>
                        <div>
                            {{ $t('payroll.locked_at_label') }} {{ payrollPeriod?.locked_at || 'N/A' }}
                            <span v-if="payrollPeriod?.locked_by"> • {{ $t('payroll.locked_by_label') }} {{ payrollPeriod?.locked_by?.full_name }}</span>
                        </div>
                    </div>
                </div>
                <el-tag type="danger" effect="dark" round>LOCKED</el-tag>
            </div>

            <!-- Payroll fund KPI overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $t('payroll.kpi_calculated_employees') }}</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <el-icon :size="16"><CircleCheck /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-slate-900 dark:text-white">{{ calculatedCount }}</span>
                        <span class="text-xs text-slate-400">/ {{ totalEmployeesCount }} {{ $t('payroll.unit_employees') }}</span>
                    </div>
                    <div class="mt-2 text-[11px] text-emerald-600 font-medium">{{ $t('payroll.card_calculated_sub') }}</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $t('payroll.card_skipped_title') }}</span>
                        <div class="w-8 h-8 rounded-lg bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                            <el-icon :size="16"><Warning /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-rose-600 dark:text-rose-400">{{ unapprovedCount }}</span>
                        <span class="text-xs text-slate-400">{{ $t('payroll.unit_employees') }}</span>
                    </div>
                    <div class="mt-2 text-[11px] text-rose-600 font-medium">{{ $t('payroll.card_skipped_sub') }}</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $t('payroll.kpi_total_gross') }}</span>
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <el-icon :size="16"><Money /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-xl font-black text-slate-900 dark:text-white">{{ formatCurrency(totalGross) }}</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-400">{{ $t('payroll.card_gross_sub') }}</div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ $t('payroll.card_net_title') }}</span>
                        <div class="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                            <el-icon :size="16"><Wallet /></el-icon>
                        </div>
                    </div>
                    <div class="mt-3">
                        <span class="text-xl font-black text-indigo-600 dark:text-indigo-400">{{ formatCurrency(totalNet) }}</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-400">{{ $t('payroll.label_tax_abbr') }} {{ formatCurrency(totalPitTax) }} • {{ $t('payroll.label_insurance_abbr') }} {{ formatCurrency(totalInsurance) }}</div>
                </div>
            </div>

            <!-- Tabs: calculated payroll vs skipped list vs employee salaries -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 p-6 shadow-xs">
                <el-tabs v-model="activeTab">
                    <!-- Tab 1: Detailed payroll list -->
                    <el-tab-pane name="records">
                        <template #label>
                            <span class="flex items-center gap-1.5 font-bold text-sm">
                                <el-icon><List /></el-icon>
                                {{ $t('payroll.tab_calculated_records', { count: records.length }) }}
                            </span>
                        </template>

                        <div class="mt-3 overflow-x-auto">
                            <el-table
                                :data="records"
                                stripe
                                border
                                style="width: 100%"
                                :empty-text="$t('payroll.empty_records')"
                            >
                                <el-table-column prop="employee.user_code" :label="$t('payroll.col_user_code')" width="100" fixed>
                                    <template #default="{ row }">
                                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ row.employee?.user_code }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="employee.full_name" :label="$t('payroll.col_full_name')" min-width="160" fixed>
                                    <template #default="{ row }">
                                        <div class="font-medium text-slate-800 dark:text-slate-200">{{ row.employee?.full_name }}</div>
                                        <div class="text-[10px] text-slate-400 uppercase">{{ row.employee?.status }}</div>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_salary_gross')" width="140" align="right">
                                    <template #default="{ row }">
                                        <span class="font-bold text-slate-800 dark:text-slate-100">{{ formatCurrency(row.gross_salary) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_allowance_position')" width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs">{{ formatCurrency(row.position_allowance_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_allowance_child')" width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs">{{ formatCurrency(row.child_allowance_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_ot_exempt')" width="120" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs text-emerald-600 font-semibold">{{ formatCurrency(row.tax_exempt_ot_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_insurance_abbr')" width="130" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs text-slate-600 dark:text-slate-300">
                                            {{ formatCurrency(Number(row.social_insurance_employee) + Number(row.health_insurance_employee) + Number(row.unemployment_insurance_employee)) }}
                                        </span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_pit_tax')" width="130" align="right">
                                    <template #default="{ row }">
                                        <span class="text-xs font-semibold text-amber-600 dark:text-amber-400">{{ formatCurrency(row.pit_tax_amount) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_net_received')" width="150" align="right">
                                    <template #default="{ row }">
                                        <span class="font-black text-indigo-600 dark:text-indigo-400 text-sm">{{ formatCurrency(row.net_salary) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.btn_payslip')" width="120" align="center" fixed="right">
                                    <template #default="{ row }">
                                        <el-button
                                            size="small"
                                            type="primary"
                                            plain
                                            :icon="Document"
                                            @click="openPayslip(row)"
                                        >
                                            {{ $t('payroll.btn_payslip') }}
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 2: Skipped employees due to unapproved timesheets -->
                    <el-tab-pane name="unapproved">
                        <template #label>
                            <span class="flex items-center gap-1.5 font-bold text-sm text-rose-600 dark:text-rose-400">
                                <el-icon><Warning /></el-icon>
                                {{ $t('payroll.tab_skipped_employees', { count: unapprovedEmployees.length }) }}
                            </span>
                        </template>

                        <div class="mt-3">
                            <div class="p-3 bg-rose-50 dark:bg-rose-950/30 rounded-xl border border-rose-200 dark:border-rose-900/40 text-xs text-rose-700 dark:text-rose-300 mb-4 flex items-center justify-between">
                                <div>
                                    {{ $t('payroll.hint_unapproved_prefix') }} <strong>"{{ $t('payroll.calculate_period_btn') }}"</strong> {{ $t('payroll.hint_unapproved_suffix') }}
                                </div>
                            </div>

                            <el-table
                                :data="unapprovedEmployees"
                                stripe
                                border
                                style="width: 100%"
                                :empty-text="$t('payroll.empty_unapproved')"
                            >
                                <el-table-column prop="user_code" :label="$t('payroll.col_user_code')" width="110">
                                    <template #default="{ row }">
                                        <span class="font-bold text-slate-700 dark:text-slate-300">{{ row.user_code }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="full_name" :label="$t('payroll.col_full_name')" min-width="180">
                                    <template #default="{ row }">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ row.full_name }}</div>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="status" :label="$t('payroll.col_contract')" width="120">
                                    <template #default="{ row }">
                                        <el-tag size="small" :type="row.status === 'PROBATION' ? 'warning' : 'info'">{{ row.status }}</el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_monthly_timesheet')" width="150">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.has_timesheet" type="warning" size="small">{{ $t('payroll.badge_pending_approval') }}</el-tag>
                                        <el-tag v-else type="danger" size="small">{{ $t('payroll.badge_not_tabulated') }}</el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="reason" :label="$t('payroll.col_skip_reason')" min-width="250">
                                    <template #default="{ row }">
                                        <span class="text-xs text-rose-600 dark:text-rose-400 font-medium">
                                            {{ row.reason }}
                                        </span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_action')" width="140" align="center">
                                    <template #default="{ row }">
                                        <el-button
                                            v-if="!isPeriodLocked"
                                            size="small"
                                            type="success"
                                            plain
                                            :icon="Check"
                                            @click="handleApproveTimesheet(row.employee_id)"
                                        >
                                            {{ $t('payroll.btn_approve_timesheet') }}
                                        </el-button>
                                        <span v-else class="text-xs text-slate-400">{{ $t('status.locked') }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 3: Employee Salary Management (Effective Dating) -->
                    <el-tab-pane name="salaries">
                        <template #label>
                            <span class="flex items-center gap-1.5 font-bold text-sm">
                                <el-icon><Money /></el-icon>
                                {{ $t('payroll.tab_salary_items', { count: employeeSalaries.length }) }}
                            </span>
                        </template>

                        <div class="mt-3 space-y-4">
                            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-3 bg-slate-50 dark:bg-slate-800/40 rounded-xl border border-slate-200 dark:border-slate-700">
                                <div class="text-xs text-slate-500">
                                    {{ $t('payroll.hint_salary_effective') }}
                                </div>
                                <el-button
                                    type="primary"
                                    size="small"
                                    :icon="Plus"
                                    @click="openCreateSalary"
                                >
                                    {{ $t('payroll.new_salary_btn') }}
                                </el-button>
                            </div>

                            <el-table
                                :data="employeeSalaries"
                                stripe
                                border
                                style="width: 100%"
                                :empty-text="$t('payroll.empty_salaries')"
                            >
                                <el-table-column prop="employee.user_code" :label="$t('payroll.col_user_code')" width="100" fixed>
                                    <template #default="{ row }">
                                        <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ row.employee?.user_code }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column prop="employee.full_name" :label="$t('payroll.col_full_name')" min-width="170" fixed>
                                    <template #default="{ row }">
                                        <div class="font-medium text-slate-800 dark:text-slate-200">{{ row.employee?.full_name }}</div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ row.employee?.current_timeline?.position?.name || $t('payroll.position_fallback') }}
                                        </div>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_base_salary_title')" width="150" align="right">
                                    <template #default="{ row }">
                                        <span class="font-bold text-slate-900 dark:text-white">{{ formatCurrency(row.base_salary) }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_insurance_salary_title')" width="150" align="right">
                                    <template #default="{ row }">
                                        <span v-if="row.insurance_salary" class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                            {{ formatCurrency(row.insurance_salary) }}
                                        </span>
                                        <span v-else class="text-xs text-slate-400 italic">{{ $t('payroll.label_follows_base') }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_contract_abbr')" width="110" align="center">
                                    <template #default="{ row }">
                                        <el-tag size="small" :type="row.contract_type === 'OFFICIAL' ? 'success' : 'warning'">
                                            {{ row.contract_type }}
                                        </el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_effective_period')" min-width="180">
                                    <template #default="{ row }">
                                        <div class="text-xs">
                                            {{ $t('payroll.label_from') }} <strong>{{ row.effective_date }}</strong>
                                            <span v-if="row.end_date"> &rarr; <strong>{{ row.end_date }}</strong></span>
                                            <span v-else class="text-emerald-600 font-semibold"> {{ $t('payroll.label_current') }}</span>
                                        </div>
                                        <div v-if="row.notes" class="text-[11px] text-slate-400 italic truncate max-w-xs">
                                            "{{ row.notes }}"
                                        </div>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_lock_status')" width="90" align="center">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.is_locked" type="danger" size="small" effect="plain">LOCKED</el-tag>
                                        <el-tag v-else type="info" size="small" effect="plain">{{ $t('payroll.salary_tag_open') }}</el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('payroll.col_actions_title')" width="140" align="center" fixed="right">
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
                                                {{ $t('payroll.btn_edit_short') }}
                                            </el-button>
                                            <el-button
                                                size="small"
                                                type="danger"
                                                link
                                                :disabled="row.is_locked"
                                                :icon="Delete"
                                                @click="deleteSalaryItem(row)"
                                            >
                                                {{ $t('common.delete') }}
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

        <!-- EMPLOYEE SALARY SETUP DIALOG -->
        <el-dialog
            v-model="salaryDialogVisible"
            :title="editingSalary ? $t('payroll.salary_dialog_title_edit') : $t('payroll.salary_dialog_title_create')"
            width="560px"
        >
            <el-form :model="salaryForm" label-position="top" class="space-y-3 text-xs">
                <el-form-item :label="$t('payroll.label_salary_employee')" required>
                    <el-select
                        v-model="salaryForm.employee_id"
                        :placeholder="$t('payroll.dialog_salary_select_employee')"
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
                        <el-form-item :label="$t('payroll.label_base_agreed')" required>
                            <el-input-number
                                v-model="salaryForm.base_salary"
                                :min="0"
                                :step="500000"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('payroll.label_insurance_salary')">
                            <el-input-number
                                v-model="salaryForm.insurance_salary"
                                :min="0"
                                :step="500000"
                                :placeholder="$t('payroll.placeholder_insurance_default')"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="$t('payroll.col_contract_type')" required>
                            <el-select v-model="salaryForm.contract_type" class="w-full">
                                <el-option :label="$t('payroll.contract_option_official')" value="OFFICIAL" />
                                <el-option :label="$t('payroll.contract_option_probation')" value="PROBATION" />
                                <el-option :label="$t('payroll.contract_option_seasonal')" value="SEASONAL" />
                                <el-option :label="$t('payroll.contract_option_intern')" value="INTERN" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('payroll.label_effective_start')" required>
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
                        <el-form-item :label="$t('payroll.label_end_date_optional')">
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

                <el-form-item :label="$t('payroll.label_salary_notes')">
                    <el-input
                        v-model="salaryForm.notes"
                        :placeholder="$t('payroll.placeholder_salary_notes')"
                    />
                </el-form-item>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="salaryDialogVisible = false">{{ $t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="salaryForm.processing" @click="submitSalaryForm">
                        {{ $t('payroll.btn_save_salary') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- DETAILED PAYSLIP MODAL -->
        <PayslipModal
            v-model="isPayslipModalVisible"
            :record="selectedPayslipRecord"
        />

        <!-- Audit History Drawer -->
        <AuditHistoryDrawer
            v-model="isAuditDrawerVisible"
            entity-type="PayrollPeriod"
            :entity-id="auditEntityId"
            :title="t('payroll.audit_history_title')"
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
