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
            ElMessage.success('Thêm phòng ban mới thành công!');
        },
        onError: () => {
            ElMessage.error('Vui lòng kiểm tra lại thông tin phòng ban!');
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
            ElMessage.success('Cập nhật thông tin phòng ban thành công!');
        },
        onError: () => {
            ElMessage.error('Không thể cập nhật phòng ban!');
        },
    });
};

const deleteDepartment = (dept: any) => {
    ElMessageBox.confirm(
        `Bạn có chắc chắn muốn xóa phòng ban "${dept.name}" (${dept.code})? Hành động này sẽ được ghi vào nhật ký kiểm toán và chỉ thành công nếu phòng ban không có nhân sự hoặc đơn vị trực thuộc.`,
        'Xác nhận xóa phòng ban',
        {
            confirmButtonText: 'Xóa phòng ban',
            cancelButtonText: 'Hủy bỏ',
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    ).then(() => {
        router.delete(`/core-hr/departments/${dept.id}`, {
            onSuccess: () => {
                ElMessage.success('Xóa phòng ban thành công!');
            },
            onError: (errors: any) => {
                ElMessage.error(errors.message || 'Không thể xóa phòng ban đang có nhân viên hoặc phòng ban con.');
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
            ElMessage.success('Thêm chức danh mới thành công!');
        },
        onError: () => {
            ElMessage.error('Vui lòng kiểm tra lại thông tin chức danh!');
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
            ElMessage.success('Cập nhật chức vụ thành công!');
        },
        onError: () => {
            ElMessage.error('Không thể cập nhật chức vụ!');
        },
    });
};

const deletePosition = (pos: any) => {
    ElMessageBox.confirm(
        `Bạn có chắc chắn muốn xóa chức danh "${pos.name}" (${pos.code})? Hành động này sẽ được ghi vào nhật ký kiểm toán và chỉ thành công nếu chức danh không có nhân sự đang đảm nhiệm.`,
        'Xác nhận xóa chức danh',
        {
            confirmButtonText: 'Xóa chức danh',
            cancelButtonText: 'Hủy bỏ',
            type: 'warning',
            confirmButtonClass: 'el-button--danger',
        }
    ).then(() => {
        router.delete(`/core-hr/positions/${pos.id}`, {
            onSuccess: () => {
                ElMessage.success('Xóa chức danh thành công!');
            },
            onError: (errors: any) => {
                ElMessage.error(errors.message || 'Không thể xóa chức danh đang có nhân sự công tác.');
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
            ElMessage.success('Tiếp nhận nhân sự mới và kích hoạt Timeline Onboarding thành công!');
        },
        onError: () => {
            ElMessage.error('Vui lòng kiểm tra lại thông tin nhập liệu!');
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
            ElMessage.success('Cập nhật hồ sơ nhân sự thành công!');
        },
        onError: () => {
            ElMessage.error('Không thể cập nhật hồ sơ!');
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
            ElMessage.success('Hoàn tất quy trình thôi việc và cập nhật timeline sự nghiệp!');
        },
        onError: () => {
            ElMessage.error('Không thể hoàn tất thủ tục thôi việc!');
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
            ElMessage.success('Ban hành phiên bản phòng ban mới (Effective Dating) thành công!');
        },
        onError: () => {
            ElMessage.error('Không thể cập nhật phiên bản phòng ban!');
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
            ElMessage.success('Khởi tạo chi nhánh mới thành công!');
        },
        onError: () => {
            ElMessage.error('Vui lòng kiểm tra lại thông tin chi nhánh!');
        },
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Quản trị Hồ sơ & Tổ chức (CoreHR) - Smart-HR" />

        <div class="space-y-6 pb-12">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">Hồ sơ & Cơ cấu Tổ chức (CoreHR)</h1>
                    <p class="text-xs text-slate-400 mt-1">
                        Quản lý sơ đồ tổ chức phân cấp đa tầng, hồ sơ nhân sự toàn diện, thâm niên và bảo hộ luật định Điều 137 BLLĐ 2019.
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
                        <span>Nhân sự ({{ employees.total }})</span>
                    </button>
                    <button
                        @click="activeTab = 'org_tree'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'org_tree' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><Share /></el-icon>
                        <span>Sơ đồ Cây Tổ chức</span>
                    </button>
                    <button
                        @click="activeTab = 'departments'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'departments' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><OfficeBuilding /></el-icon>
                        <span>Phòng ban ({{ departments.length }})</span>
                    </button>
                    <button
                        @click="activeTab = 'positions'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'positions' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><Suitcase /></el-icon>
                        <span>Chức danh ({{ positions.length }})</span>
                    </button>
                    <button
                        @click="activeTab = 'branches'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center space-x-1.5"
                        :class="activeTab === 'branches' ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-white shadow-xs' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                    >
                        <el-icon :size="14"><OfficeBuilding /></el-icon>
                        <span>Chi nhánh ({{ branches.length }})</span>
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
                                placeholder="Tìm tên, mã, CCCD, SĐT..."
                                :prefix-icon="Search"
                                clearable
                            />
                        </div>

                        <select
                            v-model="selectedDepartment"
                            @change="applyFilters"
                            class="text-xs px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Tất cả Phòng ban</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>

                        <select
                            v-model="selectedStatus"
                            @change="applyFilters"
                            class="text-xs px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Tất cả Trạng thái</option>
                            <option value="PROBATION">Thử việc</option>
                            <option value="OFFICIAL">Chính thức</option>
                            <option value="ON_LEAVE">Nghỉ thai sản/Nghỉ phép</option>
                            <option value="TERMINATED">Đã thôi việc</option>
                        </select>

                        <button
                            @click="applyFilters"
                            class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs transition"
                        >
                            Lọc
                        </button>
                        <button
                            @click="resetFilters"
                            class="p-2 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-700 rounded-xl text-xs transition"
                            title="Xóa bộ lọc"
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
                            <span>Quản lý Con nhỏ & Phúc lợi</span>
                        </Link>
                        <button
                            @click="isCreateEmployeeModalOpen = true"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center space-x-1.5 transition"
                        >
                            <el-icon :size="14"><Plus /></el-icon>
                            <span>Tiếp nhận Nhân sự</span>
                        </button>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                    <th class="py-3 px-4">Nhân sự</th>
                                    <th class="py-3 px-4">Đơn vị & Chức vụ</th>
                                    <th class="py-3 px-4">Con nhỏ & Phụ thuộc</th>
                                    <th class="py-3 px-4">Bảo hộ Thai sản (Đ.137)</th>
                                    <th class="py-3 px-4">Trạng thái & Thâm niên</th>
                                    <th class="py-3 px-4 text-center">Thao tác</th>
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
                                                    <span v-if="emp.citizen_id">• CCCD: {{ emp.citizen_id }}</span>
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
                                                <span>{{ emp.children_count }} con nhỏ</span>
                                            </Link>
                                            <span v-else class="text-slate-400 text-[11px]">Chưa khai báo</span>

                                            <span v-if="emp.tax_dependents_count > 0" class="text-[10px] text-blue-600 dark:text-blue-400 font-medium">
                                                Giảm trừ: {{ emp.tax_dependents_count }} người
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
                                                Con &lt; 12T (Đ.137: 60p có lương)
                                            </span>

                                            <span
                                                v-if="emp.is_pregnant"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800 w-fit"
                                            >
                                                Mang thai {{ emp.pregnancy_weeks_at_start ? `(~${emp.pregnancy_weeks_at_start} tuần)` : '' }}
                                            </span>

                                            <span
                                                v-if="emp.is_pregnant_restricted"
                                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800 w-fit"
                                            >
                                                Cấm làm đêm & OT (Hải đảo)
                                            </span>

                                            <span v-if="!emp.is_child_under_12m && !emp.is_pregnant" class="text-slate-400 text-[11px]">
                                                Bình thường
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
                                                    Chính thức
                                                </el-tag>
                                                <el-tag
                                                    v-else-if="emp.status === 'PROBATION'"
                                                    type="warning"
                                                    size="small"
                                                    class="!rounded-md"
                                                >
                                                    Thử việc
                                                </el-tag>
                                                <el-tag
                                                    v-else-if="emp.status === 'TERMINATED'"
                                                    type="danger"
                                                    size="small"
                                                    class="!rounded-md"
                                                >
                                                    Đã thôi việc
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
                                                Thâm niên: {{ emp.seniority_text }}
                                            </span>
                                            <span v-if="emp.status === 'TERMINATED' && emp.resigned_date" class="text-[10px] text-rose-500">
                                                Nghỉ: {{ emp.resigned_date }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Cột 6: Thao tác -->
                                    <td class="py-3.5 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <Link
                                                :href="`/core-hr/employees/${emp.id}`"
                                                class="p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                title="Xem hồ sơ & Career Timeline"
                                            >
                                                <el-icon :size="16"><Right /></el-icon>
                                            </Link>
                                            <button
                                                @click="openEditEmployee(emp)"
                                                class="p-1.5 text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                                title="Sửa hồ sơ nhân sự"
                                            >
                                                <el-icon :size="16"><Edit /></el-icon>
                                            </button>
                                            <button
                                                v-if="emp.status !== 'TERMINATED'"
                                                @click="openOffboardModal(emp)"
                                                class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/40 transition"
                                                title="Thực hiện thôi việc (Offboarding)"
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
                            <h2 class="text-lg font-bold text-slate-800 dark:text-white">Sơ đồ Cây Cơ cấu Tổ chức Phân cấp (Tree Hierarchy)</h2>
                            <p class="text-xs text-slate-400 mt-0.5">Mô hình phân cấp: Khối chức năng (Level 1) &rarr; Phòng ban (Level 2) &rarr; Đội nhóm / Team (Level 3)</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 font-semibold border border-indigo-200">
                                Khối cấp 1
                            </span>
                            <span class="text-xs px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 font-semibold border border-blue-200">
                                Phòng ban cấp 2
                            </span>
                            <span class="text-xs px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 font-semibold border border-emerald-200">
                                Đội nhóm / Team cấp 3
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
                                        <span class="text-slate-400">Trưởng khối:</span>
                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ rootNode.manager.full_name }}</span>
                                    </div>
                                    <div class="px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800 font-semibold text-slate-700 dark:text-slate-300">
                                        {{ rootNode.employee_count }} nhân sự
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
                                                <span class="text-slate-400">Trưởng phòng:</span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-300">{{ deptNode.manager.full_name }}</span>
                                            </div>
                                            <span class="px-2.5 py-0.5 rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300 font-semibold text-[11px]">
                                                {{ deptNode.employee_count }} NV
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
                                                <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">{{ teamNode.employee_count }} nhân sự</span>
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
                        <h3 class="font-bold text-slate-800 dark:text-white text-base">Danh mục Đơn vị & Phòng ban</h3>
                        <p class="text-xs text-slate-400">Quản lý cơ cấu phòng ban, gán trưởng đơn vị và bảo toàn lịch sử Effective Dating.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <el-button
                            size="default"
                            :icon="Clock"
                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Department', null, 'Nhật ký Kiểm toán Toàn bộ Phòng ban')"
                        >
                            Lịch sử Kiểm toán
                        </el-button>
                        <el-button
                            type="primary"
                            :icon="Plus"
                            @click="openCreateDepartmentModal"
                            class="!rounded-xl shadow-xs"
                        >
                            Thêm Phòng ban
                        </el-button>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Mã đơn vị</th>
                                <th class="py-3 px-4">Tên đơn vị</th>
                                <th class="py-3 px-4">Cấp bậc</th>
                                <th class="py-3 px-4">Trưởng đơn vị</th>
                                <th class="py-3 px-4">Chi nhánh</th>
                                <th class="py-3 px-4">Trạng thái</th>
                                <th class="py-3 px-4 text-center">Thao tác</th>
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
                                        {{ dept.level === 1 ? 'Khối (L1)' : dept.level === 2 ? 'Phòng ban (L2)' : 'Team (L3)' }}
                                    </el-tag>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span v-if="dept.manager" class="font-medium text-slate-700 dark:text-slate-300">
                                        {{ dept.manager.full_name }}
                                        <span class="text-slate-400 font-mono text-[11px]">({{ dept.manager.user_code }})</span>
                                    </span>
                                    <span v-else class="text-slate-400 italic">Chưa bổ nhiệm</span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500">{{ dept.branch_name }}</td>
                                <td class="py-3.5 px-4">
                                    <el-tag :type="dept.status === 'ACTIVE' ? 'success' : 'info'" size="small">
                                        {{ dept.status === 'ACTIVE' ? 'Hoạt động' : 'Tạm dừng' }}
                                    </el-tag>
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <el-button
                                            size="small"
                                            :icon="Edit"
                                            circle
                                            @click="openEditDepartmentModal(dept)"
                                            title="Sửa thông tin"
                                        />
                                        <el-button
                                            size="small"
                                            type="primary"
                                            plain
                                            @click="openDepartmentVersionModal(dept)"
                                            title="Ban hành phiên bản Effective Dating"
                                            class="!text-[11px]"
                                        >
                                            Phiên bản mới
                                        </el-button>
                                        <el-button
                                            size="small"
                                            :icon="Clock"
                                            circle
                                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Department', dept.id, 'Lịch sử kiểm toán: ' + dept.name)"
                                            title="Lịch sử kiểm toán"
                                        />
                                        <el-button
                                            size="small"
                                            type="danger"
                                            :icon="Delete"
                                            circle
                                            @click="deleteDepartment(dept)"
                                            title="Xóa phòng ban"
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
                        <h3 class="font-bold text-slate-800 dark:text-white text-base">Danh mục Vị trí & Chức danh Công việc</h3>
                        <p class="text-xs text-slate-400">Cấu hình cấp bậc chức danh, mức phụ cấp trách nhiệm và đãi ngộ bổ nhiệm.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <el-button
                            size="default"
                            :icon="Clock"
                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Position', null, 'Nhật ký Kiểm toán Toàn bộ Chức danh')"
                        >
                            Lịch sử Kiểm toán
                        </el-button>
                        <el-button
                            type="primary"
                            :icon="Plus"
                            @click="openCreatePositionModal"
                            class="!rounded-xl shadow-xs"
                        >
                            Thêm Chức danh
                        </el-button>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Mã vị trí</th>
                                <th class="py-3 px-4">Tên chức danh</th>
                                <th class="py-3 px-4">Cấp bậc</th>
                                <th class="py-3 px-4">Phòng ban</th>
                                <th class="py-3 px-4 text-right">Phụ cấp chức vụ</th>
                                <th class="py-3 px-4 text-right">Thưởng bổ nhiệm</th>
                                <th class="py-3 px-4">Mô tả công việc</th>
                                <th class="py-3 px-4 text-center">Thao tác</th>
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
                                    {{ departments.find(d => d.id === pos.department_id)?.name ?? 'Áp dụng toàn công ty' }}
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    {{ Number(pos.allowance_amount || 0).toLocaleString('vi-VN') }} đ
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono text-slate-700 dark:text-slate-300">
                                    {{ Number(pos.bonus_one_time || 0).toLocaleString('vi-VN') }} đ
                                </td>
                                <td class="py-3.5 px-4 text-slate-400 truncate max-w-xs">{{ pos.description || '—' }}</td>
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <el-button
                                            size="small"
                                            :icon="Edit"
                                            circle
                                            @click="openEditPositionModal(pos)"
                                            title="Chỉnh sửa chức danh"
                                        />
                                        <el-button
                                            size="small"
                                            :icon="Clock"
                                            circle
                                            @click="openAuditDrawer('App\\Modules\\CoreHR\\Models\\Position', pos.id, 'Lịch sử chức danh: ' + pos.name)"
                                            title="Lịch sử kiểm toán"
                                        />
                                        <el-button
                                            size="small"
                                            type="danger"
                                            :icon="Delete"
                                            circle
                                            @click="deletePosition(pos)"
                                            title="Xóa chức danh"
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
                        <span>Thêm Chi nhánh</span>
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
                                Vùng sâu/Hải đảo (Đ.137)
                            </el-tag>
                        </div>

                        <h3 class="text-base font-bold text-slate-800 dark:text-white">{{ branch.name }}</h3>
                        <p class="text-xs text-slate-400">{{ branch.address || 'Chưa cập nhật địa chỉ' }}</p>

                        <div v-if="branch.is_remote_or_island" class="p-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200/60 dark:border-rose-900/40 rounded-xl text-xs text-rose-800 dark:text-rose-300">
                            <strong>Đặc thù pháp lý:</strong> Kích hoạt cấm làm thêm giờ và ca đêm đối với lao động nữ mang thai từ <strong>tháng thứ 6</strong> theo Điều 137.1.a BLLĐ 2019.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 1: TIẾP NHẬN NHÂN SỰ MỚI (CREATE) -->
        <el-dialog
            v-model="isCreateEmployeeModalOpen"
            title="Tiếp nhận Nhân sự Mới & Kích hoạt Onboarding"
            width="680px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitCreateEmployee" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Mã Nhân viên <span class="text-rose-500">*</span>
                        </label>
                        <el-input v-model="employeeForm.user_code" placeholder="VD: EMP007" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Mã Chấm công Vân tay (Enroll ID)
                        </label>
                        <el-input v-model="employeeForm.attendance_code" placeholder="VD: 1007" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Họ và Tên <span class="text-rose-500">*</span>
                        </label>
                        <el-input v-model="employeeForm.full_name" placeholder="VD: Hoàng Văn Bình" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Giới tính <span class="text-rose-500">*</span>
                        </label>
                        <el-radio-group v-model="employeeForm.gender" class="w-full">
                            <el-radio-button value="MALE">Nam</el-radio-button>
                            <el-radio-button value="FEMALE">Nữ</el-radio-button>
                            <el-radio-button value="OTHER">Khác</el-radio-button>
                        </el-radio-group>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Ngày sinh <span class="text-rose-500">*</span>
                        </label>
                        <el-date-picker v-model="employeeForm.birth_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Số CCCD (12 số)
                        </label>
                        <el-input v-model="employeeForm.citizen_id" placeholder="VD: 001099001234" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email <span class="text-rose-500">*</span></label>
                        <el-input v-model="employeeForm.email" placeholder="binh.hoang@smarthr.vn" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Số điện thoại</label>
                        <el-input v-model="employeeForm.phone" placeholder="0987654321" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Quê quán</label>
                        <el-input v-model="employeeForm.hometown" placeholder="VD: Hà Tĩnh" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Thường trú</label>
                        <el-input v-model="employeeForm.permanent_address" placeholder="Địa chỉ hộ khẩu..." />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tạm trú</label>
                        <el-input v-model="employeeForm.temporary_address" placeholder="Nơi ở hiện tại..." />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngày vào làm <span class="text-rose-500">*</span></label>
                        <el-date-picker v-model="employeeForm.join_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Hạn thử việc</label>
                        <el-date-picker v-model="employeeForm.probation_end_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Trạng thái</label>
                        <el-select v-model="employeeForm.status" class="w-full">
                            <el-option label="Chính thức" value="OFFICIAL" />
                            <el-option label="Thử việc" value="PROBATION" />
                        </el-select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phòng ban <span class="text-rose-500">*</span></label>
                        <el-select v-model="employeeForm.department_id" class="w-full">
                            <el-option v-for="d in departments" :key="d.id" :label="d.name" :value="d.id" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Vị trí chức danh <span class="text-rose-500">*</span></label>
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
                            Chế độ Bảo hộ Thai sản (Điều 137 BLLĐ 2019)
                        </span>
                        <el-switch v-model="employeeForm.is_pregnant" />
                    </div>

                    <div v-if="employeeForm.is_pregnant" class="grid grid-cols-2 gap-3 pt-2 border-t border-pink-200/60">
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">Số tuần thai hiện tại</label>
                            <el-input-number v-model="employeeForm.pregnancy_weeks_at_start" :min="1" :max="42" class="!w-full" />
                        </div>
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">Ngày dự sinh</label>
                            <el-date-picker v-model="employeeForm.expected_due_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isCreateEmployeeModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="employeeForm.processing">
                        Tiếp nhận & Kích hoạt Onboarding
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 2: SỬA HỒ SƠ NHÂN SỰ (EDIT) -->
        <el-dialog
            v-model="isEditModalOpen"
            title="Cập nhật Hồ sơ Nhân sự"
            width="680px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitEditEmployee" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Họ và Tên *</label>
                        <el-input v-model="editForm.full_name" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Giới tính</label>
                        <el-radio-group v-model="editForm.gender" class="w-full">
                            <el-radio-button value="MALE">Nam</el-radio-button>
                            <el-radio-button value="FEMALE">Nữ</el-radio-button>
                            <el-radio-button value="OTHER">Khác</el-radio-button>
                        </el-radio-group>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngày sinh *</label>
                        <el-date-picker v-model="editForm.birth_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mã chấm công</label>
                        <el-input v-model="editForm.attendance_code" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">CCCD (12 số)</label>
                        <el-input v-model="editForm.citizen_id" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email *</label>
                        <el-input v-model="editForm.email" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Số điện thoại</label>
                        <el-input v-model="editForm.phone" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Quê quán</label>
                        <el-input v-model="editForm.hometown" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Thường trú</label>
                        <el-input v-model="editForm.permanent_address" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tạm trú</label>
                        <el-input v-model="editForm.temporary_address" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngày vào làm *</label>
                        <el-date-picker v-model="editForm.join_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Hạn thử việc</label>
                        <el-date-picker v-model="editForm.probation_end_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Trạng thái</label>
                        <el-select v-model="editForm.status" class="w-full">
                            <el-option label="Chính thức" value="OFFICIAL" />
                            <el-option label="Thử việc" value="PROBATION" />
                            <el-option label="Nghỉ phép" value="ON_LEAVE" />
                            <el-option label="Đã thôi việc" value="TERMINATED" />
                        </el-select>
                    </div>
                </div>

                <!-- Thai sản (chỉ hiện khi là Nữ) -->
                <div v-if="editForm.gender === 'FEMALE'" class="p-4 bg-pink-50/60 dark:bg-pink-950/30 rounded-xl border border-pink-200 dark:border-pink-900/60 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-pink-800 dark:text-pink-300">Chế độ Bảo hộ Thai sản (Điều 137 BLLĐ)</span>
                        <el-switch v-model="editForm.is_pregnant" />
                    </div>

                    <div v-if="editForm.is_pregnant" class="grid grid-cols-2 gap-3 pt-2">
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">Số tuần thai</label>
                            <el-input-number v-model="editForm.pregnancy_weeks_at_start" :min="1" :max="42" class="!w-full" />
                        </div>
                        <div>
                            <label class="block text-xs text-pink-800 dark:text-pink-300 mb-1">Ngày dự sinh</label>
                            <el-date-picker v-model="editForm.expected_due_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isEditModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="editForm.processing">
                        Cập nhật Hồ sơ
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 3: THÔI VIỆC (OFFBOARDING) -->
        <el-dialog
            v-model="isOffboardModalOpen"
            title="Quy trình Thôi việc Nhân sự (Offboarding)"
            width="520px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitOffboard" class="space-y-4" v-if="offboardingEmployee">
                <div class="p-3.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/60 rounded-xl text-xs text-rose-800 dark:text-rose-300">
                    Thực hiện thôi việc cho nhân sự <strong>{{ offboardingEmployee.full_name }}</strong> ({{ offboardingEmployee.user_code }}).
                    <div class="mt-1 font-medium">Thâm niên công tác: <strong>{{ offboardingEmployee.seniority_text }}</strong>. Hệ thống sẽ tự động tạo mốc Career Event <code>OFFBOARDING</code>.</div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngày chính thức thôi việc <span class="text-rose-500">*</span></label>
                    <el-date-picker v-model="offboardForm.resigned_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Lý do thôi việc <span class="text-rose-500">*</span></label>
                    <el-input v-model="offboardForm.resignation_reason" placeholder="VD: Hết hạn hợp đồng, Nguyện vọng cá nhân..." required />
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Số quyết định thôi việc</label>
                        <el-input v-model="offboardForm.decision_number" placeholder="QĐ-TV-..." />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngày ký quyết định</label>
                        <el-date-picker v-model="offboardForm.decision_date" type="date" value-format="YYYY-MM-DD" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ghi chú bàn giao</label>
                    <el-input v-model="offboardForm.notes" type="textarea" :rows="2" placeholder="Đã bàn giao máy tính xách tay, thẻ ngân hàng, tài liệu kỹ thuật..." />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isOffboardModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="danger" native-type="submit" :loading="offboardForm.processing">
                        Xác nhận Thôi việc
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 4: Ban hành Phiên bản Phòng ban Mới -->
        <el-dialog
            v-model="isDepartmentModalOpen"
            title="Ban hành Phiên bản Phòng ban Mới (Effective Dating)"
            width="500px"
        >
            <form @submit.prevent="submitDepartmentVersion" class="space-y-4">
                <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-xs text-amber-800 dark:text-amber-300">
                    Hệ thống sẽ đóng phiên bản cũ vào ngày <code>(Ngày hiệu lực - 1)</code> và lưu vết lịch sử vĩnh viễn, bảo toàn tính đúng đắn cho các báo cáo trong quá khứ.
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tên phòng ban mới <span class="text-rose-500">*</span></label>
                    <el-input v-model="departmentForm.name" required />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Ngày bắt đầu áp dụng <span class="text-rose-500">*</span></label>
                    <el-date-picker v-model="departmentForm.effective_date" type="date" value-format="YYYY-MM-DD" class="!w-full" required />
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Chức năng & Nhiệm vụ bổ sung</label>
                    <el-input v-model="departmentForm.functions" type="textarea" :rows="3" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isDepartmentModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="departmentForm.processing">
                        Ban hành Phiên bản Mới
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 5: Khởi tạo Chi nhánh mới -->
        <el-dialog
            v-model="isBranchModalOpen"
            title="Khởi tạo Chi nhánh mới"
            width="500px"
        >
            <form @submit.prevent="submitBranch" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mã Chi nhánh <span class="text-rose-500">*</span></label>
                        <el-input v-model="branchForm.code" placeholder="VD: CN_DANANG" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tên Chi nhánh <span class="text-rose-500">*</span></label>
                        <el-input v-model="branchForm.name" placeholder="VD: Chi nhánh Đà Nẵng" required />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Địa chỉ</label>
                    <el-input v-model="branchForm.address" placeholder="Số nhà, đường, quận/huyện..." />
                </div>

                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                    <el-checkbox v-model="branchForm.is_remote_or_island">
                        <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">Vùng cao, vùng sâu, vùng xa, biên giới, hải đảo (Điều 137.1.a)</span>
                    </el-checkbox>
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isBranchModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="branchForm.processing">
                        Tạo Chi nhánh
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 6: Thêm Phòng ban mới -->
        <el-dialog
            v-model="isCreateDeptModalOpen"
            title="Khởi tạo Phòng ban / Đơn vị mới"
            width="580px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitCreateDepartment" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mã đơn vị *</label>
                        <el-input v-model="createDeptForm.code" placeholder="VD: TECH, MKT, HR..." required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tên phòng ban *</label>
                        <el-input v-model="createDeptForm.name" placeholder="VD: Phòng Công nghệ Thông tin" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cấp bậc đơn vị *</label>
                        <el-select v-model="createDeptForm.level" class="w-full">
                            <el-option :value="1" label="Khối / Khối chức năng (Cấp 1)" />
                            <el-option :value="2" label="Phòng ban (Cấp 2)" />
                            <el-option :value="3" label="Đội nhóm / Team (Cấp 3)" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Chi nhánh *</label>
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
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Đơn vị cấp trên (Parent)</label>
                        <el-select v-model="createDeptForm.parent_id" clearable placeholder="Không có (Là khối gốc)" class="w-full">
                            <el-option
                                v-for="d in departments"
                                :key="d.id"
                                :value="d.id"
                                :label="d.name + ' (' + d.code + ')'"
                            />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Trưởng đơn vị</label>
                        <el-select v-model="createDeptForm.manager_id" clearable filterable placeholder="Chọn nhân sự quản lý" class="w-full">
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
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Chức năng & Nhiệm vụ</label>
                    <el-input v-model="createDeptForm.mission_function" type="textarea" :rows="2" placeholder="Mô tả chức năng nhiệm vụ chính của phòng ban..." />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isCreateDeptModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="createDeptForm.processing">
                        Tạo Phòng ban
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 7: Sửa thông tin Phòng ban -->
        <el-dialog
            v-model="isEditDeptModalOpen"
            title="Chỉnh sửa Thông tin Phòng ban"
            width="580px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitEditDepartment" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mã đơn vị *</label>
                        <el-input v-model="editDeptForm.code" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tên phòng ban *</label>
                        <el-input v-model="editDeptForm.name" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cấp bậc đơn vị *</label>
                        <el-select v-model="editDeptForm.level" class="w-full">
                            <el-option :value="1" label="Khối / Khối chức năng (Cấp 1)" />
                            <el-option :value="2" label="Phòng ban (Cấp 2)" />
                            <el-option :value="3" label="Đội nhóm / Team (Cấp 3)" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Chi nhánh *</label>
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
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Đơn vị cấp trên</label>
                        <el-select v-model="editDeptForm.parent_id" clearable placeholder="Không có (Gốc)" class="w-full">
                            <el-option
                                v-for="d in departments.filter(d => d.id !== editingDeptId)"
                                :key="d.id"
                                :value="d.id"
                                :label="d.name + ' (' + d.code + ')'"
                            />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Trưởng đơn vị</label>
                        <el-select v-model="editDeptForm.manager_id" clearable filterable placeholder="Chọn nhân sự quản lý" class="w-full">
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
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Trạng thái hoạt động</label>
                        <el-select v-model="editDeptForm.status" class="w-full">
                            <el-option value="ACTIVE" label="Hoạt động bình thường" />
                            <el-option value="INACTIVE" label="Tạm ngừng / Đang giải thể" />
                        </el-select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Thứ tự hiển thị</label>
                        <el-input-number v-model="editDeptForm.order" :min="0" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Chức năng & Nhiệm vụ</label>
                    <el-input v-model="editDeptForm.mission_function" type="textarea" :rows="2" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isEditDeptModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="editDeptForm.processing">
                        Lưu Thay Đổi
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 8: Thêm Chức danh mới -->
        <el-dialog
            v-model="isCreatePosModalOpen"
            title="Khởi tạo Vị trí / Chức danh mới"
            width="540px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitCreatePosition" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mã chức danh *</label>
                        <el-input v-model="createPosForm.code" placeholder="VD: DEV_LEAD, HR_MGR..." required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tên chức danh *</label>
                        <el-input v-model="createPosForm.name" placeholder="VD: Trưởng nhóm Lập trình" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cấp bậc (Level 1..10) *</label>
                        <el-input-number v-model="createPosForm.level" :min="1" :max="10" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phòng ban áp dụng</label>
                        <el-select v-model="createPosForm.department_id" clearable placeholder="Áp dụng toàn công ty" class="w-full">
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
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phụ cấp chức vụ (VNĐ/tháng)</label>
                        <el-input-number v-model="createPosForm.allowance_amount" :min="0" :step="100000" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Thưởng bổ nhiệm 1 lần (VNĐ)</label>
                        <el-input-number v-model="createPosForm.bonus_one_time" :min="0" :step="500000" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mô tả vị trí & Tiêu chuẩn tuyển dụng</label>
                    <el-input v-model="createPosForm.description" type="textarea" :rows="2" placeholder="Yêu cầu kỹ năng, trách nhiệm chính..." />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isCreatePosModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="createPosForm.processing">
                        Tạo Chức danh
                    </el-button>
                </div>
            </form>
        </el-dialog>

        <!-- MODAL 9: Sửa Chức danh -->
        <el-dialog
            v-model="isEditPosModalOpen"
            title="Chỉnh sửa Thông tin Chức danh"
            width="540px"
            class="!rounded-2xl"
        >
            <form @submit.prevent="submitEditPosition" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mã chức danh *</label>
                        <el-input v-model="editPosForm.code" required />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Tên chức danh *</label>
                        <el-input v-model="editPosForm.name" required />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Cấp bậc (Level 1..10) *</label>
                        <el-input-number v-model="editPosForm.level" :min="1" :max="10" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phòng ban áp dụng</label>
                        <el-select v-model="editPosForm.department_id" clearable placeholder="Áp dụng toàn công ty" class="w-full">
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
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Phụ cấp chức vụ (VNĐ/tháng)</label>
                        <el-input-number v-model="editPosForm.allowance_amount" :min="0" :step="100000" class="!w-full" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Thưởng bổ nhiệm 1 lần (VNĐ)</label>
                        <el-input-number v-model="editPosForm.bonus_one_time" :min="0" :step="500000" class="!w-full" />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Mô tả công việc</label>
                    <el-input v-model="editPosForm.description" type="textarea" :rows="2" />
                </div>

                <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                    <el-button @click="isEditPosModalOpen = false">Hủy bỏ</el-button>
                    <el-button type="primary" native-type="submit" :loading="editPosForm.processing">
                        Lưu Thay Đổi
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
