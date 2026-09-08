<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { Clock, User, Monitor, Document, Refresh } from '@element-plus/icons-vue';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

interface AuditTrail {
    id: number;
    action: string;
    auditable_type: string;
    auditable_id: number;
    old_values: Record<string, any> | null;
    new_values: Record<string, any> | null;
    ip_address: string | null;
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
    } | null;
}

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        auditableType: string;
        auditableId?: number | null;
        title?: string;
    }>(),
    {
        auditableId: null,
    }
);

const { t } = useThemeAndLocale();

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
}>();

const drawerTitle = computed(() => props.title || t('common.audit.drawer_title'));

const loading = ref(false);
const logs = ref<AuditTrail[]>([]);

const fetchAuditLogs = async () => {
    if (!props.auditableType) return;
    loading.value = true;
    try {
        const response = await axios.get('/audit-trails', {
            params: {
                auditable_type: props.auditableType,
                auditable_id: props.auditableId || undefined,
            },
        });
        logs.value = response.data.data || [];
    } catch (error) {
        ElMessage.error(t('common.audit.load_error'));
    } finally {
        loading.value = false;
    }
};

import { onMounted, onUnmounted } from 'vue';

let isHandlingPopstate = false;

watch(
    () => props.modelValue,
    (isOpen, wasOpen) => {
        if (isOpen) {
            fetchAuditLogs();
            if (!isHandlingPopstate) {
                const url = new URL(window.location.href);
                url.hash = 'audit-history';
                window.history.pushState({ drawer: 'audit-history' }, '', url.href);
            }
        } else if (wasOpen) {
            if (window.location.hash === '#audit-history') {
                window.history.back();
            }
        }
        isHandlingPopstate = false;
    }
);

const onPopState = () => {
    if (props.modelValue && window.location.hash !== '#audit-history') {
        isHandlingPopstate = true;
        emit('update:modelValue', false);
    }
};

onMounted(() => {
    window.addEventListener('popstate', onPopState);
});

onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
});

const getActionTagType = (action: string): '' | 'success' | 'warning' | 'danger' | 'info' => {
    switch (action.toUpperCase()) {
        case 'CREATED':
            return 'success';
        case 'UPDATED':
            return 'warning';
        case 'DELETED':
            return 'danger';
        case 'POLICY_VERSIONED':
            return '';
        default:
            return 'info';
    }
};

const actionLabelKeys: Record<string, string> = {
    CREATED: 'common.audit.action_created',
    UPDATED: 'common.audit.action_updated',
    DELETED: 'common.audit.action_deleted',
    STATUS_CHANGED: 'common.audit.action_status_changed',
    POLICY_VERSIONED: 'common.audit.action_policy_versioned',
};

const getActionLabel = (action: string): string => {
    const key = actionLabelKeys[action.toUpperCase()];
    return key ? t(key) : action;
};

// Tính toán các trường bị thay đổi giữa old và new
const getDiffKeys = (oldVal: Record<string, any> | null, newVal: Record<string, any> | null): string[] => {
    const keys = new Set<string>();
    if (oldVal) Object.keys(oldVal).forEach((k) => keys.add(k));
    if (newVal) Object.keys(newVal).forEach((k) => keys.add(k));
    return Array.from(keys).filter(k => k !== 'updated_at' && k !== 'created_at');
};

const formatValue = (val: any): string => {
    if (val === null || val === undefined) return t('common.audit.value_empty');
    if (typeof val === 'boolean') return val ? t('common.audit.value_true') : t('common.audit.value_false');
    if (typeof val === 'object') return JSON.stringify(val);
    return String(val);
};

const handleClose = () => {
    emit('update:modelValue', false);
};
</script>

<template>
    <el-drawer
        :model-value="modelValue"
        :title="drawerTitle"
        direction="rtl"
        size="640px"
        @close="handleClose"
    >
        <template #header>
            <div class="flex items-center justify-between pr-4">
                <div class="flex items-center gap-2">
                    <el-icon class="text-primary text-lg"><Document /></el-icon>
                    <span class="font-bold text-gray-800 dark:text-gray-100 text-base">{{ drawerTitle }}</span>
                </div>
                <el-button size="small" :icon="Refresh" circle :loading="loading" @click="fetchAuditLogs" :title="$t('common.refresh')" />
            </div>
        </template>

        <div v-loading="loading" class="p-2 min-h-[300px]">
            <div v-if="logs.length === 0 && !loading" class="py-16 text-center text-gray-400">
                <el-icon class="text-5xl mb-3"><Document /></el-icon>
                <p>{{ $t('common.audit.empty') }}</p>
            </div>

            <el-timeline v-else class="pr-2">
                <el-timeline-item
                    v-for="log in logs"
                    :key="log.id"
                    :timestamp="log.created_at"
                    placement="top"
                    :type="getActionTagType(log.action)"
                >
                    <el-card shadow="hover" class="!border-gray-200 dark:!border-gray-700 !rounded-xl mb-2">
                        <!-- Card Header: Action + Actor -->
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-2 mb-3">
                            <div class="flex items-center gap-2">
                                <el-tag :type="getActionTagType(log.action)" size="small" effect="dark" class="font-semibold">
                                    {{ getActionLabel(log.action) }}
                                </el-tag>
                                <span class="text-xs text-gray-500 font-mono">#{{ log.id }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300">
                                <el-icon><User /></el-icon>
                                <span class="font-medium">{{ log.user?.name ?? $t('common.audit.system_user') }}</span>
                                <span v-if="log.user?.role" class="text-gray-400 text-[11px]">({{ log.user.role }})</span>
                            </div>
                        </div>

                        <!-- Diff Viewer -->
                        <div v-if="log.action.toUpperCase() === 'UPDATED'" class="space-y-2">
                            <div class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">
                                {{ $t('common.audit.field_changes') }}
                            </div>
                            <div
                                v-for="key in getDiffKeys(log.old_values, log.new_values)"
                                :key="key"
                                class="bg-gray-50 dark:bg-gray-900/60 p-2 rounded-lg border border-gray-100 dark:border-gray-800 text-xs"
                            >
                                <div class="font-mono font-medium text-gray-700 dark:text-gray-300 mb-1">{{ key }}</div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="bg-red-50 dark:bg-red-950/40 text-red-700 dark:text-red-300 p-1.5 rounded border border-red-100 dark:border-red-900/50">
                                        <span class="block text-[10px] uppercase font-bold text-red-400 mb-0.5">{{ $t('common.audit.old_value') }}</span>
                                        <span class="break-all">{{ formatValue(log.old_values?.[key]) }}</span>
                                    </div>
                                    <div class="bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 p-1.5 rounded border border-emerald-100 dark:border-emerald-900/50">
                                        <span class="block text-[10px] uppercase font-bold text-emerald-400 mb-0.5">{{ $t('common.audit.new_value') }}</span>
                                        <span class="break-all">{{ formatValue(log.new_values?.[key]) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- New Record Snapshot -->
                        <div v-else-if="log.action.toUpperCase() === 'CREATED' && log.new_values" class="text-xs">
                            <div class="font-semibold text-gray-600 dark:text-gray-300 mb-1">{{ $t('common.audit.initial_data') }}</div>
                            <div class="bg-gray-50 dark:bg-gray-900/60 p-2.5 rounded-lg border border-gray-100 dark:border-gray-800 space-y-1 font-mono text-[11px]">
                                <div v-for="(val, k) in log.new_values" :key="k" class="flex justify-between py-0.5 border-b border-gray-100 dark:border-gray-800/50 last:border-0">
                                    <span class="text-gray-500">{{ k }}:</span>
                                    <span class="text-gray-800 dark:text-gray-200 font-semibold">{{ formatValue(val) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- IP and Meta info footer -->
                        <div class="mt-3 pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px] text-gray-400">
                            <div class="flex items-center gap-1">
                                <el-icon><Monitor /></el-icon>
                                <span>IP: {{ log.ip_address ?? 'N/A' }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <el-icon><Clock /></el-icon>
                                <span>{{ log.created_at }}</span>
                            </div>
                        </div>
                    </el-card>
                </el-timeline-item>
            </el-timeline>
        </div>
    </el-drawer>
</template>
