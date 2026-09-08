<script setup lang="ts">
import { ref, reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Bell,
    Check,
    Delete,
    Warning,
    CircleCheck,
    InfoFilled,
    Search,
    Promotion,
    Setting,
    Message,
    Connection,
    ArrowRight,
    RefreshRight,
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

interface NotificationItem {
    id: number;
    type: string;
    severity: string;
    title: string;
    content: string;
    action_url: string | null;
    is_read: boolean;
    created_at: string;
    channels_sent: string[] | null;
}

interface NotificationPagination {
    data: NotificationItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface NotificationStats {
    total: number;
    unread: number;
    critical: number;
    warning: number;
}

interface ChannelConfigItem {
    channel: string;
    is_active: boolean;
    settings_json: Record<string, any>;
    event_subscriptions: string[];
}

const props = defineProps<{
    notifications: NotificationPagination;
    stats: NotificationStats;
    filters: {
        filter: string;
        severity?: string;
        type?: string;
        search?: string;
    };
    channelConfigs: Record<string, ChannelConfigItem>;
    canManageChannels: boolean;
    eventTypes: Array<{ value: string; label: string }>;
}>();
const { t } = useThemeAndLocale();

const activeTab = ref('inbox');

// Bộ lọc
const filterForm = reactive({
    filter: props.filters.filter || 'all',
    severity: props.filters.severity || '',
    type: props.filters.type || '',
    search: props.filters.search || '',
});

const applyFilters = () => {
    router.get('/notifications', {
        filter: filterForm.filter,
        severity: filterForm.severity || undefined,
        type: filterForm.type || undefined,
        search: filterForm.search || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handlePageChange = (page: number) => {
    router.get('/notifications', {
        ...filterForm,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Đánh dấu đã đọc
const markAsRead = (item: NotificationItem) => {
    router.post(`/notifications/${item.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (item.action_url) {
                router.visit(item.action_url);
            }
        },
    });
};

// Đánh dấu tất cả đã đọc
const markAllRead = () => {
    router.post('/notifications/read-all', {}, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('notifications.mark_all_read_success'));
        },
    });
};

// Xóa thông báo
const deleteNotification = (item: NotificationItem) => {
    ElMessageBox.confirm(t('notifications.delete_confirm_msg'), t('notifications.delete_confirm_title'), {
        confirmButtonText: t('common.delete'),
        cancelButtonText: t('common.cancel'),
        type: 'warning',
    }).then(() => {
        router.delete(`/notifications/${item.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                ElMessage.success(t('notifications.delete_success'));
            },
        });
    });
};

// State cấu hình kênh
const telegramForm = reactive({
    channel: 'TELEGRAM',
    is_active: props.channelConfigs?.TELEGRAM?.is_active ?? false,
    bot_token: props.channelConfigs?.TELEGRAM?.settings_json?.bot_token ?? '',
    chat_id: props.channelConfigs?.TELEGRAM?.settings_json?.chat_id ?? '',
    event_subscriptions: props.channelConfigs?.TELEGRAM?.event_subscriptions ?? ['COMPLIANCE_CRITICAL_ALERT', 'WORKFLOW_PENDING_APPROVAL'],
});

const webhookForm = reactive({
    channel: 'WEBHOOK',
    is_active: props.channelConfigs?.WEBHOOK?.is_active ?? false,
    webhook_url: props.channelConfigs?.WEBHOOK?.settings_json?.webhook_url ?? '',
    secret_token: props.channelConfigs?.WEBHOOK?.settings_json?.secret_token ?? '',
    event_subscriptions: props.channelConfigs?.WEBHOOK?.event_subscriptions ?? ['COMPLIANCE_CRITICAL_ALERT'],
});

const isTestingTelegram = ref(false);
const isTestingWebhook = ref(false);

const saveTelegramConfig = () => {
    router.post('/notifications/channels/save', {
        channel: 'TELEGRAM',
        is_active: telegramForm.is_active,
        settings_json: {
            bot_token: telegramForm.bot_token,
            chat_id: telegramForm.chat_id,
        },
        event_subscriptions: telegramForm.event_subscriptions,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('notifications.save_telegram_success'));
        },
    });
};

const saveWebhookConfig = () => {
    router.post('/notifications/channels/save', {
        channel: 'WEBHOOK',
        is_active: webhookForm.is_active,
        settings_json: {
            webhook_url: webhookForm.webhook_url,
            secret_token: webhookForm.secret_token,
        },
        event_subscriptions: webhookForm.event_subscriptions,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('notifications.save_webhook_success'));
        },
    });
};

const testTelegram = async () => {
    if (!telegramForm.bot_token || !telegramForm.chat_id) {
        ElMessage.warning(t('notifications.telegram_validate_msg'));
        return;
    }
    isTestingTelegram.value = true;
    try {
        const res = await fetch('/notifications/channels/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                channel: 'TELEGRAM',
                settings_json: {
                    bot_token: telegramForm.bot_token,
                    chat_id: telegramForm.chat_id,
                },
            }),
        });
        const data = await res.json();
        if (data.success) {
            ElMessage.success(data.message);
        } else {
            ElMessage.error(data.message || t('notifications.test_connection_failed'));
        }
    } catch {
        ElMessage.error(t('notifications.server_unreachable'));
    } finally {
        isTestingTelegram.value = false;
    }
};

const testWebhook = async () => {
    if (!webhookForm.webhook_url) {
        ElMessage.warning(t('notifications.webhook_validate_msg'));
        return;
    }
    isTestingWebhook.value = true;
    try {
        const res = await fetch('/notifications/channels/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as any)?.content || '',
            },
            body: JSON.stringify({
                channel: 'WEBHOOK',
                settings_json: {
                    webhook_url: webhookForm.webhook_url,
                    secret_token: webhookForm.secret_token,
                },
            }),
        });
        const data = await res.json();
        if (data.success) {
            ElMessage.success(data.message);
        } else {
            ElMessage.error(data.message || t('notifications.test_connection_failed'));
        }
    } catch {
        ElMessage.error(t('notifications.server_unreachable'));
    } finally {
        isTestingWebhook.value = false;
    }
};

const getSeverityTagType = (severity: string): '' | 'success' | 'warning' | 'danger' | 'info' => {
    switch (severity?.toUpperCase()) {
        case 'CRITICAL': return 'danger';
        case 'WARNING': return 'warning';
        case 'SUCCESS': return 'success';
        default: return 'info';
    }
};

const getSeverityLabel = (severity: string): string => {
    switch (severity?.toUpperCase()) {
        case 'CRITICAL': return t('notifications.tag_critical');
        case 'WARNING': return t('notifications.tag_warning');
        case 'SUCCESS': return t('notifications.tag_success');
        default: return t('notifications.tag_info');
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="$t('notifications.page_title')" />

        <div class="space-y-6">
            <!-- Header Banner -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs">
                <div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                            <el-icon :size="22"><Bell /></el-icon>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 dark:text-white">{{ $t('notifications.title') }}</h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $t('notifications.subtitle') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <button
                        v-if="stats.unread > 0"
                        @click="markAllRead"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900/60 transition flex items-center space-x-1.5 border border-indigo-200 dark:border-indigo-800"
                    >
                        <el-icon :size="14"><Check /></el-icon>
                        <span>{{ $t('header.mark_all_read') }}</span>
                    </button>

                    <button
                        @click="applyFilters"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 border border-slate-200 dark:border-slate-700 transition flex items-center space-x-1.5"
                    >
                        <el-icon :size="14"><RefreshRight /></el-icon>
                        <span>{{ $t('common.refresh') }}</span>
                    </button>
                </div>
            </div>

            <!-- 4 Thẻ KPI Thông Báo -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><Message /></el-icon>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ $t('notifications.kpi_total') }}</div>
                        <div class="text-xl font-bold text-slate-800 dark:text-white">{{ stats.total }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-950/40 text-blue-600 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><Bell /></el-icon>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ $t('notifications.kpi_unread') }}</div>
                        <div class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ stats.unread }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-lg bg-rose-50 dark:bg-rose-950/40 text-rose-600 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><Warning /></el-icon>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ $t('notifications.kpi_critical') }}</div>
                        <div class="text-xl font-bold text-rose-600 dark:text-rose-400">{{ stats.critical }}</div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 flex items-center space-x-3.5">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 flex items-center justify-center shrink-0">
                        <el-icon :size="20"><InfoFilled /></el-icon>
                    </div>
                    <div>
                        <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ $t('notifications.kpi_warning') }}</div>
                        <div class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ stats.warning }}</div>
                    </div>
                </div>
            </div>

            <!-- Tabs Chuyển Đổi: Hộp thư & Cấu hình Kênh -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 px-6 pt-4">
                    <div class="flex space-x-8">
                        <button
                            @click="activeTab = 'inbox'"
                            class="pb-3 text-sm font-bold flex items-center space-x-2 border-b-2 transition"
                            :class="activeTab === 'inbox' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        >
                            <el-icon :size="16"><Message /></el-icon>
                            <span>{{ $t('notifications.tab_inbox') }}</span>
                            <span v-if="stats.unread > 0" class="ml-1.5 px-2 py-0.5 rounded-full text-[10px] bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 font-bold">
                                {{ stats.unread }}
                            </span>
                        </button>

                        <button
                            v-if="canManageChannels"
                            @click="activeTab = 'channels'"
                            class="pb-3 text-sm font-bold flex items-center space-x-2 border-b-2 transition"
                            :class="activeTab === 'channels' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
                        >
                            <el-icon :size="16"><Setting /></el-icon>
                            <span>{{ $t('notifications.tab_channels') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Tab 1: Hộp thư thông báo -->
                <div v-show="activeTab === 'inbox'" class="p-6 space-y-6">
                    <!-- Bộ lọc đa năng -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 bg-slate-50 dark:bg-slate-800/40 p-3.5 rounded-xl border border-slate-200/60 dark:border-slate-800">
                        <el-select
                            v-model="filterForm.filter"
                            :placeholder="$t('notifications.filter_read_status')"
                            class="w-full"
                            @change="applyFilters"
                        >
                            <el-option :label="$t('notifications.filter_all')" value="all" />
                            <el-option :label="$t('notifications.filter_unread_only')" value="unread" />
                            <el-option :label="$t('notifications.filter_read_only')" value="read" />
                        </el-select>

                        <el-select
                            v-model="filterForm.severity"
                            :placeholder="$t('notifications.filter_severity')"
                            clearable
                            class="w-full"
                            @change="applyFilters"
                        >
                            <el-option :label="$t('notifications.severity_critical')" value="CRITICAL" />
                            <el-option :label="$t('notifications.severity_warning')" value="WARNING" />
                            <el-option :label="$t('notifications.severity_success')" value="SUCCESS" />
                            <el-option :label="$t('notifications.severity_info')" value="INFO" />
                        </el-select>

                        <el-select
                            v-model="filterForm.type"
                            :placeholder="$t('notifications.filter_event_type')"
                            clearable
                            class="w-full"
                            @change="applyFilters"
                        >
                            <el-option
                                v-for="item in eventTypes"
                                :key="item.value"
                                :label="item.label"
                                :value="item.value"
                            />
                        </el-select>

                        <el-input
                            v-model="filterForm.search"
                            :placeholder="$t('notifications.search_placeholder')"
                            clearable
                            :prefix-icon="Search"
                            @keyup.enter="applyFilters"
                            @clear="applyFilters"
                        />
                    </div>

                    <!-- Danh sách thông báo -->
                    <div v-if="notifications.data.length > 0" class="space-y-3">
                        <div
                            v-for="item in notifications.data"
                            :key="item.id"
                            class="p-4 rounded-xl border transition flex flex-col md:flex-row md:items-center justify-between gap-3"
                            :class="[
                                item.is_read
                                    ? 'bg-white dark:bg-slate-900 border-slate-200/80 dark:border-slate-800 opacity-80'
                                    : 'bg-indigo-50/40 dark:bg-indigo-950/20 border-indigo-200/80 dark:border-indigo-800/60 shadow-xs'
                            ]"
                        >
                            <div class="flex items-start space-x-3.5 flex-1 min-w-0">
                                <div
                                    class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 mt-0.5"
                                    :class="{
                                        'bg-rose-100 dark:bg-rose-950 text-rose-600': item.severity === 'CRITICAL',
                                        'bg-amber-100 dark:bg-amber-950 text-amber-600': item.severity === 'WARNING',
                                        'bg-emerald-100 dark:bg-emerald-950 text-emerald-600': item.severity === 'SUCCESS',
                                        'bg-indigo-100 dark:bg-indigo-950 text-indigo-600': item.severity === 'INFO' || !item.severity,
                                    }"
                                >
                                    <el-icon :size="18">
                                        <component
                                            :is="item.severity === 'CRITICAL' ? Warning : (item.severity === 'SUCCESS' ? CircleCheck : InfoFilled)"
                                        />
                                    </el-icon>
                                </div>

                                <div class="space-y-1 flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate">
                                            {{ item.title }}
                                        </h3>
                                        <el-tag :type="getSeverityTagType(item.severity)" size="small" effect="plain" class="rounded-full">
                                            {{ getSeverityLabel(item.severity) }}
                                        </el-tag>
                                        <span v-if="!item.is_read" class="w-2 h-2 rounded-full bg-rose-500 shrink-0"></span>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line">
                                        {{ item.content }}
                                    </p>
                                    <div class="flex items-center space-x-3 text-[11px] text-slate-400 pt-0.5">
                                        <span>{{ item.created_at }}</span>
                                        <span v-if="item.channels_sent?.includes('telegram')" class="inline-flex items-center text-blue-500 font-medium">
                                            {{ $t('notifications.sent_telegram') }}
                                        </span>
                                        <span v-if="item.channels_sent?.includes('webhook')" class="inline-flex items-center text-purple-500 font-medium">
                                            {{ $t('notifications.sent_webhook') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Hành động -->
                            <div class="flex items-center space-x-2 shrink-0 self-end md:self-center pt-2 md:pt-0">
                                <button
                                    v-if="item.action_url"
                                    @click="markAsRead(item)"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center space-x-1"
                                >
                                    <span>{{ $t('notifications.btn_handle_now') }}</span>
                                    <el-icon :size="12"><ArrowRight /></el-icon>
                                </button>

                                <button
                                    v-if="!item.is_read"
                                    @click="markAsRead(item)"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-slate-100 dark:hover:bg-slate-800 transition"
                                    :title="$t('notifications.mark_read_tooltip')"
                                >
                                    <el-icon :size="16"><Check /></el-icon>
                                </button>

                                <button
                                    @click="deleteNotification(item)"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 transition"
                                    :title="$t('notifications.delete_tooltip')"
                                >
                                    <el-icon :size="16"><Delete /></el-icon>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-center py-16 bg-slate-50 dark:bg-slate-800/20 rounded-2xl border border-dashed border-slate-200 dark:border-slate-800">
                        <el-icon :size="48" class="text-slate-300 dark:text-slate-600 mb-2"><Bell /></el-icon>
                        <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ $t('notifications.empty_title') }}</h4>
                        <p class="text-xs text-slate-400 mt-1">{{ $t('notifications.empty_desc') }}</p>
                    </div>

                    <!-- Phân trang -->
                    <div v-if="notifications.total > notifications.per_page" class="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
                        <el-pagination
                            background
                            layout="prev, pager, next"
                            :total="notifications.total"
                            :page-size="notifications.per_page"
                            :current-page="notifications.current_page"
                            @current-change="handlePageChange"
                        />
                    </div>
                </div>

                <!-- Tab 2: Cấu hình Kênh gửi ngoài (Telegram & Webhook) -->
                <div v-if="canManageChannels" v-show="activeTab === 'channels'" class="p-6 space-y-8">
                    <div class="max-w-4xl space-y-6">
                        <!-- Card 1: Telegram Bot Dispatcher -->
                        <div class="p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-5">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-500 flex items-center justify-center">
                                        <el-icon :size="22"><Promotion /></el-icon>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-800 dark:text-white">{{ $t('notifications.telegram_channel_title') }}</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('notifications.telegram_channel_desc') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs font-medium text-slate-500">{{ telegramForm.is_active ? $t('notifications.channel_enabled') : $t('notifications.channel_disabled') }}</span>
                                    <el-switch v-model="telegramForm.is_active" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {{ $t('notifications.telegram_bot_token_label') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <el-input
                                        v-model="telegramForm.bot_token"
                                        :placeholder="$t('notifications.telegram_ph_bot_token')"
                                        show-password
                                    />
                                    <span class="text-[11px] text-slate-400 mt-1 block">{{ $t('notifications.telegram_botfather_hint') }}</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {{ $t('notifications.telegram_chat_id_label') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <el-input
                                        v-model="telegramForm.chat_id"
                                        :placeholder="$t('notifications.telegram_ph_chat_id')"
                                    />
                                    <span class="text-[11px] text-slate-400 mt-1 block">{{ $t('notifications.telegram_chat_id_hint') }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                    {{ $t('notifications.subscribe_telegram') }}
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                    <el-checkbox-group v-model="telegramForm.event_subscriptions">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full">
                                            <el-checkbox
                                                v-for="item in eventTypes"
                                                :key="item.value"
                                                :label="item.value"
                                            >
                                                {{ item.label }}
                                            </el-checkbox>
                                        </div>
                                    </el-checkbox-group>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    @click="testTelegram"
                                    :disabled="isTestingTelegram"
                                    class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 border border-slate-200 dark:border-slate-700 transition flex items-center space-x-1.5"
                                >
                                    <el-icon :size="14"><Connection /></el-icon>
                                    <span>{{ isTestingTelegram ? $t('notifications.btn_sending') : $t('notifications.btn_test') }}</span>
                                </button>

                                <button
                                    @click="saveTelegramConfig"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center space-x-1.5"
                                >
                                    <el-icon :size="14"><Check /></el-icon>
                                    <span>{{ $t('notifications.save_telegram_btn') }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Card 2: Webhook Dispatcher -->
                        <div class="p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-5">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-500 flex items-center justify-center">
                                        <el-icon :size="22"><Connection /></el-icon>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-slate-800 dark:text-white">{{ $t('notifications.webhook_channel_title') }}</h3>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('notifications.webhook_channel_desc') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <span class="text-xs font-medium text-slate-500">{{ webhookForm.is_active ? $t('notifications.channel_enabled') : $t('notifications.channel_disabled') }}</span>
                                    <el-switch v-model="webhookForm.is_active" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {{ $t('notifications.webhook_url_label') }} <span class="text-rose-500">*</span>
                                    </label>
                                    <el-input
                                        v-model="webhookForm.webhook_url"
                                        placeholder="https://api.domain.com/webhook/smarthr"
                                    />
                                    <span class="text-[11px] text-slate-400 mt-1 block">{{ $t('notifications.webhook_method_hint') }}</span>
                                </div>

                                <div>
                                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
                                        {{ $t('notifications.webhook_secret_label') }}
                                    </label>
                                    <el-input
                                        v-model="webhookForm.secret_token"
                                        :placeholder="$t('notifications.webhook_ph_secret')"
                                        show-password
                                    />
                                    <span class="text-[11px] text-slate-400 mt-1 block">{{ $t('notifications.webhook_signature_hint') }}</span>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-2">
                                    {{ $t('notifications.subscribe_webhook') }}
                                </label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800">
                                    <el-checkbox-group v-model="webhookForm.event_subscriptions">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full">
                                            <el-checkbox
                                                v-for="item in eventTypes"
                                                :key="item.value"
                                                :label="item.value"
                                            >
                                                {{ item.label }}
                                            </el-checkbox>
                                        </div>
                                    </el-checkbox-group>
                                </div>
                            </div>

                            <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                                <button
                                    @click="testWebhook"
                                    :disabled="isTestingWebhook"
                                    class="px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 border border-slate-200 dark:border-slate-700 transition flex items-center space-x-1.5"
                                >
                                    <el-icon :size="14"><Connection /></el-icon>
                                    <span>{{ isTestingWebhook ? $t('notifications.btn_sending') : $t('notifications.btn_test') }}</span>
                                </button>

                                <button
                                    @click="saveWebhookConfig"
                                    class="px-4 py-2 rounded-xl text-xs font-semibold bg-indigo-600 hover:bg-indigo-700 text-white transition flex items-center space-x-1.5"
                                >
                                    <el-icon :size="14"><Check /></el-icon>
                                    <span>{{ $t('notifications.save_webhook_btn') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
