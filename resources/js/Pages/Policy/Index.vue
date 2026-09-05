<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import {
    Operation,
    Calendar,
    Clock,
    User,
    Money,
    Plus,
    Check,
    Close,
    InfoFilled,
    WarningFilled,
    Document,
    Cpu,
    Switch,
    Wallet,
    Present,
    CollectionTag,
    Delete,
    Edit,
    View,
    Hide,
} from '@element-plus/icons-vue';

interface PenaltyTier {
    min: number;
    max: number | null;
    fine: number;
}

interface FrequencyRule {
    count: number;
    multiplier: number;
}

interface AttendanceConfig {
    id: number;
    policy_version_id: number;
    grace_period_late_minutes: number;
    grace_period_early_minutes: number;
    leave_unit_hours: string | number;
    early_leave_unit_hours: string | number;
    min_rest_hours_between_shifts: number;
    min_weekly_rest_hours: number;
    max_weekly_standard_hours: number;
    ot_to_rostered_rate: string | number;
    compensation_rate: string | number;
    penalty_tiers_json: PenaltyTier[];
    frequency_penalty_json: FrequencyRule[];
}

interface PayrollConfig {
    id: number;
    policy_version_id: number;
    standard_working_days_mode: 'FIXED' | 'CALENDAR_BASED';
    fixed_standard_working_days: string | number;
    standard_working_hours_per_day: string | number;
    payment_day: number;
    pay_early_on_holiday_weekend: boolean;
    personal_deduction: string | number;
    dependent_deduction: string | number;
    default_child_allowance: string | number;
    base_salary: string | number;
    max_salary_bhxh: string | number;
    max_salary_bhtn: string | number;
    company_bhxh_rate: string | number;
    company_bhyt_rate: string | number;
    company_bhtn_rate: string | number;
    employee_bhxh_rate: string | number;
    employee_bhyt_rate: string | number;
    employee_bhtn_rate: string | number;
    probation_salary_percentage: string | number;
    probation_pit_rate: string | number;
    probation_pit_threshold: string | number;
    ot_weekday_day_rate: string | number;
    ot_weekday_night_rate: string | number;
    ot_weekend_day_rate: string | number;
    ot_weekend_night_rate: string | number;
    ot_holiday_day_rate: string | number;
    ot_holiday_night_rate: string | number;
    ot_tax_exempt_daily_limit_percent: number;
    ot_tax_exempt_monthly_limit_hours: number;
    ot_tax_exempt_yearly_limit_hours: number;
}

interface ChildWelfarePolicy {
    id: number;
    policy_version_id: number;
    name: string;
    min_age_months: number;
    max_age_months: number;
    daily_exemption_minutes: number;
    pay_type: 'PAID' | 'UNPAID' | 'PARTIAL';
    prohibit_night_shift: boolean;
    prohibit_overtime: boolean;
    is_statutory: boolean;
    is_active: boolean;
    visibility: 'VISIBLE' | 'HIDDEN';
}

interface WelfareScheme {
    id: number;
    tenant_id: number;
    policy_version_id: number | null;
    code: string;
    name: string;
    category: string;
    visibility: 'VISIBLE' | 'HIDDEN';
    is_statutory: boolean;
    daily_exemption_minutes: number;
    pay_type: 'PAID' | 'UNPAID' | 'PARTIAL';
    prohibit_night_shift: boolean;
    prohibit_overtime: boolean;
    additional_leave_days_per_year: number;
    monthly_allowance_amount: number;
    one_time_bonus_amount: number;
    is_tax_exempt: boolean;
    applicable_gender: 'ALL' | 'FEMALE' | 'MALE';
    child_min_age_months: number | null;
    child_max_age_months: number | null;
    min_seniority_months: number;
    description: string | null;
}

interface AllowanceItem {
    id: number;
    tenant_id: number;
    code: string;
    name: string;
    apply_type: 'COMPANY_FIXED' | 'INDIVIDUAL_FIXED' | 'IRREGULAR';
    is_insurance_taxable: boolean;
    is_pit_taxable: boolean;
    is_included_in_ot_wage: boolean;
    tax_exemption_limit: number;
    default_amount: number;
    visibility: 'VISIBLE' | 'HIDDEN';
    notes: string | null;
}

interface PitTaxBracket {
    id: number;
    policy_version_id: number;
    tier: number;
    min_income: string | number;
    max_income: string | number | null;
    tax_rate: string | number;
    quick_subtraction: string | number;
}

interface PolicyVersion {
    id: number;
    tenant_id: number;
    code: string;
    name: string;
    effective_from: string;
    effective_to: string | null;
    is_active: boolean;
    attendance_config?: AttendanceConfig;
    payroll_config?: PayrollConfig;
    child_welfare_policies?: ChildWelfarePolicy[];
    welfare_schemes?: WelfareScheme[];
    pit_tax_brackets?: PitTaxBracket[];
    child_welfare_policies_count?: number;
    welfare_schemes_count?: number;
    pit_tax_brackets_count?: number;
}

interface Holiday {
    id: number;
    name: string;
    holiday_date: string;
    year: number;
    is_statutory: boolean;
    notes: string | null;
}

const props = defineProps<{
    activeVersion: PolicyVersion;
    versions: PolicyVersion[];
    holidays: Holiday[];
    allowances: AllowanceItem[];
    allWelfareSchemes: WelfareScheme[];
    canEdit: boolean;
    canEditPayroll: boolean;
    canTest: boolean;
    currentDate: string;
}>();

// Tự động nhận tab từ URL query nếu có
const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref(urlParams.get('tab') || 'attendance');

// Form Tạo phiên bản mới
const isCreateVersionDialogVisible = ref(false);
const versionForm = useForm({
    code: '',
    name: '',
    effective_from: '',
    effective_to: '',
    clone_from_version_id: props.activeVersion?.id || null,
});

const handleCreateVersion = () => {
    versionForm.post('/policies/versions', {
        onSuccess: () => {
            isCreateVersionDialogVisible.value = false;
            versionForm.reset();
            ElMessage.success('Khởi tạo phiên bản quy chế mới thành công!');
        },
    });
};

// Form Cập nhật Cấu hình Lương, Thuế & Bảo hiểm
const pConf = props.activeVersion?.payroll_config;
const payrollForm = useForm({
    standard_working_days_mode: pConf?.standard_working_days_mode || 'FIXED',
    fixed_standard_working_days: pConf?.fixed_standard_working_days || 26.0,
    standard_working_hours_per_day: pConf?.standard_working_hours_per_day || 8.0,
    payment_day: pConf?.payment_day || 10,
    pay_early_on_holiday_weekend: pConf?.pay_early_on_holiday_weekend ?? true,
    personal_deduction: pConf?.personal_deduction || 11000000,
    dependent_deduction: pConf?.dependent_deduction || 4400000,
    default_child_allowance: pConf?.default_child_allowance || 300000,
    base_salary: pConf?.base_salary || 2340000,
    max_salary_bhxh: pConf?.max_salary_bhxh || 46800000,
    max_salary_bhtn: pConf?.max_salary_bhtn || 99200000,
    company_bhxh_rate: pConf?.company_bhxh_rate || 17.5,
    company_bhyt_rate: pConf?.company_bhyt_rate || 3.0,
    company_bhtn_rate: pConf?.company_bhtn_rate || 1.0,
    employee_bhxh_rate: pConf?.employee_bhxh_rate || 8.0,
    employee_bhyt_rate: pConf?.employee_bhyt_rate || 1.5,
    employee_bhtn_rate: pConf?.employee_bhtn_rate || 1.0,
    probation_salary_percentage: pConf?.probation_salary_percentage || 85.0,
    probation_pit_rate: pConf?.probation_pit_rate || 10.0,
    probation_pit_threshold: pConf?.probation_pit_threshold || 2000000,
    ot_weekday_day_rate: pConf?.ot_weekday_day_rate || 1.5,
    ot_weekday_night_rate: pConf?.ot_weekday_night_rate || 2.0,
    ot_weekend_day_rate: pConf?.ot_weekend_day_rate || 2.0,
    ot_weekend_night_rate: pConf?.ot_weekend_night_rate || 2.7,
    ot_holiday_day_rate: pConf?.ot_holiday_day_rate || 3.0,
    ot_holiday_night_rate: pConf?.ot_holiday_night_rate || 3.9,
    ot_tax_exempt_daily_limit_percent: pConf?.ot_tax_exempt_daily_limit_percent || 50,
    ot_tax_exempt_monthly_limit_hours: pConf?.ot_tax_exempt_monthly_limit_hours || 40,
    ot_tax_exempt_yearly_limit_hours: pConf?.ot_tax_exempt_yearly_limit_hours || 200,
});

const handleSavePayrollConfig = () => {
    if (!props.activeVersion?.id) return;
    payrollForm.put(`/policies/payroll-config/${props.activeVersion.id}`, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Đã lưu cấu hình tiền lương, thuế và bảo hiểm thành công!'),
    });
};

// Form Thêm chính sách con nhỏ
const isChildPolicyDialogVisible = ref(false);
const childPolicyForm = useForm({
    name: '',
    min_age_months: 13,
    max_age_months: 132,
    daily_exemption_minutes: 30,
    pay_type: 'PAID',
    prohibit_night_shift: false,
    prohibit_overtime: false,
    is_statutory: false,
    visibility: 'VISIBLE',
});

const handleAddChildPolicy = () => {
    childPolicyForm.post(`/policies/child-welfare/${props.activeVersion.id}`, {
        onSuccess: () => {
            isChildPolicyDialogVisible.value = false;
            childPolicyForm.reset();
            ElMessage.success('Thêm chính sách con nhỏ thành công!');
        },
    });
};

const handleToggleChildPolicyVisibility = (id: number) => {
    router.patch(`/policies/child-welfare/${id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Đã cập nhật trạng thái hiển thị chính sách.'),
    });
};

// Form Thêm Chế độ Phúc lợi Doanh nghiệp Đa năng
const isWelfareDialogVisible = ref(false);
const welfareForm = useForm({
    code: '',
    name: '',
    category: 'CHILD_CARE',
    visibility: 'VISIBLE',
    is_statutory: false,
    daily_exemption_minutes: 0,
    pay_type: 'PAID',
    prohibit_night_shift: false,
    prohibit_overtime: false,
    additional_leave_days_per_year: 0.0,
    monthly_allowance_amount: 0,
    one_time_bonus_amount: 0,
    is_tax_exempt: false,
    applicable_gender: 'ALL',
    child_min_age_months: null as number | null,
    child_max_age_months: null as number | null,
    min_seniority_months: 0,
    description: '',
});

const handleAddWelfareScheme = () => {
    welfareForm.post('/policies/welfare-schemes', {
        onSuccess: () => {
            isWelfareDialogVisible.value = false;
            welfareForm.reset();
            ElMessage.success('Thêm chế độ phúc lợi thành công!');
        },
    });
};

const handleToggleWelfareScheme = (id: number) => {
    router.patch(`/policies/welfare-schemes/${id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Đã cập nhật trạng thái chế độ phúc lợi.'),
    });
};

const handleDeleteWelfareScheme = (id: number, name: string) => {
    ElMessageBox.confirm(`Bạn có chắc chắn muốn xóa chế độ "${name}"?`, 'Xác nhận xóa', {
        type: 'warning',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
    }).then(() => {
        router.delete(`/policies/welfare-schemes/${id}`, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Đã xóa chế độ phúc lợi.'),
        });
    }).catch(() => {});
};

// Form Thêm Danh mục Phụ cấp Doanh nghiệp
const isAllowanceDialogVisible = ref(false);
const allowanceForm = useForm({
    code: '',
    name: '',
    apply_type: 'COMPANY_FIXED',
    is_insurance_taxable: false,
    is_pit_taxable: true,
    is_included_in_ot_wage: false,
    tax_exemption_limit: 0,
    default_amount: 500000,
    visibility: 'VISIBLE',
    notes: '',
});

const handleAddAllowance = () => {
    allowanceForm.post('/policies/allowances', {
        onSuccess: () => {
            isAllowanceDialogVisible.value = false;
            allowanceForm.reset();
            ElMessage.success('Thêm danh mục phụ cấp thành công!');
        },
    });
};

const handleToggleAllowance = (id: number) => {
    router.patch(`/policies/allowances/${id}/toggle`, {}, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Đã cập nhật trạng thái hiển thị phụ cấp.'),
    });
};

const handleDeleteAllowance = (id: number, name: string) => {
    ElMessageBox.confirm(`Bạn có chắc muốn xóa phụ cấp "${name}"?`, 'Xác nhận xóa', {
        type: 'warning',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
    }).then(() => {
        router.delete(`/policies/allowances/${id}`, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Đã xóa khoản phụ cấp.'),
        });
    }).catch(() => {});
};

// Chuyển đổi xem phiên bản quy chế khác
const handleSelectVersion = (versionId: number) => {
    router.get('/policies', { version_id: versionId, tab: activeTab.value }, { preserveScroll: true, preserveState: true });
};

// --- Sandbox Calculator Test State ---
const lateTest = ref({
    late_minutes: 18,
    violation_count: 3,
    loading: false,
    result: null as any,
});

const runLatePenaltyTest = async () => {
    lateTest.value.loading = true;
    try {
        const res = await axios.post('/policies/test-calculation', {
            type: 'LATE_PENALTY',
            late_minutes: lateTest.value.late_minutes,
            violation_count: lateTest.value.violation_count,
        });
        lateTest.value.result = res.data.result;
    } catch (e: any) {
        ElMessage.error(e.response?.data?.message || 'Có lỗi khi chạy thử nghiệm.');
    } finally {
        lateTest.value.loading = false;
    }
};

const taxTest = ref({
    taxable_income: 35000000,
    loading: false,
    result: null as any,
});

const runPitTaxTest = async () => {
    taxTest.value.loading = true;
    try {
        const res = await axios.post('/policies/test-calculation', {
            type: 'PIT_TAX',
            taxable_income: taxTest.value.taxable_income,
        });
        taxTest.value.result = res.data.result;
    } catch (e: any) {
        ElMessage.error(e.response?.data?.message || 'Có lỗi khi chạy tính thuế.');
    } finally {
        taxTest.value.loading = false;
    }
};

// Helpers
const formatVND = (value: number | string | null | undefined) => {
    if (value === null || value === undefined || value === '') return 'Không giới hạn';
    const num = typeof value === 'string' ? parseFloat(value) : value;
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(num);
};

const formatDate = (dateStr: string | null) => {
    if (!dateStr) return 'Vô thời hạn (Đang áp dụng)';
    const d = new Date(dateStr);
    return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
};
</script>

<template>
    <AppLayout>
        <Head title="Trung tâm Cấu hình Quy chế & Hệ thống - Smart-HR" />

        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 shadow-xs transition-colors">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200/60 dark:border-indigo-800/60">
                                <Cpu class="w-3.5 h-3.5" />
                                Zero-Hardcode Unified Policy & Configuration Engine
                            </span>
                            <span v-if="activeVersion?.is_active" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Đang có hiệu lực
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-800 dark:text-white tracking-tight">
                            {{ activeVersion?.name || 'Quy chế Quản trị Hệ thống' }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                            Mã phiên bản: <span class="font-mono font-semibold text-slate-700 dark:text-slate-300">{{ activeVersion?.code }}</span> • 
                            Hiệu lực: <span class="font-medium text-slate-700 dark:text-slate-300">{{ formatDate(activeVersion?.effective_from) }}</span> đến <span class="font-medium text-slate-700 dark:text-slate-300">{{ formatDate(activeVersion?.effective_to) }}</span>
                        </p>
                    </div>

                    <!-- Actions & Version Switcher -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <el-select
                            :model-value="activeVersion?.id"
                            placeholder="Chọn phiên bản quy chế"
                            class="w-64"
                            @change="handleSelectVersion"
                        >
                            <el-option
                                v-for="ver in versions"
                                :key="ver.id"
                                :label="`${ver.code} - ${ver.name}`"
                                :value="ver.id"
                            />
                        </el-select>

                        <el-button
                            v-if="canEdit"
                            type="primary"
                            :icon="Plus"
                            @click="isCreateVersionDialogVisible = true"
                            class="!rounded-xl shadow-xs"
                        >
                            Tạo phiên bản mới
                        </el-button>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 shadow-xs">
                <el-tabs v-model="activeTab" class="demo-tabs">
                    <!-- Tab 1: Chấm công & Nấc phạt -->
                    <el-tab-pane label="Chấm công & Kỷ luật" name="attendance">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Clock class="w-4 h-4" />
                                <span>Chấm công & Kỷ luật</span>
                            </span>
                        </template>

                        <div class="space-y-6 pt-2">
                            <!-- Metrics Cards -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Miễn trừ Muộn / Sớm</div>
                                    <div class="text-lg font-bold text-slate-800 dark:text-white mt-1">
                                        {{ activeVersion?.attendance_config?.grace_period_late_minutes || 0 }} phút
                                    </div>
                                    <div class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-0.5">Không bị phạt nếu đến trong biên độ</div>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Nghỉ giữa 2 ca liên tiếp</div>
                                    <div class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                                        {{ activeVersion?.attendance_config?.min_rest_hours_between_shifts || 12 }} giờ
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Bắt buộc theo Điều 110 BLLĐ 2019</div>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Nghỉ hàng tuần liên tục</div>
                                    <div class="text-lg font-bold text-slate-800 dark:text-white mt-1">
                                        {{ activeVersion?.attendance_config?.min_weekly_rest_hours || 24 }} giờ
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Bắt buộc theo Điều 111 BLLĐ 2019</div>
                                </div>

                                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60">
                                    <div class="text-xs text-slate-500 dark:text-slate-400 font-medium">Giờ làm việc chuẩn tuần</div>
                                    <div class="text-lg font-bold text-slate-800 dark:text-white mt-1">
                                        {{ activeVersion?.attendance_config?.max_weekly_standard_hours || 48 }} giờ
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Giới hạn tối đa Điều 105 BLLĐ</div>
                                </div>
                            </div>

                            <!-- Nấc phạt và Tần suất -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="border border-slate-200/80 dark:border-slate-800 rounded-xl p-4 bg-slate-50/50 dark:bg-slate-800/30">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="font-semibold text-slate-800 dark:text-white text-sm flex items-center gap-1.5">
                                            <Document class="w-4 h-4 text-indigo-600" />
                                            Biểu nấc phạt theo số phút vi phạm
                                        </h3>
                                        <span class="text-xs text-slate-400">Động từ DB</span>
                                    </div>
                                    <el-table
                                        :data="activeVersion?.attendance_config?.penalty_tiers_json || []"
                                        stripe
                                        style="width: 100%"
                                        size="small"
                                    >
                                        <el-table-column label="Từ (phút)" prop="min" width="90">
                                            <template #default="{ row }"><span class="font-mono">{{ row.min }}p</span></template>
                                        </el-table-column>
                                        <el-table-column label="Đến (phút)" prop="max" width="90">
                                            <template #default="{ row }">
                                                <span class="font-mono">{{ row.max ? row.max + 'p' : 'Trở lên' }}</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Mức phạt (VNĐ)" prop="fine">
                                            <template #default="{ row }">
                                                <span class="font-semibold text-rose-600 dark:text-rose-400">{{ formatVND(row.fine) }}</span>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>

                                <div class="border border-slate-200/80 dark:border-slate-800 rounded-xl p-4 bg-slate-50/50 dark:bg-slate-800/30">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="font-semibold text-slate-800 dark:text-white text-sm flex items-center gap-1.5">
                                            <Operation class="w-4 h-4 text-indigo-600" />
                                            Hệ số lũy tiến phạt theo tần suất trong tháng
                                        </h3>
                                        <span class="text-xs text-slate-400">Nhân hệ số vi phạm</span>
                                    </div>
                                    <el-table
                                        :data="activeVersion?.attendance_config?.frequency_penalty_json || []"
                                        stripe
                                        style="width: 100%"
                                        size="small"
                                    >
                                        <el-table-column label="Số lần vi phạm" prop="count">
                                            <template #default="{ row }">
                                                <span>Từ lần thứ <strong>{{ row.count }}</strong> trở đi</span>
                                            </template>
                                        </el-table-column>
                                        <el-table-column label="Hệ số nhân" prop="multiplier" width="120">
                                            <template #default="{ row }">
                                                <el-tag type="warning" size="small" round effect="plain">
                                                    {{ row.multiplier }}x
                                                </el-tag>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 2: Lương, Thuế & Bảo hiểm (Payroll & Insurances - Chuẩn theo timekeeper2) -->
                    <el-tab-pane label="Lương, Thuế & Bảo hiểm" name="payroll">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Wallet class="w-4 h-4" />
                                <span>Lương, Thuế & Bảo hiểm</span>
                            </span>
                        </template>

                        <div class="space-y-6 pt-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-base font-bold text-slate-800 dark:text-white">Cấu hình Tiền lương, Thuế TNCN & Mức trích nộp Bảo hiểm</h3>
                                    <p class="text-xs text-slate-400">Tham số cốt lõi làm tiền đề tính toán công lương cho từng ngày làm việc theo luật hiện hành</p>
                                </div>
                                <el-button
                                    v-if="canEditPayroll"
                                    type="primary"
                                    :loading="payrollForm.processing"
                                    @click="handleSavePayrollConfig"
                                    class="!rounded-xl shadow-xs"
                                >
                                    Lưu Cấu hình Lương
                                </el-button>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                <!-- Nhóm 1: Ngày công & Chi trả -->
                                <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 space-y-4">
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                                        <Clock class="w-4 h-4" /> Định mức Ngày công & Chi trả
                                    </h4>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Chế độ Ngày công chuẩn</label>
                                        <el-select v-model="payrollForm.standard_working_days_mode" class="w-full" :disabled="!canEditPayroll">
                                            <el-option label="Cố định (Fixed Working Days)" value="FIXED" />
                                            <el-option label="Theo lịch thực tế (Trừ T7/CN)" value="CALENDAR_BASED" />
                                        </el-select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Công chuẩn (ngày)</label>
                                            <el-input-number v-model="payrollForm.fixed_standard_working_days" :min="1" :max="31" :step="0.5" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Giờ chuẩn/ngày</label>
                                            <el-input-number v-model="payrollForm.standard_working_hours_per_day" :min="1" :max="12" :step="0.5" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngày trả lương</label>
                                            <el-input-number v-model="payrollForm.payment_day" :min="1" :max="31" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                        <div class="flex items-center pt-5">
                                            <el-checkbox v-model="payrollForm.pay_early_on_holiday_weekend" :disabled="!canEditPayroll">
                                                <span class="text-xs text-slate-600 dark:text-slate-300">Trả sớm nếu nghỉ</span>
                                            </el-checkbox>
                                        </div>
                                    </div>
                                </div>

                                <!-- Nhóm 2: Giảm trừ gia cảnh & Mức trần BHXH -->
                                <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 space-y-4">
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                        <Money class="w-4 h-4" /> Giảm trừ Thuế & Trần Bảo hiểm
                                    </h4>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Giảm trừ gia cảnh bản thân (VNĐ)</label>
                                        <el-input-number v-model="payrollForm.personal_deduction" :step="1000000" :min="0" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Giảm trừ người phụ thuộc (VNĐ/người)</label>
                                        <el-input-number v-model="payrollForm.dependent_deduction" :step="500000" :min="0" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Lương cơ sở (VNĐ)</label>
                                            <el-input-number v-model="payrollForm.base_salary" :step="100000" :min="0" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Trợ cấp con nhỏ (đ/th)</label>
                                            <el-input-number v-model="payrollForm.default_child_allowance" :step="50000" :min="0" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Nhóm 3: Tỷ lệ Trích nộp Bảo hiểm -->
                                <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 space-y-4">
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                                        <Document class="w-4 h-4" /> Tỷ lệ Trích nộp Bảo hiểm (%)
                                    </h4>
                                    <div class="space-y-2">
                                        <div class="text-[11px] font-bold text-slate-500">Doanh nghiệp đóng (Tổng 21.5%):</div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <span class="text-[10px] text-slate-400">BHXH (%)</span>
                                                <el-input-number v-model="payrollForm.company_bhxh_rate" :step="0.5" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-slate-400">BHYT (%)</span>
                                                <el-input-number v-model="payrollForm.company_bhyt_rate" :step="0.5" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-slate-400">BHTN (%)</span>
                                                <el-input-number v-model="payrollForm.company_bhtn_rate" :step="0.5" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-2 pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                        <div class="text-[11px] font-bold text-slate-500">Người lao động đóng (Tổng 10.5%):</div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div>
                                                <span class="text-[10px] text-slate-400">BHXH (%)</span>
                                                <el-input-number v-model="payrollForm.employee_bhxh_rate" :step="0.5" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-slate-400">BHYT (%)</span>
                                                <el-input-number v-model="payrollForm.employee_bhyt_rate" :step="0.5" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-slate-400">BHTN (%)</span>
                                                <el-input-number v-model="payrollForm.employee_bhtn_rate" :step="0.5" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Hàng 2: Hệ số làm thêm giờ OT & 3 tầng trần miễn thuế -->
                            <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                                        <Cpu class="w-4 h-4" /> Hệ số Làm thêm giờ (OT) & 3 Tầng trần Bóc tách Miễn thuế (Điều 107 & NĐ 145/2020)
                                    </h4>
                                    <span class="text-xs text-slate-400">Khoán theo giờ làm chuẩn</span>
                                </div>
                                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">Ngày thường (Ngày)</label>
                                        <el-input-number v-model="payrollForm.ot_weekday_day_rate" :step="0.1" :min="1" size="small" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">Ngày thường (Đêm)</label>
                                        <el-input-number v-model="payrollForm.ot_weekday_night_rate" :step="0.1" :min="1" size="small" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">Cuối tuần (Ngày)</label>
                                        <el-input-number v-model="payrollForm.ot_weekend_day_rate" :step="0.1" :min="1" size="small" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">Cuối tuần (Đêm)</label>
                                        <el-input-number v-model="payrollForm.ot_weekend_night_rate" :step="0.1" :min="1" size="small" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">Ngày lễ (Ngày)</label>
                                        <el-input-number v-model="payrollForm.ot_holiday_day_rate" :step="0.1" :min="1" size="small" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">Ngày lễ (Đêm)</label>
                                        <el-input-number v-model="payrollForm.ot_holiday_night_rate" :step="0.1" :min="1" size="small" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-3 border-t border-slate-200/60 dark:border-slate-700/60 text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">Trần ngày:</span>
                                        <el-input-number v-model="payrollForm.ot_tax_exempt_daily_limit_percent" :min="0" :max="100" size="small" :disabled="!canEditPayroll" />
                                        <span class="text-slate-400">% giờ chuẩn ca</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">Trần tháng:</span>
                                        <el-input-number v-model="payrollForm.ot_tax_exempt_monthly_limit_hours" :min="0" :max="100" size="small" :disabled="!canEditPayroll" />
                                        <span class="text-slate-400">giờ/tháng</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-500">Trần năm:</span>
                                        <el-input-number v-model="payrollForm.ot_tax_exempt_yearly_limit_hours" :min="0" :max="500" size="small" :disabled="!canEditPayroll" />
                                        <span class="text-slate-400">giờ/năm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 3: Phúc lợi Con nhỏ & Gia đình (Bật/Tắt Visible/Hidden) -->
                    <el-tab-pane label="Phúc lợi Con nhỏ" name="child_welfare">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Present class="w-4 h-4" />
                                <span>Phúc lợi Con nhỏ</span>
                            </span>
                        </template>

                        <div class="space-y-4 pt-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Quy chế Hỗ trợ Con nhỏ & Người lao động Gia đình</h3>
                                    <p class="text-xs text-slate-400">Bật/tắt trạng thái Ẩn/Hiện (visible/hidden) áp dụng cho động cơ tính công và hiển thị nhân sự</p>
                                </div>
                                <el-button
                                    v-if="canEdit"
                                    type="primary"
                                    size="small"
                                    :icon="Plus"
                                    @click="isChildPolicyDialogVisible = true"
                                    class="!rounded-xl"
                                >
                                    Thêm Chính sách
                                </el-button>
                            </div>

                            <el-table :data="activeVersion?.child_welfare_policies || []" stripe style="width: 100%" size="small">
                                <el-table-column label="Tên chính sách" prop="name" min-width="240">
                                    <template #default="{ row }">
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-slate-800 dark:text-slate-200 text-xs">{{ row.name }}</span>
                                            <el-tag v-if="row.is_statutory" size="small" type="danger" round effect="plain">Bắt buộc (Đ.137)</el-tag>
                                            <el-tag v-else size="small" type="success" round effect="plain">Quy chế DN</el-tag>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Độ tuổi áp dụng" width="160">
                                    <template #default="{ row }">
                                        <span class="font-mono text-xs">{{ row.min_age_months }} - {{ row.max_age_months }} tháng tuổi</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Miễn trừ mỗi ngày" width="150">
                                    <template #default="{ row }">
                                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ row.daily_exemption_minutes }} phút/ngày</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Hưởng lương" width="120">
                                    <template #default="{ row }">
                                        <el-tag :type="row.pay_type === 'PAID' ? 'success' : 'info'" size="small" round>
                                            {{ row.pay_type === 'PAID' ? 'Có hưởng lương' : 'Không lương' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Trạng thái Ẩn/Hiện" width="180">
                                    <template #default="{ row }">
                                        <div class="flex items-center gap-2">
                                            <el-switch
                                                :model-value="row.visibility === 'VISIBLE'"
                                                :disabled="!canEdit || row.is_statutory"
                                                active-text="Hiện (Visible)"
                                                inactive-text="Ẩn (Hidden)"
                                                inline-prompt
                                                @change="handleToggleChildPolicyVisibility(row.id)"
                                            />
                                        </div>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 4: Chế độ Phúc lợi Doanh nghiệp Đa năng -->
                    <el-tab-pane label="Chế độ Phúc lợi Đa năng" name="welfare_schemes">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <CollectionTag class="w-4 h-4" />
                                <span>Chế độ Phúc lợi Doanh nghiệp</span>
                            </span>
                        </template>

                        <div class="space-y-4 pt-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Danh mục Chế độ Phúc lợi Toàn diện</h3>
                                    <p class="text-xs text-slate-400">Doanh nghiệp tự do thiết lập các chế độ liên quan đến Chấm công và Tiền lương / Trợ cấp</p>
                                </div>
                                <el-button
                                    v-if="canEdit"
                                    type="primary"
                                    size="small"
                                    :icon="Plus"
                                    @click="isWelfareDialogVisible = true"
                                    class="!rounded-xl"
                                >
                                    Tạo Chế độ Mới
                                </el-button>
                            </div>

                            <el-table :data="allWelfareSchemes" stripe style="width: 100%" size="small">
                                <el-table-column label="Mã & Tên chế độ" min-width="220">
                                    <template #default="{ row }">
                                        <div class="space-y-0.5">
                                            <div class="font-semibold text-slate-800 dark:text-slate-200 text-xs">{{ row.name }}</div>
                                            <span class="font-mono text-[11px] text-indigo-600 dark:text-indigo-400">{{ row.code }}</span>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Phân loại" width="150">
                                    <template #default="{ row }">
                                        <el-tag size="small" round effect="light">{{ row.category }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Tác động Chấm công" width="160">
                                    <template #default="{ row }">
                                        <div class="text-[11px]">
                                            <div v-if="row.daily_exemption_minutes > 0" class="font-semibold text-emerald-600">Miễn trừ {{ row.daily_exemption_minutes }}p/ngày</div>
                                            <div v-if="row.prohibit_night_shift" class="text-rose-500 font-medium">Cấm ca đêm</div>
                                            <div v-if="row.prohibit_overtime" class="text-amber-500 font-medium">Cấm OT</div>
                                            <div v-if="row.daily_exemption_minutes === 0 && !row.prohibit_night_shift">Không tác động</div>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Trợ cấp tài chính" width="160">
                                    <template #default="{ row }">
                                        <div class="text-[11px]">
                                            <div v-if="row.monthly_allowance_amount > 0" class="font-mono font-bold text-indigo-600">{{ formatVND(row.monthly_allowance_amount) }}/th</div>
                                            <div v-if="row.one_time_bonus_amount > 0" class="font-mono text-emerald-600">Thưởng {{ formatVND(row.one_time_bonus_amount) }}</div>
                                            <div v-if="row.monthly_allowance_amount === 0 && row.one_time_bonus_amount === 0" class="text-slate-400">---</div>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Trạng thái" width="140">
                                    <template #default="{ row }">
                                        <el-switch
                                            :model-value="row.visibility === 'VISIBLE'"
                                            :disabled="!canEdit || row.is_statutory"
                                            active-text="Hiện"
                                            inactive-text="Ẩn"
                                            inline-prompt
                                            @change="handleToggleWelfareScheme(row.id)"
                                        />
                                    </template>
                                </el-table-column>
                                <el-table-column label="Thao tác" width="90" align="center">
                                    <template #default="{ row }">
                                        <el-button
                                            v-if="!row.is_statutory && canEdit"
                                            type="danger"
                                            size="small"
                                            link
                                            :icon="Delete"
                                            @click="handleDeleteWelfareScheme(row.id, row.name)"
                                        >
                                            Xóa
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 5: Danh mục Phụ cấp Doanh nghiệp (Kế thừa từ timekeeper2) -->
                    <el-tab-pane label="Danh mục Phụ cấp" name="allowances">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Money class="w-4 h-4" />
                                <span>Danh mục Phụ cấp</span>
                            </span>
                        </template>

                        <div class="space-y-4 pt-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800 dark:text-white">Danh mục Phụ cấp & Trợ cấp Doanh nghiệp (Kế thừa timekeeper2)</h3>
                                    <p class="text-xs text-slate-400">Quản lý các khoản phụ cấp cố định công ty, theo cá nhân hoặc biến động kèm quy định thuế TNCN & BHXH</p>
                                </div>
                                <el-button
                                    v-if="canEditPayroll"
                                    type="primary"
                                    size="small"
                                    :icon="Plus"
                                    @click="isAllowanceDialogVisible = true"
                                    class="!rounded-xl"
                                >
                                    Thêm Phụ cấp Mới
                                </el-button>
                            </div>

                            <el-table :data="allowances" stripe style="width: 100%" size="small">
                                <el-table-column label="Mã & Tên phụ cấp" min-width="220">
                                    <template #default="{ row }">
                                        <div class="space-y-0.5">
                                            <div class="font-semibold text-slate-800 dark:text-slate-200 text-xs">{{ row.name }}</div>
                                            <span class="font-mono text-[11px] text-indigo-600 dark:text-indigo-400">{{ row.code }}</span>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Hình thức áp dụng" width="160">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.apply_type === 'COMPANY_FIXED'" size="small" type="primary" round>Cố định Công ty</el-tag>
                                        <el-tag v-else-if="row.apply_type === 'INDIVIDUAL_FIXED'" size="small" type="warning" round>Cố định Cá nhân</el-tag>
                                        <el-tag v-else size="small" type="info" round>Biến động theo tháng</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Mức mặc định" width="150">
                                    <template #default="{ row }">
                                        <span class="font-mono font-bold text-slate-800 dark:text-white">{{ formatVND(row.default_amount) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Thuế & BHXH" width="180">
                                    <template #default="{ row }">
                                        <div class="text-[11px] space-y-0.5">
                                            <div>BHXH: <span :class="row.is_insurance_taxable ? 'text-rose-500 font-medium' : 'text-emerald-500'">{{ row.is_insurance_taxable ? 'Có đóng' : 'Miễn đóng' }}</span></div>
                                            <div>Thuế TNCN: <span :class="row.is_pit_taxable ? 'text-amber-500' : 'text-emerald-500 font-medium'">{{ row.is_pit_taxable ? 'Chịu thuế' : 'Miễn thuế' }}</span></div>
                                            <div v-if="row.tax_exemption_limit > 0" class="text-indigo-500 text-[10px]">Trần miễn: {{ formatVND(row.tax_exemption_limit) }}</div>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Trạng thái" width="130">
                                    <template #default="{ row }">
                                        <el-switch
                                            :model-value="row.visibility === 'VISIBLE'"
                                            :disabled="!canEditPayroll"
                                            active-text="Hiện"
                                            inactive-text="Ẩn"
                                            inline-prompt
                                            @change="handleToggleAllowance(row.id)"
                                        />
                                    </template>
                                </el-table-column>
                                <el-table-column label="Thao tác" width="90" align="center">
                                    <template #default="{ row }">
                                        <el-button
                                            v-if="canEditPayroll"
                                            type="danger"
                                            size="small"
                                            link
                                            :icon="Delete"
                                            @click="handleDeleteAllowance(row.id, row.name)"
                                        >
                                            Xóa
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 6: Biểu thuế TNCN 5 Bậc -->
                    <el-tab-pane label="Biểu thuế TNCN 5 Bậc" name="pit">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Money class="w-4 h-4" />
                                <span>Biểu thuế TNCN 5 Bậc</span>
                            </span>
                        </template>

                        <div class="space-y-4 pt-2">
                            <div class="p-3.5 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60 text-xs text-indigo-800 dark:text-indigo-200">
                                <strong>Căn cứ Pháp lý:</strong> Biểu thuế thu nhập cá nhân 5 bậc lũy tiến từng phần áp dụng chính thức từ kỳ quyết toán thuế năm 2026.
                            </div>

                            <el-table :data="activeVersion?.pit_tax_brackets || []" stripe style="width: 100%">
                                <el-table-column label="Bậc thuế" prop="tier" width="100">
                                    <template #default="{ row }">
                                        <el-tag type="info" round size="small" class="font-bold">Bậc {{ row.tier }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Thu nhập tính thuế Từ (VNĐ)" prop="min_income">
                                    <template #default="{ row }"><span class="font-mono">{{ formatVND(row.min_income) }}</span></template>
                                </el-table-column>
                                <el-table-column label="Thu nhập tính thuế Đến (VNĐ)" prop="max_income">
                                    <template #default="{ row }"><span class="font-mono">{{ formatVND(row.max_income) }}</span></template>
                                </el-table-column>
                                <el-table-column label="Thuế suất" prop="tax_rate" width="120">
                                    <template #default="{ row }">
                                        <span class="font-bold text-rose-600 dark:text-rose-400">{{ row.tax_rate }}%</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Số trừ nhanh (VNĐ)" prop="quick_subtraction">
                                    <template #default="{ row }">
                                        <span class="font-mono text-slate-600 dark:text-slate-400">{{ formatVND(row.quick_subtraction) }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 7: Lịch Nghỉ Lễ Tết -->
                    <el-tab-pane label="Lịch Nghỉ Lễ Tết" name="holidays">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Calendar class="w-4 h-4" />
                                <span>Lịch Nghỉ Lễ Tết</span>
                            </span>
                        </template>

                        <div class="space-y-4 pt-2">
                            <el-table :data="holidays" stripe style="width: 100%">
                                <el-table-column label="Tên ngày lễ / Tết" prop="name" />
                                <el-table-column label="Ngày nghỉ" prop="holiday_date" width="160">
                                    <template #default="{ row }">
                                        <span class="font-mono font-medium">{{ formatDate(row.holiday_date) }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Phân loại" width="160">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.is_statutory" type="success" size="small" round>Luật định (Hưởng 100% lương)</el-tag>
                                        <el-tag v-else type="info" size="small" round>Nghỉ bù / Doanh nghiệp</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="Ghi chú" prop="notes" />
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 8: Sandbox Tính toán Thử nghiệm -->
                    <el-tab-pane label="Sandbox Thử nghiệm" name="sandbox">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Cpu class="w-4 h-4" />
                                <span>Sandbox Thử nghiệm</span>
                            </span>
                        </template>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-2">
                            <!-- Box 1: Test phạt đi muộn -->
                            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-4">
                                <h4 class="font-bold text-sm text-slate-800 dark:text-white">Thử nghiệm Tính phạt Đi muộn / Về sớm</h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold mb-1">Số phút đi muộn</label>
                                        <el-input-number v-model="lateTest.late_minutes" :min="0" :max="300" class="w-full" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold mb-1">Số lần vi phạm trong tháng</label>
                                        <el-input-number v-model="lateTest.violation_count" :min="1" :max="30" class="w-full" />
                                    </div>
                                </div>
                                <el-button type="primary" :loading="lateTest.loading" @click="runLatePenaltyTest" class="!rounded-xl w-full">
                                    Chạy Thử nghiệm Tính Phạt
                                </el-button>

                                <div v-if="lateTest.result" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs space-y-1.5">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Mức phạt gốc nấc phút:</span>
                                        <span class="font-mono font-bold">{{ formatVND(lateTest.result.base_fine) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Hệ số nhân tần suất:</span>
                                        <span class="font-mono font-bold text-amber-600">{{ lateTest.result.frequency_multiplier }}x</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                                        <span class="font-bold text-slate-800 dark:text-white">Tổng tiền phạt thực tế:</span>
                                        <span class="font-mono font-bold text-rose-600 text-sm">{{ formatVND(lateTest.result.total_fine) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Box 2: Test Thuế TNCN -->
                            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-4">
                                <h4 class="font-bold text-sm text-slate-800 dark:text-white">Thử nghiệm Thuế TNCN 5 Bậc (2026)</h4>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Thu nhập tính thuế sau giảm trừ (VNĐ/tháng)</label>
                                    <el-input-number v-model="taxTest.taxable_income" :min="0" :step="1000000" class="w-full" />
                                </div>
                                <el-button type="success" :loading="taxTest.loading" @click="runPitTaxTest" class="!rounded-xl w-full">
                                    Chạy Thử nghiệm Tính Thuế
                                </el-button>

                                <div v-if="taxTest.result" class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs space-y-1.5">
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Bậc thuế áp dụng:</span>
                                        <span class="font-bold text-indigo-600">Bậc {{ taxTest.result.matched_tier }} (Thuế suất {{ taxTest.result.tax_rate }}%)</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500">Số trừ nhanh:</span>
                                        <span class="font-mono font-bold">{{ formatVND(taxTest.result.quick_subtraction) }}</span>
                                    </div>
                                    <div class="flex justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                                        <span class="font-bold text-slate-800 dark:text-white">Thuế TNCN phải nộp:</span>
                                        <span class="font-mono font-bold text-rose-600 text-sm">{{ formatVND(taxTest.result.tax_amount) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- Dialog Tạo phiên bản quy chế mới -->
        <el-dialog v-model="isCreateVersionDialogVisible" title="Tạo Phiên bản Quy chế Mới (Effective Dating)" width="520px">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold mb-1">Mã phiên bản (VD: POLICY_2027_V1)</label>
                    <el-input v-model="versionForm.code" placeholder="POLICY_2026_V2" />
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Tên phiên bản quy chế</label>
                    <el-input v-model="versionForm.name" placeholder="Quy chế Áp dụng Mới từ Quý 4" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Ngày bắt đầu hiệu lực</label>
                        <el-date-picker v-model="versionForm.effective_from" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Ngày kết thúc (Tùy chọn)</label>
                        <el-date-picker v-model="versionForm.effective_to" type="date" value-format="YYYY-MM-DD" class="w-full" />
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isCreateVersionDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="versionForm.processing" @click="handleCreateVersion">Khởi tạo</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog Tạo Chế độ Phúc lợi Doanh nghiệp Mới -->
        <el-dialog v-model="isWelfareDialogVisible" title="Tạo Chế độ Phúc lợi Doanh nghiệp Mới" width="580px">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Mã chế độ (VD: SCHEME_CON_NHO_11T)</label>
                        <el-input v-model="welfareForm.code" placeholder="SCHEME_ANTRUA_500K" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Phân loại danh mục</label>
                        <el-select v-model="welfareForm.category" class="w-full">
                            <el-option label="Chăm sóc con nhỏ & gia đình" value="CHILD_CARE" />
                            <el-option label="Thai sản & sức khỏe phụ nữ" value="MATERNITY_FEMALE" />
                            <el-option label="Sức khỏe & y tế doanh nghiệp" value="HEALTH_WELLNESS" />
                            <el-option label="Điều kiện làm việc" value="WORKING_COND" />
                            <el-option label="Hỗ trợ tài chính & hiếu hỉ" value="FINANCIAL_SUPPORT" />
                            <el-option label="Phụ cấp chuyên biệt" value="SPECIAL_ALLOWANCE" />
                        </el-select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Tên chế độ phúc lợi</label>
                    <el-input v-model="welfareForm.name" placeholder="Chế độ Hỗ trợ Con từ 1 - 11 tuổi" />
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl space-y-3">
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Tác động Chấm công & Phân ca</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Phút miễn trừ/ngày</label>
                            <el-input-number v-model="welfareForm.daily_exemption_minutes" :min="0" :max="480" class="w-full" size="small" />
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Loại lương</label>
                            <el-select v-model="welfareForm.pay_type" size="small" class="w-full">
                                <el-option label="Có hưởng lương" value="PAID" />
                                <el-option label="Không hưởng lương" value="UNPAID" />
                            </el-select>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <el-checkbox v-model="welfareForm.prohibit_night_shift"><span class="text-xs">Cấm ca đêm</span></el-checkbox>
                        <el-checkbox v-model="welfareForm.prohibit_overtime"><span class="text-xs">Cấm OT</span></el-checkbox>
                    </div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl space-y-3">
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Tác động Tiền lương & Trợ cấp</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Trợ cấp hàng tháng (VNĐ)</label>
                            <el-input-number v-model="welfareForm.monthly_allowance_amount" :step="50000" :min="0" class="w-full" size="small" />
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Thưởng 1 lần (VNĐ)</label>
                            <el-input-number v-model="welfareForm.one_time_bonus_amount" :step="100000" :min="0" class="w-full" size="small" />
                        </div>
                    </div>
                    <el-checkbox v-model="welfareForm.is_tax_exempt"><span class="text-xs">Miễn thuế TNCN khoản này</span></el-checkbox>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isWelfareDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="welfareForm.processing" @click="handleAddWelfareScheme">Lưu Chế độ</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog Thêm Khoản Phụ cấp Mới -->
        <el-dialog v-model="isAllowanceDialogVisible" title="Thêm Khoản Phụ cấp Mới (Theo timekeeper2)" width="520px">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Mã phụ cấp (VD: PC_ANTRUA)</label>
                        <el-input v-model="allowanceForm.code" placeholder="PC_DIENTHOAI" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Hình thức áp dụng</label>
                        <el-select v-model="allowanceForm.apply_type" class="w-full">
                            <el-option label="Cố định toàn công ty" value="COMPANY_FIXED" />
                            <el-option label="Cố định theo cá nhân" value="INDIVIDUAL_FIXED" />
                            <el-option label="Biến động theo tháng" value="IRREGULAR" />
                        </el-select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Tên khoản phụ cấp</label>
                    <el-input v-model="allowanceForm.name" placeholder="Phụ cấp Tiền ăn ca trưa" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Mức tiền mặc định (VNĐ)</label>
                        <el-input-number v-model="allowanceForm.default_amount" :step="50000" :min="0" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Trần miễn thuế TNCN (VNĐ)</label>
                        <el-input-number v-model="allowanceForm.tax_exemption_limit" :step="50000" :min="0" class="w-full" />
                    </div>
                </div>
                <div class="flex gap-4 pt-2">
                    <el-checkbox v-model="allowanceForm.is_insurance_taxable"><span class="text-xs">Tính đóng BHXH</span></el-checkbox>
                    <el-checkbox v-model="allowanceForm.is_pit_taxable"><span class="text-xs">Chịu thuế TNCN</span></el-checkbox>
                    <el-checkbox v-model="allowanceForm.is_included_in_ot_wage"><span class="text-xs">Tính vào lương OT</span></el-checkbox>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isAllowanceDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="allowanceForm.processing" @click="handleAddAllowance">Lưu Phụ cấp</el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
