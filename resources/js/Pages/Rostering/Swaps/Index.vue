<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Switch,
    Check,
    Close,
    Clock,
    WarningFilled,
    User,
    Calendar,
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

interface SwapRequestItem {
    id: number;
    requester: {
        id: number;
        user_code: string;
        full_name: string;
    };
    target_employee: {
        id: number;
        user_code: string;
        full_name: string;
    };
    requester_roster?: {
        id: number;
        roster_date: string;
        shift?: {
            name: string;
            code: string;
            start_time: string;
            end_time: string;
        };
    };
    target_roster?: {
        id: number;
        roster_date: string;
        shift?: {
            name: string;
            code: string;
            start_time: string;
            end_time: string;
        };
    };
    swap_type: 'SWAP' | 'COVER';
    reason: string | null;
    is_legal_compliant: boolean;
    violation_warnings: string[];
    status: 'PENDING_TARGET' | 'PENDING_MANAGER' | 'APPROVED' | 'REJECTED' | 'CANCELLED';
    created_at: string;
}

const props = defineProps<{
    swapRequests: {
        data: SwapRequestItem[];
        current_page: number;
        last_page: number;
        total: number;
    };
}>();
const { t } = useThemeAndLocale();

const handleApprove = (req: SwapRequestItem) => {
    ElMessageBox.confirm(
        t('rostering.swaps_approve_confirm_msg', { from: req.requester.full_name, to: req.target_employee.full_name }),
        t('rostering.swaps_approve_confirm_title'),
        {
            confirmButtonText: t('rostering.swaps_btn_approve'),
            cancelButtonText: t('rostering.btn_review'),
            type: 'success',
        }
    ).then(() => {
        router.post(`/rosters/swaps/${req.id}/approve`, {}, {
            onSuccess: () => {
                ElMessage.success(t('rostering.swaps_approve_success'));
            },
        });
    }).catch(() => {});
};

const handleReject = (req: SwapRequestItem) => {
    ElMessageBox.prompt(t('rostering.swaps_reject_prompt_msg'), t('rostering.swaps_reject_title'), {
        confirmButtonText: t('rostering.swaps_btn_reject'),
        cancelButtonText: t('common.cancel'),
        inputPattern: /\S+/,
        inputErrorMessage: t('rostering.swaps_reject_reason_required'),
    }).then(({ value }) => {
        router.post(`/rosters/swaps/${req.id}/reject`, { reason: value }, {
            onSuccess: () => {
                ElMessage.success(t('rostering.swaps_reject_success'));
            },
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout>
        <Head :title="t('rostering.swaps_page_title')" />

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header Card -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <el-icon :size="22"><Switch /></el-icon>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            {{ $t('rostering.swaps_heading') }}
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $t('rostering.swaps_subtitle') }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                <el-table :data="swapRequests.data" stripe style="width: 100%" class="!text-sm">
                    <el-table-column :label="$t('rostering.swaps_col_requester')" min-width="200">
                        <template #default="{ row }">
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ row.requester.full_name }}
                                </div>
                                <div class="text-xs text-slate-400 font-mono">
                                    {{ row.requester.user_code }}
                                </div>
                                <div v-if="row.requester_roster" class="mt-1 inline-flex items-center space-x-1 text-xs text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 px-2 py-0.5 rounded">
                                    <span class="font-bold">{{ row.requester_roster.roster_date }}:</span>
                                    <span>{{ row.requester_roster.shift?.name }}</span>
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.swaps_col_target')" min-width="200">
                        <template #default="{ row }">
                            <div>
                                <div class="font-bold text-slate-900 dark:text-white">
                                    {{ row.target_employee.full_name }}
                                </div>
                                <div class="text-xs text-slate-400 font-mono">
                                    {{ row.target_employee.user_code }}
                                </div>
                                <div v-if="row.target_roster" class="mt-1 inline-flex items-center space-x-1 text-xs text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/50 px-2 py-0.5 rounded">
                                    <span class="font-bold">{{ row.target_roster.roster_date }}:</span>
                                    <span>{{ row.target_roster.shift?.name }}</span>
                                </div>
                                <div v-else class="mt-1 text-xs text-slate-400 italic">
                                    {{ $t('rostering.swaps_cover_note') }}
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.swaps_col_type')" width="130">
                        <template #default="{ row }">
                            <el-tag :type="row.swap_type === 'SWAP' ? 'primary' : 'warning'" size="small" class="!rounded-md">
                                {{ row.swap_type === 'SWAP' ? $t('rostering.swaps_type_swap') : $t('rostering.swaps_type_cover') }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.swaps_col_compliance')" width="180">
                        <template #default="{ row }">
                            <div v-if="row.is_legal_compliant" class="flex items-center space-x-1 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
                                <el-icon><Check /></el-icon>
                                <span>{{ $t('rostering.swaps_legal_ok') }}</span>
                            </div>
                            <div v-else class="flex flex-col text-xs text-red-600 dark:text-red-400">
                                <div class="flex items-center space-x-1 font-bold">
                                    <el-icon><WarningFilled /></el-icon>
                                    <span>{{ $t('rostering.swaps_legal_warn') }}</span>
                                </div>
                                <div class="text-[10px] text-slate-400 truncate mt-0.5" :title="row.violation_warnings?.join('; ')">
                                    {{ row.violation_warnings?.[0] }}
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.swaps_col_status')" width="140">
                        <template #default="{ row }">
                            <el-tag v-if="row.status === 'APPROVED'" type="success" size="small">{{ $t('rostering.swaps_status_approved') }}</el-tag>
                            <el-tag v-else-if="row.status === 'REJECTED'" type="danger" size="small">{{ $t('rostering.swaps_status_rejected') }}</el-tag>
                            <el-tag v-else type="warning" size="small">{{ $t('rostering.swaps_status_pending') }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.swaps_col_actions')" width="160" fixed="right">
                        <template #default="{ row }">
                            <div v-if="row.status === 'PENDING_MANAGER'" class="flex items-center space-x-2">
                                <el-button size="small" type="success" :icon="Check" circle @click="handleApprove(row)" :title="$t('rostering.swaps_btn_approve')" />
                                <el-button size="small" type="danger" :icon="Close" circle @click="handleReject(row)" :title="$t('rostering.swaps_btn_reject')" />
                            </div>
                            <span v-else class="text-xs text-slate-400 italic">{{ $t('rostering.swaps_done') }}</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    </AppLayout>
</template>
