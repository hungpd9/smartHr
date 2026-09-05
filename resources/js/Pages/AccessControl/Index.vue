<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Check,
    Lock,
    Unlock,
    User,
    UserFilled,
    OfficeBuilding,
    Briefcase,
    Money,
    Clock,
    Setting,
    Cpu,
    Plus,
    Delete,
    Search,
    Refresh,
    InfoFilled,
    WarningFilled,
    CircleCheckFilled,
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';

interface PermissionAssignmentItem {
    id?: number;
    tenant_id?: number;
    subject_type: 'ROLE' | 'DEPARTMENT' | 'POSITION' | 'USER';
    subject_id: number | null;
    subject_role: string | null;
    module_code: 'CORE_HR' | 'PAYROLL_SALARY' | 'ATTENDANCE' | 'POLICY_WELFARE' | 'OFFICE_RESOURCES';
    can_view: boolean;
    can_manage: boolean;
    can_approve: boolean;
    notes: string | null;
}

interface ModuleInfo {
    code: 'CORE_HR' | 'PAYROLL_SALARY' | 'ATTENDANCE' | 'POLICY_WELFARE' | 'OFFICE_RESOURCES';
    name: string;
    description: string;
    icon: string;
}

interface RoleInfo {
    code: string;
    name: string;
    badge: 'primary' | 'success' | 'warning' | 'danger' | 'info';
}

interface DepartmentInfo {
    id: number;
    name: string;
    level: number;
}

interface PositionInfo {
    id: number;
    name: string;
    level: number;
}

interface EmployeeInfo {
    id: number;
    user_code: string;
    full_name: string;
    department: string;
    position: string;
}

const props = defineProps<{
    assignments: PermissionAssignmentItem[];
    modules: ModuleInfo[];
    roles: RoleInfo[];
    departments: DepartmentInfo[];
    positions: PositionInfo[];
    employees: EmployeeInfo[];
}>();

// Active Tab: ROLE | DEPARTMENT | POSITION | USER
const activeTab = ref<'ROLE' | 'DEPARTMENT' | 'POSITION' | 'USER'>('ROLE');

// Local clone of assignments for reactive editing
const matrix = ref<PermissionAssignmentItem[]>(JSON.parse(JSON.stringify(props.assignments)));

const saving = ref(false);

// Helper to get module icon component
const getModuleIcon = (code: string) => {
    switch (code) {
        case 'CORE_HR': return User;
        case 'PAYROLL_SALARY': return Money;
        case 'ATTENDANCE': return Clock;
        case 'POLICY_WELFARE': return Setting;
        case 'OFFICE_RESOURCES': return Cpu;
        default: return Setting;
    }
};

// --- TAB 1: ROLES LOGIC ---
const selectedRole = ref<string>(props.roles[0]?.code || 'SUPER_ADMIN');

const getRoleAssignment = (roleCode: string, moduleCode: string): PermissionAssignmentItem => {
    let item = matrix.value.find(
        (a) => a.subject_type === 'ROLE' && a.subject_role === roleCode && a.module_code === moduleCode
    );
    if (!item) {
        item = {
            subject_type: 'ROLE',
            subject_id: null,
            subject_role: roleCode,
            module_code: moduleCode as any,
            can_view: roleCode === 'SUPER_ADMIN',
            can_manage: roleCode === 'SUPER_ADMIN',
            can_approve: roleCode === 'SUPER_ADMIN',
            notes: null,
        };
        matrix.value.push(item);
    }
    return item;
};

// --- TAB 2: DEPARTMENTS LOGIC ---
const selectedDeptId = ref<number | undefined>(props.departments[0]?.id);

const getDeptAssignment = (deptId: number, moduleCode: string): PermissionAssignmentItem => {
    let item = matrix.value.find(
        (a) => a.subject_type === 'DEPARTMENT' && a.subject_id === deptId && a.module_code === moduleCode
    );
    if (!item) {
        item = {
            subject_type: 'DEPARTMENT',
            subject_id: deptId,
            subject_role: null,
            module_code: moduleCode as any,
            can_view: false,
            can_manage: false,
            can_approve: false,
            notes: null,
        };
        matrix.value.push(item);
    }
    return item;
};

// --- TAB 3: POSITIONS LOGIC ---
const selectedPosId = ref<number | undefined>(props.positions[0]?.id);

const getPosAssignment = (posId: number, moduleCode: string): PermissionAssignmentItem => {
    let item = matrix.value.find(
        (a) => a.subject_type === 'POSITION' && a.subject_id === posId && a.module_code === moduleCode
    );
    if (!item) {
        item = {
            subject_type: 'POSITION',
            subject_id: posId,
            subject_role: null,
            module_code: moduleCode as any,
            can_view: false,
            can_manage: false,
            can_approve: false,
            notes: null,
        };
        matrix.value.push(item);
    }
    return item;
};

// --- TAB 4: INDIVIDUAL EMPLOYEES LOGIC ---
const employeeSearchQuery = ref('');
const newEmployeeId = ref<number | undefined>(undefined);

// Distinct list of employees configured
const configuredEmployeeIds = computed(() => {
    const ids = new Set<number>();
    matrix.value
        .filter((a) => a.subject_type === 'USER' && a.subject_id !== null)
        .forEach((a) => ids.add(a.subject_id as number));
    return Array.from(ids);
});

const filteredConfiguredEmployees = computed(() => {
    return props.employees.filter((e) => {
        const matchesSearch =
            !employeeSearchQuery.value ||
            e.full_name.toLowerCase().includes(employeeSearchQuery.value.toLowerCase()) ||
            e.user_code.toLowerCase().includes(employeeSearchQuery.value.toLowerCase());
        return configuredEmployeeIds.value.includes(e.id) && matchesSearch;
    });
});

const getUserAssignment = (userId: number, moduleCode: string): PermissionAssignmentItem => {
    let item = matrix.value.find(
        (a) => a.subject_type === 'USER' && a.subject_id === userId && a.module_code === moduleCode
    );
    if (!item) {
        item = {
            subject_type: 'USER',
            subject_id: userId,
            subject_role: null,
            module_code: moduleCode as any,
            can_view: false,
            can_manage: false,
            can_approve: false,
            notes: null,
        };
        matrix.value.push(item);
    }
    return item;
};

const addEmployeeException = () => {
    if (!newEmployeeId.value) return;
    const empId = newEmployeeId.value;
    // ensure matrix has items for all modules
    props.modules.forEach((m) => {
        getUserAssignment(empId, m.code);
    });
    newEmployeeId.value = undefined;
    ElMessage.success('Đã thêm nhân sự vào danh sách Ủy quyền Cá nhân Đặc biệt.');
};

const removeEmployeeException = (empId: number) => {
    ElMessageBox.confirm('Bạn có chắc chắn muốn gỡ bỏ toàn bộ ủy quyền ngoại lệ cho nhân sự này?', 'Cảnh báo', {
        confirmButtonText: 'Gỡ bỏ',
        cancelButtonText: 'Hủy',
        type: 'warning',
    }).then(() => {
        matrix.value = matrix.value.filter((a) => !(a.subject_type === 'USER' && a.subject_id === empId));
        ElMessage.success('Đã gỡ bỏ ủy quyền cá nhân.');
    });
};

// Quick presets
const grantFullAccess = (items: PermissionAssignmentItem[]) => {
    items.forEach((item) => {
        item.can_view = true;
        item.can_manage = true;
        item.can_approve = true;
    });
};

const revokeAllAccess = (items: PermissionAssignmentItem[]) => {
    items.forEach((item) => {
        item.can_view = false;
        item.can_manage = false;
        item.can_approve = false;
    });
};

const setReadOnlyAccess = (items: PermissionAssignmentItem[]) => {
    items.forEach((item) => {
        item.can_view = true;
        item.can_manage = false;
        item.can_approve = false;
    });
};

// Submit whole matrix
const saveMatrix = () => {
    saving.value = true;
    router.post(
        '/access-control/update',
        {
            assignments: matrix.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success('Đã lưu cấu hình Ma trận Phân quyền & Ủy quyền thành công!');
            },
            onError: (err) => {
                ElMessage.error('Có lỗi xảy ra khi lưu ma trận phân quyền: ' + Object.values(err).join(', '));
            },
            onFinish: () => {
                saving.value = false;
            },
        }
    );
};
</script>

<template>
    <AppLayout>
        <Head title="Ma Trận Phân Quyền & Ủy Quyền Hệ Thống - Smart-HR" />

        <div class="space-y-6 pb-16">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs">
                <div>
                    <div class="flex items-center gap-2">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                            <el-icon :size="22"><Lock /></el-icon>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                                Ma Trận Phân Quyền & Ủy Quyền Hệ Thống (RBAC Matrix)
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Kiểm soát quyền Xem (View), Quản lý/Sửa xóa (Manage) và Phê duyệt (Approve) theo Vai trò, Phòng ban, Chức danh hoặc Cá nhân.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <el-button
                        type="primary"
                        :icon="Check"
                        :loading="saving"
                        class="!rounded-xl font-medium shadow-sm !px-5"
                        @click="saveMatrix"
                    >
                        Lưu Cấu hình Ma trận
                    </el-button>
                </div>
            </div>

            <!-- KPI & Policy Note Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><UserFilled /></el-icon>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Vai trò Hệ thống</span>
                        <div class="text-lg font-bold text-slate-900 dark:text-white">{{ roles.length }} Nhóm quyền</div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><OfficeBuilding /></el-icon>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Phòng ban Ủy quyền</span>
                        <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ departments.length }} Bộ phận</div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><Briefcase /></el-icon>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Chức danh / Cấp bậc</span>
                        <div class="text-lg font-bold text-amber-600 dark:text-amber-400">{{ positions.length }} Vị trí</div>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><User /></el-icon>
                    </div>
                    <div>
                        <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">Ủy quyền Ngoại lệ</span>
                        <div class="text-lg font-bold text-purple-600 dark:text-purple-400">{{ configuredEmployeeIds.length }} Nhân sự</div>
                    </div>
                </div>
            </div>

            <!-- Main Tabs Container -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
                <!-- Tab Headers -->
                <div class="p-3 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700/80 flex flex-wrap gap-2">
                    <button
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2"
                        :class="activeTab === 'ROLE' ? 'bg-primary-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700/60'"
                        @click="activeTab = 'ROLE'"
                    >
                        <el-icon><UserFilled /></el-icon>
                        1. Phân quyền theo Vai trò (Roles)
                    </button>
                    <button
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2"
                        :class="activeTab === 'DEPARTMENT' ? 'bg-primary-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700/60'"
                        @click="activeTab = 'DEPARTMENT'"
                    >
                        <el-icon><OfficeBuilding /></el-icon>
                        2. Ủy quyền Phòng ban (Departments)
                    </button>
                    <button
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2"
                        :class="activeTab === 'POSITION' ? 'bg-primary-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700/60'"
                        @click="activeTab = 'POSITION'"
                    >
                        <el-icon><Briefcase /></el-icon>
                        3. Ủy quyền Chức vụ (Positions)
                    </button>
                    <button
                        class="px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2"
                        :class="activeTab === 'USER' ? 'bg-primary-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-700/60'"
                        @click="activeTab = 'USER'"
                    >
                        <el-icon><User /></el-icon>
                        4. Ủy quyền Cá nhân Ngoại lệ (Individuals)
                    </button>
                </div>

                <div class="p-6">
                    <!-- ================= TAB 1: ROLES ================= -->
                    <div v-if="activeTab === 'ROLE'" class="space-y-6">
                        <!-- Role Selector -->
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">
                                    Chọn Vai trò Hệ thống cần Phân quyền:
                                </span>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="role in roles"
                                        :key="role.code"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-medium border transition-all"
                                        :class="selectedRole === role.code
                                            ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-950/60 dark:border-indigo-700 dark:text-indigo-300 font-semibold shadow-xs'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-slate-300'"
                                        @click="selectedRole = role.code"
                                    >
                                        {{ role.name }}
                                    </button>
                                </div>
                            </div>

                            <!-- Fast Presets Buttons -->
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">Thao tác nhanh:</span>
                                <el-button
                                    size="small"
                                    class="!rounded-lg"
                                    @click="grantFullAccess(modules.map(m => getRoleAssignment(selectedRole, m.code)))"
                                >
                                    Toàn quyền (Full)
                                </el-button>
                                <el-button
                                    size="small"
                                    class="!rounded-lg"
                                    @click="setReadOnlyAccess(modules.map(m => getRoleAssignment(selectedRole, m.code)))"
                                >
                                    Chỉ xem (Read)
                                </el-button>
                                <el-button
                                    size="small"
                                    class="!rounded-lg text-rose-600 hover:text-rose-700"
                                    @click="revokeAllAccess(modules.map(m => getRoleAssignment(selectedRole, m.code)))"
                                >
                                    Thu hồi hết
                                </el-button>
                            </div>
                        </div>

                        <!-- Matrix Table for Current Role -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/40">
                                        <th class="p-3.5 font-semibold">Phân hệ & Chức năng (Module)</th>
                                        <th class="p-3.5 font-semibold text-center w-36">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <el-icon><Unlock /></el-icon>
                                                <span>Xem (View)</span>
                                            </div>
                                        </th>
                                        <th class="p-3.5 font-semibold text-center w-44">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <el-icon><Setting /></el-icon>
                                                <span>Quản lý / CRUD</span>
                                            </div>
                                        </th>
                                        <th class="p-3.5 font-semibold text-center w-36">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <el-icon><CircleCheckFilled /></el-icon>
                                                <span>Phê duyệt</span>
                                            </div>
                                        </th>
                                        <th class="p-3.5 font-semibold w-72">Ghi chú & Phạm vi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr
                                        v-for="mod in modules"
                                        :key="mod.code"
                                        class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                                    >
                                        <td class="p-3.5">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 flex items-center justify-center shrink-0 mt-0.5">
                                                    <el-icon :size="16">
                                                        <component :is="getModuleIcon(mod.code)" />
                                                    </el-icon>
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-slate-800 dark:text-slate-200 text-sm block">
                                                        {{ mod.name }}
                                                    </span>
                                                    <span class="text-xs text-slate-400 block mt-0.5">
                                                        {{ mod.description }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- View -->
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getRoleAssignment(selectedRole, mod.code).can_view"
                                                active-color="#10b981"
                                            />
                                        </td>
                                        <!-- Manage -->
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getRoleAssignment(selectedRole, mod.code).can_manage"
                                                active-color="#3b82f6"
                                                :disabled="!getRoleAssignment(selectedRole, mod.code).can_view"
                                            />
                                        </td>
                                        <!-- Approve -->
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getRoleAssignment(selectedRole, mod.code).can_approve"
                                                active-color="#8b5cf6"
                                                :disabled="!getRoleAssignment(selectedRole, mod.code).can_view"
                                            />
                                        </td>
                                        <!-- Notes -->
                                        <td class="p-3.5">
                                            <el-input
                                                v-model="getRoleAssignment(selectedRole, mod.code).notes"
                                                size="small"
                                                placeholder="VD: Áp dụng toàn hệ thống"
                                                clearable
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ================= TAB 2: DEPARTMENTS ================= -->
                    <div v-if="activeTab === 'DEPARTMENT'" class="space-y-6">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">
                                    Chọn Phòng ban Ủy quyền Chuyên trách:
                                </span>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="dept in departments"
                                        :key="dept.id"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-medium border transition-all"
                                        :class="selectedDeptId === dept.id
                                            ? 'bg-emerald-50 border-emerald-300 text-emerald-700 dark:bg-emerald-950/60 dark:border-emerald-700 dark:text-emerald-300 font-semibold shadow-xs'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-slate-300'"
                                        @click="selectedDeptId = dept.id"
                                    >
                                        {{ dept.name }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="selectedDeptId" class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">Thao tác nhanh:</span>
                                <el-button
                                    size="small"
                                    class="!rounded-lg"
                                    @click="grantFullAccess(modules.map(m => getDeptAssignment(selectedDeptId!, m.code)))"
                                >
                                    Toàn quyền
                                </el-button>
                                <el-button
                                    size="small"
                                    class="!rounded-lg text-rose-600"
                                    @click="revokeAllAccess(modules.map(m => getDeptAssignment(selectedDeptId!, m.code)))"
                                >
                                    Thu hồi
                                </el-button>
                            </div>
                        </div>

                        <!-- Department Info Card -->
                        <div v-if="selectedDeptId" class="p-4 rounded-xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/40 flex items-center gap-3">
                            <el-icon :size="20" class="text-emerald-600 dark:text-emerald-400"><InfoFilled /></el-icon>
                            <span class="text-xs text-emerald-800 dark:text-emerald-300">
                                <strong>Nguyên lý kế thừa:</strong> Thành viên trực thuộc phòng ban này sẽ được tự động trao quyền tương ứng cho các chức năng bên dưới nếu quyền đó cao hơn quyền cá nhân của họ.
                            </span>
                        </div>

                        <!-- Matrix Table for Selected Department -->
                        <div v-if="selectedDeptId" class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/40">
                                        <th class="p-3.5 font-semibold">Phân hệ & Chức năng (Module)</th>
                                        <th class="p-3.5 font-semibold text-center w-36">Xem (View)</th>
                                        <th class="p-3.5 font-semibold text-center w-44">Quản lý / CRUD</th>
                                        <th class="p-3.5 font-semibold text-center w-36">Phê duyệt</th>
                                        <th class="p-3.5 font-semibold w-72">Ghi chú & Phạm vi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr
                                        v-for="mod in modules"
                                        :key="mod.code"
                                        class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                                    >
                                        <td class="p-3.5">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                                                    <component :is="getModuleIcon(mod.code)" />
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-slate-800 dark:text-slate-200 text-sm block">
                                                        {{ mod.name }}
                                                    </span>
                                                    <span class="text-xs text-slate-400 block mt-0.5">
                                                        {{ mod.description }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getDeptAssignment(selectedDeptId, mod.code).can_view"
                                                active-color="#10b981"
                                            />
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getDeptAssignment(selectedDeptId, mod.code).can_manage"
                                                active-color="#3b82f6"
                                                :disabled="!getDeptAssignment(selectedDeptId, mod.code).can_view"
                                            />
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getDeptAssignment(selectedDeptId, mod.code).can_approve"
                                                active-color="#8b5cf6"
                                                :disabled="!getDeptAssignment(selectedDeptId, mod.code).can_view"
                                            />
                                        </td>
                                        <td class="p-3.5">
                                            <el-input
                                                v-model="getDeptAssignment(selectedDeptId, mod.code).notes"
                                                size="small"
                                                placeholder="VD: Ủy quyền cho phòng ban"
                                                clearable
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ================= TAB 3: POSITIONS ================= -->
                    <div v-if="activeTab === 'POSITION'" class="space-y-6">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider block mb-1">
                                    Chọn Chức danh / Vị trí Phân quyền (Ví dụ: Trưởng phòng HR, Kế toán trưởng...):
                                </span>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="pos in positions"
                                        :key="pos.id"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-medium border transition-all"
                                        :class="selectedPosId === pos.id
                                            ? 'bg-amber-50 border-amber-300 text-amber-700 dark:bg-amber-950/60 dark:border-amber-700 dark:text-amber-300 font-semibold shadow-xs'
                                            : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 hover:border-slate-300'"
                                        @click="selectedPosId = pos.id"
                                    >
                                        {{ pos.name }} (Cấp {{ pos.level }})
                                    </button>
                                </div>
                            </div>

                            <div v-if="selectedPosId" class="flex items-center gap-2">
                                <span class="text-xs text-slate-400">Thao tác nhanh:</span>
                                <el-button
                                    size="small"
                                    class="!rounded-lg"
                                    @click="grantFullAccess(modules.map(m => getPosAssignment(selectedPosId!, m.code)))"
                                >
                                    Toàn quyền
                                </el-button>
                                <el-button
                                    size="small"
                                    class="!rounded-lg text-rose-600"
                                    @click="revokeAllAccess(modules.map(m => getPosAssignment(selectedPosId!, m.code)))"
                                >
                                    Thu hồi
                                </el-button>
                            </div>
                        </div>

                        <!-- Position Info Card -->
                        <div v-if="selectedPosId" class="p-4 rounded-xl bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200/60 dark:border-amber-900/40 flex items-center gap-3">
                            <el-icon :size="20" class="text-amber-600 dark:text-amber-400"><Briefcase /></el-icon>
                            <span class="text-xs text-amber-800 dark:text-amber-300">
                                <strong>Ủy quyền Chức danh:</strong> Ví dụ cho phép <em>Trưởng phòng HR</em> được toàn quyền Quản lý/Thiết lập Lương (PAYROLL_SALARY) của nhân viên, trong khi nhân viên thông thường chỉ có quyền Xem hoặc không được truy cập.
                            </span>
                        </div>

                        <!-- Matrix Table for Selected Position -->
                        <div v-if="selectedPosId" class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200 dark:border-slate-700 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/40">
                                        <th class="p-3.5 font-semibold">Phân hệ & Chức năng (Module)</th>
                                        <th class="p-3.5 font-semibold text-center w-36">Xem (View)</th>
                                        <th class="p-3.5 font-semibold text-center w-44">Quản lý / CRUD</th>
                                        <th class="p-3.5 font-semibold text-center w-36">Phê duyệt</th>
                                        <th class="p-3.5 font-semibold w-72">Ghi chú & Phạm vi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <tr
                                        v-for="mod in modules"
                                        :key="mod.code"
                                        class="hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition-colors"
                                    >
                                        <td class="p-3.5">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                                                    <component :is="getModuleIcon(mod.code)" />
                                                </div>
                                                <div>
                                                    <span class="font-semibold text-slate-800 dark:text-slate-200 text-sm block">
                                                        {{ mod.name }}
                                                    </span>
                                                    <span class="text-xs text-slate-400 block mt-0.5">
                                                        {{ mod.description }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getPosAssignment(selectedPosId, mod.code).can_view"
                                                active-color="#10b981"
                                            />
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getPosAssignment(selectedPosId, mod.code).can_manage"
                                                active-color="#3b82f6"
                                                :disabled="!getPosAssignment(selectedPosId, mod.code).can_view"
                                            />
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <el-switch
                                                v-model="getPosAssignment(selectedPosId, mod.code).can_approve"
                                                active-color="#8b5cf6"
                                                :disabled="!getPosAssignment(selectedPosId, mod.code).can_view"
                                            />
                                        </td>
                                        <td class="p-3.5">
                                            <el-input
                                                v-model="getPosAssignment(selectedPosId, mod.code).notes"
                                                size="small"
                                                placeholder="VD: Ủy quyền cho vị trí lãnh đạo"
                                                clearable
                                            />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ================= TAB 4: INDIVIDUALS ================= -->
                    <div v-if="activeTab === 'USER'" class="space-y-6">
                        <!-- Add Individual Exception Toolbar -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 flex flex-col md:flex-row items-center justify-between gap-4">
                            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                                <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">
                                    Ủy quyền cho Nhân sự Cụ thể:
                                </span>
                                <el-select
                                    v-model="newEmployeeId"
                                    placeholder="Chọn nhân viên từ danh sách..."
                                    filterable
                                    class="!w-80"
                                >
                                    <el-option
                                        v-for="emp in employees"
                                        :key="emp.id"
                                        :label="`${emp.user_code} - ${emp.full_name} (${emp.position})`"
                                        :value="emp.id"
                                    />
                                </el-select>
                                <el-button
                                    type="primary"
                                    :icon="Plus"
                                    :disabled="!newEmployeeId"
                                    class="!rounded-xl"
                                    @click="addEmployeeException"
                                >
                                    Thêm Ủy quyền
                                </el-button>
                            </div>

                            <div class="w-full md:w-72">
                                <el-input
                                    v-model="employeeSearchQuery"
                                    placeholder="Lọc nhân sự đã ủy quyền..."
                                    clearable
                                    :prefix-icon="Search"
                                />
                            </div>
                        </div>

                        <!-- Configured Employees List -->
                        <div v-if="filteredConfiguredEmployees.length === 0" class="text-center py-12 text-slate-400">
                            <el-icon :size="32" class="mb-2"><User /></el-icon>
                            <p class="text-sm">Chưa có nhân sự nào được cấu hình ủy quyền ngoại lệ cá nhân.</p>
                            <p class="text-xs mt-1 text-slate-500">Hãy chọn nhân sự ở trên và nhấn "Thêm Ủy quyền" để cấp quyền riêng biệt.</p>
                        </div>

                        <div v-else class="space-y-6">
                            <div
                                v-for="emp in filteredConfiguredEmployees"
                                :key="emp.id"
                                class="p-5 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/60 shadow-xs space-y-4"
                            >
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-sm">
                                            {{ emp.full_name.charAt(0) }}
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900 dark:text-white text-base">
                                                    {{ emp.full_name }}
                                                </span>
                                                <el-tag size="small" type="info" class="font-mono">{{ emp.user_code }}</el-tag>
                                            </div>
                                            <span class="text-xs text-slate-400">
                                                {{ emp.department }} • {{ emp.position }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <el-button
                                            size="small"
                                            class="!rounded-lg"
                                            @click="grantFullAccess(modules.map(m => getUserAssignment(emp.id, m.code)))"
                                        >
                                            Toàn quyền
                                        </el-button>
                                        <el-button
                                            size="small"
                                            type="danger"
                                            link
                                            :icon="Delete"
                                            @click="removeEmployeeException(emp.id)"
                                        >
                                            Gỡ bỏ nhân sự
                                        </el-button>
                                    </div>
                                </div>

                                <!-- User Matrix Table -->
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="border-b border-slate-100 dark:border-slate-800 text-xs text-slate-400">
                                                <th class="p-2 font-medium">Module</th>
                                                <th class="p-2 font-medium text-center w-32">Xem (View)</th>
                                                <th class="p-2 font-medium text-center w-36">Quản lý / CRUD</th>
                                                <th class="p-2 font-medium text-center w-32">Phê duyệt</th>
                                                <th class="p-2 font-medium w-64">Ghi chú ngoại lệ</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50 dark:divide-slate-800/60 text-xs">
                                            <tr v-for="mod in modules" :key="mod.code">
                                                <td class="p-2">
                                                    <span class="font-semibold text-slate-700 dark:text-slate-300">
                                                        {{ mod.name }}
                                                    </span>
                                                </td>
                                                <td class="p-2 text-center">
                                                    <el-switch
                                                        v-model="getUserAssignment(emp.id, mod.code).can_view"
                                                        size="small"
                                                        active-color="#10b981"
                                                    />
                                                </td>
                                                <td class="p-2 text-center">
                                                    <el-switch
                                                        v-model="getUserAssignment(emp.id, mod.code).can_manage"
                                                        size="small"
                                                        active-color="#3b82f6"
                                                        :disabled="!getUserAssignment(emp.id, mod.code).can_view"
                                                    />
                                                </td>
                                                <td class="p-2 text-center">
                                                    <el-switch
                                                        v-model="getUserAssignment(emp.id, mod.code).can_approve"
                                                        size="small"
                                                        active-color="#8b5cf6"
                                                        :disabled="!getUserAssignment(emp.id, mod.code).can_view"
                                                    />
                                                </td>
                                                <td class="p-2">
                                                    <el-input
                                                        v-model="getUserAssignment(emp.id, mod.code).notes"
                                                        size="small"
                                                        placeholder="VD: Cấp quyền riêng"
                                                        clearable
                                                    />
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
