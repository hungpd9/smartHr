<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';
import { ElMessage } from 'element-plus';
import {
    Lock,
    Key,
    Check,
    Close,
    UploadFilled,
    Connection,
    Cpu,
    DocumentChecked,
    CopyDocument,
    View,
    Hide,
    Download,
    RefreshRight,
    CircleCheckFilled,
    WarningFilled,
    Tools,
    Compass
} from '@element-plus/icons-vue';

interface LicenseInfo {
    is_valid: boolean;
    status: 'ACTIVE' | 'EXPIRED' | 'NOT_INSTALLED';
    customer_name: string;
    allowed_domain: string;
    expires_at: string | null;
    is_expired: boolean;
    days_remaining: number;
    issued_at?: string | null;
    shared_hmac_secret?: string | null;
    shared_hmac_secret_masked?: string | null;
    signature_preview?: string | null;
    raw_signature?: string | null;
}

interface SystemStatus {
    current_domain: string;
    dek_active: boolean;
    crypto_engine: string;
    sodium_available: boolean;
    openssl_gcm_available: boolean;
    php_version: string;
    license_file_exists: boolean;
}

interface BranchItem {
    id: number;
    name: string;
    code: string;
    address?: string | null;
    secret_key?: string | null;
    is_active: boolean;
}

interface RecentLog {
    id: number;
    employee_id: number;
    employee?: {
        id: number;
        user_code: string;
        full_name: string;
    } | null;
    punch_time: string;
    punch_state: string;
    device_sn: string;
    verification_mode: string;
    sync_batch_id: string;
    created_at: string;
}

const props = defineProps<{
    license: LicenseInfo;
    system_status: SystemStatus;
    branches: BranchItem[];
    recent_logs: RecentLog[];
    stats: {
        total_raw_today: number;
        total_batches_all: number;
    };
}>();

const { t } = useThemeAndLocale();
const page = usePage();

const activeTab = ref('overview');

// Upload License Form State
const uploadMode = ref<'file' | 'text'>('file');
const fileList = ref<any[]>([]);
const licenseTextContent = ref('');
const isSubmittingUpload = ref(false);

const handleFileChange = (file: any) => {
    fileList.value = [file];
};

const submitUpload = () => {
    isSubmittingUpload.value = true;
    const formData = new FormData();

    if (uploadMode.value === 'file' && fileList.value.length > 0) {
        formData.append('license_file', fileList.value[0].raw);
    } else if (uploadMode.value === 'text' && licenseTextContent.value.trim()) {
        formData.append('license_content', licenseTextContent.value.trim());
    } else {
        ElMessage.warning(t('licensing.upload_select_warning'));
        isSubmittingUpload.value = false;
        return;
    }

    router.post('/licensing/upload', formData, {
        preserveScroll: true,
        onSuccess: () => {
            fileList.value = [];
            licenseTextContent.value = '';
            ElMessage.success(t('licensing.upload_success'));
        },
        onError: (err) => {
            ElMessage.error(err.message || t('licensing.upload_failed'));
        },
        onFinish: () => {
            isSubmittingUpload.value = false;
        }
    });
};

// Crypto Sandbox State
const sandboxInput = ref('001202008899'); // Mẫu số CCCD
const sandboxDomain = ref('');
const sandboxCiphertext = ref('');
const sandboxPlaintext = ref('');
const isEncrypting = ref(false);
const isDecrypting = ref(false);

const runEncryptTest = async () => {
    if (!sandboxInput.value.trim()) {
        ElMessage.warning(t('licensing.encrypt_input_warning'));
        return;
    }

    isEncrypting.value = true;
    sandboxCiphertext.value = '';
    sandboxPlaintext.value = '';

    try {
        const response = await fetch('/licensing/test-crypto', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (page.props as any).csrf_token || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                action: 'encrypt',
                data: sandboxInput.value.trim(),
                custom_domain: sandboxDomain.value.trim() || undefined,
            })
        });

        const res = await response.json();
        if (response.ok && res.status === 'success') {
            sandboxCiphertext.value = res.ciphertext;
            ElMessage.success(t('licensing.encrypt_success'));
        } else {
            ElMessage.error(res.message || t('licensing.encrypt_error'));
        }
    } catch (e: any) {
        ElMessage.error(t('licensing.encrypt_conn_error', { msg: e.message }));
    } finally {
        isEncrypting.value = false;
    }
};

const runDecryptTest = async () => {
    if (!sandboxCiphertext.value.trim()) {
        ElMessage.warning(t('licensing.decrypt_input_warning'));
        return;
    }

    isDecrypting.value = true;
    sandboxPlaintext.value = '';

    try {
        const response = await fetch('/licensing/test-crypto', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (page.props as any).csrf_token || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                action: 'decrypt',
                data: sandboxCiphertext.value.trim(),
                custom_domain: sandboxDomain.value.trim() || undefined,
            })
        });

        const res = await response.json();
        if (response.ok && res.status === 'success') {
            sandboxPlaintext.value = res.plaintext;
            ElMessage.success(t('licensing.decrypt_success'));
        } else {
            ElMessage.error(res.message || t('licensing.decrypt_error'));
        }
    } catch (e: any) {
        ElMessage.error(t('licensing.decrypt_conn_error', { msg: e.message }));
    } finally {
        isDecrypting.value = false;
    }
};

// Edge Secret Visibility State
const showSecret = ref(false);

const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text);
    ElMessage.success(t('licensing.copy_success'));
};

// Modal chi tiết chữ ký Ed25519
const isAuditModalOpen = ref(false);
</script>

<template>
    <AppLayout>
        <Head :title="$t('licensing.title')" />

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header Banner Doanh Nghiệp -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 shadow-xl border border-slate-800">
                <div class="absolute -right-10 -bottom-10 opacity-10 text-indigo-400 pointer-events-none">
                    <Lock class="w-72 h-72" />
                </div>
                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1.5">
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mr-1.5 animate-pulse"></span>
                                Enterprise Cryptographic Anchoring
                            </span>
                            <span class="text-xs text-slate-400">Ed25519 Verified</span>
                        </div>
                        <h1 class="text-2xl font-bold tracking-tight text-white flex items-center gap-2">
                            {{ $t('licensing.title') }}
                        </h1>
                        <p class="text-sm text-slate-300 max-w-2xl">
                            {{ $t('licensing.subtitle') }}
                        </p>
                    </div>

                    <!-- Nút Thao Tác Nhanh -->
                    <div class="flex items-center gap-3 shrink-0">
                        <el-button
                            type="success"
                            plain
                            @click="isAuditModalOpen = true"
                            :icon="DocumentChecked"
                        >
                            {{ $t('licensing.integrity_check') }}
                        </el-button>
                        <a href="/licensing/sample-config" download="config.json">
                            <el-button type="primary" :icon="Download">
                                {{ $t('licensing.download_config') }}
                            </el-button>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tabs Điều Hướng Nghiệp Vụ -->
            <el-tabs v-model="activeTab" class="bg-white dark:bg-slate-900 rounded-xl p-4 shadow-xs border border-slate-200/80 dark:border-slate-800">
                <!-- ================= TAB 1: TỔNG QUAN BẢN QUYỀN ================= -->
                <el-tab-pane :label="$t('licensing.tab_overview')" name="overview">
                    <div class="space-y-6 pt-2">
                        <!-- Hero Card Trạng Thái Bản Quyền -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <!-- Card 1: Trạng thái & Doanh nghiệp -->
                            <div class="md:col-span-2 p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-gradient-to-br from-slate-50 to-emerald-50/20 dark:from-slate-900 dark:to-emerald-950/20 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                                                <el-icon :size="18"><Lock /></el-icon>
                                            </div>
                                            <div>
                                                <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $t('licensing.issuing_org_label') }}</div>
                                                <div class="font-bold text-slate-900 dark:text-white text-base">{{ license.customer_name }}</div>
                                            </div>
                                        </div>
                                        <el-tag :type="license.is_valid ? 'success' : 'danger'" effect="dark" round>
                                            {{ license.is_valid ? $t('licensing.license_valid') : (license.is_expired ? $t('licensing.license_expired') : $t('licensing.license_inactive')) }}
                                        </el-tag>
                                    </div>
                                    <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1 mt-4">
                                        <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                                            <span class="text-slate-400">{{ $t('licensing.allowed_domain_label') }}</span>
                                            <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">{{ license.allowed_domain }}</span>
                                        </div>
                                        <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800">
                                            <span class="text-slate-400">{{ $t('licensing.current_domain_label') }}</span>
                                            <span class="font-mono text-slate-700 dark:text-slate-300">{{ system_status.current_domain }}</span>
                                        </div>
                                        <div class="flex justify-between py-1">
                                            <span class="text-slate-400">{{ $t('licensing.signature_label') }}</span>
                                            <span class="font-mono text-xs text-emerald-600 dark:text-emerald-400 truncate max-w-[200px]" :title="license.raw_signature || ''">
                                                {{ license.signature_preview || $t('licensing.no_signature') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card 2: Thời hạn giấy phép -->
                            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col justify-between">
                                <div>
                                    <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $t('licensing.validity_title') }}</div>
                                    <div class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ license.days_remaining }} <span class="text-sm font-normal text-slate-500">{{ $t('licensing.days_left') }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $t('licensing.expires_at_label') }} <span class="font-medium text-slate-700 dark:text-slate-300">{{ license.expires_at || $t('licensing.unlimited') }}</span>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                                    <span class="text-slate-400">{{ $t('licensing.issued_at_label') }}</span>
                                    <span class="font-medium text-slate-700 dark:text-slate-300">{{ license.issued_at || 'N/A' }}</span>
                                </div>
                            </div>

                            <!-- Card 3: Khóa Mật Mã CSDL (DEK) -->
                            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between">
                                        <div class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ $t('licensing.dek_title') }}</div>
                                        <span class="relative flex h-2.5 w-2.5">
                                            <span v-if="system_status.dek_active" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span :class="['relative inline-flex rounded-full h-2.5 w-2.5', system_status.dek_active ? 'bg-emerald-500' : 'bg-red-500']"></span>
                                        </span>
                                    </div>
                                    <div class="mt-2 text-lg font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                        <el-icon class="text-emerald-500" :size="20"><Cpu /></el-icon>
                                        {{ system_status.dek_active ? 'ACTIVE (256-bit)' : 'LOCKED' }}
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $t('licensing.algorithm_label') }} <span class="font-mono text-slate-700 dark:text-slate-300">{{ system_status.crypto_engine }}</span>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs">
                                    <span class="text-slate-400">{{ $t('licensing.db_status_label') }}</span>
                                    <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $t('licensing.db_protected') }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Khối Nạp / Thay Thế Giấy Phép (Upload smart-hr.lic) -->
                        <div class="p-6 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="space-y-0.5">
                                    <h3 class="text-base font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                        <el-icon class="text-indigo-500"><Key /></el-icon>
                                        {{ $t('licensing.upload_title') }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        {{ $t('licensing.upload_desc') }}
                                    </p>
                                </div>
                                <el-radio-group v-model="uploadMode" size="small">
                                    <el-radio-button value="file">{{ $t('licensing.upload_file_tab') }}</el-radio-button>
                                    <el-radio-button value="text">{{ $t('licensing.upload_text_tab') }}</el-radio-button>
                                </el-radio-group>
                            </div>

                            <!-- Upload tệp -->
                            <div v-if="uploadMode === 'file'" class="max-w-xl mx-auto">
                                <el-upload
                                    drag
                                    action="#"
                                    :auto-upload="false"
                                    :file-list="fileList"
                                    :on-change="handleFileChange"
                                    :limit="1"
                                    accept=".lic,.json"
                                    class="w-full"
                                >
                                    <el-icon class="el-icon--upload"><upload-filled /></el-icon>
                                    <div class="el-upload__text">
                                        {{ $t('licensing.upload_drag_prefix') }} <em>smart-hr.lic</em> {{ $t('licensing.upload_drag_mid') }} <em>{{ $t('licensing.upload_drag_click') }}</em>
                                    </div>
                                    <template #tip>
                                        <div class="el-upload__tip text-center text-xs text-slate-400">
                                            {{ $t('licensing.upload_format_hint') }}
                                        </div>
                                    </template>
                                </el-upload>
                            </div>

                            <!-- Nhập chuỗi text -->
                            <div v-else class="max-w-2xl mx-auto space-y-2">
                                <el-input
                                    v-model="licenseTextContent"
                                    type="textarea"
                                    :rows="5"
                                    :placeholder="$t('licensing.upload_textarea_placeholder')"
                                    class="font-mono text-xs"
                                />
                            </div>

                            <div class="flex justify-center pt-2">
                                <el-button
                                    type="primary"
                                    size="large"
                                    :loading="isSubmittingUpload"
                                    @click="submitUpload"
                                    class="px-8"
                                    :icon="DocumentChecked"
                                >
                                    {{ $t('licensing.activate_now_btn') }}
                                </el-button>
                            </div>
                        </div>
                    </div>
                </el-tab-pane>

                <!-- ================= TAB 2: MẬT MÃ CSDL & SANDBOX NEO DỮ LIỆU ================= -->
                <el-tab-pane :label="$t('licensing.tab_crypto')" name="crypto">
                    <div class="space-y-6 pt-2">
                        <!-- Sơ đồ giải thích Cryptographic Data Anchoring -->
                        <div class="p-5 rounded-xl border border-indigo-100 dark:border-indigo-900/40 bg-indigo-50/40 dark:bg-indigo-950/20 space-y-3">
                            <div class="flex items-center space-x-2 text-indigo-900 dark:text-indigo-300 font-semibold text-sm">
                                <el-icon :size="18"><Cpu /></el-icon>
                                <span>{{ $t('licensing.anchoring_title') }}</span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-xs">
                                <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200/80 dark:border-slate-800">
                                    <div class="text-slate-400 font-mono">1. Salt (Domain)</div>
                                    <div class="font-semibold text-slate-800 dark:text-slate-200 mt-1 font-mono truncate">{{ license.allowed_domain }}</div>
                                </div>
                                <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200/80 dark:border-slate-800">
                                    <div class="text-slate-400 font-mono">2. IKM (Ed25519 Sig)</div>
                                    <div class="font-semibold text-emerald-600 dark:text-emerald-400 mt-1 font-mono truncate">{{ license.signature_preview || 'Verified' }}</div>
                                </div>
                                <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200/80 dark:border-slate-800">
                                    <div class="text-slate-400 font-mono">{{ $t('licensing.crypto_step3') }}</div>
                                    <div class="font-semibold text-indigo-600 dark:text-indigo-400 mt-1 font-mono">HKDF-SHA256 (32 bytes)</div>
                                </div>
                                <div class="p-3 bg-white dark:bg-slate-900 rounded-lg border border-slate-200/80 dark:border-slate-800">
                                    <div class="text-slate-400 font-mono">{{ $t('licensing.crypto_step4') }}</div>
                                    <div class="font-semibold text-purple-600 dark:text-purple-400 mt-1 font-mono">AES-256-GCM + IV 12B</div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 italic">
                                * {{ $t('licensing.anchoring_warning') }}
                            </p>
                        </div>

                        <!-- Sandbox Kiểm Thử Trực Tiếp -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Bên Trái: Nhập liệu & Mã hóa -->
                            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-semibold text-sm text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                        <el-icon class="text-emerald-500"><Lock /></el-icon>
                                        {{ $t('licensing.sandbox_encrypt_title') }}
                                    </h4>
                                    <span class="text-xs text-slate-400 font-mono">AES-256-GCM</span>
                                </div>

                                <div class="space-y-3 text-xs">
                                    <div>
                                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $t('licensing.data_to_protect_label') }}</label>
                                        <el-input v-model="sandboxInput" :placeholder="$t('licensing.encrypt_input_placeholder')" />
                                    </div>
                                    <div>
                                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $t('licensing.salt_domain_label') }}</label>
                                        <el-input v-model="sandboxDomain" :placeholder="license.allowed_domain || 'smart-hr.test'" />
                                    </div>
                                    <div class="pt-1">
                                        <el-button type="primary" :loading="isEncrypting" @click="runEncryptTest" class="w-full">
                                            {{ $t('licensing.encrypt_btn') }}
                                        </el-button>
                                    </div>
                                    <div v-if="sandboxCiphertext" class="space-y-1 pt-2">
                                        <label class="block font-medium text-emerald-600 dark:text-emerald-400">{{ $t('licensing.ciphertext_result_label') }}</label>
                                        <div class="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 font-mono text-xs break-all select-all text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700">
                                            {{ sandboxCiphertext }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bên Phải: Giải mã đối soát -->
                            <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-semibold text-sm text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                        <el-icon class="text-indigo-500"><Key /></el-icon>
                                        {{ $t('licensing.sandbox_decrypt_title') }}
                                    </h4>
                                    <span class="text-xs text-slate-400 font-mono">HKDF DEK Verification</span>
                                </div>

                                <div class="space-y-3 text-xs">
                                    <div>
                                        <label class="block font-medium text-slate-700 dark:text-slate-300 mb-1">{{ $t('licensing.ciphertext_input_label') }}</label>
                                        <el-input
                                            v-model="sandboxCiphertext"
                                            type="textarea"
                                            :rows="4"
                                            :placeholder="$t('licensing.ciphertext_input_placeholder')"
                                            class="font-mono text-xs"
                                        />
                                    </div>
                                    <div class="pt-1">
                                        <el-button type="success" :loading="isDecrypting" @click="runDecryptTest" class="w-full" :disabled="!sandboxCiphertext">
                                            {{ $t('licensing.decrypt_btn') }}
                                        </el-button>
                                    </div>
                                    <div v-if="sandboxPlaintext" class="space-y-1 pt-2">
                                        <label class="block font-medium text-indigo-600 dark:text-indigo-400">{{ $t('licensing.plaintext_result_label') }}</label>
                                        <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 font-semibold text-emerald-800 dark:text-emerald-200 text-sm">
                                            {{ sandboxPlaintext }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Danh sách các trường được bảo vệ trong CSDL -->
                        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
                            <h4 class="font-semibold text-sm text-slate-900 dark:text-white">{{ $t('licensing.anchored_tables_title') }}</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                                <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $t('licensing.table_prefix', { table: 'employees' }) }}</div>
                                    <div class="font-mono text-indigo-600 dark:text-indigo-400 mt-1">encrypted_citizen_id</div>
                                    <div class="text-slate-400 text-[11px] mt-0.5">{{ $t('licensing.anchor_emp_cccd_desc') }}</div>
                                </div>
                                <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $t('licensing.table_prefix', { table: 'payroll_records' }) }}</div>
                                    <div class="font-mono text-indigo-600 dark:text-indigo-400 mt-1">encrypted_salary_details</div>
                                    <div class="text-slate-400 text-[11px] mt-0.5">{{ $t('licensing.anchor_payroll_desc') }}</div>
                                </div>
                                <div class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $t('licensing.table_prefix', { table: 'employee_contracts' }) }}</div>
                                    <div class="font-mono text-indigo-600 dark:text-indigo-400 mt-1">encrypted_salary_data</div>
                                    <div class="text-slate-400 text-[11px] mt-0.5">{{ $t('licensing.anchor_contract_desc') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </el-tab-pane>

                <!-- ================= TAB 3: KẾT NỐI GO EDGE DAEMON ================= -->
                <el-tab-pane :label="$t('licensing.tab_edge')" name="edge">
                    <div class="space-y-6 pt-2">
                        <!-- Thông số kỹ thuật đồng bộ -->
                        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="space-y-0.5">
                                    <h4 class="font-semibold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                        <el-icon class="text-emerald-500"><Connection /></el-icon>
                                        {{ $t('licensing.edge_conn_title') }}
                                    </h4>
                                    <p class="text-xs text-slate-500">
                                        {{ $t('licensing.edge_conn_desc') }}
                                    </p>
                                </div>
                                <a href="/licensing/sample-config" download="config.json">
                                    <el-button type="primary" size="small" :icon="Download">
                                        {{ $t('licensing.download_sample_btn') }}
                                    </el-button>
                                </a>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                <div class="space-y-1">
                                    <span class="text-slate-400">{{ $t('licensing.edge_sync_endpoint_label') }}</span>
                                    <div class="p-2.5 rounded-lg bg-slate-100 dark:bg-slate-800 font-mono text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                        <span>POST /api/v1/attendance/sync</span>
                                        <el-button link type="primary" @click="copyToClipboard('/api/v1/attendance/sync')">
                                            <el-icon><CopyDocument /></el-icon>
                                        </el-button>
                                    </div>
                                </div>

                                <div class="space-y-1">
                                    <span class="text-slate-400">{{ $t('licensing.edge_hmac_label') }}</span>
                                    <div class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800 font-mono text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                        <span class="truncate">
                                            {{ showSecret ? license.shared_hmac_secret : license.shared_hmac_secret_masked }}
                                        </span>
                                        <div class="flex items-center space-x-1 shrink-0">
                                            <el-button link type="info" @click="showSecret = !showSecret">
                                                <el-icon><component :is="showSecret ? Hide : View" /></el-icon>
                                            </el-button>
                                            <el-button link type="primary" @click="copyToClipboard(license.shared_hmac_secret || '')" :disabled="!license.shared_hmac_secret">
                                                <el-icon><CopyDocument /></el-icon>
                                            </el-button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Danh sách Chi nhánh & Khóa Secret -->
                        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
                            <h4 class="font-semibold text-sm text-slate-900 dark:text-white">{{ $t('licensing.branches_title') }}</h4>
                            <el-table :data="branches" stripe border size="small" class="w-full">
                                <el-table-column prop="code" :label="$t('licensing.branch_code_col')" width="140">
                                    <template #default="{ row }">
                                        <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ row.code }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="name" :label="$t('licensing.branch_name_col')" min-width="180" />
                                <el-table-column prop="address" :label="$t('licensing.branch_address_col')" min-width="200" />
                                <el-table-column prop="secret_key" :label="$t('licensing.branch_secret_col')" min-width="160">
                                    <template #default="{ row }">
                                        <span class="font-mono text-xs text-slate-500 truncate">{{ row.secret_key ? '••••••••••••••••' : $t('licensing.not_set') }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="is_active" :label="$t('licensing.col_status')" width="120" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                            {{ row.is_active ? $t('licensing.branch_active') : $t('licensing.branch_inactive') }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>

                        <!-- 10 Lượt Quẹt Thẻ Gần Nhất Nhận Từ Edge Collector -->
                        <div class="p-5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="font-semibold text-sm text-slate-900 dark:text-white">{{ $t('licensing.recent_logs_title') }}</h4>
                                <div class="text-xs text-slate-500">
                                    {{ $t('licensing.today_label') }} <span class="font-semibold text-emerald-600">{{ stats.total_raw_today }}</span> {{ $t('licensing.punch_count_suffix') }}
                                </div>
                            </div>

                            <el-table :data="recent_logs" stripe border size="small" class="w-full">
                                <el-table-column prop="punch_time" :label="$t('licensing.punch_time_col')" width="160">
                                    <template #default="{ row }">
                                        <span class="font-mono text-xs text-slate-700 dark:text-slate-300">{{ row.punch_time }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('licensing.employee_col')" min-width="180">
                                    <template #default="{ row }">
                                        <div v-if="row.employee" class="font-medium text-slate-800 dark:text-slate-200">
                                            {{ row.employee.full_name }}
                                            <span class="font-mono text-xs text-slate-400">({{ row.employee.user_code }})</span>
                                        </div>
                                        <span v-else class="text-slate-400 italic">ID #{{ row.employee_id }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="punch_state" :label="$t('licensing.punch_type_col')" width="120" align="center">
                                    <template #default="{ row }">
                                        <el-tag :type="row.punch_state === 'CHECK_IN' ? 'success' : (row.punch_state === 'CHECK_OUT' ? 'warning' : 'info')" size="small">
                                            {{ row.punch_state }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="verification_mode" :label="$t('licensing.verification_mode_col')" width="130" align="center">
                                    <template #default="{ row }">
                                        <span class="text-xs font-mono">{{ row.verification_mode }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="device_sn" :label="$t('licensing.device_sn_col')" min-width="150">
                                    <template #default="{ row }">
                                        <span class="font-mono text-xs text-slate-600 dark:text-slate-400">{{ row.device_sn }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="sync_batch_id" :label="$t('licensing.sync_batch_col')" min-width="170">
                                    <template #default="{ row }">
                                        <span class="font-mono text-xs text-indigo-600 dark:text-indigo-400">{{ row.sync_batch_id }}</span>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </div>

        <!-- Dialog Chi Tiết Kiểm Tra Toàn Vẹn Chữ Ký Ed25519 -->
        <el-dialog
            v-model="isAuditModalOpen"
            :title="$t('licensing.integrity_modal_title')"
            width="650px"
            destroy-on-close
        >
            <div class="space-y-4 text-xs">
                <div class="p-3 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 flex items-center space-x-3 text-emerald-800 dark:text-emerald-300">
                    <el-icon :size="24" class="text-emerald-600"><CircleCheckFilled /></el-icon>
                    <div>
                        <div class="font-bold text-sm">{{ $t('licensing.signature_valid_title') }}</div>
                        <div>{{ $t('licensing.signature_valid_desc') }}</div>
                    </div>
                </div>

                <div class="space-y-2 border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50 dark:bg-slate-800/50">
                    <div class="flex justify-between py-1 border-b border-slate-200 dark:border-slate-700">
                        <span class="text-slate-500">{{ $t('licensing.license_format_label') }}</span>
                        <span class="font-mono font-bold">SMARTHR-LIC-V1</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200 dark:border-slate-700">
                        <span class="text-slate-500">{{ $t('licensing.verify_algorithm_label') }}</span>
                        <span class="font-mono">Ed25519 Detached Signature (RFC 8032)</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200 dark:border-slate-700">
                        <span class="text-slate-500">{{ $t('licensing.author_pubkey_label') }}</span>
                        <span class="font-mono truncate max-w-[300px]">2459e6b349932dfcfaa04db0130bc2a197ac46de32354b8e93b068367c81ac2c</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200 dark:border-slate-700">
                        <span class="text-slate-500">{{ $t('licensing.licensed_domain_label') }}</span>
                        <span class="font-mono font-semibold text-emerald-600">{{ license.allowed_domain }}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-slate-500">{{ $t('licensing.extracted_signature_label') }}</span>
                        <span class="font-mono truncate max-w-[300px] select-all">{{ license.raw_signature }}</span>
                    </div>
                </div>
            </div>
            <template #footer>
                <el-button type="primary" @click="isAuditModalOpen = false">{{ $t('common.close') }}</el-button>
            </template>
        </el-dialog>
    </AppLayout>
</template>

<style scoped>
/* Responsive fix for Element Plus tabs in dark mode */
:deep(.el-tabs__item) {
    font-weight: 500;
}
:deep(.el-tabs__item.is-active) {
    font-weight: 600;
}
</style>
