<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import AuditHistoryDrawer from '@/Components/AuditHistoryDrawer.vue';
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
    Lock,
    RefreshRight,
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
    enable_auto_submit_timesheet?: boolean;
    emp_review_deadline_day?: number;
    auto_submit_day?: number;
    manager_approval_deadline_day?: number;
    higher_manager_deadline_day?: number;
    hr_lock_deadline_day?: number;
    payroll_payment_day?: number;
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
    isLocked?: boolean;
    canEdit: boolean;
    canEditPayroll: boolean;
    canTest: boolean;
    currentDate: string;
}>();

import { useSyncedTab } from '@/Composables/useNavigationHistory';

// Tự động nhận và đồng bộ tab với URL query và Browser History (Back/Forward)
const activeTab = useSyncedTab('attendance', 'tab');

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

// Form Cập nhật Cấu hình Chấm công & Chu trình đóng công (Zero-Hardcode)
const aConf = props.activeVersion?.attendance_config;
const attendanceConfigForm = useForm({
    enable_auto_submit_timesheet: aConf?.enable_auto_submit_timesheet ?? true,
    emp_review_deadline_day: aConf?.emp_review_deadline_day ?? 3,
    auto_submit_day: aConf?.auto_submit_day ?? 4,
    manager_approval_deadline_day: aConf?.manager_approval_deadline_day ?? 5,
    higher_manager_deadline_day: aConf?.higher_manager_deadline_day ?? 6,
    hr_lock_deadline_day: aConf?.hr_lock_deadline_day ?? 7,
    payroll_payment_day: aConf?.payroll_payment_day ?? 10,
    grace_period_late_minutes: aConf?.grace_period_late_minutes ?? 5,
    grace_period_early_minutes: aConf?.grace_period_early_minutes ?? 0,
    leave_unit_hours: aConf?.leave_unit_hours ?? 4.0,
    early_leave_unit_hours: aConf?.early_leave_unit_hours ?? 4.0,
    min_rest_hours_between_shifts: aConf?.min_rest_hours_between_shifts ?? 12,
    min_weekly_rest_hours: aConf?.min_weekly_rest_hours ?? 24,
    max_weekly_standard_hours: aConf?.max_weekly_standard_hours ?? 48,
    ot_to_rostered_rate: aConf?.ot_to_rostered_rate ?? 1.5,
    compensation_rate: aConf?.compensation_rate ?? 1.0,
});

const handleSaveAttendanceConfig = () => {
    if (!props.activeVersion?.id) return;
    attendanceConfigForm.put(`/policies/attendance-config/${props.activeVersion.id}`, {
        preserveScroll: true,
        onSuccess: () => ElMessage.success('Đã lưu cấu hình chu trình đóng công và quy chế kỷ luật thành công!'),
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

// Sửa & Xóa Phúc lợi con nhỏ
const isEditChildPolicyDialogVisible = ref(false);
const editingChildPolicyId = ref<number | null>(null);
const editChildPolicyForm = useForm({
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

const openEditChildPolicy = (policy: any) => {
    editingChildPolicyId.value = policy.id;
    editChildPolicyForm.name = policy.name;
    editChildPolicyForm.min_age_months = policy.min_age_months;
    editChildPolicyForm.max_age_months = policy.max_age_months;
    editChildPolicyForm.daily_exemption_minutes = policy.daily_exemption_minutes;
    editChildPolicyForm.pay_type = policy.pay_type;
    editChildPolicyForm.prohibit_night_shift = !!policy.prohibit_night_shift;
    editChildPolicyForm.prohibit_overtime = !!policy.prohibit_overtime;
    editChildPolicyForm.is_statutory = !!policy.is_statutory;
    editChildPolicyForm.visibility = policy.visibility || 'VISIBLE';
    isEditChildPolicyDialogVisible.value = true;
};

const handleUpdateChildPolicy = () => {
    if (!editingChildPolicyId.value) return;
    editChildPolicyForm.put(`/policies/child-welfare/${editingChildPolicyId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditChildPolicyDialogVisible.value = false;
            ElMessage.success('Cập nhật chính sách con nhỏ thành công!');
        },
        onError: () => ElMessage.error('Không thể cập nhật chính sách con nhỏ!'),
    });
};

const handleDeleteChildPolicy = (id: number, name: string) => {
    ElMessageBox.confirm(`Bạn có chắc chắn muốn xóa chính sách "${name}"?`, 'Xác nhận xóa', {
        type: 'warning',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(`/policies/child-welfare/${id}`, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Đã xóa chính sách con nhỏ.'),
            onError: () => ElMessage.error('Không thể xóa chính sách này.'),
        });
    }).catch(() => {});
};

// CRUD Biểu thuế TNCN (PIT Tax Brackets)
const isPitBracketDialogVisible = ref(false);
const isEditingPitBracket = ref(false);
const editingPitBracketId = ref<number | null>(null);
const pitBracketForm = useForm({
    tier: 1,
    min_income: 0,
    max_income: null as number | null,
    tax_rate: 5,
    quick_subtraction: 0,
});

const openCreatePitBracket = () => {
    isEditingPitBracket.value = false;
    editingPitBracketId.value = null;
    const nextTier = (props.activeVersion?.pit_tax_brackets?.length || 0) + 1;
    pitBracketForm.reset();
    pitBracketForm.tier = nextTier;
    isPitBracketDialogVisible.value = true;
};

const openEditPitBracket = (bracket: any) => {
    isEditingPitBracket.value = true;
    editingPitBracketId.value = bracket.id;
    pitBracketForm.tier = bracket.tier;
    pitBracketForm.min_income = Number(bracket.min_income);
    pitBracketForm.max_income = bracket.max_income !== null ? Number(bracket.max_income) : null;
    pitBracketForm.tax_rate = Number(bracket.tax_rate);
    pitBracketForm.quick_subtraction = Number(bracket.quick_subtraction || 0);
    isPitBracketDialogVisible.value = true;
};

const handleSavePitBracket = () => {
    if (isEditingPitBracket.value && editingPitBracketId.value) {
        pitBracketForm.put(`/policies/pit-tax-brackets/${editingPitBracketId.value}`, {
            preserveScroll: true,
            onSuccess: () => {
                isPitBracketDialogVisible.value = false;
                ElMessage.success('Cập nhật bậc thuế thành công!');
            },
            onError: () => ElMessage.error('Không thể cập nhật bậc thuế!'),
        });
    } else {
        pitBracketForm.post(`/policies/pit-tax-brackets/${props.activeVersion.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isPitBracketDialogVisible.value = false;
                ElMessage.success('Thêm bậc thuế mới thành công!');
            },
            onError: () => ElMessage.error('Không thể thêm bậc thuế!'),
        });
    }
};

const handleDeletePitBracket = (id: number, tier: number) => {
    ElMessageBox.confirm(`Bạn có chắc chắn muốn xóa Bậc ${tier}?`, 'Xác nhận xóa bậc thuế', {
        type: 'warning',
        confirmButtonText: 'Xóa bậc',
        cancelButtonText: 'Hủy',
        confirmButtonClass: 'el-button--danger',
    }).then(() => {
        router.delete(`/policies/pit-tax-brackets/${id}`, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Đã xóa bậc thuế.'),
            onError: () => ElMessage.error('Không thể xóa bậc thuế.'),
        });
    }).catch(() => {});
};

const handleResetPitBrackets = () => {
    ElMessageBox.confirm('Hệ thống sẽ thiết lập lại Biểu thuế TNCN 5 bậc chuẩn luật định 2026 cho phiên bản này. Bạn có muốn tiếp tục?', 'Khôi phục 5 bậc chuẩn 2026', {
        type: 'info',
        confirmButtonText: 'Khôi phục',
        cancelButtonText: 'Hủy',
    }).then(() => {
        router.post(`/policies/pit-tax-brackets/${props.activeVersion.id}/reset-defaults`, {}, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success('Đã thiết lập lại Biểu thuế TNCN 5 bậc chuẩn năm 2026.'),
        });
    }).catch(() => {});
};

// Form Thêm Chế độ Phúc lợi Doanh nghiệp Đa năng
const isWelfareDialogVisible = ref(false);
const isEditWelfareDialogVisible = ref(false);
const editingWelfareId = ref<number | null>(null);
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
const editWelfareForm = useForm({
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

const openEditWelfare = (scheme: any) => {
    editingWelfareId.value = scheme.id;
    editWelfareForm.code = scheme.code;
    editWelfareForm.name = scheme.name;
    editWelfareForm.category = scheme.category;
    editWelfareForm.visibility = scheme.visibility;
    editWelfareForm.is_statutory = !!scheme.is_statutory;
    editWelfareForm.daily_exemption_minutes = scheme.daily_exemption_minutes || 0;
    editWelfareForm.pay_type = scheme.pay_type || 'PAID';
    editWelfareForm.prohibit_night_shift = !!scheme.prohibit_night_shift;
    editWelfareForm.prohibit_overtime = !!scheme.prohibit_overtime;
    editWelfareForm.additional_leave_days_per_year = scheme.additional_leave_days_per_year || 0;
    editWelfareForm.monthly_allowance_amount = scheme.monthly_allowance_amount || 0;
    editWelfareForm.one_time_bonus_amount = scheme.one_time_bonus_amount || 0;
    editWelfareForm.is_tax_exempt = !!scheme.is_tax_exempt;
    editWelfareForm.applicable_gender = scheme.applicable_gender || 'ALL';
    editWelfareForm.child_min_age_months = scheme.child_min_age_months;
    editWelfareForm.child_max_age_months = scheme.child_max_age_months;
    editWelfareForm.min_seniority_months = scheme.min_seniority_months || 0;
    editWelfareForm.description = scheme.description || '';
    isEditWelfareDialogVisible.value = true;
};

const handleUpdateWelfareScheme = () => {
    if (!editingWelfareId.value) return;
    editWelfareForm.put(`/policies/welfare-schemes/${editingWelfareId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditWelfareDialogVisible.value = false;
            ElMessage.success('Cập nhật chế độ phúc lợi thành công!');
        },
        onError: () => ElMessage.error('Không thể cập nhật chế độ phúc lợi!'),
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
const isEditAllowanceDialogVisible = ref(false);
const editingAllowanceId = ref<number | null>(null);
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
const editAllowanceForm = useForm({
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

const openEditAllowance = (allowance: any) => {
    editingAllowanceId.value = allowance.id;
    editAllowanceForm.code = allowance.code;
    editAllowanceForm.name = allowance.name;
    editAllowanceForm.apply_type = allowance.apply_type;
    editAllowanceForm.is_insurance_taxable = !!allowance.is_insurance_taxable;
    editAllowanceForm.is_pit_taxable = !!allowance.is_pit_taxable;
    editAllowanceForm.is_included_in_ot_wage = !!allowance.is_included_in_ot_wage;
    editAllowanceForm.tax_exemption_limit = allowance.tax_exemption_limit || 0;
    editAllowanceForm.default_amount = allowance.default_amount || 0;
    editAllowanceForm.visibility = allowance.visibility || 'VISIBLE';
    editAllowanceForm.notes = allowance.notes || '';
    isEditAllowanceDialogVisible.value = true;
};

const handleUpdateAllowance = () => {
    if (!editingAllowanceId.value) return;
    editAllowanceForm.put(`/policies/allowances/${editingAllowanceId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            isEditAllowanceDialogVisible.value = false;
            ElMessage.success('Cập nhật khoản phụ cấp thành công!');
        },
        onError: () => ElMessage.error('Không thể cập nhật phụ cấp!'),
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

// State Audit History Drawer
const isAuditDrawerOpen = ref(false);
const auditType = ref('');
const auditId = ref<number | null>(null);
const auditTitle = ref('');

const openAuditDrawer = (type: string, id: number | null, title: string) => {
    auditType.value = type;
    auditId.value = id;
    auditTitle.value = title;
    isAuditDrawerOpen.value = true;
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
                            <span v-if="isLocked" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                <el-icon><Lock /></el-icon>
                                Đã khóa Bất biến Lịch sử
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
                            size="default"
                            :icon="Clock"
                            @click="openAuditDrawer('App\\Modules\\PolicyEngine\\Models\\PolicyVersion', activeVersion?.id, 'Nhật ký Kiểm toán Quy chế: ' + (activeVersion?.name || ''))"
                        >
                            Lịch sử Kiểm toán
                        </el-button>

                        <el-button
                            type="primary"
                            :icon="Plus"
                            @click="isCreateVersionDialogVisible = true"
                            class="!rounded-xl shadow-xs"
                        >
                            Tạo phiên bản mới
                        </el-button>
                    </div>
                </div>

                <!-- Alert Khóa Bất biến Lịch sử -->
                <div v-if="isLocked" class="mt-4 p-3.5 rounded-xl bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-900 dark:text-amber-200 flex items-start gap-2.5">
                    <el-icon class="text-amber-600 text-base mt-0.5 shrink-0"><Lock /></el-icon>
                    <div>
                        <span class="font-bold">Bảo vệ Toàn vẹn Dữ liệu Quá khứ:</span>
                        Phiên bản quy chế này đã áp dụng cho các kỳ bảng công hoặc kỳ chi trả lương đã chốt. Để đảm bảo dữ liệu quá khứ không bị sai lệch, hệ thống đã khóa chỉnh sửa trực tiếp. Để áp dụng chính sách mới, vui lòng nhấn <strong>"Tạo phiên bản mới (Effective Dating)"</strong> với ngày hiệu lực kế tiếp.
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

                            <!-- Chu Trình Đóng Công & Tự Động Gửi Bảng Công Hàng Tháng (Zero-Hardcode Lifecycle) -->
                            <div class="border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 bg-white dark:bg-slate-900/60 shadow-xs space-y-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="p-2 rounded-lg bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                                                <Calendar class="w-5 h-5" />
                                            </span>
                                            <h3 class="text-base font-bold text-slate-800 dark:text-white">
                                                Chu Trình Đóng Công & Tự Động Gửi Bảng Công (Monthly Lifecycle)
                                            </h3>
                                        </div>
                                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                            Cấu hình các mốc ngày trong chu trình chốt công, gửi duyệt và chi trả lương hàng tháng (Quy tắc Không Hardcode).
                                        </p>
                                    </div>

                                    <el-button
                                        v-if="canEdit && !isLocked"
                                        type="primary"
                                        :loading="attendanceConfigForm.processing"
                                        @click="handleSaveAttendanceConfig"
                                    >
                                        <el-icon class="mr-1.5"><Check /></el-icon>
                                        Lưu cấu hình chu trình
                                    </el-button>
                                </div>

                                <!-- Visual Timeline Step Indicator -->
                                <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                                    <div class="p-3 rounded-xl border border-sky-200/80 dark:border-sky-900/60 bg-sky-50/50 dark:bg-sky-950/30">
                                        <div class="flex items-center justify-between text-xs font-semibold text-sky-700 dark:text-sky-300">
                                            <span>Mốc 1: Rà soát</span>
                                            <span class="px-1.5 py-0.5 rounded bg-sky-100 dark:bg-sky-900 text-[10px]">Ngày 1 - {{ attendanceConfigForm.emp_review_deadline_day }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                            Nhân viên kiểm tra công, nộp bổ sung đơn nghỉ, OT, sinh lý...
                                        </p>
                                    </div>

                                    <div class="p-3 rounded-xl border border-indigo-200/80 dark:border-indigo-900/60 bg-indigo-50/50 dark:bg-indigo-950/30">
                                        <div class="flex items-center justify-between text-xs font-semibold text-indigo-700 dark:text-indigo-300">
                                            <span>Mốc 2: Tự động gửi</span>
                                            <span class="px-1.5 py-0.5 rounded bg-indigo-100 dark:bg-indigo-900 text-[10px]">Ngày {{ attendanceConfigForm.auto_submit_day }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                            Hệ thống tự động chốt & gửi bảng công lên Quản lý nếu chưa nộp.
                                        </p>
                                    </div>

                                    <div class="p-3 rounded-xl border border-amber-200/80 dark:border-amber-900/60 bg-amber-50/50 dark:bg-amber-950/30">
                                        <div class="flex items-center justify-between text-xs font-semibold text-amber-700 dark:text-amber-300">
                                            <span>Mốc 3: Quản lý duyệt</span>
                                            <span class="px-1.5 py-0.5 rounded bg-amber-100 dark:bg-amber-900 text-[10px]">Đến ngày {{ attendanceConfigForm.manager_approval_deadline_day }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                            Quản lý trực tiếp duyệt công, duyệt đơn hoặc từ chối giải trình.
                                        </p>
                                    </div>

                                    <div class="p-3 rounded-xl border border-purple-200/80 dark:border-purple-900/60 bg-purple-50/50 dark:bg-purple-950/30">
                                        <div class="flex items-center justify-between text-xs font-semibold text-purple-700 dark:text-purple-300">
                                            <span>Mốc 4: Khối rà soát</span>
                                            <span class="px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-900 text-[10px]">Đến ngày {{ attendanceConfigForm.higher_manager_deadline_day }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                            Lãnh đạo khối / Quản lý cấp cao thẩm định toàn bộ bộ phận.
                                        </p>
                                    </div>

                                    <div class="p-3 rounded-xl border border-rose-200/80 dark:border-rose-900/60 bg-rose-50/50 dark:bg-rose-950/30">
                                        <div class="flex items-center justify-between text-xs font-semibold text-rose-700 dark:text-rose-300">
                                            <span>Mốc 5: HR Khóa công</span>
                                            <span class="px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-900 text-[10px]">Ngày {{ attendanceConfigForm.hr_lock_deadline_day }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                            Trưởng phòng HR / Giám đốc chốt công và khóa bất biến kỳ công.
                                        </p>
                                    </div>

                                    <div class="p-3 rounded-xl border border-emerald-200/80 dark:border-emerald-900/60 bg-emerald-50/50 dark:bg-emerald-950/30">
                                        <div class="flex items-center justify-between text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                            <span>Mốc 6: Trả lương</span>
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900 text-[10px]">Ngày {{ attendanceConfigForm.payroll_payment_day }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1.5 leading-relaxed">
                                            Kế toán quyết toán lương và giải ngân chi trả cho người lao động.
                                        </p>
                                    </div>
                                </div>

                                <!-- Form Inputs -->
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 pt-2">
                                    <div class="flex items-center justify-between p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200/60 dark:border-slate-700/60">
                                        <div>
                                            <div class="text-xs font-semibold text-slate-800 dark:text-white">Tự động gửi bảng công tháng</div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">Tự động nộp công nếu nhân viên quên gửi</div>
                                        </div>
                                        <el-switch
                                            v-model="attendanceConfigForm.enable_auto_submit_timesheet"
                                            :disabled="!canEdit || isLocked"
                                            active-color="#4f46e5"
                                        />
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Hạn chót nhân viên rà soát & nộp đơn (Ngày):</label>
                                        <el-input-number
                                            v-model="attendanceConfigForm.emp_review_deadline_day"
                                            :min="1"
                                            :max="15"
                                            class="w-full"
                                            :disabled="!canEdit || isLocked"
                                        />
                                        <div class="text-[11px] text-slate-400">Khoảng thời gian cho phép nhân viên nộp bổ sung đơn từ (Mặc định: Ngày 3)</div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Ngày hệ thống tự động gửi bảng công:</label>
                                        <el-input-number
                                            v-model="attendanceConfigForm.auto_submit_day"
                                            :min="2"
                                            :max="20"
                                            class="w-full"
                                            :disabled="!canEdit || isLocked"
                                        />
                                        <div class="text-[11px] text-slate-400">Đến ngày này, các bảng công chưa nộp sẽ tự động submit (Mặc định: Ngày 4)</div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Hạn chót Quản lý trực tiếp duyệt (Ngày):</label>
                                        <el-input-number
                                            v-model="attendanceConfigForm.manager_approval_deadline_day"
                                            :min="2"
                                            :max="25"
                                            class="w-full"
                                            :disabled="!canEdit || isLocked"
                                        />
                                        <div class="text-[11px] text-slate-400">Quản lý trực tiếp duyệt hoặc reject yêu cầu giải trình (Mặc định: Ngày 5)</div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Hạn chót Quản lý cấp cao / Khối rà soát (Ngày):</label>
                                        <el-input-number
                                            v-model="attendanceConfigForm.higher_manager_deadline_day"
                                            :min="3"
                                            :max="28"
                                            class="w-full"
                                            :disabled="!canEdit || isLocked"
                                        />
                                        <div class="text-[11px] text-slate-400">Lãnh đạo bộ phận/khối rà soát công (Mặc định: Ngày 6)</div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">Hạn chót HR chốt & Khóa bảng công (Ngày):</label>
                                        <el-input-number
                                            v-model="attendanceConfigForm.hr_lock_deadline_day"
                                            :min="4"
                                            :max="31"
                                            class="w-full"
                                            :disabled="!canEdit || isLocked"
                                        />
                                        <div class="text-[11px] text-slate-400">Trưởng phòng HR chốt công và khóa bất biến (Mặc định: Ngày 7)</div>
                                    </div>
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

                                <!-- Nhóm 2: Giảm trừ gia cảnh & Lương cơ sở -->
                                <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 space-y-4">
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                                        <Money class="w-4 h-4" /> Giảm trừ Thuế & Lương Cơ sở
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

                                <!-- Nhóm 3: Thuế Thử việc, Lương Thử việc & Trần Bảo hiểm (Zero-hardcode) -->
                                <div class="p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 space-y-4">
                                    <h4 class="font-bold text-xs uppercase tracking-wider text-blue-600 dark:text-blue-400 flex items-center gap-1.5">
                                        <User class="w-4 h-4" /> Thử việc & Trần Bảo hiểm Bắt buộc
                                    </h4>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Thuế TNCN thử việc (%)</label>
                                            <el-input-number v-model="payrollForm.probation_pit_rate" :step="1" :min="0" :max="100" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngưỡng khấu trừ (VNĐ)</label>
                                            <el-input-number v-model="payrollForm.probation_pit_threshold" :step="500000" :min="0" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tỷ lệ hưởng lương thử việc (% Điều 26 BLLĐ)</label>
                                        <el-input-number v-model="payrollForm.probation_salary_percentage" :step="1" :min="85" :max="100" class="w-full" :disabled="!canEditPayroll" />
                                    </div>
                                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200/60 dark:border-slate-700/60">
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Trần lương BHXH (VNĐ)</label>
                                            <el-input-number v-model="payrollForm.max_salary_bhxh" :step="1000000" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">Trần lương BHTN (VNĐ)</label>
                                            <el-input-number v-model="payrollForm.max_salary_bhtn" :step="1000000" :min="0" size="small" class="w-full" :disabled="!canEditPayroll" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Nhóm 4: Tỷ lệ Trích nộp Bảo hiểm -->
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
                                <el-table-column label="Thao tác" width="110" align="center">
                                    <template #default="{ row }">
                                        <div class="flex items-center justify-center gap-1">
                                            <el-button
                                                v-if="canEdit"
                                                size="small"
                                                :icon="Edit"
                                                circle
                                                @click="openEditChildPolicy(row)"
                                                title="Chỉnh sửa chính sách"
                                            />
                                            <el-button
                                                v-if="!row.is_statutory && canEdit"
                                                size="small"
                                                type="danger"
                                                :icon="Delete"
                                                circle
                                                @click="handleDeleteChildPolicy(row.id, row.name)"
                                                title="Xóa chính sách"
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
                                <el-table-column label="Thao tác" width="110" align="center">
                                    <template #default="{ row }">
                                        <div class="flex items-center justify-center gap-1">
                                            <el-button
                                                v-if="canEdit"
                                                size="small"
                                                :icon="Edit"
                                                circle
                                                @click="openEditWelfare(row)"
                                                title="Sửa chế độ"
                                            />
                                            <el-button
                                                v-if="!row.is_statutory && canEdit"
                                                type="danger"
                                                size="small"
                                                :icon="Delete"
                                                circle
                                                @click="handleDeleteWelfareScheme(row.id, row.name)"
                                                title="Xóa chế độ"
                                            />
                                        </div>
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
                                <el-table-column label="Thao tác" width="110" align="center">
                                    <template #default="{ row }">
                                        <div class="flex items-center justify-center gap-1">
                                            <el-button
                                                v-if="canEditPayroll"
                                                size="small"
                                                :icon="Edit"
                                                circle
                                                @click="openEditAllowance(row)"
                                                title="Sửa phụ cấp"
                                            />
                                            <el-button
                                                v-if="canEditPayroll"
                                                type="danger"
                                                size="small"
                                                :icon="Delete"
                                                circle
                                                @click="handleDeleteAllowance(row.id, row.name)"
                                                title="Xóa phụ cấp"
                                            />
                                        </div>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>

                    <!-- Tab 6: Biểu thuế TNCN 5 Bậc Động (CRUD) -->
                    <el-tab-pane label="Biểu thuế TNCN 5 Bậc" name="pit">
                        <template #label>
                            <span class="flex items-center gap-1.5">
                                <Money class="w-4 h-4" />
                                <span>Biểu thuế TNCN 5 Bậc</span>
                            </span>
                        </template>

                        <div class="space-y-4 pt-2">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 rounded-xl bg-indigo-50/60 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/60">
                                <div class="text-xs text-indigo-800 dark:text-indigo-200">
                                    <strong>Căn cứ Pháp lý:</strong> Biểu thuế thu nhập cá nhân 5 bậc lũy tiến từng phần áp dụng từ kỳ quyết toán thuế năm 2026. Doanh nghiệp có thể cấu hình linh hoạt nấc và thuế suất theo quy định.
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <el-button
                                        v-if="canEditPayroll"
                                        size="small"
                                        :icon="RefreshRight"
                                        @click="handleResetPitBrackets"
                                        title="Khôi phục 5 bậc chuẩn luật định 2026"
                                    >
                                        Mặc định 2026
                                    </el-button>
                                    <el-button
                                        v-if="canEditPayroll"
                                        type="primary"
                                        size="small"
                                        :icon="Plus"
                                        @click="openCreatePitBracket"
                                    >
                                        Thêm Bậc Thuế
                                    </el-button>
                                </div>
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
                                <el-table-column label="Thao tác" width="110" align="center">
                                    <template #default="{ row }">
                                        <div class="flex items-center justify-center gap-1">
                                            <el-button
                                                v-if="canEditPayroll"
                                                size="small"
                                                :icon="Edit"
                                                circle
                                                @click="openEditPitBracket(row)"
                                                title="Sửa bậc thuế"
                                            />
                                            <el-button
                                                v-if="canEditPayroll"
                                                type="danger"
                                                size="small"
                                                :icon="Delete"
                                                circle
                                                @click="handleDeletePitBracket(row.id, row.tier)"
                                                title="Xóa bậc thuế"
                                            />
                                        </div>
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

        <!-- Dialog 1: Thêm Chính sách Phúc lợi Con nhỏ Mới (Đã bổ sung) -->
        <el-dialog v-model="isChildPolicyDialogVisible" title="Thêm Chính sách Phúc lợi Con nhỏ Mới" width="560px">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold mb-1">Tên chính sách *</label>
                    <el-input v-model="childPolicyForm.name" placeholder="VD: Hỗ trợ mẹ nuôi con từ 1 - 3 tuổi" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Độ tuổi từ (tháng)</label>
                        <el-input-number v-model="childPolicyForm.min_age_months" :min="0" :max="216" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Đến độ tuổi (tháng)</label>
                        <el-input-number v-model="childPolicyForm.max_age_months" :min="1" :max="216" class="w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Thời gian miễn trừ (phút/ngày)</label>
                        <el-input-number v-model="childPolicyForm.daily_exemption_minutes" :min="0" :max="480" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Chế độ hưởng lương</label>
                        <el-select v-model="childPolicyForm.pay_type" class="w-full">
                            <el-option label="Có hưởng lương" value="PAID" />
                            <el-option label="Không hưởng lương" value="UNPAID" />
                            <el-option label="Hưởng một phần" value="PARTIAL" />
                        </el-select>
                    </div>
                </div>
                <div class="flex gap-4 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                    <el-checkbox v-model="childPolicyForm.prohibit_night_shift"><span class="text-xs">Cấm phân ca đêm</span></el-checkbox>
                    <el-checkbox v-model="childPolicyForm.prohibit_overtime"><span class="text-xs">Cấm làm thêm giờ (OT)</span></el-checkbox>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isChildPolicyDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="childPolicyForm.processing" @click="handleAddChildPolicy">Lưu Chính sách</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog 2: Sửa Chính sách Phúc lợi Con nhỏ -->
        <el-dialog v-model="isEditChildPolicyDialogVisible" title="Chỉnh sửa Chính sách Con nhỏ" width="560px">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold mb-1">Tên chính sách *</label>
                    <el-input v-model="editChildPolicyForm.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Độ tuổi từ (tháng)</label>
                        <el-input-number v-model="editChildPolicyForm.min_age_months" :min="0" :max="216" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Đến độ tuổi (tháng)</label>
                        <el-input-number v-model="editChildPolicyForm.max_age_months" :min="1" :max="216" class="w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Thời gian miễn trừ (phút/ngày)</label>
                        <el-input-number v-model="editChildPolicyForm.daily_exemption_minutes" :min="0" :max="480" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Chế độ hưởng lương</label>
                        <el-select v-model="editChildPolicyForm.pay_type" class="w-full">
                            <el-option label="Có hưởng lương" value="PAID" />
                            <el-option label="Không hưởng lương" value="UNPAID" />
                            <el-option label="Hưởng một phần" value="PARTIAL" />
                        </el-select>
                    </div>
                </div>
                <div class="flex gap-4 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                    <el-checkbox v-model="editChildPolicyForm.prohibit_night_shift"><span class="text-xs">Cấm phân ca đêm</span></el-checkbox>
                    <el-checkbox v-model="editChildPolicyForm.prohibit_overtime"><span class="text-xs">Cấm làm thêm giờ (OT)</span></el-checkbox>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isEditChildPolicyDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="editChildPolicyForm.processing" @click="handleUpdateChildPolicy">Cập nhật Chính sách</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog 3: Thêm / Sửa Bậc Thuế TNCN (PIT Brackets) -->
        <el-dialog v-model="isPitBracketDialogVisible" :title="isEditingPitBracket ? 'Chỉnh sửa Bậc thuế TNCN' : 'Thêm Bậc thuế TNCN Mới'" width="520px">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Bậc thuế (Thứ tự)</label>
                        <el-input-number v-model="pitBracketForm.tier" :min="1" :max="20" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Thuế suất (%)</label>
                        <el-input-number v-model="pitBracketForm.tax_rate" :step="1" :min="0" :max="100" class="w-full" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Thu nhập tính thuế Từ (VNĐ)</label>
                        <el-input-number v-model="pitBracketForm.min_income" :step="1000000" :min="0" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Thu nhập tính thuế Đến (VNĐ, để trống nếu vô cực)</label>
                        <el-input-number v-model="pitBracketForm.max_income" :step="1000000" :min="0" class="w-full" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Số trừ nhanh (VNĐ)</label>
                    <el-input-number v-model="pitBracketForm.quick_subtraction" :step="500000" :min="0" class="w-full" />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isPitBracketDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="pitBracketForm.processing" @click="handleSavePitBracket">Lưu Bậc thuế</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog 4: Sửa Chế độ Phúc lợi Doanh nghiệp -->
        <el-dialog v-model="isEditWelfareDialogVisible" title="Chỉnh sửa Chế độ Phúc lợi Doanh nghiệp" width="580px">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Mã chế độ *</label>
                        <el-input v-model="editWelfareForm.code" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Phân loại danh mục</label>
                        <el-select v-model="editWelfareForm.category" class="w-full">
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
                    <label class="block text-xs font-semibold mb-1">Tên chế độ phúc lợi *</label>
                    <el-input v-model="editWelfareForm.name" />
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl space-y-3">
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Tác động Chấm công & Phân ca</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Phút miễn trừ/ngày</label>
                            <el-input-number v-model="editWelfareForm.daily_exemption_minutes" :min="0" :max="480" class="w-full" size="small" />
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Loại lương</label>
                            <el-select v-model="editWelfareForm.pay_type" size="small" class="w-full">
                                <el-option label="Có hưởng lương" value="PAID" />
                                <el-option label="Không hưởng lương" value="UNPAID" />
                            </el-select>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <el-checkbox v-model="editWelfareForm.prohibit_night_shift"><span class="text-xs">Cấm ca đêm</span></el-checkbox>
                        <el-checkbox v-model="editWelfareForm.prohibit_overtime"><span class="text-xs">Cấm OT</span></el-checkbox>
                    </div>
                </div>
                <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl space-y-3">
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Tác động Tiền lương & Trợ cấp</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Trợ cấp hàng tháng (VNĐ)</label>
                            <el-input-number v-model="editWelfareForm.monthly_allowance_amount" :step="50000" :min="0" class="w-full" size="small" />
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-500 mb-1">Thưởng 1 lần (VNĐ)</label>
                            <el-input-number v-model="editWelfareForm.one_time_bonus_amount" :step="100000" :min="0" class="w-full" size="small" />
                        </div>
                    </div>
                    <el-checkbox v-model="editWelfareForm.is_tax_exempt"><span class="text-xs">Miễn thuế TNCN khoản này</span></el-checkbox>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isEditWelfareDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="editWelfareForm.processing" @click="handleUpdateWelfareScheme">Lưu Thay Đổi</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog 5: Sửa Khoản Phụ cấp -->
        <el-dialog v-model="isEditAllowanceDialogVisible" title="Chỉnh sửa Khoản Phụ cấp" width="520px">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Mã phụ cấp *</label>
                        <el-input v-model="editAllowanceForm.code" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Hình thức áp dụng</label>
                        <el-select v-model="editAllowanceForm.apply_type" class="w-full">
                            <el-option label="Cố định toàn công ty" value="COMPANY_FIXED" />
                            <el-option label="Cố định theo cá nhân" value="INDIVIDUAL_FIXED" />
                            <el-option label="Biến động theo tháng" value="IRREGULAR" />
                        </el-select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Tên khoản phụ cấp *</label>
                    <el-input v-model="editAllowanceForm.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold mb-1">Mức tiền mặc định (VNĐ)</label>
                        <el-input-number v-model="editAllowanceForm.default_amount" :step="50000" :min="0" class="w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1">Trần miễn thuế TNCN (VNĐ)</label>
                        <el-input-number v-model="editAllowanceForm.tax_exemption_limit" :step="50000" :min="0" class="w-full" />
                    </div>
                </div>
                <div class="flex gap-4 pt-2">
                    <el-checkbox v-model="editAllowanceForm.is_insurance_taxable"><span class="text-xs">Tính đóng BHXH</span></el-checkbox>
                    <el-checkbox v-model="editAllowanceForm.is_pit_taxable"><span class="text-xs">Chịu thuế TNCN</span></el-checkbox>
                    <el-checkbox v-model="editAllowanceForm.is_included_in_ot_wage"><span class="text-xs">Tính vào lương OT</span></el-checkbox>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="isEditAllowanceDialogVisible = false">Hủy</el-button>
                    <el-button type="primary" :loading="editAllowanceForm.processing" @click="handleUpdateAllowance">Lưu Thay Đổi</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- COMPONENT: Lịch sử Kiểm toán Quy chế -->
        <AuditHistoryDrawer
            v-model="isAuditDrawerOpen"
            :auditable-type="auditType"
            :auditable-id="auditId"
            :title="auditTitle"
        />
    </AppLayout>
</template>
