<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ElMessage } from 'element-plus';
import {
    Back,
    Plus,
    Check,
    Calendar,
    User,
    Document,
    CircleCheck,
    Warning,
    Collection,
    Clock,
} from '@element-plus/icons-vue';

interface EmployeeDetail {
    id: number;
    user_code: string;
    full_name: string;
    gender: string;
    birth_date: string;
    email: string;
    phone: string | null;
    join_date: string;
    status: string;
    is_pregnant: boolean;
    pregnancy_start_date: string | null;
    child_birth_date: string | null;
    is_child_under_12m: boolean;
    is_pregnant_restricted: boolean;
}

interface CareerTimelineItem {
    id: number;
    event_type: string;
    job_title: string;
    position_name: string;
    department_name: string;
    manager_name: string | null;
    effective_date: string;
    end_date: string;
    decision_number: string | null;
    decision_date: string | null;
    notes: string | null;
    is_active: boolean;
}

interface ContractItem {
    id: number;
    contract_number: string;
    contract_type: string;
    sign_date: string;
    start_date: string;
    end_date: string;
    status: string;
}

const props = defineProps<{
    employee: EmployeeDetail;
    timelines: CareerTimelineItem[];
    contracts: ContractItem[];
    departments: Array<{ id: number; name: string }>;
    positions: Array<{ id: number; code: string; name: string }>;
    managers: Array<{ id: number; full_name: string; user_code: string }>;
}>();

const activeSection = ref<'timeline' | 'contracts'>('timeline');

// Modal Ghi nhận Biến động Nhân sự
const isAddTimelineModalOpen = ref(false);
const timelineForm = useForm({
    event_type: 'PROMOTION',
    department_id: props.departments[0]?.id || '',
    position_id: props.positions[0]?.id || '',
    manager_id: props.managers[0]?.id || null,
    job_title: '',
    effective_date: new Date().toISOString().split('T')[0],
    decision_number: '',
    decision_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const submitTimelineEvent = () => {
    timelineForm.post(`/core-hr/employees/${props.employee.id}/timeline`, {
        onSuccess: () => {
            isAddTimelineModalOpen.value = false;
            timelineForm.reset();
            ElMessage.success('Đã cập nhật lịch sử sự nghiệp (Effective Dating) thành công!');
        },
        onError: () => {
            ElMessage.error('Vui lòng kiểm tra lại các trường thông tin!');
        },
    });
};

const getEventTagType = (type: string) => {
    switch (type) {
        case 'ONBOARDING': return 'success';
        case 'PROMOTION': return 'primary';
        case 'TRANSFER': return 'info';
        case 'SALARY_ADJUSTMENT': return 'warning';
        case 'DEMOTION': return 'danger';
        case 'TERMINATION': return 'info';
        default: return 'info';
    }
};

const getEventLabel = (type: string) => {
    switch (type) {
        case 'ONBOARDING': return 'Gia nhập doanh nghiệp (Onboarding)';
        case 'PROMOTION': return 'Thăng chức (Promotion)';
        case 'TRANSFER': return 'Điều chuyển công tác (Transfer)';
        case 'SALARY_ADJUSTMENT': return 'Điều chỉnh đãi ngộ / Lương';
        case 'DEMOTION': return 'Hạ bậc / Giáng cấp';
        case 'TERMINATION': return 'Chấm dứt hợp đồng';
        default: return type;
    }
};

const getStatusTagType = (status: string) => {
    switch (status) {
        case 'OFFICIAL': return 'success';
        case 'PROBATION': return 'warning';
        case 'SUSPENDED': return 'danger';
        case 'TERMINATED': return 'info';
        default: return 'info';
    }
};

const getStatusLabel = (status: string) => {
    switch (status) {
        case 'OFFICIAL': return 'Chính thức';
        case 'PROBATION': return 'Thử việc';
        case 'SUSPENDED': return 'Tạm hoãn HĐ';
        case 'TERMINATED': return 'Đã thôi việc';
        default: return status;
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`${employee.full_name} (${employee.user_code}) - Hồ sơ nhân sự`" />

        <div class="space-y-6">
            <!-- Navigation Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white/80 backdrop-blur-md dark:bg-slate-900/80 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm">
                <div class="flex items-center space-x-4">
                    <Link
                        href="/core-hr"
                        class="p-2.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all"
                    >
                        <el-icon :size="18"><Back /></el-icon>
                    </Link>
                    <div>
                        <div class="flex items-center gap-2.5 flex-wrap">
                            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
                                {{ employee.full_name }}
                            </h1>
                            <span class="text-xs font-mono font-bold px-2 py-0.5 bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 rounded border border-indigo-200/60 dark:border-indigo-800/50">
                                {{ employee.user_code }}
                            </span>
                            <el-tag :type="getStatusTagType(employee.status)" size="small" round>
                                {{ getStatusLabel(employee.status) }}
                            </el-tag>
                        </div>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                            Gia nhập từ ngày {{ employee.join_date }} • Hồ sơ nhân sự và Lịch sử Effective Dating
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <el-button
                        type="primary"
                        class="!bg-indigo-600 hover:!bg-indigo-700 !rounded-xl !border-none !font-semibold"
                        :icon="Plus"
                        @click="isAddTimelineModalOpen = true"
                    >
                        Thêm biến động sự nghiệp
                    </el-button>
                </div>
            </div>

            <!-- Legal Protection Alert (Điều 137 BLLĐ 2019) -->
            <div
                v-if="employee.is_child_under_12m || employee.is_pregnant_restricted || employee.is_pregnant"
                class="p-5 rounded-2xl border transition-all duration-300 shadow-sm"
                :class="{
                    'bg-amber-50/90 dark:bg-amber-950/30 border-amber-300/80 dark:border-amber-800/60 text-amber-900 dark:text-amber-200': employee.is_child_under_12m,
                    'bg-rose-50/90 dark:bg-rose-950/30 border-rose-300/80 dark:border-rose-800/60 text-rose-900 dark:text-rose-200': employee.is_pregnant_restricted,
                    'bg-blue-50/90 dark:bg-blue-950/30 border-blue-300/80 dark:border-blue-800/60 text-blue-900 dark:text-blue-200': employee.is_pregnant && !employee.is_pregnant_restricted,
                }"
            >
                <div class="flex items-start gap-3.5">
                    <div class="p-2 rounded-xl bg-white/60 dark:bg-slate-900/40 shadow-xs mt-0.5">
                        <el-icon :size="20">
                            <Warning v-if="employee.is_pregnant_restricted" class="text-rose-600" />
                            <Clock v-else-if="employee.is_child_under_12m" class="text-amber-600" />
                            <Calendar v-else class="text-blue-600" />
                        </el-icon>
                    </div>
                    <div class="flex-1 text-sm leading-relaxed">
                        <h4 class="font-bold text-base mb-1">
                            Bảo hộ pháp lý đặc biệt (Bộ luật Lao động 2019 - Điều 137)
                        </h4>
                        <div v-if="employee.is_pregnant_restricted">
                            <span class="font-semibold text-rose-700 dark:text-rose-300">RÀNG BUỘC CỨNG (Hard Constraint - Khoản 1 Điểm a):</span>
                            Lao động nữ mang thai từ mốc tháng bảo hộ luật định (tháng 6 đối với vùng cao/biên giới/hải đảo hoặc tháng 7 đối với vùng thông thường).
                            <strong>TUYỆT ĐỐI KHÔNG ĐƯỢC</strong> phân ca đêm (22:00 - 06:00) hoặc bố trí làm thêm giờ (OT).
                        </div>
                        <div v-else-if="employee.is_child_under_12m">
                            <span class="font-semibold text-amber-700 dark:text-amber-300">CHẾ ĐỘ CON DƯỚI 12 THÁNG (Khoản 1 Điểm b):</span>
                            Lao động đang nuôi con dưới 12 tháng tuổi (sinh ngày {{ employee.child_birth_date }}).
                            Mặc định cấm xếp ca đêm hoặc làm thêm giờ, <strong>TRỪ TRƯỜNG HỢP</strong> người lao động tự nguyện và có thỏa thuận đồng thuận bằng văn bản.
                            Nhân viên được hưởng chế độ giảm 60 phút làm việc mỗi ngày có hưởng nguyên lương.
                        </div>
                        <div v-else-if="employee.is_pregnant">
                            <span class="font-semibold">Theo dõi thai sản:</span>
                            Bắt đầu mang thai từ ngày {{ employee.pregnancy_start_date }}. Hệ thống sẽ tự động kích hoạt chế độ bảo vệ khi thai kỳ chạm ngưỡng luật định.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Layout: Sidebar & Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Personal Profile Details -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                        <div class="flex items-center space-x-4 mb-6">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-500 to-purple-500 text-white flex items-center justify-center font-bold text-2xl shadow-md">
                                {{ employee.full_name.charAt(0) }}
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                                    {{ employee.full_name }}
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ employee.email }}
                                </p>
                                <div class="mt-2 flex items-center gap-1.5">
                                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        {{ employee.gender === 'FEMALE' ? 'Nữ' : (employee.gender === 'MALE' ? 'Nam' : 'Khác') }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        Sinh: {{ employee.birth_date }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Mã nhân viên:</span>
                                <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ employee.user_code }}</span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Số điện thoại:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ employee.phone || 'Chưa cập nhật' }}</span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Ngày vào làm:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ employee.join_date }}</span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Tình trạng con nhỏ:</span>
                                <span class="font-medium" :class="employee.is_child_under_12m ? 'text-amber-600 font-bold' : 'text-slate-800 dark:text-slate-200'">
                                    {{ employee.child_birth_date ? `Con sinh ${employee.child_birth_date}` : 'Không' }}
                                </span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">Thai sản:</span>
                                <span class="font-medium" :class="employee.is_pregnant ? 'text-indigo-600 font-bold' : 'text-slate-800 dark:text-slate-200'">
                                    {{ employee.is_pregnant ? 'Đang mang thai' : 'Bình thường' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Quick Tabs -->
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-2 shadow-sm flex flex-col gap-1">
                        <button
                            @click="activeSection = 'timeline'"
                            class="flex items-center justify-between p-3 rounded-xl text-sm font-medium transition-all text-left"
                            :class="activeSection === 'timeline' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 font-bold shadow-xs' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <el-icon><Clock /></el-icon>
                                Cây sự nghiệp (Career Timeline)
                            </span>
                            <span class="text-xs bg-slate-200/70 dark:bg-slate-800 px-2 py-0.5 rounded-full">{{ timelines.length }}</span>
                        </button>

                        <button
                            @click="activeSection = 'contracts'"
                            class="flex items-center justify-between p-3 rounded-xl text-sm font-medium transition-all text-left"
                            :class="activeSection === 'contracts' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 font-bold shadow-xs' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800/50'"
                        >
                            <span class="flex items-center gap-2.5">
                                <el-icon><Document /></el-icon>
                                Hợp đồng lao động
                            </span>
                            <span class="text-xs bg-slate-200/70 dark:bg-slate-800 px-2 py-0.5 rounded-full">{{ contracts.length }}</span>
                        </button>
                    </div>
                </div>

                <!-- Right Column: Timeline / Contracts / Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- SECTION 1: Career Timeline (Effective Dating) -->
                    <div v-show="activeSection === 'timeline'" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <el-icon class="text-indigo-600"><Clock /></el-icon>
                                    Dòng thời gian sự nghiệp (Effective Dating)
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Mỗi biến động tự động đóng mốc cũ và lưu vết lịch sử trọn đời, bảo toàn tính đúng đắn của dữ liệu quá khứ.
                                </p>
                            </div>
                            <el-button
                                type="primary"
                                size="small"
                                plain
                                :icon="Plus"
                                class="!rounded-lg"
                                @click="isAddTimelineModalOpen = true"
                            >
                                Thêm mốc mới
                            </el-button>
                        </div>

                        <!-- Element Plus Timeline -->
                        <el-timeline class="pl-2">
                            <el-timeline-item
                                v-for="item in timelines"
                                :key="item.id"
                                :timestamp="`${item.effective_date} → ${item.end_date}`"
                                placement="top"
                                :type="item.is_active ? 'primary' : 'info'"
                                size="large"
                            >
                                <div
                                    class="p-4 rounded-xl border transition-all hover:shadow-md"
                                    :class="item.is_active ? 'bg-indigo-50/40 border-indigo-200/80 dark:bg-slate-800/80 dark:border-indigo-500/50' : 'bg-slate-50/60 border-slate-200/60 dark:bg-slate-850 dark:border-slate-800'"
                                >
                                    <div class="flex items-center justify-between mb-1.5">
                                        <el-tag :type="getEventTagType(item.event_type)" size="small" round>
                                            {{ getEventLabel(item.event_type) }}
                                        </el-tag>
                                        <span v-if="item.is_active" class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                            Đang áp dụng
                                        </span>
                                    </div>

                                    <h4 class="text-base font-bold text-slate-800 dark:text-slate-100">
                                        {{ item.position_name }} - {{ item.department_name }}
                                    </h4>

                                    <p v-if="item.job_title" class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">
                                        Chức danh: {{ item.job_title }}
                                    </p>

                                    <div class="mt-3 pt-2 border-t border-slate-200/50 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 space-y-1">
                                        <div v-if="item.manager_name" class="flex items-center gap-1.5">
                                            <el-icon :size="12"><User /></el-icon>
                                            <span>Báo cáo cho: <strong>{{ item.manager_name }}</strong></span>
                                        </div>
                                        <div v-if="item.decision_number" class="flex items-center gap-1.5">
                                            <el-icon :size="12"><Document /></el-icon>
                                            <span>QĐ số: {{ item.decision_number }} ({{ item.decision_date }})</span>
                                        </div>
                                        <div v-if="item.notes" class="mt-1 italic text-slate-600 dark:text-slate-300">
                                            "{{ item.notes }}"
                                        </div>
                                    </div>
                                </div>
                            </el-timeline-item>
                        </el-timeline>
                    </div>

                    <!-- SECTION 2: Employment Contracts -->
                    <div v-show="activeSection === 'contracts'" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-100 dark:border-slate-800">
                            <div>
                                <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    <el-icon class="text-indigo-600"><Document /></el-icon>
                                    Danh sách hợp đồng lao động
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Lưu vết toàn bộ hợp đồng thử việc, xác định thời hạn và không xác định thời hạn.
                                </p>
                            </div>
                        </div>

                        <el-table :data="contracts" size="small" style="width: 100%" stripe>
                            <el-table-column prop="contract_number" label="Số HĐ" width="140">
                                <template #default="{ row }">
                                    <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ row.contract_number }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="contract_type" label="Loại hợp đồng">
                                <template #default="{ row }">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800">
                                        {{ row.contract_type }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="start_date" label="Bắt đầu" width="110" />
                            <el-table-column prop="end_date" label="Kết thúc" width="110" />
                            <el-table-column prop="status" label="Trạng thái" width="110">
                                <template #default="{ row }">
                                    <el-tag
                                        :type="row.status === 'ACTIVE' ? 'success' : 'info'"
                                        size="small"
                                        round
                                    >
                                        {{ row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: Ghi nhận biến động sự nghiệp mới (Effective Dating) -->
        <el-dialog
            v-model="isAddTimelineModalOpen"
            title="Ghi nhận biến động nhân sự (Effective Dating)"
            width="560px"
        >
            <form @submit.prevent="submitTimelineEvent" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Loại biến động sự kiện <span class="text-rose-500">*</span>
                    </label>
                    <select
                        v-model="timelineForm.event_type"
                        class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        required
                    >
                        <option value="PROMOTION">Thăng chức (Promotion)</option>
                        <option value="TRANSFER">Điều chuyển công tác (Transfer)</option>
                        <option value="SALARY_ADJUSTMENT">Điều chỉnh lương / Đãi ngộ</option>
                        <option value="DEMOTION">Hạ bậc / Giáng cấp (Demotion)</option>
                        <option value="TERMINATION">Chấm dứt làm việc (Termination)</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Phòng ban áp dụng <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="timelineForm.department_id"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                            required
                        >
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Chức danh / Vị trí <span class="text-rose-500">*</span>
                        </label>
                        <select
                            v-model="timelineForm.position_id"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                            required
                        >
                            <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Quản lý trực tiếp
                        </label>
                        <select
                            v-model="timelineForm.manager_id"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        >
                            <option :value="null">-- Không có --</option>
                            <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.full_name }} ({{ m.user_code }})</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Chức danh nội bộ
                        </label>
                        <input
                            v-model="timelineForm.job_title"
                            type="text"
                            placeholder="VD: Trưởng nhóm Backend"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Ngày bắt đầu hiệu lực <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="timelineForm.effective_date"
                            type="date"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            Số quyết định ban hành
                        </label>
                        <input
                            v-model="timelineForm.decision_number"
                            type="text"
                            placeholder="VD: QĐ-2026/08-HR"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        Ghi chú / Lý do điều chuyển
                    </label>
                    <textarea
                        v-model="timelineForm.notes"
                        rows="2"
                        placeholder="Nội dung chi tiết quyết định..."
                        class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <el-button @click="isAddTimelineModalOpen = false">Hủy bỏ</el-button>
                    <el-button
                        type="primary"
                        native-type="submit"
                        :loading="timelineForm.processing"
                    >
                        Lưu quyết định
                    </el-button>
                </div>
            </form>
        </el-dialog>
    </AppLayout>
</template>
