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

import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

const { t } = useThemeAndLocale();

const activeSection = ref<'timeline' | 'contracts'>('timeline');

// Modal: Record HR change
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
            ElMessage.success(t('core_hr.msg_timeline_success'));
        },
        onError: () => {
            ElMessage.error(t('core_hr.msg_timeline_error'));
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

const eventLabelKeys: Record<string, string> = {
    ONBOARDING: 'timeline_onboarding',
    PROMOTION: 'timeline_promotion',
    TRANSFER: 'timeline_transfer',
    SALARY_ADJUSTMENT: 'timeline_salary_change',
    DEMOTION: 'timeline_demotion',
    TERMINATION: 'timeline_resignation',
};

const getEventLabel = (type: string) => {
    const key = eventLabelKeys[type];
    return key ? t(`core_hr.${key}`) : type;
};

const timelineEventOptions = [
    { value: 'PROMOTION', labelKey: 'core_hr.timeline_promotion' },
    { value: 'TRANSFER', labelKey: 'core_hr.timeline_transfer' },
    { value: 'SALARY_ADJUSTMENT', labelKey: 'core_hr.timeline_salary_change' },
    { value: 'DEMOTION', labelKey: 'core_hr.timeline_demotion' },
    { value: 'TERMINATION', labelKey: 'core_hr.timeline_resignation' },
];

const getStatusTagType = (status: string) => {
    switch (status) {
        case 'OFFICIAL': return 'success';
        case 'PROBATION': return 'warning';
        case 'SUSPENDED': return 'danger';
        case 'TERMINATED': return 'info';
        default: return 'info';
    }
};

const statusLabelKeys: Record<string, string> = {
    OFFICIAL: 'status_official',
    PROBATION: 'status_probation',
    SUSPENDED: 'status_suspended',
    TERMINATED: 'status_terminated',
};

const getStatusLabel = (status: string) => {
    const key = statusLabelKeys[status];
    return key ? t(`core_hr.${key}`) : status;
};
</script>

<template>
    <AppLayout>
        <Head :title="$t('core_hr.profile_doc_title', { name: employee.full_name, code: employee.user_code })" />

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
                            {{ $t('core_hr.profile_joined_subtitle', { date: employee.join_date }) }}
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
                        {{ $t('core_hr.btn_add_career_event') }}
                    </el-button>
                </div>
            </div>

            <!-- Legal Protection Alert (Article 137 of the 2019 Labor Code) -->
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
                            {{ $t('core_hr.legal_protection_title') }}
                        </h4>
                        <div v-if="employee.is_pregnant_restricted">
                            <span class="font-semibold text-rose-700 dark:text-rose-300">{{ $t('core_hr.legal_hard_constraint_label') }}</span>
                            {{ $t('core_hr.legal_hard_constraint_desc') }}
                            <strong>{{ $t('core_hr.legal_never_label') }}</strong> {{ $t('core_hr.legal_never_desc') }}
                        </div>
                        <div v-else-if="employee.is_child_under_12m">
                            <span class="font-semibold text-amber-700 dark:text-amber-300">{{ $t('core_hr.legal_child_12m_label') }}</span>
                            {{ $t('core_hr.legal_child_12m_born', { date: employee.child_birth_date }) }}
                            {{ $t('core_hr.legal_child_12m_ban_prefix') }} <strong>{{ $t('core_hr.legal_child_12m_except_label') }}</strong> {{ $t('core_hr.legal_child_12m_except_desc') }}
                            {{ $t('core_hr.legal_child_12m_reduced_hour') }}
                        </div>
                        <div v-else-if="employee.is_pregnant">
                            <span class="font-semibold">{{ $t('core_hr.legal_pregnancy_label') }}</span>
                            {{ $t('core_hr.legal_pregnancy_desc', { date: employee.pregnancy_start_date }) }}
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
                                        {{ employee.gender === 'FEMALE' ? $t('core_hr.gender_female') : (employee.gender === 'MALE' ? $t('core_hr.gender_male') : $t('core_hr.gender_other')) }}
                                    </span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                                        {{ $t('core_hr.profile_birth', { date: employee.birth_date }) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">{{ $t('core_hr.label_emp_code') }}:</span>
                                <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ employee.user_code }}</span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">{{ $t('core_hr.label_phone') }}:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ employee.phone || $t('common.not_updated') }}</span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">{{ $t('core_hr.label_join_date') }}:</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200">{{ employee.join_date }}</span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">{{ $t('core_hr.profile_child_status') }}:</span>
                                <span class="font-medium" :class="employee.is_child_under_12m ? 'text-amber-600 font-bold' : 'text-slate-800 dark:text-slate-200'">
                                    {{ employee.child_birth_date ? $t('core_hr.profile_child_birth', { date: employee.child_birth_date }) : $t('common.no') }}
                                </span>
                            </div>
                            <div class="py-3 flex justify-between">
                                <span class="text-slate-500 dark:text-slate-400">{{ $t('core_hr.profile_maternity') }}:</span>
                                <span class="font-medium" :class="employee.is_pregnant ? 'text-indigo-600 font-bold' : 'text-slate-800 dark:text-slate-200'">
                                    {{ employee.is_pregnant ? $t('core_hr.badge_pregnant') : $t('core_hr.badge_normal') }}
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
                                {{ $t('core_hr.tab_timeline') }}
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
                                {{ $t('core_hr.tab_contracts') }}
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
                                    {{ $t('core_hr.profile_career_timeline') }}
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $t('core_hr.timeline_subtitle') }}
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
                                {{ $t('core_hr.btn_add_timeline_entry') }}
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
                                            {{ $t('core_hr.timeline_active_badge') }}
                                        </span>
                                    </div>

                                    <h4 class="text-base font-bold text-slate-800 dark:text-slate-100">
                                        {{ item.position_name }} - {{ item.department_name }}
                                    </h4>

                                    <p v-if="item.job_title" class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">
                                        {{ $t('core_hr.timeline_job_title') }}: {{ item.job_title }}
                                    </p>

                                    <div class="mt-3 pt-2 border-t border-slate-200/50 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 space-y-1">
                                        <div v-if="item.manager_name" class="flex items-center gap-1.5">
                                            <el-icon :size="12"><User /></el-icon>
                                            <span>{{ $t('core_hr.timeline_reports_to') }}: <strong>{{ item.manager_name }}</strong></span>
                                        </div>
                                        <div v-if="item.decision_number" class="flex items-center gap-1.5">
                                            <el-icon :size="12"><Document /></el-icon>
                                            <span>{{ $t('core_hr.timeline_decision', { number: item.decision_number, date: item.decision_date }) }}</span>
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
                                    {{ $t('core_hr.contracts_section_title') }}
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $t('core_hr.contracts_section_subtitle') }}
                                </p>
                            </div>
                        </div>

                        <el-table :data="contracts" size="small" style="width: 100%" stripe>
                            <el-table-column prop="contract_number" :label="$t('core_hr.col_contract_number')" width="140">
                                <template #default="{ row }">
                                    <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ row.contract_number }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="contract_type" :label="$t('core_hr.col_contract_type')">
                                <template #default="{ row }">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800">
                                        {{ row.contract_type }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column prop="start_date" :label="$t('core_hr.col_contract_start')" width="110" />
                            <el-table-column prop="end_date" :label="$t('core_hr.col_contract_end')" width="110" />
                            <el-table-column prop="status" :label="$t('core_hr.col_status')" width="110">
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

        <!-- MODAL: Record new career change (Effective Dating) -->
        <el-dialog
            v-model="isAddTimelineModalOpen"
            :title="$t('core_hr.dialog_timeline_title')"
            width="560px"
        >
            <form @submit.prevent="submitTimelineEvent" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ $t('core_hr.label_event_type') }} <span class="text-rose-500">*</span>
                    </label>
                    <select
                        v-model="timelineForm.event_type"
                        class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        required
                    >
                        <option v-for="opt in timelineEventOptions" :key="opt.value" :value="opt.value">{{ $t(opt.labelKey) }}</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_applicable_dept') }} <span class="text-rose-500">*</span>
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
                            {{ $t('common.position') }} <span class="text-rose-500">*</span>
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
                            {{ $t('core_hr.label_direct_manager') }}
                        </label>
                        <select
                            v-model="timelineForm.manager_id"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        >
                            <option :value="null">{{ $t('core_hr.select_no_manager') }}</option>
                            <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.full_name }} ({{ m.user_code }})</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_internal_job_title') }}
                        </label>
                        <input
                            v-model="timelineForm.job_title"
                            type="text"
                            :placeholder="$t('core_hr.ph_internal_job_title')"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                            {{ $t('core_hr.label_effective_date') }} <span class="text-rose-500">*</span>
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
                            {{ $t('core_hr.label_decision_issued') }}
                        </label>
                        <input
                            v-model="timelineForm.decision_number"
                            type="text"
                            :placeholder="$t('core_hr.ph_decision_issued')"
                            class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                        {{ $t('core_hr.label_notes_reason') }}
                    </label>
                    <textarea
                        v-model="timelineForm.notes"
                        rows="2"
                        :placeholder="$t('core_hr.ph_timeline_notes')"
                        class="w-full px-3 py-2 border rounded-xl bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-700 text-xs focus:ring-2 focus:ring-indigo-500"
                    ></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-slate-200 dark:border-slate-800">
                    <el-button @click="isAddTimelineModalOpen = false">{{ $t('core_hr.btn_cancel') }}</el-button>
                    <el-button
                        type="primary"
                        native-type="submit"
                        :loading="timelineForm.processing"
                    >
                        {{ $t('core_hr.btn_save_decision') }}
                    </el-button>
                </div>
            </form>
        </el-dialog>
    </AppLayout>
</template>
