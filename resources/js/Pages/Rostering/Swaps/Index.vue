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

const handleApprove = (req: SwapRequestItem) => {
    ElMessageBox.confirm(
        `Phê duyệt hoán đổi ca giữa ${req.requester.full_name} và ${req.target_employee.full_name}? Lịch phân ca sẽ tự động được cập nhật.`,
        'Xác nhận Phê duyệt',
        {
            confirmButtonText: 'Phê duyệt',
            cancelButtonText: 'Xem lại',
            type: 'success',
        }
    ).then(() => {
        router.post(`/rosters/swaps/${req.id}/approve`, {}, {
            onSuccess: () => {
                ElMessage.success('Đã phê duyệt yêu cầu đổi ca thành công.');
            },
        });
    }).catch(() => {});
};

const handleReject = (req: SwapRequestItem) => {
    ElMessageBox.prompt('Vui lòng nhập lý do từ chối yêu cầu đổi ca này:', 'Từ chối Đổi ca', {
        confirmButtonText: 'Từ chối',
        cancelButtonText: 'Hủy',
        inputPattern: /\S+/,
        inputErrorMessage: 'Lý do từ chối không được để trống.',
    }).then(({ value }) => {
        router.post(`/rosters/swaps/${req.id}/reject`, { reason: value }, {
            onSuccess: () => {
                ElMessage.success('Đã từ chối yêu cầu đổi ca.');
            },
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout>
        <Head title="Yêu cầu Đổi ca & Bắt ca thay - Smart-HR" />

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header Card -->
            <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                        <el-icon :size="22"><Switch /></el-icon>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                            Yêu Cầu Đổi Ca & Bắt Ca Thay (Shift Swap & Cover)
                        </h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Theo dõi và phê duyệt các yêu cầu hoán đổi ca làm việc của nhân viên, đối soát tự động tính tuân thủ pháp luật
                        </p>
                    </div>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                <el-table :data="swapRequests.data" stripe style="width: 100%" class="!text-sm">
                    <el-table-column label="Người Đề Xuất & Ca Gốc" min-width="200">
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

                    <el-table-column label="Đồng Nghiệp Nhận Đổi" min-width="200">
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
                                    (Nhận ca thay - Cover)
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Hình Thức" width="130">
                        <template #default="{ row }">
                            <el-tag :type="row.swap_type === 'SWAP' ? 'primary' : 'warning'" size="small" class="!rounded-md">
                                {{ row.swap_type === 'SWAP' ? 'Đổi Ca Hai Chiều' : 'Bắt Ca Thay' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Tuân Thủ BLLĐ" width="180">
                        <template #default="{ row }">
                            <div v-if="row.is_legal_compliant" class="flex items-center space-x-1 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
                                <el-icon><Check /></el-icon>
                                <span>Hợp lệ (Đủ 12h/24h)</span>
                            </div>
                            <div v-else class="flex flex-col text-xs text-red-600 dark:text-red-400">
                                <div class="flex items-center space-x-1 font-bold">
                                    <el-icon><WarningFilled /></el-icon>
                                    <span>Có cảnh báo luật</span>
                                </div>
                                <div class="text-[10px] text-slate-400 truncate mt-0.5" :title="row.violation_warnings?.join('; ')">
                                    {{ row.violation_warnings?.[0] }}
                                </div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column label="Trạng Thái" width="140">
                        <template #default="{ row }">
                            <el-tag v-if="row.status === 'APPROVED'" type="success" size="small">Đã Duyệt</el-tag>
                            <el-tag v-else-if="row.status === 'REJECTED'" type="danger" size="small">Từ Chối</el-tag>
                            <el-tag v-else type="warning" size="small">Chờ Duyệt</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column label="Thao Tác" width="160" fixed="right">
                        <template #default="{ row }">
                            <div v-if="row.status === 'PENDING_MANAGER'" class="flex items-center space-x-2">
                                <el-button size="small" type="success" :icon="Check" circle @click="handleApprove(row)" title="Phê duyệt" />
                                <el-button size="small" type="danger" :icon="Close" circle @click="handleReject(row)" title="Từ chối" />
                            </div>
                            <span v-else class="text-xs text-slate-400 italic">Đã hoàn tất</span>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>
    </AppLayout>
</template>
