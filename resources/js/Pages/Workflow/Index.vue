<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CreateRequestModal from './Components/CreateRequestModal.vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';
import {
    Document,
    Check,
    Close,
    Plus,
    Filter,
    Clock,
    User,
    Warning,
    FolderChecked,
    Setting,
    Connection,
    Link,
    Search,
    Refresh
} from '@element-plus/icons-vue';

interface ApprovalHistory {
    id: number;
    step: number;
    action: string;
    comment: string | null;
    verified_child_under_12m_consent: boolean;
    created_at: string;
    approver?: {
        id: number;
        full_name: string;
        user_code: string;
    };
}

interface ApproverStep {
    level: number;
    title: string;
    role_type: string;
    approver_id: number;
    approver_name: string;
    approver_code: string;
    status: 'PENDING' | 'WAITING' | 'APPROVED' | 'REJECTED';
    action_at: string | null;
    comment: string | null;
}

interface ApprovalRequestItem {
    id: number;
    tenant_id: number;
    employee_id: number;
    request_type: string;
    form_payload: Record<string, any>;
    is_voluntary_consent: boolean;
    voluntary_agreement_file: string | null;
    voluntary_agreement_url: string | null;
    current_step: number;
    snapshot_approvers: ApproverStep[];
    status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'CANCELLED';
    created_at: string;
    employee?: {
        id: number;
        full_name: string;
        user_code: string;
        gender: string;
        children?: Array<{ id: number; birth_date: string; is_active: boolean }>;
    };
    histories?: ApprovalHistory[];
}

interface WorkflowConfigItem {
    id: number;
    request_type: string;
    total_levels: number;
    steps_config: Array<{ level: number; role_type: string; title: string }>;
    condition_rules: Record<string, any> | null;
    is_active: boolean;
}

const props = defineProps<{
    requestsToApprove: ApprovalRequestItem[];
    myRequests: {
        data: ApprovalRequestItem[];
        total: number;
        current_page: number;
        last_page: number;
    };
    currentEmployee: { id: number; full_name: string; user_code: string } | null;
    leaveBalance: { remaining_days: number; used_days: number; pending_days: number } | null;
    employees: Array<{ id: number; full_name: string; user_code: string; email: string; gender: string }>;
    workflowConfigs: WorkflowConfigItem[];
    filters: {
        tab: string;
        status?: string;
        type?: string;
    };
    requestTypes: Array<{ value: string; label: string }>;
}>();

const { t } = useThemeAndLocale();

const activeTab = ref(props.filters.tab || 'to_approve');
const selectedType = ref(props.filters.type || '');
const selectedStatus = ref(props.filters.status || '');
const showCreateModal = ref(false);

// Dialog Phê duyệt
const approveDialogVisible = ref(false);
const selectedRequestToApprove = ref<ApprovalRequestItem | null>(null);
const approveForm = useForm({
    comment: '',
    verified_child_under_12m_consent: false,
});

// Dialog Từ chối
const rejectDialogVisible = ref(false);
const selectedRequestToReject = ref<ApprovalRequestItem | null>(null);
const rejectForm = useForm({
    comment: '',
});

// Dialog Chi tiết đơn
const detailDialogVisible = ref(false);
const selectedDetailRequest = ref<ApprovalRequestItem | null>(null);

// Lọc đơn
const applyFilters = () => {
    router.get('/workflow/requests', {
        tab: activeTab.value,
        type: selectedType.value || undefined,
        status: selectedStatus.value || undefined,
    }, { preserveState: true, preserveScroll: true });
};

const handleTabClick = (tab: any) => {
    activeTab.value = tab.paneName;
    applyFilters();
};

// Mở dialog duyệt
const openApproveDialog = (req: ApprovalRequestItem) => {
    selectedRequestToApprove.value = req;
    approveForm.reset();
    approveForm.verified_child_under_12m_consent = false;
    approveDialogVisible.value = true;
};

const submitApprove = () => {
    if (!selectedRequestToApprove.value) return;
    approveForm.post(`/workflow/requests/${selectedRequestToApprove.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('workflow.approve_success'));
            approveDialogVisible.value = false;
        },
        onError: (errs) => {
            ElMessage.error(Object.values(errs)[0] as string || t('workflow.error_occurred'));
        }
    });
};

// Mở dialog từ chối
const openRejectDialog = (req: ApprovalRequestItem) => {
    selectedRequestToReject.value = req;
    rejectForm.reset();
    rejectDialogVisible.value = true;
};

const submitReject = () => {
    if (!selectedRequestToReject.value) return;
    if (!rejectForm.comment.trim()) {
        ElMessage.warning(t('workflow.reject_reason_required'));
        return;
    }
    rejectForm.post(`/workflow/requests/${selectedRequestToReject.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('workflow.reject_success'));
            rejectDialogVisible.value = false;
        },
        onError: (errs) => {
            ElMessage.error(Object.values(errs)[0] as string || t('workflow.error_occurred'));
        }
    });
};

// Hủy đơn
const cancelRequest = (req: ApprovalRequestItem) => {
    ElMessageBox.confirm(
        t('workflow.cancel_confirm_msg'),
        t('workflow.cancel_confirm_title'),
        { confirmButtonText: t('workflow.btn_cancel'), cancelButtonText: t('common.close'), type: 'warning' }
    ).then(() => {
        router.post(`/workflow/requests/${req.id}/cancel`, {}, {
            preserveScroll: true,
            onSuccess: () => ElMessage.success(t('workflow.cancel_success')),
        });
    }).catch(() => {});
};

// Xem chi tiết
const viewDetail = (req: ApprovalRequestItem) => {
    selectedDetailRequest.value = req;
    detailDialogVisible.value = true;
};

// Format tag loại đơn
const requestTypeTagMap: Record<string, { type: string; key: string }> = {
    LEAVE: { type: 'primary', key: 'workflow.tag_type_leave' },
    OVERTIME: { type: 'warning', key: 'workflow.tag_type_overtime' },
    NIGHT_SHIFT: { type: 'purple', key: 'workflow.tag_type_night_shift' },
    TIMESHEET_EDIT: { type: 'success', key: 'workflow.tag_type_timesheet_edit' },
    SHIFT_SWAP: { type: 'info', key: 'workflow.tag_type_shift_swap' },
    REMOTE_WORK: { type: 'info', key: 'workflow.tag_type_remote_work' },
    RESIGNATION: { type: 'danger', key: 'workflow.tag_type_resignation' },
};
const getRequestTypeTag = (type: string) => {
    const entry = requestTypeTagMap[type];
    return entry ? { type: entry.type, label: t(entry.key) } : { type: 'info', label: type };
};
const getRequestTypeOptionLabel = (type: string, fallbackLabel?: string) => {
    const entry = requestTypeTagMap[type];
    return entry ? t(entry.key) : (fallbackLabel || type);
};

// Format tag trạng thái
const statusTagMap: Record<string, { type: string; key: string }> = {
    PENDING: { type: 'warning', key: 'status.pending' },
    APPROVED: { type: 'success', key: 'status.approved' },
    REJECTED: { type: 'danger', key: 'status.rejected' },
    CANCELLED: { type: 'info', key: 'status.cancelled' },
};
const getStatusTag = (status: string) => {
    const entry = statusTagMap[status];
    return entry ? { type: entry.type, label: t(entry.key) } : { type: 'info', label: status };
};
const statusFilterValues = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED'];

// Cập nhật cấu hình quy trình duyệt (Tab 3)
const editingConfig = ref<WorkflowConfigItem | null>(null);
const configForm = useForm({
    request_type: '',
    total_levels: 1,
    steps_config: [] as Array<{ level: number; role_type: string; title: string }>,
    condition_rules: {} as Record<string, any>,
    is_active: true,
});

const openEditConfig = (cfg: WorkflowConfigItem) => {
    editingConfig.value = cfg;
    configForm.request_type = cfg.request_type;
    configForm.total_levels = cfg.total_levels;
    configForm.steps_config = JSON.parse(JSON.stringify(cfg.steps_config));
    configForm.condition_rules = cfg.condition_rules || {};
    configForm.is_active = cfg.is_active;
};

const handleLevelsChange = (val: number) => {
    while (configForm.steps_config.length < val) {
        const nextLevel = configForm.steps_config.length + 1;
        configForm.steps_config.push({
            level: nextLevel,
            role_type: nextLevel === 2 ? 'DEPARTMENT_HEAD' : 'HR_ADMIN',
            title: t('workflow.config_step_default_title', { level: nextLevel }),
        });
    }
    while (configForm.steps_config.length > val) {
        configForm.steps_config.pop();
    }
};

const saveWorkflowConfig = () => {
    configForm.post('/workflow/configs', {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('workflow.config_save_success'));
            editingConfig.value = null;
        },
        onError: (errs) => {
            ElMessage.error(Object.values(errs)[0] as string || t('workflow.config_save_error'));
        }
    });
};
</script>

<template>
    <AppLayout>
        <Head :title="t('workflow.title')" />

        <div class="space-y-6">
            <!-- Header Banner Doanh Nghiệp -->
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-5 sm:p-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                                <FolderChecked class="w-5 h-5" />
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ $t('workflow.title') }}
                                </h1>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ $t('workflow.subtitle') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div v-if="leaveBalance" class="hidden md:flex items-center gap-3 px-3 py-1.5 rounded-lg bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 text-xs">
                            <span class="text-blue-700 dark:text-blue-300">{{ $t('workflow.leave_balance_available') }}</span>
                            <strong class="text-blue-900 dark:text-blue-100 font-bold text-sm">{{ leaveBalance.remaining_days }} {{ $t('common.days') }}</strong>
                        </div>

                        <el-button
                            type="primary"
                            :icon="Plus"
                            @click="showCreateModal = true"
                        >
                            {{ $t('workflow.btn_create_request') }}
                        </el-button>
                    </div>
                </div>

                <!-- Thanh Lọc (Filters) -->
                <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <el-select
                            v-model="selectedType"
                            :placeholder="$t('workflow.filter_all_types')"
                            clearable
                            size="default"
                            class="w-44"
                            @change="applyFilters"
                        >
                            <el-option
                                v-for="t in requestTypes"
                                :key="t.value"
                                :label="getRequestTypeOptionLabel(t.value, t.label)"
                                :value="t.value"
                            />
                        </el-select>

                        <el-select
                            v-if="activeTab === 'my_requests'"
                            v-model="selectedStatus"
                            :placeholder="$t('common.status')"
                            clearable
                            size="default"
                            class="w-36"
                            @change="applyFilters"
                        >
                            <el-option
                                v-for="s in statusFilterValues"
                                :key="s"
                                :label="getStatusTag(s).label"
                                :value="s"
                            />
                        </el-select>

                        <el-button :icon="Refresh" @click="applyFilters">{{ $t('common.refresh') }}</el-button>
                    </div>
                </div>
            </div>

            <!-- Tabs Điều Hướng Chính -->
            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm p-4">
                <el-tabs v-model="activeTab" @tab-click="handleTabClick">
                    <!-- TAB 1: ĐƠN CẦN TÔI DUYỆT -->
                    <el-tab-pane name="to_approve">
                        <template #label>
                            <span class="flex items-center gap-2">
                                <span>{{ $t('workflow.tab_to_approve') }}</span>
                                <el-badge
                                    v-if="requestsToApprove.length > 0"
                                    :value="requestsToApprove.length"
                                    class="item"
                                    type="danger"
                                />
                            </span>
                        </template>

                        <div v-if="requestsToApprove.length === 0" class="py-12 text-center text-gray-400 text-sm">
                            <Check class="w-10 h-10 mx-auto text-emerald-500 mb-2 opacity-70" />
                            <p>{{ $t('workflow.empty_to_approve') }}</p>
                        </div>

                        <el-table
                            v-else
                            :data="requestsToApprove"
                            stripe
                            style="width: 100%"
                            class="rounded-lg overflow-hidden text-xs"
                        >
                            <el-table-column :label="$t('workflow.col_request_id')" width="90" align="center">
                                <template #default="{ row }">
                                    <span class="font-mono font-semibold text-blue-600 dark:text-blue-400">#{{ row.id }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_creator')" min-width="170">
                                <template #default="{ row }">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ row.employee?.full_name }}
                                    </div>
                                    <div class="text-[11px] text-gray-400">
                                        {{ row.employee?.user_code }}
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_type')" width="130">
                                <template #default="{ row }">
                                    <el-tag :type="getRequestTypeTag(row.request_type).type" size="small" effect="plain">
                                        {{ getRequestTypeTag(row.request_type).label }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_content')" min-width="220">
                                <template #default="{ row }">
                                    <div class="space-y-1">
                                        <!-- Chi tiết theo loại đơn -->
                                        <div v-if="row.request_type === 'LEAVE'" class="font-medium">
                                            {{ $t('workflow.summary_leave', { days: row.form_payload?.days, start: row.form_payload?.start_date, end: row.form_payload?.end_date }) }}
                                        </div>
                                        <div v-else-if="row.request_type === 'OVERTIME'" class="font-medium">
                                            {{ $t('workflow.summary_overtime', { hours: row.form_payload?.hours, date: row.form_payload?.date }) }}
                                        </div>
                                        <div v-else-if="row.request_type === 'NIGHT_SHIFT'" class="font-medium">
                                            {{ $t('workflow.summary_night_shift', { date: row.form_payload?.work_date }) }}
                                        </div>
                                        <div v-else-if="row.request_type === 'TIMESHEET_EDIT'" class="font-medium">
                                            {{ $t('workflow.summary_timesheet_edit', { date: row.form_payload?.work_date }) }}
                                        </div>

                                        <div v-if="row.form_payload?.reason" class="text-[11px] text-gray-500 italic truncate max-w-xs">
                                            "{{ row.form_payload.reason }}"
                                        </div>

                                        <!-- Badge Cảnh Báo Điều 137 BLLĐ -->
                                        <div v-if="row.is_voluntary_consent" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 text-[10px] font-semibold border border-purple-200 dark:border-purple-800">
                                            <Warning class="w-3 h-3 text-purple-600" />
                                            <span>{{ $t('workflow.consent_under_12m_badge') }}</span>
                                            <a v-if="row.voluntary_agreement_url" :href="row.voluntary_agreement_url" target="_blank" class="underline ml-1">{{ $t('workflow.doc_link') }}</a>
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_progress')" width="200">
                                <template #default="{ row }">
                                    <div class="flex items-center gap-1.5">
                                        <div
                                            v-for="step in row.snapshot_approvers"
                                            :key="step.level"
                                            class="flex items-center gap-1"
                                        >
                                            <el-tooltip :content="`${step.title}: ${step.approver_name} (${step.status})`" placement="top">
                                                <div
                                                    :class="[
                                                        'w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center',
                                                        step.status === 'APPROVED' ? 'bg-emerald-500 text-white' :
                                                        step.status === 'PENDING' ? 'bg-amber-500 text-white animate-pulse' :
                                                        step.status === 'REJECTED' ? 'bg-red-500 text-white' :
                                                        'bg-gray-200 dark:bg-gray-700 text-gray-500'
                                                    ]"
                                                >
                                                    {{ step.level }}
                                                </div>
                                            </el-tooltip>
                                            <span v-if="step.level < row.snapshot_approvers.length" class="text-gray-300">&rarr;</span>
                                        </div>
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        {{ $t('workflow.current_step_label', { current: row.current_step, total: row.snapshot_approvers?.length }) }}
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_created_at')" width="130">
                                <template #default="{ row }">
                                    <span class="text-gray-500">{{ row.created_at?.substring(0, 16) }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_actions')" width="190" align="center" fixed="right">
                                <template #default="{ row }">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <el-button
                                            type="success"
                                            size="small"
                                            :icon="Check"
                                            @click="openApproveDialog(row)"
                                        >
                                            {{ $t('workflow.btn_approve') }}
                                        </el-button>
                                        <el-button
                                            type="danger"
                                            size="small"
                                            :icon="Close"
                                            @click="openRejectDialog(row)"
                                        >
                                            {{ $t('workflow.btn_reject') }}
                                        </el-button>
                                        <el-button
                                            size="small"
                                            @click="viewDetail(row)"
                                        >
                                            {{ $t('common.detail') }}
                                        </el-button>
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-tab-pane>

                    <!-- TAB 2: ĐƠN CỦA TÔI -->
                    <el-tab-pane :label="$t('workflow.tab_my_requests')" name="my_requests">
                        <div v-if="myRequests.data.length === 0" class="py-12 text-center text-gray-400 text-sm">
                            <Document class="w-10 h-10 mx-auto text-gray-300 mb-2 opacity-50" />
                            <p>{{ $t('workflow.empty_my_requests') }}</p>
                        </div>

                        <el-table
                            v-else
                            :data="myRequests.data"
                            stripe
                            style="width: 100%"
                            class="rounded-lg overflow-hidden text-xs"
                        >
                            <el-table-column :label="$t('workflow.col_request_id')" width="90" align="center">
                                <template #default="{ row }">
                                    <span class="font-mono font-semibold text-blue-600 dark:text-blue-400">#{{ row.id }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_type')" width="130">
                                <template #default="{ row }">
                                    <el-tag :type="getRequestTypeTag(row.request_type).type" size="small" effect="plain">
                                        {{ getRequestTypeTag(row.request_type).label }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_content')" min-width="240">
                                <template #default="{ row }">
                                    <div class="space-y-1">
                                        <div v-if="row.request_type === 'LEAVE'" class="font-medium">
                                            {{ $t('workflow.summary_leave', { days: row.form_payload?.days, start: row.form_payload?.start_date, end: row.form_payload?.end_date }) }}
                                        </div>
                                        <div v-else-if="row.request_type === 'OVERTIME'" class="font-medium">
                                            {{ $t('workflow.summary_overtime', { hours: row.form_payload?.hours, date: row.form_payload?.date }) }}
                                        </div>
                                        <div v-else-if="row.request_type === 'NIGHT_SHIFT'" class="font-medium">
                                            {{ $t('workflow.summary_night_shift', { date: row.form_payload?.work_date }) }}
                                        </div>
                                        <div v-else class="font-medium">
                                            {{ row.form_payload?.reason || $t('workflow.summary_work_request') }}
                                        </div>

                                        <div v-if="row.form_payload?.reason && row.request_type !== 'RESIGNATION'" class="text-[11px] text-gray-500 italic truncate">
                                            "{{ row.form_payload.reason }}"
                                        </div>
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_progress')" width="220">
                                <template #default="{ row }">
                                    <div class="flex items-center gap-1.5">
                                        <div
                                            v-for="step in row.snapshot_approvers"
                                            :key="step.level"
                                            class="flex items-center gap-1"
                                        >
                                            <el-tooltip :content="`${step.title}: ${step.approver_name} (${step.status})`" placement="top">
                                                <div
                                                    :class="[
                                                        'w-5 h-5 rounded-full text-[10px] font-bold flex items-center justify-center',
                                                        step.status === 'APPROVED' ? 'bg-emerald-500 text-white' :
                                                        step.status === 'PENDING' ? 'bg-amber-500 text-white animate-pulse' :
                                                        step.status === 'REJECTED' ? 'bg-red-500 text-white' :
                                                        'bg-gray-200 dark:bg-gray-700 text-gray-500'
                                                    ]"
                                                >
                                                    {{ step.level }}
                                                </div>
                                            </el-tooltip>
                                            <span v-if="step.level < row.snapshot_approvers.length" class="text-gray-300">&rarr;</span>
                                        </div>
                                    </div>
                                    <div class="text-[10px] text-gray-400 mt-1">
                                        {{ row.snapshot_approvers?.[row.current_step - 1]?.approver_name || $t('status.completed') }}
                                    </div>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('common.status')" width="110" align="center">
                                <template #default="{ row }">
                                    <el-tag :type="getStatusTag(row.status).type" size="small">
                                        {{ getStatusTag(row.status).label }}
                                    </el-tag>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_created_at')" width="130">
                                <template #default="{ row }">
                                    <span class="text-gray-500">{{ row.created_at?.substring(0, 16) }}</span>
                                </template>
                            </el-table-column>

                            <el-table-column :label="$t('workflow.col_actions')" width="150" align="center" fixed="right">
                                <template #default="{ row }">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <el-button
                                            size="small"
                                            @click="viewDetail(row)"
                                        >
                                            {{ $t('common.view') }}
                                        </el-button>
                                        <el-button
                                            v-if="row.status === 'PENDING'"
                                            type="danger"
                                            size="small"
                                            plain
                                            @click="cancelRequest(row)"
                                        >
                                            {{ $t('workflow.btn_cancel') }}
                                        </el-button>
                                    </div>
                                </template>
                            </el-table-column>
                        </el-table>

                        <!-- Phân trang -->
                        <div v-if="myRequests.total > 15" class="mt-4 flex justify-end">
                            <el-pagination
                                background
                                layout="prev, pager, next"
                                :total="myRequests.total"
                                :page-size="15"
                                :current-page="myRequests.current_page"
                                @current-change="(page: number) => router.get('/workflow/requests', { ...filters, page }, { preserveState: true })"
                            />
                        </div>
                    </el-tab-pane>

                    <!-- TAB 3: CẤU HÌNH QUY TRÌNH PHÊ DUYỆT (ZERO-HARDCODE) -->
                    <el-tab-pane :label="$t('workflow.tab_configs')" name="configs">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="text-xs text-gray-500">
                                    {{ $t('workflow.config_tab_desc') }}
                                </div>
                            </div>

                            <el-table :data="workflowConfigs" stripe class="text-xs">
                                <el-table-column :label="$t('workflow.col_type')" width="180">
                                    <template #default="{ row }">
                                        <span class="font-semibold">{{ getRequestTypeTag(row.request_type).label }}</span>
                                        <div class="text-[10px] font-mono text-gray-400">{{ row.request_type }}</div>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('workflow.col_total_levels')" width="120" align="center">
                                    <template #default="{ row }">
                                        <span class="font-bold text-blue-600">{{ row.total_levels }} {{ $t('workflow.config_level_unit') }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('workflow.col_approval_chain')" min-width="280">
                                    <template #default="{ row }">
                                        <div class="flex items-center gap-2">
                                            <div
                                                v-for="step in row.steps_config"
                                                :key="step.level"
                                                class="px-2 py-1 rounded bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[11px]"
                                            >
                                                <span class="font-semibold text-blue-600">{{ $t('workflow.level_prefix', { level: step.level }) }}</span>
                                                {{ step.title }} ({{ step.role_type }})
                                            </div>
                                        </div>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('workflow.col_conditions')" width="180">
                                    <template #default="{ row }">
                                        <div v-if="row.condition_rules?.min_days_for_level_2" class="text-[11px] text-amber-600">
                                            {{ $t('workflow.rule_min_days', { days: row.condition_rules.min_days_for_level_2 }) }}
                                        </div>
                                        <div v-else class="text-gray-400 text-[11px]">
                                            {{ $t('workflow.rule_fixed') }}
                                        </div>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('common.status')" width="100" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                            {{ row.is_active ? $t('workflow.tag_active') : $t('workflow.tag_inactive') }}
                                        </el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column :label="$t('workflow.col_actions')" width="100" align="center">
                                    <template #default="{ row }">
                                        <el-button type="primary" size="small" link @click="openEditConfig(row)">
                                            {{ $t('common.edit') }}
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-tab-pane>
                </el-tabs>
            </div>
        </div>

        <!-- MODAL TẠO ĐƠN MỚI -->
        <CreateRequestModal
            v-model="showCreateModal"
            :employees="employees"
            :current-employee="currentEmployee"
            :leave-balance="leaveBalance"
            :request-types="requestTypes"
            @success="applyFilters"
        />

        <!-- DIALOG PHÊ DUYỆT -->
        <el-dialog
            v-model="approveDialogVisible"
            :title="$t('workflow.dialog_approve_title')"
            width="500px"
        >
            <div v-if="selectedRequestToApprove" class="space-y-4 text-xs">
                <div class="p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                    <div class="font-medium text-gray-800 dark:text-gray-200">
                        {{ $t('workflow.approve_dialog_line_prefix') }} <strong>#{{ selectedRequestToApprove.id }}</strong> {{ $t('workflow.approve_dialog_line_suffix') }} <strong>{{ selectedRequestToApprove.employee?.full_name }}</strong>
                    </div>
                </div>

                <!-- Checkbox Điều 137 BLLĐ nếu đơn có thỏa thuận tự nguyện -->
                <div v-if="selectedRequestToApprove.is_voluntary_consent" class="p-3 rounded-lg bg-purple-50 dark:bg-purple-950/40 border border-purple-300 dark:border-purple-800 space-y-2">
                    <div class="flex items-center gap-1.5 text-purple-900 dark:text-purple-200 font-semibold text-[11px]">
                        <Warning class="w-4 h-4 text-purple-600" />
                        {{ $t('workflow.consent_art137_title') }}
                    </div>
                    <p class="text-[11px] text-purple-700 dark:text-purple-300">
                        {{ $t('workflow.consent_art137_desc') }}
                    </p>
                    <el-checkbox
                        v-model="approveForm.verified_child_under_12m_consent"
                        class="text-purple-900 dark:text-purple-100 font-semibold"
                    >
                        {{ $t('workflow.consent_checkbox_confirm') }}
                    </el-checkbox>
                </div>

                <div>
                    <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('workflow.dialog_approve_comment') }}</label>
                    <el-input
                        v-model="approveForm.comment"
                        type="textarea"
                        :rows="3"
                        :placeholder="$t('workflow.approve_dialog_comment_placeholder')"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="approveDialogVisible = false">{{ $t('common.close') }}</el-button>
                    <el-button type="success" :loading="approveForm.processing" @click="submitApprove">
                        {{ $t('workflow.btn_approve') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- DIALOG TỪ CHỐI -->
        <el-dialog
            v-model="rejectDialogVisible"
            :title="$t('workflow.dialog_reject_title')"
            width="500px"
        >
            <div v-if="selectedRequestToReject" class="space-y-4 text-xs">
                <div class="p-3 rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 text-red-800 dark:text-red-300">
                    {{ $t('workflow.reject_dialog_line_prefix') }} #{{ selectedRequestToReject.id }} {{ $t('workflow.reject_dialog_line_suffix') }} <strong>{{ selectedRequestToReject.employee?.full_name }}</strong>. {{ $t('workflow.reject_dialog_note') }}
                </div>

                <div>
                    <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('workflow.dialog_reject_comment') }}</label>
                    <el-input
                        v-model="rejectForm.comment"
                        type="textarea"
                        :rows="3"
                        :placeholder="$t('workflow.reject_dialog_comment_placeholder')"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="rejectDialogVisible = false">{{ $t('common.cancel') }}</el-button>
                    <el-button type="danger" :loading="rejectForm.processing" @click="submitReject">
                        {{ $t('workflow.btn_reject') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- DIALOG CHI TIẾT ĐƠN & LỊCH SỬ PHÊ DUYỆT -->
        <el-dialog
            v-model="detailDialogVisible"
            :title="$t('workflow.detail_title')"
            width="620px"
        >
            <div v-if="selectedDetailRequest" class="space-y-4 text-xs">
                <!-- Thông tin cơ bản -->
                <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700">
                    <div>
                        <span class="text-gray-400">{{ $t('common.employee') }}:</span>
                        <strong class="ml-1 text-gray-800 dark:text-gray-200">{{ selectedDetailRequest.employee?.full_name }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ $t('workflow.col_type') }}:</span>
                        <el-tag :type="getRequestTypeTag(selectedDetailRequest.request_type).type" size="small" class="ml-1">
                            {{ getRequestTypeTag(selectedDetailRequest.request_type).label }}
                        </el-tag>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ $t('common.status') }}:</span>
                        <el-tag :type="getStatusTag(selectedDetailRequest.status).type" size="small" class="ml-1">
                            {{ getStatusTag(selectedDetailRequest.status).label }}
                        </el-tag>
                    </div>
                    <div>
                        <span class="text-gray-400">{{ $t('workflow.detail_created_at_label') }}:</span>
                        <span class="ml-1 text-gray-700 dark:text-gray-300">{{ selectedDetailRequest.created_at }}</span>
                    </div>
                </div>

                <!-- Lịch sử các cấp duyệt -->
                <div>
                    <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-2 flex items-center gap-1.5">
                        <Clock class="w-4 h-4 text-blue-500" /> {{ $t('workflow.detail_progress_title') }}
                    </h4>

                    <div class="space-y-2">
                        <div
                            v-for="step in selectedDetailRequest.snapshot_approvers"
                            :key="step.level"
                            class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 flex items-start gap-3 bg-white dark:bg-gray-800/40"
                        >
                            <div
                                :class="[
                                    'w-6 h-6 rounded-full text-xs font-bold flex items-center justify-center shrink-0 mt-0.5',
                                    step.status === 'APPROVED' ? 'bg-emerald-500 text-white' :
                                    step.status === 'PENDING' ? 'bg-amber-500 text-white animate-pulse' :
                                    step.status === 'REJECTED' ? 'bg-red-500 text-white' :
                                    'bg-gray-200 dark:bg-gray-700 text-gray-500'
                                ]"
                            >
                                {{ step.level }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="font-semibold text-gray-800 dark:text-gray-200">
                                        {{ step.title }} &bull; {{ step.approver_name }}
                                    </div>
                                    <span class="text-[10px] text-gray-400">{{ step.action_at || $t('workflow.step_not_acted') }}</span>
                                </div>
                                <div class="text-[11px] text-gray-500 mt-0.5">
                                    {{ $t('common.status') }}: <strong :class="step.status === 'APPROVED' ? 'text-emerald-600' : step.status === 'REJECTED' ? 'text-red-600' : 'text-amber-600'">{{ step.status }}</strong>
                                </div>
                                <div v-if="step.comment" class="mt-1 p-1.5 rounded bg-gray-50 dark:bg-gray-900/50 text-[11px] text-gray-600 dark:text-gray-300 italic">
                                    "{{ step.comment }}"
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <el-button @click="detailDialogVisible = false">{{ $t('common.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- DIALOG CHỈNH SỬA CẤU HÌNH QUY TRÌNH (TAB 3) -->
        <el-dialog
            v-model="editingConfig"
            :title="$t('workflow.config_dialog_title')"
            width="560px"
        >
            <el-form :model="configForm" label-position="top" class="space-y-3 text-xs">
                <el-form-item :label="$t('workflow.col_type')">
                    <el-input :value="getRequestTypeTag(configForm.request_type).label" disabled />
                </el-form-item>

                <el-form-item :label="$t('workflow.config_total_levels_label')">
                    <el-radio-group v-model="configForm.total_levels" @change="handleLevelsChange">
                        <el-radio-button :value="1">{{ $t('workflow.config_level_1') }}</el-radio-button>
                        <el-radio-button :value="2">{{ $t('workflow.config_level_2') }}</el-radio-button>
                        <el-radio-button :value="3">{{ $t('workflow.config_level_3') }}</el-radio-button>
                    </el-radio-group>
                </el-form-item>

                <div class="space-y-2 border-t border-gray-200 dark:border-gray-700 pt-3">
                    <label class="block font-semibold text-gray-700 dark:text-gray-300">{{ $t('workflow.config_roles_label') }}</label>
                    <div
                        v-for="(step, idx) in configForm.steps_config"
                        :key="step.level"
                        class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 flex items-center gap-2"
                    >
                        <span class="font-bold text-blue-600 shrink-0">{{ $t('workflow.level_prefix', { level: step.level }) }}</span>
                        <el-input v-model="step.title" :placeholder="$t('workflow.config_step_title_placeholder')" size="small" class="w-36" />
                        <el-select v-model="step.role_type" size="small" class="w-48">
                            <el-option :label="$t('workflow.config_role_direct_manager')" value="DIRECT_MANAGER" />
                            <el-option :label="$t('workflow.config_role_dept_head')" value="DEPARTMENT_HEAD" />
                            <el-option :label="$t('workflow.config_role_hr_admin')" value="HR_ADMIN" />
                            <el-option :label="$t('workflow.config_role_general_director')" value="GENERAL_DIRECTOR" />
                        </el-select>
                    </div>
                </div>

                <div v-if="configForm.request_type === 'LEAVE' && configForm.total_levels > 1" class="border-t border-gray-200 dark:border-gray-700 pt-3">
                    <label class="block font-semibold text-gray-700 dark:text-gray-300 mb-1">{{ $t('workflow.config_leave_rule_label') }}</label>
                    <div class="flex items-center gap-2 text-xs">
                        <span>{{ $t('workflow.config_rule_prefix') }}</span>
                        <el-input-number
                            v-model="configForm.condition_rules.min_days_for_level_2"
                            :min="1"
                            :max="30"
                            size="small"
                        />
                        <span>{{ $t('common.days') }}</span>
                    </div>
                </div>
            </el-form>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="editingConfig = null">{{ $t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="configForm.processing" @click="saveWorkflowConfig">
                        {{ $t('workflow.config_save_button') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
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
