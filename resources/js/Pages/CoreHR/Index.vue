<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import AuditHistoryDrawer from '@/Components/AuditHistoryDrawer.vue';
import {
    User,
    OfficeBuilding,
    Plus,
    Search,
    Refresh,
    Edit,
    Share,
    Right,
    Female,
    Male,
    UserFilled,
    CircleClose,
    Warning,
    Document,
    Money,
    InfoFilled,
    Collection,
    Delete,
    Suitcase,
    Clock,
} from '@element-plus/icons-vue';

interface OrgTreeNode {
    id: number;
    code: string;
    name: string;
    level: number;
    level_label: string;
    branch_name?: string;
    mission_function?: string;
    manager?: {
        id: number;
        full_name: string;
        user_code: string;
    } | null;
    employee_count: number;
    positions: Array<{
        id: number;
        name: string;
        level: number;
        allowance_amount: number;
    }>;
    children: OrgTreeNode[];
}

const props = defineProps<{
    employees: {
        data: Array<{
            id: number;
            user_code: string;
            attendance_code?: string;
            full_name: string;
            gender: string;
            birth_date: string | null;
            birth_date_display: string;
            email: string;
            phone: string | null;
            citizen_id?: string | null;
            hometown?: string | null;
            permanent_address?: string | null;
            temporary_address?: string | null;
            join_date: string | null;
            join_date_display: string;
            probation_end_date?: string | null;
            status: string;
            department_id?: number;
            position_id?: number;
            department_name: string;
            position_name: string;
            branch_name: string;
            is_child_under_12m: boolean;
            is_pregnant_restricted: boolean;
            is_pregnant?: boolean;
            pregnancy_start_date?: string | null;
            pregnancy_weeks_at_start?: number | null;
            expected_due_date?: string | null;
            children_count: number;
            tax_dependents_count: number;
            certificates_count: number;
            seniority_text: string;
            resigned_date?: string | null;
            resignation_reason?: string | null;
        }>;
        links: any[];
        total: number;
    };
    departments: Array<{
        id: number;
        code: string;
        name: string;
        level?: number;
        parent_id?: number | null;
        branch_id?: number;
        branch_name: string;
        status?: string;
        manager?: { id: number; full_name: string; user_code: string } | null;
        mission_function?: string | null;
        order?: number;
    }>;
    orgTree?: OrgTreeNode[];
    positions: Array<{
        id: number;
        code: string;
        name: string;
        level: number;
        department_id?: number | null;
        allowance_amount?: number;
        bonus_one_time?: number;
        description?: string | null;
    }>;
    branches: Array<{
        id: number;
        code: string;
        name: string;
        address: string | null;
        is_remote_or_island: boolean;
    }>;
    allEmployeesShort?: Array<{
        id: number;
        full_name: string;
        user_code: string;
    }>;
    filters: {
        search: string | null;
        department_id: string | null;
        status: string | null;
    };
}>();

import { useSyncedTab } from '@/Composables/useNavigationHistory';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

const { t } = useThemeAndLocale();

const activeTab = useSyncedTab('employees', 'tab') as any;

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

// CRUD Phòng ban (Departments)
const isCreateDeptModalOpen = ref(false);
const createDeptForm = useForm({
    code: '',
    name: '',
    branch_id: props.branches[0]?.id || 1,
    parent_id: null as number | null,
    level: 2,
    manager_id: null as number | null,
    mission_function: '',
    order: 0,
});

const openCreateDepartmentModal = () => {
    createDeptForm.reset();
    createDeptForm.branch_id = props.branches[0]?.id || 1;
    createDeptForm.level = 2;
    createDeptForm.order = props.departments.length + 1;
    isCreateDeptModalOpen.value = true;
};

const submitCreateDepartment = () => {
    createDeptForm.post('/core-hr/departments', {
        onSuccess: () => {
            isCreateDeptModalOpen.value = false;
            createDeptForm.reset();
            ElMessage.success(t('core_hr.msg_dept_create_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_dept_create_error'));
        },
    });
};

const isEditDeptModalOpen = ref(false);
const editingDeptId = ref<number | null>(null);
const editDeptForm = useForm({
    code: '',
    name: '',
    branch_id: 1,
    parent_id: null as number | null,
    level: 2,
    manager_id: null as number | null,
    mission_function: '',
    status: 'ACTIVE',
    order: 0,
});

const openEditDepartmentModal = (dept: any) => {
    editingDeptId.value = dept.id;
    editDeptForm.code = dept.code;
    editDeptForm.name = dept.name;
    editDeptForm.branch_id = dept.branch_id || props.branches[0]?.id || 1;
    editDeptForm.parent_id = dept.parent_id || null;
    editDeptForm.level = dept.level || 2;
    editDeptForm.manager_id = dept.manager?.id || null;
    editDeptForm.mission_function = dept.mission_function || '';
    editDeptForm.status = dept.status || 'ACTIVE';
    editDeptForm.order = dept.order || 0;
    isEditDeptModalOpen.value = true;
};

const submitEditDepartment = () => {
    if (!editingDeptId.value) return;
    editDeptForm.put(`/core-hr/departments/${editingDeptId.value}`, {
        onSuccess: () => {
            isEditDeptModalOpen.value = false;
            ElMessage.success(t('core_hr.msg_dept_update_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_dept_update_error'));
        },
    });
};

const deleteDepartment = (dept: any) => {
    ElMessageBox.confirm(
        t('core_hr.dept_delete_confirm', { name: dept.name, code: dept.code }),
        t('core_hr.dept_delete_confirm_title'),
        {
            confirmButtonText: t('core_hr.dept_delete_confirm_btn'),
            cancelButtonText: t('core_hr.btn_cancel'),
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    ).then(() => {
        router.delete(`/core-hr/departments/${dept.id}`, {
            onSuccess: () => {
                ElMessage.success(t('core_hr.msg_dept_delete_success'));
            },
            onError: (errors: any) => {
                ElMessage.error(errors.message || t('core_hr.msg_dept_delete_error'));
            },
        });
    }).catch(() => {});
};

// CRUD Chức vụ (Positions)
const isCreatePosModalOpen = ref(false);
const createPosForm = useForm({
    code: '',
    name: '',
    level: 1,
    department_id: null as number | null,
    allowance_amount: 0,
    bonus_one_time: 0,
    description: '',
});

const openCreatePositionModal = () => {
    createPosForm.reset();
    createPosForm.level = 1;
    createPosForm.allowance_amount = 0;
    createPosForm.bonus_one_time = 0;
    isCreatePosModalOpen.value = true;
};

const submitCreatePosition = () => {
    createPosForm.post('/core-hr/positions', {
        onSuccess: () => {
            isCreatePosModalOpen.value = false;
            createPosForm.reset();
            ElMessage.success(t('core_hr.msg_pos_create_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_pos_create_error'));
        },
    });
};

const isEditPosModalOpen = ref(false);
const editingPosId = ref<number | null>(null);
const editPosForm = useForm({
    code: '',
    name: '',
    level: 1,
    department_id: null as number | null,
    allowance_amount: 0,
    bonus_one_time: 0,
    description: '',
});

const openEditPositionModal = (pos: any) => {
    editingPosId.value = pos.id;
    editPosForm.code = pos.code;
    editPosForm.name = pos.name;
    editPosForm.level = pos.level;
    editPosForm.department_id = pos.department_id || null;
    editPosForm.allowance_amount = pos.allowance_amount || 0;
    editPosForm.bonus_one_time = pos.bonus_one_time || 0;
    editPosForm.description = pos.description || '';
    isEditPosModalOpen.value = true;
};

const submitEditPosition = () => {
    if (!editingPosId.value) return;
    editPosForm.put(`/core-hr/positions/${editingPosId.value}`, {
        onSuccess: () => {
            isEditPosModalOpen.value = false;
            ElMessage.success(t('core_hr.msg_pos_update_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_pos_update_error'));
        },
    });
};

const deletePosition = (pos: any) => {
    ElMessageBox.confirm(
        t('core_hr.pos_delete_confirm', { name: pos.name, code: pos.code }),
        t('core_hr.pos_delete_confirm_title'),
        {
            confirmButtonText: t('core_hr.pos_delete_confirm_btn'),
            cancelButtonText: t('core_hr.btn_cancel'),
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    ).then(() => {
        router.delete(`/core-hr/positions/${pos.id}`, {
            onSuccess: () => {
                ElMessage.success(t('core_hr.msg_pos_delete_success'));
            },
            onError: (errors: any) => {
                ElMessage.error(errors.message || t('core_hr.msg_pos_delete_error'));
            },
        });
    }).catch(() => {});
};

// Bộ lọc
const search = ref(props.filters.search || '');
const selectedDepartment = ref(props.filters.department_id || '');
const selectedStatus = ref(props.filters.status || '');

const applyFilters = () => {
    router.get('/core-hr', {
        search: search.value || undefined,
        department_id: selectedDepartment.value || undefined,
        status: selectedStatus.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    search.value = '';
    selectedDepartment.value = '';
    selectedStatus.value = '';
    applyFilters();
};

// Modal Tiếp nhận Nhân viên Mới
const isCreateEmployeeModalOpen = ref(false);
const employeeForm = useForm({
    user_code: '',
    attendance_code: '',
    full_name: '',
    gender: 'MALE',
    birth_date: '',
    email: '',
    phone: '',
    citizen_id: '',
    hometown: '',
    permanent_address: '',
    temporary_address: '',
    join_date: new Date().toISOString().split('T')[0],
    probation_end_date: '',
    department_id: props.departments[0]?.id || '',
    position_id: props.positions[0]?.id || '',
    status: 'OFFICIAL',
    is_pregnant: false,
    pregnancy_start_date: '',
    pregnancy_weeks_at_start: 24,
    expected_due_date: '',
});

const submitCreateEmployee = () => {
    employeeForm.post('/core-hr/employees', {
        onSuccess: () => {
            isCreateEmployeeModalOpen.value = false;
            employeeForm.reset();
            ElMessage.success(t('core_hr.msg_employee_onboard_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_employee_onboard_error'));
        },
    });
};

// Modal Sửa Nhân viên (Edit)
const isEditModalOpen = ref(false);
const editingEmployeeId = ref<number | null>(null);
const editForm = useForm({
    full_name: '',
    gender: 'MALE',
    birth_date: '',
    email: '',
    phone: '',
    attendance_code: '',
    citizen_id: '',
    hometown: '',
    permanent_address: '',
    temporary_address: '',
    join_date: '',
    probation_end_date: '',
    status: 'OFFICIAL',
    is_pregnant: false,
    pregnancy_start_date: '',
    pregnancy_weeks_at_start: 24,
    expected_due_date: '',
});

const openEditEmployee = (emp: any) => {
    editingEmployeeId.value = emp.id;
    editForm.full_name = emp.full_name;
    editForm.gender = emp.gender;
    editForm.birth_date = emp.birth_date || '';
    editForm.email = emp.email;
    editForm.phone = emp.phone || '';
    editForm.attendance_code = emp.attendance_code || '';
    editForm.citizen_id = emp.citizen_id || '';
    editForm.hometown = emp.hometown || '';
    editForm.permanent_address = emp.permanent_address || '';
    editForm.temporary_address = emp.temporary_address || '';
    editForm.join_date = emp.join_date || '';
    editForm.probation_end_date = emp.probation_end_date || '';
    editForm.status = emp.status;
    editForm.is_pregnant = !!emp.is_pregnant;
    editForm.pregnancy_start_date = emp.pregnancy_start_date || '';
    editForm.pregnancy_weeks_at_start = emp.pregnancy_weeks_at_start || 24;
    editForm.expected_due_date = emp.expected_due_date || '';
    isEditModalOpen.value = true;
};

const submitEditEmployee = () => {
    if (!editingEmployeeId.value) return;
    editForm.put(`/core-hr/employees/${editingEmployeeId.value}`, {
        onSuccess: () => {
            isEditModalOpen.value = false;
            ElMessage.success(t('core_hr.msg_employee_update_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_employee_update_error'));
        },
    });
};

// Modal Thôi việc (Offboarding)
const isOffboardModalOpen = ref(false);
const offboardingEmployee = ref<any>(null);
const offboardForm = useForm({
    resigned_date: new Date().toISOString().split('T')[0],
    resignation_reason: '',
    decision_number: '',
    decision_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const openOffboardModal = (emp: any) => {
    offboardingEmployee.value = emp;
    offboardForm.resigned_date = new Date().toISOString().split('T')[0];
    offboardForm.resignation_reason = '';
    offboardForm.decision_number = `QĐ-TV-${emp.user_code}`;
    offboardForm.decision_date = new Date().toISOString().split('T')[0];
    offboardForm.notes = '';
    isOffboardModalOpen.value = true;
};

const submitOffboard = () => {
    if (!offboardingEmployee.value) return;
    offboardForm.post(`/core-hr/employees/${offboardingEmployee.value.id}/offboard`, {
        onSuccess: () => {
            isOffboardModalOpen.value = false;
            ElMessage.success(t('core_hr.msg_offboard_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_offboard_error'));
        },
    });
};

// Modal Ban hành Phiên bản Phòng ban (Effective Dating)
const isDepartmentModalOpen = ref(false);
const selectedDeptId = ref<number | null>(null);
const departmentForm = useForm({
    name: '',
    effective_date: new Date().toISOString().split('T')[0],
    functions: '',
});

const openDepartmentVersionModal = (dept: { id: number; name: string }) => {
    selectedDeptId.value = dept.id;
    departmentForm.name = dept.name;
    departmentForm.effective_date = new Date().toISOString().split('T')[0];
    departmentForm.functions = '';
    isDepartmentModalOpen.value = true;
};

const submitDepartmentVersion = () => {
    if (!selectedDeptId.value) return;
    departmentForm.post(`/core-hr/departments/${selectedDeptId.value}/version`, {
        onSuccess: () => {
            isDepartmentModalOpen.value = false;
            departmentForm.reset();
            ElMessage.success(t('core_hr.msg_version_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_version_error'));
        },
    });
};

// Modal Tạo Chi nhánh mới
const isBranchModalOpen = ref(false);
const branchForm = useForm({
    code: '',
    name: '',
    address: '',
    is_remote_or_island: false,
});

const submitBranch = () => {
    branchForm.post('/core-hr/branches', {
        onSuccess: () => {
            isBranchModalOpen.value = false;
            branchForm.reset();
            ElMessage.success(t('core_hr.msg_branch_create_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_branch_create_error'));
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head :title="$t('core_hr.doc_title')" />

        <div class="space-y-6 pb-12">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">{{ $t('core_hr.page_title') }}</h1>
                    <p class="text-xs text-slate-400 mt-1">
                        {{ $t('core_hr.page_subtitle') }}
                    </p>
                </div>

                <!-- Navigation Tabs -->
                <div class="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
                    <button
                        @click="activeTab = 'employees'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'employees' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><User /></el-icon>
                        <span>{{ $t('core_hr.tab_people_count', { count: employees.total }) }}</span>
                    </button>
                    <button
                        @click="activeTab = 'org_tree'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'org_tree' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><Share /></el-icon>
                        <span>{{ $t('core_hr.tab_org_tree') }}</span>
                    </button>
                    <button
                        @click="activeTab = 'departments'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'departments' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><OfficeBuilding /></el-icon>
                        <span>{{ $t('core_hr.tab_departments_count', { count: departments.length }) }}</span>
                    </button>
                    <button
                        @click="activeTab = 'positions'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'positions' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><Suitcase /></el-icon>
                        <span>{{ $t('core_hr.tab_positions_count', { count: positions.length }) }}</span>
                    </button>
                    <button
                        @click="activeTab = 'branches'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'branches' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><OfficeBuilding /></el-icon>
                        <span>{{ $t('core_hr.tab_branches_count', { count: branches.length }) }}</span>
                    </button>
                </div>
            </div>

            <!-- TAB 1: DANH SÁCH NHÂN SỰ -->
            <div v-if="activeTab === 'employees'" class="space-y-4">
                <!-- Filter Bar -->
                <div class="bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 shadow-xs">
                    <div class="flex flex-wrap items-center gap-2.5 flex-1 min-w-[280px]">
                        <div class="w-64">
                            <el-input
                                v-model="search"
                                @keyup.enter="applyFilters"
                                :placeholder="$t('core_hr.search_placeholder')"
                                :prefix-icon="Search"
                                clearable
                            />
                        </div>

                        <select
                            v-model="selectedDepartment"
                            @change="applyFilters"
                            class="text-xs px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">{{ $t('core_hr.filter_all_departments') }}</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>

                        <select
                            v-model="selectedStatus"
                            @change="applyFilters"
                            class="text-xs px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">{{ $t('core_hr.filter_all_statuses') }}</option>
                            <option value="PROBATION">{{ $t('core_hr.status_probation') }}</option>
                            <option value="OFFICIAL">{{ $t('core_hr.status_official') }}</option>
                            <option value="ON_LEAVE">{{ $t('core_hr.filter_status_maternity_leave') }}</option>
                            <option value="TERMINATED">{{ $t('core_hr.status_terminated') }}</option>
                        </select>

                        <button
                            @click="applyFilters"
                            class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs transition"
                        >
                            {{ $t('common.filter') }}
                        </button>
                        <button
                            @click="resetFilters"
                            class="p-2 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-700 rounded-xl text-xs transition"
                            :title="$t('core_hr.filter_reset_title')"
                        >
                            <el-icon :size="14"><Refresh /></el-icon>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <Link
                            href="/employee-children"
                            class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-pink-50 dark:bg-pink-950/60 text-pink-600 dark:text-pink-400 hover:bg-pink-100 transition border border-pink-200 dark:border-pink-900/60 flex items-center gap-1.5"
                        >
                            <el-icon :size="14"><Female /></el-icon>
                            <span>{{ $t('core_hr.children_welfare_link') }}</span>
                        </Link>
                        <button
                            @click="isCreateEmployeeModalOpen = true"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center space-x-1.5 transition"
                        >
                            <el-icon :size="14"><Plus /></el-icon>
                            <span>{{ $t('core_hr.btn_onboard_employee') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                    <th class="py-3 px-4">{{ $t('common.employee') }}</th>
                                    <th class="py-3 px-4">{{ $t('core_hr.col_unit_position') }}</th>
                                    <th class="py-3 px-4">{{ $t('core_hr.col_children_dependents') }}</th>
                                    <th class="py-3 px-4">{{ $t('core_hr.col_maternity_protection') }}</th>
                                    <th class="py-3 px-4">{{ $t('core_hr.col_status_seniority') }}</th>
                                    <th class="py-3 px-4 text-center">{{ $t('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                                <tr
                                    v-for="emp in employees.data"
                                    :key="emp.id"
                                    class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition group"
                                >
                                    <!-- Cột 1: Thông tin nhân sự -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm shrink-0 shadow-xs"
                                                :class="emp.gender === 'FEMALE' ? 'bg-pink-50 text-pink-600 dark:bg-pink-950/60 dark:text-pink-400' : 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400'"
                                            >
                                                {{ emp.full_name.substring(0, 2).toUpperCase() }}
                                            </div>
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-bold text-slate-800 dark:text-white text-sm">{{ emp.full_name }}</span>
                                                    <span class="text-[10px] px-1.5 py-0.2 rounded font-mono font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                                                        {{ emp.user_code }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 text-[11px] text-slate-400 mt-0.5">
                                                    <span>{{ emp.email }}</span>
                                                    <span v-if="emp.citizen_id">• {{ $t('core_hr.ccid_label', { code: emp.citizen_id }) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Cột 2: Đơn vị & Chức vụ -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-slate-800 dark:text-slate-200">{{ emp.position_name }}</span>
                                            <span class="text-[11px] text-slate-400">{{ emp.department_name }}</span>
                                            <span class="text-[10px] text-indigo-500 dark:text-indigo-400 font-medium">{{ emp.branch_name }}</span>
                                        </div>
                                    </td>

                                    <!-- Cột 3: Con nhỏ & Người phụ thuộc -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-col space-y-1">
                                            <Link
                                                v-if="emp.children_count > 0"
                                                :href="`/employee-children?employee_id=${emp.id}`"
                                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-pink-600 dark:text-pink-400 hover:underline"
                                            >
                                                <el-icon :size="13"><Female /></el-icon>
                                                <span>{{ $t('core_hr.children_count', { count: emp.children_count }) }}</span>
                                            </Link>
                                            <span v-else class="text-slate-400 text-[11px]">{{ $t('core_hr.children_none') }}</span>

                                            <span v-if="emp.tax_dependents_count > 0" class="text-[10px] text-blue-600 dark:text-blue-400 font-medium">
                                                {{ $t('core_hr.tax_dependents_count', { count: emp.tax_dependents_count }) }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Cột 4: Chế độ Thai sản (Đ.137) -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-col space-y-1">
                                            <span
                                                v-if="emp.is_child_under_12m"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800 w-fit"
                                            >
                                                {{ $t('core_hr.badge_child_under_12m') }}
                                            </span>

                                            <span
                                                v-if="emp.is_pregnant"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800 w-fit"
                                            >
                                                {{ $t('core_hr.badge_pregnant') }} {{ emp.pregnancy_weeks_at_start ? $t('core_hr.badge_pregnant_weeks', { weeks: emp.pregnancy_weeks_at_start }) : '' }}
                                            </span>

                                            <span
                                                v-if="emp.is_pregnant_restricted"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800 w-fit"
                                            >
                                                {{ $t('core_hr.badge_restricted') }}
                                            </span>

                                            <span v-if="!emp.is_child_under_12m && !emp.is_pregnant" class="text-slate-400 text-[11px]">
                                                {{ $t('core_hr.badge_normal') }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Cột 5: Trạng thái & Thâm niên -->
                                    <td class="py-3.5 px-4">
                                        <div class="flex flex-col">
                                            <div>
                                                <el-tag
                                                    v-if="emp.status === 'OFFICIAL'"
                                                    type="success"
                                                    size="small"
                                                    class="!rounded-md"
                                                >
                                                    {{ $t('core_hr.status_official') }}
                                                </el-tag>
                                                <el-tag
                                                    v-else-if="emp.status === 'PROBATION'"
                                                    type="warning"
                                                    size="small"
                                                    class="!rounded-md"
                                                >
                                                    {{ $t('core_hr.status_probation') }}
                                                </el-tag>
                                                <el-tag
                                                    v-else-if="emp.status === 'TERMINATED'"
                                                    type="danger"
                                                    size="small"
                                                    class="!rounded-md"
                                                >
                                                    {{ $t('core_hr.status_terminated') }}
                                                </el-tag>
                                                <el-tag
                                                    v-else
                                                    type="info"
                                                    size="small"
                                                    class="!rounded-md"
                                                >
                                                    {{ emp.status }}
                                                </el-tag>
                                            </div>
                                            <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 font-medium">
                                                {{ $t('core_hr.seniority_label', { text: emp.seniority_text }) }}
                                            </span>
                                            <span v-if="emp.status === 'TERMINATED' && emp.resigned_date" class="text-[10px] text-rose-500">
                                                {{ $t('core_hr.resigned_label', { date: emp.resigned_date }) }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Cột 6: Thao tác -->
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <Link
                                                :href="`/core-hr/employees/${emp.id}`"
                                                class="p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                :title="$t('core_hr.title_view_profile')"
                                            >
                                                <el-icon :size="16"><Right /></el-icon>
                                            </Link>
                                            <button
                                                @click="openEditEmployee(emp)"
                                                class="p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                :title="$t('core_hr.title_edit_employee')"
                                            >
                                                <el-icon :size="16"><Edit /></el-icon>
                                            </button>
                                            <button
                                                v-if="emp.status !== 'TERMINATED'"
                                                @click="openOffboardModal(emp)"
                                                class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                                                :title="$t('core_hr.title_offboard')"
                                            >
                                                <el-icon :size="16"><CircleClose /></el-icon>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: SƠ ĐỒ CÂY CƠ CẤU TỔ CHỨC (ORG CHART TREE GRAPH) -->
            <div v-else-if="activeTab === 'org_tree'" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                    <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h2 class="text-lg font-bold text-slate-800 dark:text-white">{{ $t('core_hr.tree_title') }}</h2>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $t('core_hr.tree_subtitle') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold border border-indigo-200">
                                {{ $t('core_hr.legend_level_1') }}
                            </span>
                            <span class="text-xs px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 font-semibold border border-blue-200">
                                {{ $t('core_hr.legend_level_2') }}
                            </span>
                            <span class="text-xs px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-semibold border border-emerald-200">
                                {{ $t('core_hr.legend_level_3') }}
                            </span>
                        </div>
                    </div>

                    <!-- Tree Nodes Display -->
                    <div class="space-y-6">
                        <div
                            v-for="rootNode in orgTree"
                            :key="rootNode.id"
                            class="p-5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-700 space-y-4"
                        >
                            <!-- Khối cấp 1 Header -->
                            <div class="flex items-center justify-between bg-white dark:bg-slate-900 p-4 rounded-xl border-l-4 border-indigo-600 shadow-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400 flex items-center justify-center font-bold">
                                        K1
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h3 class="font-bold text-slate-900 dark:text-white text-base">{{ rootNode.name }}</h3>
                                            <el-tag type="primary" size="small" class="font-mono">{{ rootNode.code }}</el-tag>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-0.5">{{ rootNode.mission_function }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 text-xs">
                                    <div v-if="rootNode.manager" class="flex items-center gap-2">
                                        <span class="text-slate-400">{{ $t('core_hr.manager_label_1') }}</span>
                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ rootNode.manager.full_name }}</span>
                                    </div>
                                    <div class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 font-semibold text-slate-700 dark:text-slate-300">
                                        {{ $t('core_hr.node_count', { count: rootNode.employee_count }) }}
                                    </div>
                                </div>
                            </div>

                            <!-- Phòng ban cấp 2 (Children of root) -->
                            <div v-if="rootNode.children && rootNode.children.length > 0" class="pl-6 space-y-4 border-l-2 border-dashed border-indigo-200 dark:border-indigo-900/60 ml-4">
                                <div
                                    v-for="deptNode in rootNode.children"
                                    :key="deptNode.id"
                                    class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400 flex items-center justify-center font-bold text-xs">
                                                P2
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <h4 class="font-bold text-slate-800 dark:text-white text-sm">{{ deptNode.name }}</h4>
                                                    <span class="text-[10px] px-1.5 py-0.2 font-mono rounded bg-slate-100 dark:bg-slate-800 text-slate-600">{{ deptNode.code }}</span>
                                                    <span v-if="deptNode.branch_name" class="text-[10px] px-2 py-0.5 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 font-medium">
                                                        {{ deptNode.branch_name }}
                                                    </span>
                                                </div>
                                                <p class="text-[11px] text-slate-400 mt-0.5">{{ deptNode.mission_function }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 text-xs">
                                            <div v-if="deptNode.manager" class="flex items-center gap-1.5">
                                                <span class="text-slate-400">{{ $t('core_hr.manager_label_2') }}</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-300">{{ deptNode.manager.full_name }}</span>
                                            </div>
                                            <span class="px-2.5 py-0.5 rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 font-semibold text-[11px]">
                                                {{ $t('core_hr.node_count_short', { count: deptNode.employee_count }) }}
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Đội nhóm cấp 3 (Teams) -->
                                    <div v-if="deptNode.children && deptNode.children.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2 pl-4 border-l-2 border-slate-200 dark:border-slate-800">
                                        <div
                                            v-for="teamNode in deptNode.children"
                                            :key="teamNode.id"
                                            class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/80 dark:border-slate-700 flex items-center justify-between"
                                        >
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 flex items-center justify-center font-bold text-[10px]">
                                                    T3
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-xs text-slate-800 dark:text-slate-200">{{ teamNode.name }}</span>
                                                    <span class="text-[10px] text-slate-400 block font-mono">{{ teamNode.code }}</span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">{{ $t('core_hr.node_count', { count: teamNode.employee_count }) }}</span>
                                                <span v-if="teamNode.manager" class="text-[10px] text-slate-400 block">Lead: {{ teamNode.manager.full_name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: DANH SÁCH PHÒNG BAN DẠNG BẢNG & CRUD -->
            <div v-else-if="activeTab === 'departments'" class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white text-base">{{ $t('core_hr.dept_catalog_title') }}</h3>
                        <p class="text-xs text-slate-400">{{ $t('core_hr.dept_catalog_subtitle') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <el-button
                            size="default"
                            :icon="Clock"
                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Department', null, $t('core_hr.audit_all_departments'))"
                        >
                            {{ $t('core_hr.btn_audit_history') }}
                        </el-button>
                        <el-button
                            type="primary"
                            :icon="Plus"
                            @click="openCreateDepartmentModal"
                            class="!rounded-xl shadow-xs"
                        >
                            {{ $t('core_hr.btn_add_department') }}
                        </el-button>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">{{ $t('core_hr.col_dept_code') }}</th>
                                <th class="py-3 px-4">{{ $t('core_hr.col_dept_name') }}</th>
                                <th class="py-3 px-4">{{ $t('core_hr.col_level') }}</th>
                                <th class="py-3 px-4">{{ $t('core_hr.col_dept_head') }}</th>
                                <th class="py-3 px-4">{{ $t('common.branch') }}</th>
                                <th class="py-3 px-4">{{ $t('common.status') }}</th>
                                <th class="py-3 px-4 text-center">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <tr v-for="dept in departments" :key="dept.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="py-3.5 px-4 font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ dept.code }}</td>
                                <td class="py-3.5 px-4">
                                    <div class="font-medium text-slate-800 dark:text-white">{{ dept.name }}</div>
                                    <div v-if="dept.mission_function" class="text-[11px] text-slate-400 truncate max-w-xs mt-0.5">{{ dept.mission_function }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <el-tag :type="dept.level === 1 ? 'primary' : dept.level === 2 ? 'success' : 'warning'" size="small">
                                        {{ dept.level === 1 ? $t('core_hr.level_tag_1') : dept.level === 2 ? $t('core_hr.level_tag_2') : $t('core_hr.level_tag_3') }}
                                    </el-tag>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span v-if="dept.manager" class="font-medium text-slate-700 dark:text-slate-300">
                                        {{ dept.manager.full_name }}
                                        <span class="text-slate-400 font-mono text-[11px]">({{ dept.manager.user_code }})</span>
                                    </span>
                                    <span v-else class="text-slate-400 italic">{{ $t('core_hr.dept_no_manager') }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500">{{ dept.branch_name }}</td>
                                <td class="py-3.5 px-4">
                                    <el-tag :type="dept.status === 'ACTIVE' ? 'success' : 'info'" size="small">
                                        {{ dept.status === 'ACTIVE' ? $t('core_hr.dept_status_active') : $t('core_hr.dept_status_inactive') }}
                                    </el-tag>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <el-button
                                            size="small"
                                            :icon="Edit"
                                            circle
                                            @click="openEditDepartmentModal(dept)"
                                            :title="$t('core_hr.title_edit_info')"
                                        />
                                        <el-button
                                            size="small"
                                            type="primary"
                                            plain
                                            @click="openDepartmentVersionModal(dept)"
                                            :title="$t('core_hr.title_publish_version')"
                                            class="!text-[11px]"
                                        >
                                            {{ $t('core_hr.btn_new_version') }}
                                        </el-button>
                                        <el-button
                                            size="small"
                                            :icon="Clock"
                                            circle
                                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Department', dept.id, $t('core_hr.audit_department_title', { name: dept.name }))"
                                            :title="$t('core_hr.title_audit_history')"
                                        />
                                        <el-button
                                            size="small"
                                            type="danger"
                                            :icon="Delete"
                                            circle
                                            @click="deleteDepartment(dept)"
                                            :title="$t('core_hr.title_delete_dept')"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3.5: DANH MỤC CHỨC DANH & VỊ TRÍ (POSITIONS CRUD) -->
            <div v-else-if="activeTab === 'positions'" class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-slate-900 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white text-base">{{ $t('core_hr.pos_catalog_title') }}</h3>
                        <p class="text-xs text-slate-400">{{ $t('core_hr.pos_catalog_subtitle') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <el-button
                            size="default"
                            :icon="Clock"
                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Position', null, $t('core_hr.audit_all_positions'))"
                        >
                            {{ $t('core_hr.btn_audit_history') }}
                        </el-button>
                        <el-button
                            type="primary"
                            :icon="Plus"
                            @click="openCreatePositionModal"
                            class="!rounded-xl shadow-xs"
                        >
                            {{ $t('core_hr.btn_add_position') }}
                        </el-button>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">{{ $t('core_hr.col_position_code') }}</th>
                                <th class="py-3 px-4">{{ $t('core_hr.col_position_name') }}</th>
                                <th class="py-3 px-4">{{ $t('core_hr.col_level') }}</th>
                                <th class="py-3 px-4">{{ $t('common.department') }}</th>
                                <th class="py-3 px-4 text-right">{{ $t('core_hr.col_allowance') }}</th>
                                <th class="py-3 px-4 text-right">{{ $t('core_hr.col_appointment_bonus') }}</th>
                                <th class="py-3 px-4">{{ $t('core_hr.col_position_description') }}</th>
                                <th class="py-3 px-4 text-center">{{ $t('common.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <tr v-for="pos in positions" :key="pos.id" class="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                                <td class="py-3.5 px-4 font-mono font-semibold text-indigo-600 dark:text-indigo-400">{{ pos.code }}</td>
                                <td class="py-3.5 px-4 font-bold text-slate-800 dark:text-white">{{ pos.name }}</td>
                                <td class="py-3.5 px-4">
                                    <el-tag size="small" type="info" effect="plain" class="font-mono font-semibold">
                                        Level {{ pos.level }}
                                    </el-tag>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                                    {{ departments.find(d => d.id === pos.department_id)?.name ?? $t('core_hr.dept_all_company') }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ Number(pos.allowance_amount || 0).toLocaleString('vi-VN') }} {{ $t('common.currency_vnd') }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono text-slate-700 dark:text-slate-300">
                                    {{ Number(pos.bonus_one_time || 0).toLocaleString('vi-VN') }} {{ $t('common.currency_vnd') }}
                                </td>
                                <td class="py-3.5 px-4 text-slate-400 truncate max-w-xs">{{ pos.description || '—' }}</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <el-button
                                            size="small"
                                            :icon="Edit"
                                            circle
                                            @click="openEditPositionModal(pos)"
                                            :title="$t('core_hr.title_edit_position')"
                                        />
                                        <el-button
                                            size="small"
                                            :icon="Clock"
                                            circle
                                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Position', pos.id, $t('core_hr.audit_position_title', { name: pos.name }))"
                                            :title="$t('core_hr.title_audit_history')"
                                        />
                                        <el-button
                                            size="small"
                                            type="danger"
                                            :icon="Delete"
                                            circle
                                            @click="deletePosition(pos)"
                                            :title="$t('core_hr.title_delete_position')"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 4: CHI NHÁNH & ĐIỂM BÁN -->
            <div v-else-if="activeTab === 'branches'" class="space-y-4">
                <div class="flex justify-end">
                    <button
                        @click="isBranchModalOpen = true"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center space-x-1.5 transition"
                    >
                        <el-icon :size="14"><Plus /></el-icon>
                        <span>{{ $t('core_hr.btn_add_branch') }}</span>
                    </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div
                        v-for="branch in branches"
                        :key="branch.id"
                        class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs space-y-3"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ branch.code }}</span>
                            <el-tag v-if="branch.is_remote_or_island" type="danger" size="small">
                                {{ $t('core_hr.branch_remote_tag') }}
                            </el-tag>
                        </div>

                        <h3 class="text-base font-bold text-slate-800 dark:text-white">{{ branch.name }}</h3>
                        <p class="text-xs text-slate-400">{{ branch.address || $t('core_hr.branch_no_address') }}</p>

                        <div v-if="branch.is_remote_or_island" class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200/60 dark:border-rose-900/40 rounded-xl text-xs text-rose-800 dark:text-rose-300">
                            <strong>{{ $t('core_hr.branch_legal_label') }}</strong> {{ $t('core_hr.branch_legal_prefix') }} <strong>{{ $t('core_hr.branch_legal_month') }}</strong> {{ $t('core_hr.branch_legal_suffix') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 1: TIẾP NHẬN NHÂN SỰ MỚI (CREATE) -->
        <el-dialog
            v-model="isCreateEmployeeModalOpen"
            :title="$t('core_hr.dialog_onboard_title')"
            width="680px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitCreateEmployee" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_emp_code') }} <span class="text-rose-500">*</span>
                        </label>
                        <el-input v-model="employeeForm.user_code" :placeholder="$t('core_hr.ph_emp_code')" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_attendance_enroll') }}
                        </label>
                        <el-input v-model="employeeForm.attendance_code" :placeholder="$t('core_hr.ph_attendance_enroll')" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_full_name') }} <span class="text-rose-500">*</span>
                        </label>
                        <el-input v-model="employeeForm.full_name" :placeholder="$t('core_hr.ph_full_name')" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_gender') }} <span class="text-rose-500">*</span>
                        </label>
                        <el-radio-group v-model="employeeForm.gender" class="w-full">
                            <el-radio-button value="MALE">{{ $t('core_hr.gender_male') }}</el-radio-button>
                            <el-radio-button value="FEMALE">{{ $t('core_hr.gender_female') }}</el-radio-button>
                            <el-radio-button value="OTHER">{{ $t('core_hr.gender_other') }}</el-radio-button>
                        </el-radio-group>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_birth_date') }} <span class="text-rose-500">*</span>
                        </label>
                        <el-date-picker v-model="employeeForm.birth_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_citizen_id') }}
                        </label>
                        <el-input v-model="employeeForm.citizen_id" :placeholder="$t('core_hr.ph_citizen_id')" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_email') }} <span class="text-rose-500">*</span></label>
                        <el-input v-model="employeeForm.email" placeholder="binh.hoang@smarthr.vn" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_phone') }}</label>
                        <el-input v-model="employeeForm.phone" placeholder="0987654321" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_hometown') }}</label>
                        <el-input v-model="employeeForm.hometown" :placeholder="$t('core_hr.ph_hometown')" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_permanent_address') }}</label>
                        <el-input v-model="employeeForm.permanent_address" :placeholder="$t('core_hr.ph_permanent_address')" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_temporary_address') }}</label>
                        <el-input v-model="employeeForm.temporary_address" :placeholder="$t('core_hr.ph_temporary_address')" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_join_date') }} <span class="text-rose-500">*</span></label>
                        <el-date-picker v-model="employeeForm.join_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_probation_end') }}</label>
                        <el-date-picker v-model="employeeForm.probation_end_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('common.status') }}</label>
                        <el-select v-model="employeeForm.status" class="w-full">
                            <el-option :label="$t('core_hr.status_official')" value="OFFICIAL" />
                            <el-option :label="$t('core_hr.status_probation')" value="PROBATION" />
                        </el-select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('common.department') }} <span class="text-rose-500">*</span></label>
                        <el-select v-model="employeeForm.department_id" class="w-full">
                            <el-option v-for="d in departments" :key="d.id" :label="d.name" :value="d.id" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_position') }} <span class="text-rose-500">*</span></label>
                        <el-select v-model="employeeForm.position_id" class="w-full">
                            <el-option v-for="p in positions" :key="p.id" :label="p.name" :value="p.id" />
                        </el-select>
                    </div>
                </div>

                <!-- Thai sản thông minh: Chỉ hiển thị khi là Nữ -->
                <div v-if="employeeForm.gender === 'FEMALE'" class="p-4 bg-pink-50/60 dark:bg-pink-950/30 rounded-xl border border-pink-200 dark:border-pink-900/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-pink-800 dark:text-pink-300 flex items-center gap-1.5">
                            <el-icon :size="14"><Female /></el-icon>
                            {{ $t('core_hr.maternity_section_create') }}
                        </span>
                        <el-switch v-model="employeeForm.is_pregnant" />
                    </div>

                    <div v-if="employeeForm.is_pregnant" class="grid grid-cols-2 gap-3 pt-2 border-t border-pink-200/60">
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">{{ $t('core_hr.label_pregnancy_weeks_create') }}</label>
                            <el-input-number v-model="employeeForm.pregnancy_weeks_at_start" :min="1" :max="42" class="!w-full" />
                        </div>
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">{{ $t('core_hr.label_due_date') }}</label>
                            <el-date-picker v-model="employeeForm.expected_due_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isCreateEmployeeModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="employeeForm.processing">
                        {{ $t('core_hr.btn_onboard_confirm') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 2: SỬA HỒ SƠ NHÂN SỰ (EDIT) -->
        <el-dialog
            v-model="isEditModalOpen"
            :title="$t('core_hr.dialog_edit_title')"
            width="680px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitEditEmployee" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_full_name') }} *</label>
                        <el-input v-model="editForm.full_name" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_gender') }}</label>
                        <el-radio-group v-model="editForm.gender" class="w-full">
                            <el-radio-button value="MALE">{{ $t('core_hr.gender_male') }}</el-radio-button>
                            <el-radio-button value="FEMALE">{{ $t('core_hr.gender_female') }}</el-radio-button>
                            <el-radio-button value="OTHER">{{ $t('core_hr.gender_other') }}</el-radio-button>
                        </el-radio-group>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_birth_date') }} *</label>
                        <el-date-picker v-model="editForm.birth_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_attendance_code') }}</label>
                        <el-input v-model="editForm.attendance_code" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_citizen_short') }}</label>
                        <el-input v-model="editForm.citizen_id" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_email') }} *</label>
                        <el-input v-model="editForm.email" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_phone') }}</label>
                        <el-input v-model="editForm.phone" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_hometown') }}</label>
                        <el-input v-model="editForm.hometown" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_permanent_address') }}</label>
                        <el-input v-model="editForm.permanent_address" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_temporary_address') }}</label>
                        <el-input v-model="editForm.temporary_address" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_join_date') }} *</label>
                        <el-date-picker v-model="editForm.join_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_probation_end') }}</label>
                        <el-date-picker v-model="editForm.probation_end_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('common.status') }}</label>
                        <el-select v-model="editForm.status" class="w-full">
                            <el-option :label="$t('core_hr.status_official')" value="OFFICIAL" />
                            <el-option :label="$t('core_hr.status_probation')" value="PROBATION" />
                            <el-option :label="$t('core_hr.status_on_leave')" value="ON_LEAVE" />
                            <el-option :label="$t('core_hr.status_terminated')" value="TERMINATED" />
                        </el-select>
                    </div>
                </div>

                <!-- Thai sản (chỉ hiện khi là Nữ) -->
                <div v-if="editForm.gender === 'FEMALE'" class="p-4 bg-pink-50/60 dark:bg-pink-950/30 rounded-xl border border-pink-200 dark:border-pink-900/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-pink-800 dark:text-pink-300">{{ $t('core_hr.maternity_section_edit') }}</span>
                        <el-switch v-model="editForm.is_pregnant" />
                    </div>

                    <div v-if="editForm.is_pregnant" class="grid grid-cols-2 gap-3 pt-2">
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">{{ $t('core_hr.label_pregnancy_weeks') }}</label>
                            <el-input-number v-model="editForm.pregnancy_weeks_at_start" :min="1" :max="42" class="!w-full" />
                        </div>
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">{{ $t('core_hr.label_due_date') }}</label>
                            <el-date-picker v-model="editForm.expected_due_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isEditModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="editForm.processing">
                        {{ $t('core_hr.btn_save_profile') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 3: THÔI VIỆC (OFFBOARDING) -->
        <el-dialog
            v-model="isOffboardModalOpen"
            :title="$t('core_hr.dialog_offboard_title')"
            width="520px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitOffboard" class="space-y-4" v-if="offboardingEmployee">
                <div class="p-3.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-xl text-xs text-rose-800 dark:text-rose-300">
                    {{ $t('core_hr.offboard_intro') }} <strong>{{ offboardingEmployee.full_name }}</strong> ({{ offboardingEmployee.user_code }}).
                    <div class="mt-1 font-medium">{{ $t('core_hr.offboard_seniority_label') }} <strong>{{ offboardingEmployee.seniority_text }}</strong>. {{ $t('core_hr.offboard_event_note') }} <code>OFFBOARDING</code>.</div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_resigned_date') }} <span class="text-rose-500">*</span></label>
                    <el-date-picker v-model="offboardForm.resigned_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_resignation_reason') }} <span class="text-rose-500">*</span></label>
                    <el-input v-model="offboardForm.resignation_reason" :placeholder="$t('core_hr.ph_resignation_reason')" required />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_decision_number') }}</label>
                        <el-input v-model="offboardForm.decision_number" :placeholder="$t('core_hr.ph_decision_number')" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_decision_date') }}</label>
                        <el-date-picker v-model="offboardForm.decision_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_handover_notes') }}</label>
                    <el-input v-model="offboardForm.notes" type="textarea" :rows="2" :placeholder="$t('core_hr.ph_handover_notes')" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isOffboardModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="danger" native-type="submit" :loading="offboardForm.processing">
                        {{ $t('core_hr.btn_confirm_offboard') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 4: Ban hành Phiên bản Phòng ban Mới -->
        <el-dialog
            v-model="isDepartmentModalOpen"
            :title="$t('core_hr.dialog_version_title')"
            width="500px"
        >
            <form @submit.prevent="submitDepartmentVersion" class="space-y-4">
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-xs text-amber-800 dark:text-amber-300">
                    {{ $t('core_hr.version_note_prefix') }} <code>{{ $t('core_hr.version_note_code') }}</code> {{ $t('core_hr.version_note_suffix') }}
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_dept_name') }} <span class="text-rose-500">*</span></label>
                    <el-input v-model="departmentForm.name" required />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_effective_date') }} <span class="text-rose-500">*</span></label>
                    <el-date-picker v-model="departmentForm.effective_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_version_functions') }}</label>
                    <el-input v-model="departmentForm.functions" type="textarea" :rows="3" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isDepartmentModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="departmentForm.processing">
                        {{ $t('core_hr.btn_publish_version') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 5: Khởi tạo Chi nhánh mới -->
        <el-dialog
            v-model="isBranchModalOpen"
            :title="$t('core_hr.dialog_branch_title')"
            width="500px"
        >
            <form @submit.prevent="submitBranch" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_branch_code') }} <span class="text-rose-500">*</span></label>
                        <el-input v-model="branchForm.code" :placeholder="$t('core_hr.ph_branch_code')" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_branch_name') }} <span class="text-rose-500">*</span></label>
                        <el-input v-model="branchForm.name" :placeholder="$t('core_hr.ph_branch_name')" required />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_address') }}</label>
                    <el-input v-model="branchForm.address" :placeholder="$t('core_hr.ph_branch_address')" />
                </div>

                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                    <el-checkbox v-model="branchForm.is_remote_or_island">
                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $t('core_hr.remote_island_check') }}</span>
                    </el-checkbox>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isBranchModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="branchForm.processing">
                        {{ $t('core_hr.btn_create_branch') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 6: Thêm Phòng ban mới -->
        <el-dialog
            v-model="isCreateDeptModalOpen"
            :title="$t('core_hr.dialog_create_dept_title')"
            width="580px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitCreateDepartment" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.col_dept_code') }} *</label>
                        <el-input v-model="createDeptForm.code" :placeholder="$t('core_hr.ph_dept_code')" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_dept_name') }} *</label>
                        <el-input v-model="createDeptForm.name" :placeholder="$t('core_hr.ph_dept_name')" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_dept_level') }} *</label>
                        <el-select v-model="createDeptForm.level" class="w-full">
                            <el-option :value="1" :label="$t('core_hr.level_option_1')" />
                            <el-option :value="2" :label="$t('core_hr.level_option_2')" />
                            <el-option :value="3" :label="$t('core_hr.level_option_3')" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('common.branch') }} *</label>
                        <el-select v-model="createDeptForm.branch_id" class="w-full">
                            <el-option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                                :label="branch.name"
                            />
                        </el-select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_parent_unit') }}</label>
                        <el-select v-model="createDeptForm.parent_id" clearable :placeholder="$t('core_hr.ph_parent_none')" class="w-full">
                            <el-option
                                v-for="d in departments"
                                :key="d.id"
                                :value="d.id"
                                :label="d.name + ' (' + d.code + ')'"
                            />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.col_dept_head') }}</label>
                        <el-select v-model="createDeptForm.manager_id" clearable filterable :placeholder="$t('core_hr.ph_select_manager')" class="w-full">
                            <el-option
                                v-for="emp in allEmployeesShort"
                                :key="emp.id"
                                :value="emp.id"
                                :label="emp.full_name + ' (' + emp.user_code + ')'"
                            />
                        </el-select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_mission_function') }}</label>
                    <el-input v-model="createDeptForm.mission_function" type="textarea" :rows="2" :placeholder="$t('core_hr.ph_mission_function')" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isCreateDeptModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="createDeptForm.processing">
                        {{ $t('core_hr.btn_create_dept') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 7: Sửa thông tin Phòng ban -->
        <el-dialog
            v-model="isEditDeptModalOpen"
            :title="$t('core_hr.dialog_edit_dept_title')"
            width="580px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitEditDepartment" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.col_dept_code') }} *</label>
                        <el-input v-model="editDeptForm.code" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_dept_name') }} *</label>
                        <el-input v-model="editDeptForm.name" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_dept_level') }} *</label>
                        <el-select v-model="editDeptForm.level" class="w-full">
                            <el-option :value="1" :label="$t('core_hr.level_option_1')" />
                            <el-option :value="2" :label="$t('core_hr.level_option_2')" />
                            <el-option :value="3" :label="$t('core_hr.level_option_3')" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('common.branch') }} *</label>
                        <el-select v-model="editDeptForm.branch_id" class="w-full">
                            <el-option
                                v-for="branch in branches"
                                :key="branch.id"
                                :value="branch.id"
                                :label="branch.name"
                            />
                        </el-select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_parent_unit_short') }}</label>
                        <el-select v-model="editDeptForm.parent_id" clearable :placeholder="$t('core_hr.ph_parent_root')" class="w-full">
                            <el-option
                                v-for="d in departments.filter(d => d.id !== editingDeptId)"
                                :key="d.id"
                                :value="d.id"
                                :label="d.name + ' (' + d.code + ')'"
                            />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.col_dept_head') }}</label>
                        <el-select v-model="editDeptForm.manager_id" clearable filterable :placeholder="$t('core_hr.ph_select_manager')" class="w-full">
                            <el-option
                                v-for="emp in allEmployeesShort"
                                :key="emp.id"
                                :value="emp.id"
                                :label="emp.full_name + ' (' + emp.user_code + ')'"
                            />
                        </el-select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_operating_status') }}</label>
                        <el-select v-model="editDeptForm.status" class="w-full">
                            <el-option value="ACTIVE" :label="$t('core_hr.dept_status_option_active')" />
                            <el-option value="INACTIVE" :label="$t('core_hr.dept_status_option_inactive')" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_display_order') }}</label>
                        <el-input-number v-model="editDeptForm.order" :min="0" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_mission_function') }}</label>
                    <el-input v-model="editDeptForm.mission_function" type="textarea" :rows="2" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isEditDeptModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="editDeptForm.processing">
                        {{ $t('core_hr.btn_save_changes') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 8: Thêm Chức danh mới -->
        <el-dialog
            v-model="isCreatePosModalOpen"
            :title="$t('core_hr.dialog_create_pos_title')"
            width="540px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitCreatePosition" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_pos_code') }} *</label>
                        <el-input v-model="createPosForm.code" :placeholder="$t('core_hr.ph_pos_code')" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_pos_name') }} *</label>
                        <el-input v-model="createPosForm.name" :placeholder="$t('core_hr.ph_pos_name')" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_pos_level') }} *</label>
                        <el-input-number v-model="createPosForm.level" :min="1" :max="10" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_applicable_dept') }}</label>
                        <el-select v-model="createPosForm.department_id" clearable :placeholder="$t('core_hr.dept_all_company')" class="w-full">
                            <el-option
                                v-for="d in departments"
                                :key="d.id"
                                :value="d.id"
                                :label="d.name"
                            />
                        </el-select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_position_allowance') }}</label>
                        <el-input-number v-model="createPosForm.allowance_amount" :min="0" :step="100000" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_appointment_bonus') }}</label>
                        <el-input-number v-model="createPosForm.bonus_one_time" :min="0" :step="500000" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_pos_description') }}</label>
                    <el-input v-model="createPosForm.description" type="textarea" :rows="2" :placeholder="$t('core_hr.ph_pos_description')" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isCreatePosModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="createPosForm.processing">
                        {{ $t('core_hr.btn_create_position') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 9: Sửa Chức danh -->
        <el-dialog
            v-model="isEditPosModalOpen"
            :title="$t('core_hr.dialog_edit_pos_title')"
            width="540px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitEditPosition" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_pos_code') }} *</label>
                        <el-input v-model="editPosForm.code" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_pos_name') }} *</label>
                        <el-input v-model="editPosForm.name" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_pos_level') }} *</label>
                        <el-input-number v-model="editPosForm.level" :min="1" :max="10" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_applicable_dept') }}</label>
                        <el-select v-model="editPosForm.department_id" clearable :placeholder="$t('core_hr.dept_all_company')" class="w-full">
                            <el-option
                                v-for="d in departments"
                                :key="d.id"
                                :value="d.id"
                                :label="d.name"
                            />
                        </el-select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_position_allowance') }}</label>
                        <el-input-number v-model="editPosForm.allowance_amount" :min="0" :step="100000" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_appointment_bonus') }}</label>
                        <el-input-number v-model="editPosForm.bonus_one_time" :min="0" :step="500000" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">{{ $t('core_hr.label_job_description') }}</label>
                    <el-input v-model="editPosForm.description" type="textarea" :rows="2" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isEditPosModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button type="primary" native-type="submit" :loading="editPosForm.processing">
                        {{ $t('core_hr.btn_save_changes') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- COMPONENT: Lịch sử Kiểm toán & Biến động Dữ liệu -->
        <AuditHistoryDrawer
            v-model="isAuditDrawerOpen"
            :auditable-type="auditType"
            :auditable-id="auditId"
            :title="auditTitle"
        />
    </AppLayout>
</template>
