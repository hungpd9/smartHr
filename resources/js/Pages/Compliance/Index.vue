<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Warning,
    CircleCheck,
    RefreshRight,
    Search,
    InfoFilled,
    Check,
    Document,
    Clock,
    User,
    OfficeBuilding,
    ScaleToOriginal,
    CloseBold
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

interface EmployeeItem {
    id: number;
    user_code: string;
    full_name: string;
    email: string;
    current_timeline?: {
        department?: {
            id: number;
            branch_id: number;
            current_version?: { name: string };
            branch?: { name: string };
        };
        position?: { name: string };
    };
}

interface ComplianceAlertItem {
    id: number;
    tenant_id: number;
    employee_id: number | null;
    alert_type: string;
    severity: 'INFO' | 'WARNING' | 'CRITICAL';
    title: string;
    description: string;
    is_resolved: boolean;
    resolved_by: number | null;
    resolved_at: string | null;
    created_at: string;
    employee?: EmployeeItem;
    resolved_by_employee?: { full_name: string };
}

interface StatsData {
    total_unresolved: number;
    critical_count: number;
    warning_count: number;
    info_count: number;
    resolved_count: number;
    compliance_score: number;
    breakdown_by_type: Record<string, number>;
}

interface LegalRef {
    law: string;
    decree: string;
    penalty: string;
    solution: string;
}

const props = defineProps<{
    alerts: {
        data: ComplianceAlertItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: any[];
    };
    stats: StatsData;
    departments: Array<{ id: number; current_version?: { name: string } }>;
    branches: Array<{ id: number; name: string }>;
    legalReferences: Record<string, LegalRef>;
    filters: {
        severity?: string;
        alert_type?: string;
        is_resolved?: string;
        search?: string;
        department_id?: string;
        branch_id?: string;
    };
}>();
const { t } = useThemeAndLocale();

// State quản lý bộ lọc
const searchKeyword = ref(props.filters.search || '');
const filterSeverity = ref(props.filters.severity || '');
const filterAlertType = ref(props.filters.alert_type || '');
const filterIsResolved = ref(props.filters.is_resolved ?? '0');
const filterDepartment = ref(props.filters.department_id || '');
const filterBranch = ref(props.filters.branch_id || '');

const isScanning = ref(false);
const selectedDate = ref(new Date().toISOString().slice(0, 10));

// Xử lý lọc
const applyFilters = () => {
    router.get(
        '/compliance',
        {
            search: searchKeyword.value || undefined,
            severity: filterSeverity.value || undefined,
            alert_type: filterAlertType.value || undefined,
            is_resolved: filterIsResolved.value !== '' ? filterIsResolved.value : undefined,
            department_id: filterDepartment.value || undefined,
            branch_id: filterBranch.value || undefined,
        },
        { preserveState: true, preserveScroll: true }
    );
};

const resetFilters = () => {
    searchKeyword.value = '';
    filterSeverity.value = '';
    filterAlertType.value = '';
    filterIsResolved.value = '0';
    filterDepartment.value = '';
    filterBranch.value = '';
    applyFilters();
};

// Kích hoạt quét tức thì
const triggerScan = () => {
    isScanning.value = true;
    router.post(
        '/compliance/scan',
        { target_date: selectedDate.value },
        {
            preserveScroll: true,
            onFinish: () => {
                isScanning.value = false;
            },
        }
    );
};

// Dialog giải quyết 1 cảnh báo
const resolveDialogVisible = ref(false);
const resolvingAlert = ref<ComplianceAlertItem | null>(null);
const resolveForm = useForm({
    resolution_notes: '',
});

const openResolveDialog = (alert: ComplianceAlertItem) => {
    resolvingAlert.value = alert;
    resolveForm.resolution_notes = '';
    resolveDialogVisible.value = true;
};

const submitResolve = () => {
    if (!resolvingAlert.value) return;

    resolveForm.post(`/compliance/alerts/${resolvingAlert.value.id}/resolve`, {
        preserveScroll: true,
        onSuccess: () => {
            resolveDialogVisible.value = false;
            ElMessage.success(t('compliance.resolve_success'));
        },
    });
};

// Khắc phục hàng loạt
const selectedAlerts = ref<ComplianceAlertItem[]>([]);
const handleSelectionChange = (val: ComplianceAlertItem[]) => {
    selectedAlerts.value = val;
};

const handleBulkResolve = () => {
    if (selectedAlerts.value.length === 0) return;

    ElMessageBox.prompt(
        t('compliance.bulk_resolve_prompt_msg', { count: selectedAlerts.value.length }),
        t('compliance.bulk_resolve_title'),
        {
            confirmButtonText: t('compliance.bulk_resolve_confirm_btn'),
            cancelButtonText: t('common.cancel'),
            inputPlaceholder: t('compliance.bulk_resolve_placeholder'),
        }
    ).then(({ value }) => {
        router.post(
            '/compliance/alerts/bulk-resolve',
            {
                alert_ids: selectedAlerts.value.map((a) => a.id),
                resolution_notes: value,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    selectedAlerts.value = [];
                    ElMessage.success(t('compliance.bulk_resolve_success'));
                },
            }
        );
    });
};

// Modal Căn cứ Pháp lý & Khung Phạt
const legalDialogVisible = ref(false);
const activeLegalRef = ref<{ type: string; details: LegalRef } | null>(null);

const openLegalModal = (alertType: string) => {
    const details = props.legalReferences[alertType];
    if (details) {
        activeLegalRef.value = { type: alertType, details };
        legalDialogVisible.value = true;
    }
};

// Danh mục loại vi phạm
const alertTypeKeys: Record<string, string> = {
    OT_BREACH_RED_40H: 'compliance.type_ot_breach_red_40h',
    OT_WARNING_YELLOW_35H: 'compliance.type_ot_warning_yellow_35h',
    OT_ANNUAL_CAP_BREACH: 'compliance.type_ot_annual_cap_breach',
    REST_BETWEEN_SHIFTS_BREACH: 'compliance.type_rest_between_shifts_breach',
    WEEKLY_REST_BREACH: 'compliance.type_weekly_rest_breach',
    PREGNANT_NIGHT_BREACH: 'compliance.type_pregnant_night_breach',
    CONTRACT_EXPIRING_30D: 'compliance.type_contract_expiring_30d',
    BURNOUT_RISK_HIGH: 'compliance.type_burnout_risk_high',
};

const alertTypeOptions = computed(() =>
    Object.entries(alertTypeKeys).map(([type, key]) => ({ type, label: t(key) }))
);

const getAlertTypeLabel = (type: string) => (alertTypeKeys[type] ? t(alertTypeKeys[type]) : type);

const getSeverityTag = (sev: string) => {
    switch (sev) {
        case 'CRITICAL':
            return { type: 'danger', label: t('compliance.tag_critical'), class: 'bg-red-500/10 text-red-500 border-red-500/20' };
        case 'WARNING':
            return { type: 'warning', label: t('compliance.tag_warning'), class: 'bg-amber-500/10 text-amber-500 border-amber-500/20' };
        default:
            return { type: 'info', label: t('compliance.tag_info'), class: 'bg-blue-500/10 text-blue-500 border-blue-500/20' };
    }
};

// Màu sắc điểm tuân thủ
const scoreColorClass = computed(() => {
    const s = props.stats.compliance_score;
    if (s >= 90) return 'text-emerald-500';
    if (s >= 75) return 'text-amber-500';
    return 'text-rose-500';
});
</script>

<template>
    <AppLayout :title="$t('compliance.layout_title')">
        <Head :title="$t('compliance.page_title')" />

        <div class="space-y-6">
            <!-- Header Card Banner Doanh Nghiệp -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 md:p-8 text-white shadow-xl border border-slate-800">
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 text-xs font-semibold uppercase tracking-wider mb-3">
                            <ScaleToOriginal class="w-3.5 h-3.5" />
                            {{ $t('compliance.hero_badge') }}
                        </div>
                        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-white">
                            {{ $t('compliance.hero_title') }}
                        </h1>
                        <p class="mt-2 text-sm text-slate-300 max-w-3xl leading-relaxed">
                            {{ $t('compliance.hero_subtitle') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-3 bg-white/5 backdrop-blur-sm p-2.5 rounded-xl border border-white/10 shrink-0">
                        <el-date-picker
                            v-model="selectedDate"
                            type="date"
                            :placeholder="$t('compliance.scan_date_placeholder')"
                            format="YYYY-MM-DD"
                            value-format="YYYY-MM-DD"
                            size="default"
                            class="!w-40"
                        />
                        <el-button
                            type="primary"
                            :loading="isScanning"
                            @click="triggerScan"
                            class="!bg-indigo-600 hover:!bg-indigo-500 !border-indigo-600 font-semibold"
                        >
                            <el-icon class="mr-1.5"><RefreshRight /></el-icon>
                            {{ $t('compliance.scan_now_btn') }}
                        </el-button>
                    </div>
                </div>
            </div>

            <!-- 4 Thẻ KPI Chỉ Số Tuân Thủ -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Thẻ 1: Điểm Sức Khỏe Tuân Thủ -->
                <div class="rounded-xl p-5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">
                            {{ $t('compliance.kpi_compliance_score') }}
                        </div>
                        <div class="mt-2 text-3xl font-extrabold" :class="scoreColorClass">
                            {{ stats.compliance_score }}%
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ stats.compliance_score >= 90 ? $t('compliance.score_status_safe') : $t('compliance.score_status_risk') }}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-700/50">
                        <ScaleToOriginal class="w-7 h-7 text-indigo-500" />
                    </div>
                </div>

                <!-- Thẻ 2: Vi Phạm Nghiêm Trọng (Critical) -->
                <div class="rounded-xl p-5 bg-white dark:bg-slate-800 border border-red-200 dark:border-red-900/40 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase text-red-600 dark:text-red-400">
                            {{ $t('compliance.kpi_critical') }}
                        </div>
                        <div class="mt-2 text-3xl font-extrabold text-red-600 dark:text-red-400">
                            {{ stats.critical_count }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('compliance.kpi_critical_sub') }}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-red-50 dark:bg-red-900/20">
                        <Warning class="w-7 h-7 text-red-500" />
                    </div>
                </div>

                <!-- Thẻ 3: Cảnh Báo Nguy Cơ (Warning) -->
                <div class="rounded-xl p-5 bg-white dark:bg-slate-800 border border-amber-200 dark:border-amber-900/40 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase text-amber-600 dark:text-amber-400">
                            {{ $t('compliance.kpi_warning') }}
                        </div>
                        <div class="mt-2 text-3xl font-extrabold text-amber-600 dark:text-amber-400">
                            {{ stats.warning_count }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('compliance.kpi_warning_sub') }}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-900/20">
                        <Clock class="w-7 h-7 text-amber-500" />
                    </div>
                </div>

                <!-- Thẻ 4: Đã Khắc Phục (Resolved) -->
                <div class="rounded-xl p-5 bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-900/40 shadow-sm flex items-center justify-between">
                    <div>
                        <div class="text-xs font-semibold uppercase text-emerald-600 dark:text-emerald-400">
                            {{ $t('compliance.kpi_resolved') }}
                        </div>
                        <div class="mt-2 text-3xl font-extrabold text-emerald-600 dark:text-emerald-400">
                            {{ stats.resolved_count }}
                        </div>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $t('compliance.kpi_resolved_sub') }}
                        </div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-emerald-50 dark:bg-emerald-900/20">
                        <CircleCheck class="w-7 h-7 text-emerald-500" />
                    </div>
                </div>
            </div>

            <!-- Khu Vực Danh Sách & Bộ Lọc Cảnh Báo -->
            <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm p-6 space-y-5">
                <!-- Toolbar Bộ Lọc -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 flex-1">
                        <el-input
                            v-model="searchKeyword"
                            :placeholder="$t('compliance.filter_search_placeholder')"
                            :prefix-icon="Search"
                            clearable
                            @change="applyFilters"
                        />

                        <el-select
                            v-model="filterSeverity"
                            :placeholder="$t('compliance.severity')"
                            clearable
                            @change="applyFilters"
                        >
                            <el-option :label="$t('compliance.severity_all')" value="" />
                            <el-option :label="$t('compliance.severity_critical')" value="CRITICAL" />
                            <el-option :label="$t('compliance.severity_warning')" value="WARNING" />
                            <el-option :label="$t('compliance.severity_info')" value="INFO" />
                        </el-select>

                        <el-select
                            v-model="filterAlertType"
                            :placeholder="$t('compliance.filter_type_placeholder')"
                            clearable
                            @change="applyFilters"
                        >
                            <el-option :label="$t('compliance.type_all')" value="" />
                            <el-option
                                v-for="option in alertTypeOptions"
                                :key="option.type"
                                :label="option.label"
                                :value="option.type"
                            />
                        </el-select>

                        <el-select
                            v-model="filterIsResolved"
                            :placeholder="$t('common.status')"
                            @change="applyFilters"
                        >
                            <el-option :label="$t('compliance.unresolved')" value="0" />
                            <el-option :label="$t('compliance.resolved')" value="1" />
                            <el-option :label="$t('compliance.status_all')" value="" />
                        </el-select>

                        <el-select
                            v-model="filterBranch"
                            :placeholder="$t('common.branch')"
                            clearable
                            @change="applyFilters"
                        >
                            <el-option :label="$t('compliance.branch_all')" value="" />
                            <el-option
                                v-for="branch in branches"
                                :key="branch.id"
                                :label="branch.name"
                                :value="branch.id"
                            />
                        </el-select>

                        <el-select
                            v-model="filterDepartment"
                            :placeholder="$t('common.department')"
                            clearable
                            @change="applyFilters"
                        >
                            <el-option :label="$t('compliance.department_all')" value="" />
                            <el-option
                                v-for="dept in departments"
                                :key="dept.id"
                                :label="dept.current_version?.name || $t('compliance.department_fallback', { id: dept.id })"
                                :value="dept.id"
                            />
                        </el-select>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <el-button @click="resetFilters">
                            {{ $t('compliance.btn_reset') }}
                        </el-button>
                        <el-button
                            v-if="selectedAlerts.length > 0"
                            type="success"
                            @click="handleBulkResolve"
                        >
                            <el-icon class="mr-1"><Check /></el-icon>
                            {{ $t('compliance.bulk_resolve_btn', { count: selectedAlerts.length }) }}
                        </el-button>
                    </div>
                </div>

                <!-- Bảng Dữ Liệu Cảnh Báo -->
                <el-table
                    :data="alerts.data"
                    stripe
                    border
                    style="width: 100%"
                    @selection-change="handleSelectionChange"
                    :empty-text="$t('compliance.table_empty')"
                >
                    <el-table-column type="selection" width="45" :selectable="(row: ComplianceAlertItem) => !row.is_resolved" />

                    <!-- Mức độ -->
                    <el-table-column :label="$t('compliance.severity')" width="130" align="center">
                        <template #default="{ row }">
                            <el-tag :type="getSeverityTag(row.severity).type" effect="dark" size="small" class="font-semibold">
                                {{ getSeverityTag(row.severity).label }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <!-- Loại vi phạm & Tiêu đề -->
                    <el-table-column :label="$t('compliance.col_content')" min-width="280">
                        <template #default="{ row }">
                            <div class="font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-1.5">
                                <span>{{ row.title }}</span>
                                <el-tooltip :content="$t('compliance.legal_tooltip')" placement="top">
                                    <button
                                        type="button"
                                        @click="openLegalModal(row.alert_type)"
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 p-0.5 rounded"
                                    >
                                        <InfoFilled class="w-4 h-4" />
                                    </button>
                                </el-tooltip>
                            </div>
                            <div class="mt-1 text-xs text-slate-500 dark:text-slate-400 line-clamp-2">
                                {{ row.description }}
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Nhân sự liên quan -->
                    <el-table-column :label="$t('common.employee')" width="220">
                        <template #default="{ row }">
                            <div v-if="row.employee" class="space-y-0.5">
                                <div class="font-medium text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                                    <User class="w-3.5 h-3.5 text-slate-400" />
                                    <span>{{ row.employee.full_name }}</span>
                                    <span class="text-xs text-slate-400">({{ row.employee.user_code }})</span>
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                                    <OfficeBuilding class="w-3 h-3 text-slate-400" />
                                    <span>{{ row.employee.current_timeline?.department?.current_version?.name || $t('compliance.not_assigned') }}</span>
                                </div>
                            </div>
                            <span v-else class="text-xs text-slate-400 italic">{{ $t('compliance.company_wide') }}</span>
                        </template>
                    </el-table-column>

                    <!-- Trạng thái -->
                    <el-table-column :label="$t('common.status')" width="160" align="center">
                        <template #default="{ row }">
                            <div v-if="row.is_resolved" class="space-y-0.5">
                                <el-tag type="success" size="small" effect="plain" class="font-medium">
                                    <el-icon class="mr-0.5"><CircleCheck /></el-icon> {{ $t('compliance.resolved') }}
                                </el-tag>
                                <div class="text-[11px] text-slate-400">
                                    {{ row.resolved_at ? new Date(row.resolved_at).toLocaleDateString('vi-VN') : '' }}
                                </div>
                            </div>
                            <div v-else>
                                <el-tag type="danger" size="small" effect="plain" class="font-medium animate-pulse">
                                    <el-icon class="mr-0.5"><Warning /></el-icon> {{ $t('compliance.needs_action') }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- Thao tác -->
                    <el-table-column :label="$t('common.actions')" width="160" align="center" fixed="right">
                        <template #default="{ row }">
                            <div class="flex items-center justify-center gap-2">
                                <el-button
                                    v-if="!row.is_resolved"
                                    type="primary"
                                    size="small"
                                    @click="openResolveDialog(row)"
                                >
                                    {{ $t('compliance.btn_resolve') }}
                                </el-button>
                                <el-button
                                    type="info"
                                    size="small"
                                    plain
                                    @click="openLegalModal(row.alert_type)"
                                >
                                    {{ $t('compliance.btn_legal') }}
                                </el-button>
                            </div>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- Phân trang -->
                <div v-if="alerts.total > 0" class="flex items-center justify-between pt-2">
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('compliance.pagination_info', { shown: alerts.data.length, total: alerts.total }) }}
                    </div>
                    <el-pagination
                        background
                        layout="prev, pager, next"
                        :current-page="alerts.current_page"
                        :page-count="alerts.last_page"
                        @current-change="(page: number) => router.get('/compliance', { ...filters, page }, { preserveState: true, preserveScroll: true })"
                    />
                </div>
            </div>
        </div>

        <!-- Dialog Ghi Nhận Khắc Phục Vi Phạm -->
        <el-dialog
            v-model="resolveDialogVisible"
            :title="$t('compliance.resolve_dialog_title')"
            width="540px"
            destroy-on-close
        >
            <div v-if="resolvingAlert" class="space-y-4">
                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 space-y-1">
                    <div class="font-semibold text-slate-800 dark:text-slate-100 text-sm">
                        {{ resolvingAlert.title }}
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $t('compliance.resolve_employee_info', { name: resolvingAlert.employee?.full_name || $t('compliance.system_employee'), code: resolvingAlert.employee?.user_code || '' }) }}
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ $t('compliance.resolve_notes_label') }}
                    </label>
                    <el-input
                        v-model="resolveForm.resolution_notes"
                        type="textarea"
                        :rows="4"
                        :placeholder="$t('compliance.resolve_notes_placeholder')"
                    />
                    <p class="mt-1.5 text-[11px] text-slate-400">
                        {{ $t('compliance.resolve_audit_note') }}
                    </p>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <el-button @click="resolveDialogVisible = false">{{ $t('common.cancel') }}</el-button>
                    <el-button
                        type="primary"
                        :loading="resolveForm.processing"
                        @click="submitResolve"
                    >
                        {{ $t('compliance.resolve_confirm_btn') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>

        <!-- Dialog Tra Cứu Căn Cứ Pháp Lý & Mức Phạt Hành Chính -->
        <el-dialog
            v-model="legalDialogVisible"
            :title="$t('compliance.legal_dialog_title')"
            width="620px"
            destroy-on-close
        >
            <div v-if="activeLegalRef" class="space-y-5">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-200 dark:border-slate-700">
                    <el-tag type="danger" effect="dark" size="small" class="font-semibold">
                        {{ getAlertTypeLabel(activeLegalRef.type) }}
                    </el-tag>
                </div>

                <!-- Căn cứ luật -->
                <div class="space-y-1.5">
                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 flex items-center gap-1.5">
                        <Document class="w-3.5 h-3.5" />
                        {{ $t('compliance.legal_section_law') }}
                    </div>
                    <div class="p-3 rounded-lg bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-900/40 text-sm font-medium text-slate-800 dark:text-slate-200">
                        {{ activeLegalRef.details.law }}
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ activeLegalRef.details.decree }}
                        </div>
                    </div>
                </div>

                <!-- Khung xử phạt hành chính -->
                <div class="space-y-1.5">
                    <div class="text-xs font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 flex items-center gap-1.5">
                        <Warning class="w-3.5 h-3.5" />
                        {{ $t('compliance.legal_section_penalty') }}
                    </div>
                    <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/40 text-sm text-rose-800 dark:text-rose-200 leading-relaxed font-medium">
                        {{ activeLegalRef.details.penalty }}
                    </div>
                </div>

                <!-- Giải pháp đề xuất -->
                <div class="space-y-1.5">
                    <div class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                        <CircleCheck class="w-3.5 h-3.5" />
                        {{ $t('compliance.legal_section_solution') }}
                    </div>
                    <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/40 text-sm text-emerald-800 dark:text-emerald-200 leading-relaxed">
                        {{ activeLegalRef.details.solution }}
                    </div>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end">
                    <el-button type="primary" @click="legalDialogVisible = false">{{ $t('compliance.legal_understood_btn') }}</el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
