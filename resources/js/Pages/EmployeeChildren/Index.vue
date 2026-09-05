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
            title: 'Dự sinh trong tương lai',
            message: 'Ngày sinh đang được đặt trong tương lai (kế hoạch thai sản).',
        };
    } else if (diffMonths < 12) {
        return {
            type: 'danger',
            title: 'Chế độ Điều 137 BLLĐ 2019 (Con dưới 12 tháng)',
            message: `Bé hiện tại ${diffMonths} tháng tuổi. Lao động nữ sẽ tự động được hưởng chế độ miễn trừ 60 phút/ngày có hưởng lương và tuyệt đối cấm làm ca đêm / làm thêm giờ!`,
        };
    } else if (diffMonths <= 132) {
        const years = Math.floor(diffMonths / 12);
        return {
            type: 'warning',
            title: 'Chế độ Con nhỏ Mở rộng (1 - 11 tuổi)',
            message: `Bé hiện tại ${years} tuổi. Nhân sự được áp dụng chế độ hỗ trợ con nhỏ mở rộng (miễn trừ 30 phút/ngày có hưởng lương) theo quy chế công ty.`,
        };
    } else {
        return {
            type: 'info',
            title: 'Trẻ trên 11 tuổi',
            message: 'Bé đã trên 11 tuổi. Vẫn được tiếp tục đăng ký giảm trừ gia cảnh thuế TNCN theo quy định pháp luật thuế.',
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
        ElMessage.warning('Vui lòng điền đầy đủ các trường bắt buộc: Nhân viên phụ huynh, Họ tên bé và Ngày sinh!');
        return;
    }

    submitting.value = true;
    if (isEditing.value && currentChildId.value) {
        router.put(`/employee-children/${currentChildId.value}`, form.value, {
            onSuccess: () => {
                dialogVisible.value = false;
                ElMessage.success('Cập nhật hồ sơ con nhỏ thành công!');
            },
            onFinish: () => {
                submitting.value = false;
            },
        });
    } else {
        router.post('/employee-children', form.value, {
            onSuccess: () => {
                dialogVisible.value = false;
                ElMessage.success('Thêm mới hồ sơ con nhỏ thành công!');
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
            ElMessage.success(`Đã xóa hồ sơ bé ${child.full_name}`);
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
        <Head title="Quản lý Con nhỏ & Chế độ Phúc lợi Gia đình - Smart-HR" />

        <div class="space-y-6 pb-12">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-xl bg-pink-50 dark:bg-pink-950/60 text-pink-600 dark:text-pink-400 flex items-center justify-center">
                            <el-icon :size="20"><Female /></el-icon>
                        </div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            Quản lý Con nhỏ & Chế độ Phúc lợi Gia đình
                        </h1>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Bảo vệ quyền lợi thai sản Điều 137 BLLĐ 2019, chế độ đi muộn về sớm có lương, trợ cấp con nhỏ hàng tháng và giảm trừ gia cảnh thuế TNCN.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-button :icon="Setting" class="!rounded-xl font-medium" @click="router.visit('/policies?tab=child_welfare')">
                        Cấu hình Quy chế Con nhỏ
                    </el-button>
                    <el-button type="primary" :icon="Plus" class="!rounded-xl font-medium" @click="openCreateModal">
                        Thêm Con nhỏ Mới
                    </el-button>
                </div>
            </div>

            <!-- KPI Cards Overview -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <!-- Card 1: Tổng số trẻ -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
                    <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Tổng Trẻ em Bảo trợ</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-slate-900 dark:text-white">{{ kpis.totalChildrenCount }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-medium">Bé</span>
                    </div>
                    <span class="text-[10px] text-slate-400 mt-1">Thuộc nhân sự tập đoàn</span>
                </div>

                <!-- Card 2: Điều 137 (<12 tháng) -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-rose-200 dark:border-rose-900/60 shadow-xs flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-12 h-12 bg-rose-500/10 rounded-bl-full pointer-events-none"></div>
                    <span class="text-xs text-rose-600 dark:text-rose-400 font-medium flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        Chế độ Điều 137 (&lt; 12T)
                    </span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ kpis.under12mCount }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 font-medium">Bé</span>
                    </div>
                    <span class="text-[10px] text-rose-500 mt-1">Miễn trừ 60p/ngày, cấm ca đêm</span>
                </div>

                <!-- Card 3: Con 1 - 11 tuổi -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-amber-200 dark:border-amber-900/60 shadow-xs flex flex-col justify-between">
                    <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">Con nhỏ Mở rộng (1-11T)</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ kpis.range1to11Count }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 font-medium">Bé</span>
                    </div>
                    <span class="text-[10px] text-amber-500 mt-1">Miễn trừ 30p/ngày có lương</span>
                </div>

                <!-- Card 4: Giảm trừ thuế TNCN -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-blue-200 dark:border-blue-900/60 shadow-xs flex flex-col justify-between">
                    <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">Giảm trừ Thuế TNCN</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ kpis.taxDependentCount }}</span>
                        <span class="text-[11px] px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 font-medium">Người</span>
                    </div>
                    <span class="text-[10px] text-blue-500 mt-1">4.400.000 đ/người/tháng</span>
                </div>

                <!-- Card 5: Quỹ trợ cấp hàng tháng -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-emerald-200 dark:border-emerald-900/60 shadow-xs flex flex-col justify-between col-span-2 md:col-span-1">
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Quỹ Trợ cấp Hàng tháng</span>
                    <div class="mt-2 flex items-baseline justify-between">
                        <span class="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                            {{ Number(kpis.totalMonthlyAllowance).toLocaleString('vi-VN') }}
                        </span>
                        <span class="text-[11px] text-emerald-600 font-medium">đ</span>
                    </div>
                    <span class="text-[10px] text-emerald-500 mt-1">300.000 đ/bạn nhỏ/tháng</span>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <!-- Search Input -->
                    <el-input
                        v-model="searchQuery"
                        placeholder="Tìm tên bé, số GKS, tên cha mẹ, mã NV..."
                        clearable
                        class="!w-72"
                        :prefix-icon="Search"
                        @keyup.enter="applyFilters"
                        @clear="applyFilters"
                    />

                    <!-- Filter Employee -->
                    <el-select
                        v-model="selectedEmployeeId"
                        placeholder="Chọn nhân viên (Cha/Mẹ)"
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
                        placeholder="Nhóm độ tuổi"
                        clearable
                        class="!w-48"
                        @change="applyFilters"
                    >
                        <el-option label="Tất cả độ tuổi" value="" />
                        <el-option label="< 12 tháng (Điều 137)" value="UNDER_12M" />
                        <el-option label="1 - 6 tuổi" value="1_TO_6Y" />
                        <el-option label="6 - 11 tuổi" value="6_TO_11Y" />
                        <el-option label="Trên 11 tuổi" value="OVER_11Y" />
                    </el-select>

                    <!-- Filter Tax Dependent -->
                    <el-select
                        v-model="selectedTaxDependent"
                        placeholder="Giảm trừ thuế"
                        clearable
                        class="!w-40"
                        @change="applyFilters"
                    >
                        <el-option label="Tất cả" value="" />
                        <el-option label="Có giảm trừ thuế" value="1" />
                        <el-option label="Không giảm trừ" value="0" />
                    </el-select>
                </div>

                <div class="flex items-center gap-2 w-full md:w-auto justify-end">
                    <el-button type="primary" :icon="Search" class="!rounded-xl font-medium" @click="applyFilters">
                        Tìm kiếm
                    </el-button>
                    <el-button :icon="Refresh" class="!rounded-xl" @click="resetFilters">
                        Làm mới
                    </el-button>
                </div>
            </div>

            <!-- Children Data Table -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
                <el-table :data="children.data" stripe class="w-full" empty-text="Không có hồ sơ con nhỏ nào">
                    <!-- Bé & Độ tuổi -->
                    <el-table-column label="Thông tin Bé" min-width="190">
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
                    <el-table-column label="Phụ huynh (Nhân viên)" min-width="180">
                        <template #default="{ row }">
                            <div v-if="row.employee" class="flex flex-col">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium text-slate-800 dark:text-slate-200 text-sm">{{ row.employee.full_name }}</span>
                                    <el-tag size="small" type="info" class="!text-[10px] font-mono">{{ row.employee.user_code }}</el-tag>
                                </div>
                                <span class="text-[11px] text-slate-400">{{ row.employee.department_name }}</span>
                            </div>
                            <span v-else class="text-xs text-slate-400">Chưa liên kết</span>
                        </template>
                    </el-table-column>

                    <!-- Giấy khai sinh & Mã số thuế -->
                    <el-table-column label="Giấy tờ & MST" min-width="160">
                        <template #default="{ row }">
                            <div class="flex flex-col text-xs space-y-1">
                                <div v-if="row.birth_certificate_number" class="flex items-center gap-1 text-slate-600 dark:text-slate-300">
                                    <span class="text-slate-400">GKS:</span>
                                    <span class="font-mono">{{ row.birth_certificate_number }}</span>
                                </div>
                                <div v-if="row.is_tax_dependent" class="flex items-center gap-1 text-blue-600 dark:text-blue-400 font-medium">
                                    <span class="text-slate-400">MST:</span>
                                    <span class="font-mono">{{ row.tax_dependent_code || 'Chưa cấp' }}</span>
                                </div>
                                <span v-if="!row.birth_certificate_number && !row.is_tax_dependent" class="text-slate-400">Chưa cập nhật</span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Chế độ Chính sách Áp dụng -->
                    <el-table-column label="Chế độ Chính sách Áp dụng" min-width="260">
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
                                    Không áp dụng chính sách
                                </span>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Trợ cấp hàng tháng -->
                    <el-table-column label="Trợ cấp Tháng" min-width="120" align="right">
                        <template #default="{ row }">
                            <span v-if="row.has_monthly_allowance" class="font-semibold text-emerald-600 dark:text-emerald-400 text-sm font-mono">
                                {{ Number(row.monthly_allowance_amount).toLocaleString('vi-VN') }} đ
                            </span>
                            <span v-else class="text-xs text-slate-400">0 đ</span>
                        </template>
                    </el-table-column>

                    <!-- Thao tác -->
                    <el-table-column label="Thao tác" width="110" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="flex items-center justify-center gap-1">
                                <el-button
                                    type="primary"
                                    link
                                    :icon="Edit"
                                    title="Sửa hồ sơ"
                                    @click="openEditModal(row)"
                                />
                                <el-popconfirm
                                    title="Bạn có chắc chắn muốn xóa hồ sơ con nhỏ này?"
                                    confirm-button-text="Xóa"
                                    cancel-button-text="Hủy"
                                    confirm-button-type="danger"
                                    @confirm="deleteChild(row)"
                                >
                                    <template #reference>
                                        <el-button
                                            type="danger"
                                            link
                                            :icon="Delete"
                                            title="Xóa hồ sơ"
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
                :title="isEditing ? 'Cập nhật Hồ sơ Con nhỏ' : 'Thêm mới Con nhỏ & Đăng ký Phúc lợi'"
                width="640px"
                class="!rounded-2xl"
                destroy-on-close
            >
                <el-form label-position="top" class="space-y-4">
                    <!-- Chọn phụ huynh -->
                    <el-form-item label="Nhân sự Phụ huynh (Cha / Mẹ) *" required>
                        <el-select
                            v-model="form.employee_id"
                            placeholder="Chọn nhân viên"
                            filterable
                            class="w-full"
                        >
                            <el-option
                                v-for="emp in employees"
                                :key="emp.id"
                                :label="`${emp.user_code} - ${emp.full_name} (${emp.gender === 'FEMALE' ? 'Mẹ' : 'Cha'})`"
                                :value="emp.id"
                            />
                        </el-select>
                    </el-form-item>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Họ tên bé -->
                        <el-form-item label="Họ và tên con *" required>
                            <el-input v-model="form.full_name" placeholder="VD: Nguyễn Tuệ Minh" />
                        </el-form-item>

                        <!-- Giới tính -->
                        <el-form-item label="Giới tính" required>
                            <el-radio-group v-model="form.gender">
                                <el-radio-button value="MALE">Bé Trai</el-radio-button>
                                <el-radio-button value="FEMALE">Bé Gái</el-radio-button>
                            </el-radio-group>
                        </el-form-item>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <!-- Ngày sinh -->
                        <el-form-item label="Ngày sinh của bé *" required>
                            <el-date-picker
                                v-model="form.birth_date"
                                type="date"
                                placeholder="Chọn ngày sinh"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                class="!w-full"
                            />
                        </el-form-item>

                        <!-- Số giấy khai sinh -->
                        <el-form-item label="Số Giấy khai sinh">
                            <el-input v-model="form.birth_certificate_number" placeholder="VD: GKS-HN-2026-001" />
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
                                <span class="font-semibold text-sm text-slate-800 dark:text-slate-200">Trợ cấp con nhỏ hàng tháng</span>
                            </div>
                            <span class="text-xs text-slate-400">Chi trả cùng kỳ lương</span>
                        </div>
                        <div v-if="form.has_monthly_allowance" class="grid grid-cols-2 gap-3 pt-2">
                            <div>
                                <span class="text-xs text-slate-500 mb-1 block">Mức trợ cấp (VND/tháng)</span>
                                <el-input-number
                                    v-model="form.monthly_allowance_amount"
                                    :min="0"
                                    :step="50000"
                                    class="!w-full"
                                />
                            </div>
                            <div class="text-xs text-slate-400 flex items-center">
                                Mức tiêu chuẩn công ty: 300.000 đ/tháng cho mỗi con nhỏ.
                            </div>
                        </div>
                    </div>

                    <!-- Tuân thủ: Giảm trừ thuế TNCN -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <el-switch v-model="form.is_tax_dependent" />
                                <span class="font-semibold text-sm text-slate-800 dark:text-slate-200">Đăng ký người phụ thuộc giảm trừ gia cảnh</span>
                            </div>
                            <span class="text-xs text-blue-600 dark:text-blue-400 font-semibold">-4.400.000 đ/tháng</span>
                        </div>
                        <div v-if="form.is_tax_dependent" class="grid grid-cols-2 gap-3 pt-2">
                            <div>
                                <span class="text-xs text-slate-500 mb-1 block">Mã số thuế người phụ thuộc</span>
                                <el-input v-model="form.tax_dependent_code" placeholder="VD: MST_DEP_0123" />
                            </div>
                            <div>
                                <span class="text-xs text-slate-500 mb-1 block">Ngày bắt đầu giảm trừ</span>
                                <el-date-picker
                                    v-model="form.tax_dependent_start_date"
                                    type="date"
                                    placeholder="Chọn ngày"
                                    format="YYYY-MM-DD"
                                    value-format="YYYY-MM-DD"
                                    class="!w-full"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Ghi chú -->
                    <el-form-item label="Ghi chú">
                        <el-input
                            v-model="form.notes"
                            type="textarea"
                            :rows="2"
                            placeholder="Ghi chú về sức khỏe, hoàn cảnh hoặc chế độ riêng..."
                        />
                    </el-form-item>
                </el-form>

                <template #footer>
                    <div class="flex justify-end gap-2">
                        <el-button @click="dialogVisible = false">Hủy</el-button>
                        <el-button type="primary" :loading="submitting" @click="submitForm">
                            {{ isEditing ? 'Cập nhật' : 'Thêm mới' }}
                        </el-button>
                    </div>
                </template>
            </el-dialog>
        </div>
    </AppLayout>
</template>
