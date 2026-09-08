<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus,
    Search,
    Refresh,
    Edit,
    Delete,
    Female,
    Male,
    User,
    Check,
    Calendar,
    Document,
    Money,
    Warning,
    CircleCheck,
    InfoFilled,
    Setting,
} from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

interface EmployeeOption {
    id: number;
    user_code: string;
    full_name: string;
    gender: 'MALE' | 'FEMALE' | 'OTHER';
}

interface ApplicablePolicy {
    type: string;
    name: string;
    badge: 'danger' | 'warning' | 'success' | 'primary' | 'info';
    description: string;
}

interface ChildItem {
    id: number;
    employee_id: number;
    full_name: string;
    gender: 'MALE' | 'FEMALE' | 'OTHER';
    birth_date: string | null;
    birth_date_display: string;
    birth_certificate_number: string | null;
    age_text: string;
    is_tax_dependent: boolean;
    tax_dependent_code: string | null;
    tax_dependent_start_date: string | null;
    tax_dependent_end_date: string | null;
    has_monthly_allowance: boolean;
    monthly_allowance_amount: number;
    notes: string | null;
    is_active: boolean;
    applicable_policies: ApplicablePolicy[];
    employee: {
        id: number;
        user_code: string;
        full_name: string;
        gender: string;
        department_name: string;
        position_name: string;
    } | null;
}

const props = defineProps<{
    children: {
        data: ChildItem[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    employees: EmployeeOption[];
    kpis: {
        totalChildrenCount: number;
        under12mCount: number;
        range1to11Count: number;
        taxDependentCount: number;
        totalMonthlyAllowance: number;
    };
    filters: {
        search?: string;
        employee_id?: number | string;
        age_group?: string;
        tax_dependent?: string;
    };
}>();

const { t } = useThemeAndLocale();

// Filter States
const searchQuery = ref(props.filters.search || '');
const selectedEmployeeId = ref(props.filters.employee_id ? Number(props.filters.employee_id) : undefined);
const selectedAgeGroup = ref(props.filters.age_group || '');
const selectedTaxDependent = ref(props.filters.tax_dependent || '');

const applyFilters = () => {
    router.get(
        '/employee-children',
        {
            search: searchQuery.value || undefined,
            employee_id: selectedEmployeeId.value || undefined,
            age_group: selectedAgeGroup.value || undefined,
            tax_dependent: selectedTaxDependent.value || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    searchQuery.value = '';
    selectedEmployeeId.value = undefined;
    selectedAgeGroup.value = '';
    selectedTaxDependent.value = '';
    applyFilters();
};

// Modal Form State
const dialogVisible = ref(false);
const isEditing = ref(false);
const submitting = ref(false);
const currentChildId = ref<number | null>(null);

const form = ref({
    employee_id: undefined as number | undefined,
    full_name: '',
    gender: 'MALE' as 'MALE' | 'FEMALE' | 'OTHER',
    birth_date: '',
    birth_certificate_number: '',
    is_tax_dependent: true,
    tax_dependent_code: '',
    tax_dependent_start_date: '',
    tax_dependent_end_date: '',
    has_monthly_allowance: true,
    monthly_allowance_amount: 300000,
    notes: '',
    is_active: true,
});

// Dynamic Smart Age Detection
const smartDetection = computed(() => {
    if (!form.value.birth_date) return null;
    const birth = new Date(form.value.birth_date);
    const now = new Date();
    const diffMonths = (now.getFullYear() - birth.getFullYear()) * 12 + (now.getMonth() - birth.getMonth());

    if (diffMonths < 0) {
        return {
            type: 'warning',
            title: t('employee_children.sd_future_title'),
            message: t('employee_children.sd_future_msg'),
        };
    } else if (diffMonths < 12) {
        return {
            type: 'danger',
            title: t('employee_children.sd_under12_title'),
            message: t('employee_children.sd_under12_msg', { months: diffMonths }),
        };
    } else if (diffMonths <= 132) {
        const years = Math.floor(diffMonths / 12);
        return {
            type: 'warning',
            title: t('employee_children.sd_1to11_title'),
            message: t('employee_children.sd_1to11_msg', { years }),
        };
    } else {
        return {
            type: 'info',
            title: t('employee_children.sd_over11_title'),
            message: t('employee_children.sd_over11_msg'),
        };
    }
});

const openCreateModal = () => {
    isEditing.value = false;
    currentChildId.value = null;
    form.value = {
        employee_id: undefined,
        full_name: '',
        gender: 'MALE',
        birth_date: '',
        birth_certificate_number: '',
        is_tax_dependent: true,
        tax_dependent_code: '',
        tax_dependent_start_date: '',
        tax_dependent_end_date: '',
        has_monthly_allowance: true,
        monthly_allowance_amount: 300000,
        notes: '',
        is_active: true,
    };
    dialogVisible.value = true;
};

const openEditModal = (child: ChildItem) => {
    isEditing.value = true;
    currentChildId.value = child.id;
    form.value = {
        employee_id: child.employee_id,
        full_name: child.full_name,
        gender: child.gender,
        birth_date: child.birth_date || '',
        birth_certificate_number: child.birth_certificate_number || '',
        is_tax_dependent: child.is_tax_dependent,
        tax_dependent_code: child.tax_dependent_code || '',
        tax_dependent_start_date: child.tax_dependent_start_date || '',
        tax_dependent_end_date: child.tax_dependent_end_date || '',
        has_monthly_allowance: child.has_monthly_allowance,
        monthly_allowance_amount: child.monthly_allowance_amount,
        notes: child.notes || '',
        is_active: child.is_active,
    };
    dialogVisible.value = true;
};

const submitForm = () => {
    if (!form.value.employee_id || !form.value.full_name || !form.value.birth_date) {
        ElMessage.warning(t('employee_children.validation_required_msg'));
        return;
    }

    submitting.value = true;
    if (isEditing.value && currentChildId.value) {
        router.put(`/employee-children/${currentChildId.value}`, form.value, {
            onSuccess: () => {
                dialogVisible.value = false;
                ElMessage.success(t('employee_children.update_success_msg'));
            },
            onFinish: () => {
                submitting.value = false;
            },
        });
    } else {
        router.post('/employee-children', form.value, {
            onSuccess: () => {
                dialogVisible.value = false;
                ElMessage.success(t('employee_children.create_success_msg'));
            },
            onFinish: () => {
                submitting.value = false;
            },
        });
    }
};

const deleteChild = (child: ChildItem) => {
    router.delete(`/employee-children/${child.id}`, {
        onSuccess: () => {
            ElMessage.success(t('employee_children.delete_success_msg', { name: child.full_name }));
        },
    });
};

const handlePageChange = (page: number) => {
    router.get(
        '/employee-children',
        {
            page,
            search: searchQuery.value || undefined,
            employee_id: selectedEmployeeId.value || undefined,
            age_group: selectedAgeGroup.value || undefined,
            tax_dependent: selectedTaxDependent.value || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};
</script>

<template>
    <AppLayout>
        <Head :title="$t('employee_children.page_title')" />

        <div class="space-y-6 pb-12">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-xl bg-pink-50 dark:bg-pink-950/60 text-pink-600 dark:text-pink-400 flex items-center justify-center">
                            <el-icon :size="20"><Female /></el-icon>
                        </div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            {{ $t('employee_children.page_heading') }}
                        </h1>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        {{ $t('employee_children.page_subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-button :icon="Setting" class="!rounded-xl font-medium" @click="router.visit('/policies?tab=child_welfare')">
                        {{ $t('employee_children.btn_configure_policy') }}
                    </el-button>
                    <el-button type="primary" :icon="Plus" class="!rounded-xl font-medium" @click="openCreateModal">
                        {{ $t('employee_children.btn_add_child') }}
                    </el-button>
                </div>
            </div>

            <!-- KPI Cards Overview -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <!-- Card 1: Tổng số trẻ -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('employee_children.kpi_total_children') }}</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ kpis.totalChildrenCount }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-medium">{{ $t('employee_children.unit_child') }}</span>
                    </div>
                    <span class="text-[10px] text-slate-400 mt-1">{{ $t('employee_children.kpi_total_children_sub') }}</span>
                </div>

                <!-- Card 2: Điều 137 (<12 tháng) -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-rose-200 dark:border-rose-900/60 shadow-xs flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-12 h-12 bg-rose-500/10 rounded-bl-full pointer-events-none"></div>
                    <span class="text-xs text-rose-600 dark:text-rose-400 font-medium flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        {{ $t('employee_children.kpi_article137') }}
                    </span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ kpis.under12mCount }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 font-medium">{{ $t('employee_children.unit_child') }}</span>
                    </div>
                    <span class="text-[10px] text-rose-500 mt-1">{{ $t('employee_children.kpi_article137_sub') }}</span>
                </div>

                <!-- Card 3: Con 1 - 11 tuổi -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-amber-200 dark:border-amber-900/60 shadow-xs flex flex-col justify-between">
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">{{ $t('employee_children.kpi_extended_welfare') }}</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ kpis.range1to11Count }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 font-medium">{{ $t('employee_children.unit_child') }}</span>
                    </div>
                    <span class="text-[10px] text-amber-500 mt-1">{{ $t('employee_children.kpi_extended_welfare_sub') }}</span>
                </div>

                <!-- Card 4: Giảm trừ thuế TNCN -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-blue-200 dark:border-blue-900/60 shadow-xs flex flex-col justify-between">
                    <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">{{ $t('employee_children.kpi_tax_dependent') }}</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ kpis.taxDependentCount }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-medium">{{ $t('employee_children.unit_person') }}</span>
                    </div>
                    <span class="text-[10px] text-blue-500 mt-1">{{ $t('employee_children.kpi_tax_dependent_sub') }}</span>
                </div>

                <!-- Card 5: Quỹ trợ cấp hàng tháng -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-emerald-200 dark:border-emerald-900/60 shadow-xs flex flex-col justify-between col-span-2 md:col-span-1">
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">{{ $t('employee_children.kpi_monthly_fund') }}</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ Number(kpis.totalMonthlyAllowance).toLocaleString('vi-VN') }}
                        </span>
                        <span class="text-[11px] text-emerald-600 font-medium">{{ $t('common.currency_vnd') }}</span>
                    </div>
                    <span class="text-[10px] text-emerald-500 mt-1">{{ $t('employee_children.kpi_monthly_fund_sub') }}</span>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <!-- Search Input -->
                    <el-input
                        v-model="searchQuery"
                        :placeholder="$t('employee_children.search_placeholder')"
                        clearable
                        class="!w-72"
                        :prefix-icon="Search"
                        @keyup.enter="applyFilters"
                        @clear="applyFilters"
                    />

                    <!-- Filter Employee -->
                    <el-select
                        v-model="selectedEmployeeId"
                        :placeholder="$t('employee_children.filter_employee_placeholder')"
                        clearable
                        filterable
                        class="!w-64"
                        @change="applyFilters"
                    >
                        <el-option
                            v-for="emp in employees"
                            :key="emp.id"
                            :label="`${emp.user_code} - ${emp.full_name}`"
                            :value="emp.id"
                        />
                    </el-select>

                    <!-- Filter Age Group -->
                    <el-select
                        v-model="selectedAgeGroup"
                        :placeholder="$t('employee_children.filter_age_placeholder')"
                        clearable
                        class="!w-48"
                        @change="applyFilters"
                    >
                        <el-option :label="$t('employee_children.age_all')" value="" />
                        <el-option :label="$t('employee_children.age_under_12m')" value="UNDER_12M" />
                        <el-option :label="$t('employee_children.age_1_to_6y')" value="1_TO_6Y" />
                        <el-option :label="$t('employee_children.age_6_to_11y')" value="6_TO_11Y" />
                        <el-option :label="$t('employee_children.age_over_11y')" value="OVER_11Y" />
                    </el-select>

                    <!-- Filter Tax Dependent -->
                    <el-select
                        v-model="selectedTaxDependent"
                        :placeholder="$t('employee_children.filter_tax_placeholder')"
                        clearable
                        class="!w-40"
                        @change="applyFilters"
                    >
                        <el-option :label="$t('common.all')" value="" />
                        <el-option :label="$t('employee_children.tax_yes')" value="1" />
                        <el-option :label="$t('employee_children.tax_no')" value="0" />
                    </el-select>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                    <el-button type="primary" :icon="Search" class="!rounded-xl font-medium" @click="applyFilters">
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button :icon="Refresh" class="!rounded-xl" @click="resetFilters">
                        {{ $t('common.refresh') }}
                    </el-button>
                </div>
            </div>

            <!-- Children Data Table -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
                <el-table :data="children.data" stripe class="w-full" :empty-text="$t('employee_children.empty_records')">
                    <!-- Bé & Độ tuổi -->
                    <el-table-column :label="$t('employee_children.col_child_info')" min-width="190">
                        <template #default="{ row }">
                            <div class="flex items-center gap-3">
                                <div
                                    class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 shadow-xs"
                                    :class="row.gender === 'MALE' ? 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400' : 'bg-pink-50 text-pink-600 dark:bg-pink-950/50 dark:text-pink-400'"
                                >
                                    <el-icon :size="18">
                                        <component :is="row.gender === 'MALE' ? Male : Female" />
                                    </el-icon>
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-semibold text-slate-800 dark:text-white text-sm">{{ row.full_name }}</span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ row.birth_date_display }}</span>
                                        <span class="text-slate-300 dark:text-slate-600">•</span>
                                        <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ row.age_text }}</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Phụ huynh (Nhân viên) -->
                    <el-table-column :label="$t('employee_children.col_parent_employee')" min-width="180">
                        <template #default="{ row }">
                            <div v-if="row.employee" class="flex flex-col">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium text-slate-800 dark:text-slate-200 text-sm">{{ row.employee.full_name }}</span>
                                    <el-tag size="small" type="info" class="!text-[10px] font-mono">{{ row.employee.user_code }}</el-tag>
                                </div>
                                <span class="text-[11px] text-slate-400">{{ row.employee.department_name }}</span>
                            </div>
                            <span v-else class="text-xs text-slate-400">{{ $t('employee_children.empty_no_parent') }}</span>
                        </template>
                    </el-table-column>

                    <!-- Giấy khai sinh & Mã số thuế -->
                    <el-table-column :label="$t('employee_children.col_docs_mst')" min-width="160">
                        <template #default="{ row }">
                            <div class="flex flex-col text-xs space-y-1">
                                <div v-if="row.birth_certificate_number" class="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">{{ $t('employee_children.gks_abbr') }}</span>
                                    <span class="font-mono">{{ row.birth_certificate_number }}</span>
                                </div>
                                <div v-if="row.is_tax_dependent" class="flex items-center gap-1 text-blue-600 dark:text-blue-400 font-medium">
                                    <span class="text-slate-400">{{ $t('employee_children.mst_abbr') }}</span>
                                    <span class="font-mono">{{ row.tax_dependent_code || $t('employee_children.not_yet_issued') }}</span>
                                </div>
                                <span v-if="!row.birth_certificate_number && !row.is_tax_dependent" class="text-slate-400">{{ $t('common.not_updated') }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Chế độ Chính sách Áp dụng -->
                    <el-table-column :label="$t('employee_children.col_policies')" min-width="260">
                        <template #default="{ row }">
                            <div class="flex flex-wrap gap-1.5">
                                <el-tag
                                    v-for="(policy, idx) in row.applicable_policies"
                                    :key="idx"
                                    :type="policy.badge"
                                    size="small"
                                    class="!rounded-md"
                                    :title="policy.description"
                                >
                                    {{ policy.name }}
                                </el-tag>
                                <span v-if="!row.applicable_policies || row.applicable_policies.length === 0" class="text-xs text-slate-400">
                                    {{ $t('employee_children.no_applicable_policy') }}
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Trợ cấp hàng tháng -->
                    <el-table-column :label="$t('employee_children.col_monthly_allowance')" min-width="120" align="right">
                        <template #default="{ row }">
                            <span v-if="row.has_monthly_allowance" class="font-semibold text-emerald-600 dark:text-emerald-400 text-sm font-mono">
                                {{ Number(row.monthly_allowance_amount).toLocaleString('vi-VN') }} {{ $t('common.currency_vnd') }}
                            </span>
                            <span v-else class="text-xs text-slate-400">0 {{ $t('common.currency_vnd') }}</span>
                        </template>
                    </el-table-column>

                    <!-- Thao tác -->
                    <el-table-column :label="$t('common.actions')" width="110" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="flex items-center justify-center gap-1">
                                <el-button
                                    type="primary"
                                    link
                                    :icon="Edit"
                                    :title="$t('employee_children.edit_title')"
                                    @click="openEditModal(row)"
                                />
                                <el-popconfirm
                                    :title="$t('employee_children.popconfirm_delete_msg')"
                                    :confirm-button-text="$t('common.delete')"
                                    :cancel-button-text="$t('common.cancel')"
                                    confirm-button-type="danger"
                                    @confirm="deleteChild(row)"
                                >
                                    <template #reference>
                                        <el-button
                                            type="danger"
                                            link
                                            :icon="Delete"
                                            :title="$t('employee_children.delete_title')"
                                        />
                                    </template>
                                </el-popconfirm>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Pagination -->
                <div v-if="children.total > children.per_page" class="p-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                    <el-pagination
                        background
                        layout="prev, pager, next, total"
                        :current-page="children.current_page"
                        :page-size="children.per_page"
                        :total="children.total"
                        @current-change="handlePageChange"
                    />
                </div>
            </div>

            <!-- Modal Thêm / Sửa Con nhỏ -->
            <el-dialog
                v-model="dialogVisible"
                :title="isEditing ? $t('employee_children.dialog_title_edit') : $t('employee_children.dialog_title_create')"
                width="640px"
                class="!rounded-2xl"
                destroy-on-close
            >
                <el-form label-position="top" class="space-y-4">
                    <!-- Chọn phụ huynh -->
                    <el-form-item :label="$t('employee_children.label_parent_employee')" required>
                        <el-select
                            v-model="form.employee_id"
                            :placeholder="$t('employee_children.select_employee_placeholder')"
                            filterable
                            class="w-full"
                        >
                            <el-option
                                v-for="emp in employees"
                                :key="emp.id"
                                :label="`${emp.user_code} - ${emp.full_name} (${emp.gender === 'FEMALE' ? $t('employee_children.parent_gender_mother') : $t('employee_children.parent_gender_father')})`"
                                :value="emp.id"
                            />
                        </el-select>
                    </el-form-item>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Họ tên bé -->
                        <el-form-item :label="$t('employee_children.label_child_full_name')" required>
                            <el-input v-model="form.full_name" :placeholder="$t('employee_children.ph_child_full_name')" />
                        </el-form-item>

                        <!-- Giới tính -->
                        <el-form-item :label="$t('employee_children.label_gender')" required>
                            <el-radio-group v-model="form.gender">
                                <el-radio-button value="MALE">{{ $t('employee_children.gender_boy') }}</el-radio-button>
                                <el-radio-button value="FEMALE">{{ $t('employee_children.gender_girl') }}</el-radio-button>
                            </el-radio-group>
                        </el-form-item>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Ngày sinh -->
                        <el-form-item :label="$t('employee_children.label_birth_date')" required>
                            <el-date-picker
                                v-model="form.birth_date"
                                type="date"
                                :placeholder="$t('employee_children.ph_birth_date')"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                class="!w-full"
                            />
                        </el-form-item>

                        <!-- Số giấy khai sinh -->
                        <el-form-item :label="$t('employee_children.label_birth_cert_no')">
                            <el-input v-model="form.birth_certificate_number" :placeholder="$t('employee_children.ph_birth_cert_no')" />
                        </el-form-item>
                    </div>

                    <!-- Smart Detection Alert Box -->
                    <div v-if="smartDetection" class="p-3.5 rounded-xl border" :class="[
                        smartDetection.type === 'danger' ? 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-900/50 dark:text-rose-300' :
                        smartDetection.type === 'warning' ? 'bg-amber-50 border-amber-200 text-amber-800 dark:bg-amber-950/40 dark:border-amber-900/50 dark:text-amber-300' :
                        'bg-indigo-50 border-indigo-200 text-indigo-800 dark:bg-indigo-950/40 dark:border-indigo-900/50 dark:text-indigo-300'
                    ]">
                        <div class="flex items-center gap-2 font-semibold text-xs">
                            <el-icon :size="15"><InfoFilled /></el-icon>
                            <span>{{ smartDetection.title }}</span>
                        </div>
                        <p class="text-xs mt-1 leading-relaxed">{{ smartDetection.message }}</p>
                    </div>

                    <!-- Phúc lợi: Trợ cấp hàng tháng -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <el-switch v-model="form.has_monthly_allowance" />
                                <span class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $t('employee_children.label_monthly_allowance') }}</span>
                            </div>
                            <span class="text-xs text-slate-400">{{ $t('employee_children.allowance_pay_note') }}</span>
                        </div>
                        <div v-if="form.has_monthly_allowance" class="grid grid-cols-2 gap-3 pt-2">
                            <div>
                                <span class="text-xs text-slate-500 mb-1 block">{{ $t('employee_children.label_allowance_amount') }}</span>
                                <el-input-number
                                    v-model="form.monthly_allowance_amount"
                                    :min="0"
                                    :step="50000"
                                    class="!w-full"
                                />
                            </div>
                            <div class="text-xs text-slate-400 flex items-center">
                                {{ $t('employee_children.allowance_standard_note') }}
                            </div>
                        </div>
                    </div>

                    <!-- Tuân thủ: Giảm trừ thuế TNCN -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <el-switch v-model="form.is_tax_dependent" />
                                <span class="font-semibold text-sm text-slate-800 dark:text-slate-200">{{ $t('employee_children.label_tax_dependent') }}</span>
                            </div>
                            <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold">{{ $t('employee_children.tax_amount_note') }}</span>
                        </div>
                        <div v-if="form.is_tax_dependent" class="grid grid-cols-2 gap-3 pt-2">
                            <div>
                                <span class="text-xs text-slate-500 mb-1 block">{{ $t('employee_children.label_tax_code') }}</span>
                                <el-input v-model="form.tax_dependent_code" :placeholder="$t('employee_children.ph_tax_code')" />
                            </div>
                            <div>
                                <span class="text-xs text-slate-500 mb-1 block">{{ $t('employee_children.label_tax_start_date') }}</span>
                                <el-date-picker
                                    v-model="form.tax_dependent_start_date"
                                    type="date"
                                    :placeholder="$t('employee_children.ph_pick_date')"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    class="!w-full"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Ghi chú -->
                    <el-form-item :label="$t('common.notes')">
                        <el-input
                            v-model="form.notes"
                            type="textarea"
                            :rows="2"
                            :placeholder="$t('employee_children.ph_notes')"
                        />
                    </el-form-item>
                </el-form>

                <template #footer>
                    <div class="flex justify-end gap-2">
                        <el-button @click="dialogVisible = false">{{ $t('common.cancel') }}</el-button>
                        <el-button type="primary" :loading="submitting" @click="submitForm">
                            {{ isEditing ? $t('employee_children.btn_submit_update') : $t('employee_children.btn_submit_create') }}
                        </el-button>
                    </div>
                </template>
            </el-dialog>
        </div>
    </AppLayout>
</template>
