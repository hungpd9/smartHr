<script setup lang="ts">
import { ref, reactive, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import {
    Document,
    Clock,
    Calendar,
    Moon,
    Switch,
    Warning,
    Connection,
    Check,
    Link,
    Paperclip
} from '@element-plus/icons-vue';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

const props = defineProps<{
    modelValue: boolean;
    employees: Array<{ id: number; full_name: string; user_code: string; email: string; gender: string }>;
    currentEmployee: { id: number; full_name: string; user_code: string } | null;
    leaveBalance: { remaining_days: number; used_days: number; pending_days: number } | null;
    requestTypes: Array<{ value: string; label: string }>;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: boolean): void;
    (e: 'success'): void;
}>();

const { t } = useThemeAndLocale();

const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

const requestTypeOptionKeys: Record<string, string> = {
    LEAVE: 'workflow.tag_type_leave',
    OVERTIME: 'workflow.tag_type_overtime',
    NIGHT_SHIFT: 'workflow.tag_type_night_shift',
    TIMESHEET_EDIT: 'workflow.tag_type_timesheet_edit',
    SHIFT_SWAP: 'workflow.tag_type_shift_swap',
    REMOTE_WORK: 'workflow.tag_type_remote_work',
    RESIGNATION: 'workflow.tag_type_resignation',
};
const getRequestTypeOptionLabel = (value: string, fallback: string) => {
    const key = requestTypeOptionKeys[value];
    return key ? t(key) : fallback;
};

const form = useForm({
    employee_id: props.currentEmployee?.id || null,
    request_type: 'LEAVE',
    form_payload: {
        leave_category: 'ANNUAL',
        start_date: new Date().toISOString().substring(0, 10),
        end_date: new Date().toISOString().substring(0, 10),
        days: 1.0,
        hours: 2.0,
        work_date: new Date().toISOString().substring(0, 10),
        actual_in: '',
        actual_out: '',
        target_employee_id: null as number | null,
        requester_roster_id: null as number | null,
        target_roster_id: null as number | null,
        reason: '',
    },
    is_voluntary_consent: false,
    voluntary_agreement_url: '',
});

const previewChain = ref<Array<{
    level: number;
    title: string;
    role_type: string;
    approver_name: string;
    approver_code: string;
}>>([]);

const loadingPreview = ref(false);

// Kiểm tra xem đơn hiện tại có cần cam kết tự nguyện Điều 137 BLLĐ không
const requiresVoluntaryConsent = computed(() => {
    return form.request_type === 'NIGHT_SHIFT' || form.request_type === 'OVERTIME';
});

// Tự động tính số ngày xin nghỉ
watch([() => form.form_payload.start_date, () => form.form_payload.end_date], ([start, end]) => {
    if (start && end) {
        const d1 = new Date(start);
        const d2 = new Date(end);
        const diffTime = Math.abs(d2.getTime() - d1.getTime());
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
        if (!isNaN(diffDays) && diffDays > 0) {
            form.form_payload.days = diffDays;
        }
    }
});

// Lấy trước chuỗi người duyệt khi thay đổi loại đơn hoặc nhân viên
const fetchPreviewChain = async () => {
    if (!form.employee_id && !props.currentEmployee?.id) return;
    loadingPreview.value = true;
    try {
        const empId = form.employee_id || props.currentEmployee?.id;
        const res = await fetch(`/workflow/preview-chain?employee_id=${empId}&request_type=${form.request_type}`);
        if (res.ok) {
            const data = await res.json();
            previewChain.value = data.chain || [];
        }
    } catch (e) {
        console.error('Lỗi lấy preview chain:', e);
    } finally {
        loadingPreview.value = false;
    }
};

watch(() => form.request_type, () => {
    fetchPreviewChain();
}, { immediate: true });

watch(() => form.employee_id, () => {
    fetchPreviewChain();
});

const submit = () => {
    if (requiresVoluntaryConsent.value && !form.is_voluntary_consent) {
        ElMessage.warning(t('workflow.consent_submitter_required'));
        return;
    }

    form.post('/workflow/requests', {
        preserveScroll: true,
        onSuccess: () => {
            ElMessage.success(t('workflow.submit_request_success'));
            visible.value = false;
            form.reset();
            emit('success');
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            ElMessage.error(typeof firstErr === 'string' ? firstErr : t('workflow.submit_request_error'));
        }
    });
};
</script>

<template>
    <el-dialog
        v-model="visible"
        :title="$t('workflow.modal_create_title')"
        width="680px"
        destroy-on-close
        class="workflow-create-modal"
    >
        <el-form :model="form" label-position="top" class="space-y-4">
            <!-- Chọn nhân sự nếu có quyền (mặc định là nhân sự hiện tại) -->
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('workflow.label_requester')" required>
                        <el-select
                            v-model="form.employee_id"
                            :placeholder="$t('workflow.placeholder_select_employee')"
                            filterable
                            class="w-full"
                        >
                            <el-option
                                v-for="emp in employees"
                                :key="emp.id"
                                :label="`${emp.full_name} (${emp.user_code})`"
                                :value="emp.id"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>

                <el-col :span="12">
                    <el-form-item :label="$t('workflow.label_request_type')" required>
                        <el-select
                            v-model="form.request_type"
                            :placeholder="$t('workflow.placeholder_select_request_type')"
                            class="w-full"
                        >
                            <el-option
                                v-for="type in requestTypes"
                                :key="type.value"
                                :label="getRequestTypeOptionLabel(type.value, type.label)"
                                :value="type.value"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <!-- Chi tiết form theo loại đơn: LEAVE -->
            <div v-if="form.request_type === 'LEAVE'" class="p-4 rounded-lg bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/50 space-y-3">
                <div class="flex items-center justify-between text-xs font-semibold text-blue-800 dark:text-blue-300">
                    <span class="flex items-center gap-1.5"><Calendar class="w-4 h-4" /> {{ $t('workflow.leave_details_title') }}</span>
                    <span v-if="leaveBalance" class="bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-200 px-2 py-0.5 rounded">
                        {{ $t('workflow.leave_balance_available') }} <strong>{{ leaveBalance.remaining_days }} {{ $t('common.days') }}</strong>
                    </span>
                </div>

                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="$t('workflow.label_leave_type')" size="small">
                            <el-select v-model="form.form_payload.leave_category" class="w-full">
                                <el-option :label="$t('workflow.label_leave_annual')" value="ANNUAL" />
                                <el-option :label="$t('workflow.label_leave_comp')" value="COMPENSATION" />
                                <el-option :label="$t('workflow.label_leave_marriage')" value="SPECIAL" />
                                <el-option :label="$t('workflow.label_leave_unpaid')" value="UNPAID" />
                                <el-option :label="$t('workflow.label_leave_maternity')" value="SOCIAL_INSURANCE" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('common.from_date')" size="small">
                            <el-date-picker
                                v-model="form.form_payload.start_date"
                                type="date"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('common.to_date')" size="small">
                            <el-date-picker
                                v-model="form.form_payload.end_date"
                                type="date"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="$t('workflow.label_total_duration')" size="small">
                            <el-input-number
                                v-model="form.form_payload.days"
                                :min="0.5"
                                :step="0.5"
                                :max="30"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="16">
                        <el-form-item :label="$t('workflow.label_reason')" size="small">
                            <el-input
                                v-model="form.form_payload.reason"
                                :placeholder="$t('workflow.placeholder_leave_reason')"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
            </div>

            <!-- Chi tiết form theo loại đơn: OVERTIME -->
            <div v-else-if="form.request_type === 'OVERTIME'" class="p-4 rounded-lg bg-amber-50/50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 space-y-3">
                <div class="flex items-center justify-between text-xs font-semibold text-amber-800 dark:text-amber-300">
                    <span class="flex items-center gap-1.5"><Clock class="w-4 h-4" /> {{ $t('workflow.ot_details_title') }}</span>
                    <span class="text-amber-600 dark:text-amber-400 text-[11px]">{{ $t('workflow.ot_legal_cap') }}</span>
                </div>

                <el-row :gutter="12">
                    <el-col :span="10">
                        <el-form-item :label="$t('workflow.label_ot_date')" size="small">
                            <el-date-picker
                                v-model="form.form_payload.date"
                                type="date"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="6">
                        <el-form-item :label="$t('workflow.label_ot_hours')" size="small">
                            <el-input-number
                                v-model="form.form_payload.hours"
                                :min="0.5"
                                :step="0.5"
                                :max="4.0"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('workflow.label_ot_content')" size="small">
                            <el-input v-model="form.form_payload.reason" :placeholder="$t('workflow.placeholder_ot_plan')" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </div>

            <!-- Chi tiết form theo loại đơn: NIGHT_SHIFT -->
            <div v-else-if="form.request_type === 'NIGHT_SHIFT'" class="p-4 rounded-lg bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-900/50 space-y-3">
                <div class="flex items-center justify-between text-xs font-semibold text-purple-800 dark:text-purple-300">
                    <span class="flex items-center gap-1.5"><Moon class="w-4 h-4" /> {{ $t('workflow.night_details_title') }}</span>
                    <span class="text-purple-600 dark:text-purple-400 text-[11px]">{{ $t('workflow.night_allowance_hint') }}</span>
                </div>

                <el-row :gutter="12">
                    <el-col :span="12">
                        <el-form-item :label="$t('workflow.label_night_date')" size="small">
                            <el-date-picker
                                v-model="form.form_payload.work_date"
                                type="date"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('workflow.label_night_reason')" size="small">
                            <el-input v-model="form.form_payload.reason" :placeholder="$t('workflow.placeholder_enter_reason')" />
                        </el-form-item>
                    </el-col>
                </el-row>
            </div>

            <!-- Chi tiết form theo loại đơn: TIMESHEET_EDIT -->
            <div v-else-if="form.request_type === 'TIMESHEET_EDIT'" class="p-4 rounded-lg bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/50 space-y-3">
                <div class="flex items-center justify-between text-xs font-semibold text-emerald-800 dark:text-emerald-300">
                    <span class="flex items-center gap-1.5"><Document class="w-4 h-4" /> {{ $t('workflow.timesheet_details_title') }}</span>
                </div>

                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="$t('workflow.label_timesheet_date')" size="small">
                            <el-date-picker
                                v-model="form.form_payload.work_date"
                                type="date"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('workflow.label_actual_in')" size="small">
                            <el-time-picker
                                v-model="form.form_payload.actual_in"
                                format="HH:mm:ss"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="08:00:00"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="$t('workflow.label_actual_out')" size="small">
                            <el-time-picker
                                v-model="form.form_payload.actual_out"
                                format="HH:mm:ss"
                                value-format="YYYY-MM-DD HH:mm:ss"
                                placeholder="17:00:00"
                                class="w-full"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item :label="$t('workflow.label_timesheet_reason')" size="small">
                    <el-input v-model="form.form_payload.reason" :placeholder="$t('workflow.placeholder_timesheet_reason')" />
                </el-form-item>
            </div>

            <!-- Các loại đơn khác -->
            <div v-else class="p-4 rounded-lg bg-gray-50 dark:bg-gray-800/40 border border-gray-200 dark:border-gray-700 space-y-3">
                <el-form-item :label="$t('workflow.label_reason_content')" size="small">
                    <el-input
                        v-model="form.form_payload.reason"
                        type="textarea"
                        :rows="3"
                        :placeholder="$t('workflow.placeholder_request_content')"
                    />
                </el-form-item>
            </div>

            <!-- Cảnh báo BLLĐ Điều 137 Khoản 1 Điểm b (Cam kết tự nguyện làm đêm/OT cho lao động nuôi con nhỏ < 12 tháng) -->
            <div v-if="requiresVoluntaryConsent" class="p-3.5 rounded-lg bg-purple-50 dark:bg-purple-950/30 border border-purple-300 dark:border-purple-800 space-y-2.5">
                <div class="flex items-start gap-2 text-purple-900 dark:text-purple-200 text-xs leading-relaxed">
                    <Warning class="w-4 h-4 text-purple-600 dark:text-purple-400 mt-0.5 shrink-0" />
                    <div>
                        <strong>{{ $t('workflow.consent_legal_heading') }}</strong>
                        <p class="mt-0.5 text-[11px] text-purple-700 dark:text-purple-300">
                            {{ $t('workflow.consent_legal_body_1') }}<em>{{ $t('workflow.consent_legal_body_em') }}</em>.
                        </p>
                    </div>
                </div>

                <el-checkbox
                    v-model="form.is_voluntary_consent"
                    class="text-purple-900 dark:text-purple-200 font-medium text-xs"
                >
                    {{ $t('workflow.consent_submitter_confirm') }}
                </el-checkbox>

                <div class="flex items-center gap-2">
                    <el-input
                        v-model="form.voluntary_agreement_url"
                        :placeholder="$t('workflow.placeholder_voluntary_url')"
                        size="small"
                        class="text-xs"
                    >
                        <template #prefix>
                            <Link class="w-3.5 h-3.5 text-gray-400" />
                        </template>
                    </el-input>
                </div>
            </div>

            <!-- Khung Xem Trước Chuỗi Người Duyệt (Approver Chain Preview) -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-3">
                <div class="flex items-center justify-between mb-2.5">
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                        <Connection class="w-4 h-4 text-blue-500" /> {{ $t('workflow.approver_chain_preview_title') }}
                    </span>
                    <span v-if="loadingPreview" class="text-[11px] text-gray-400">{{ $t('workflow.approver_chain_loading') }}</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                    <div
                        v-for="step in previewChain"
                        :key="step.level"
                        class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-700/60 bg-gray-50/70 dark:bg-gray-800/40 flex items-center gap-2.5"
                    >
                        <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 text-xs font-bold flex items-center justify-center shrink-0">
                            {{ step.level }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-semibold text-gray-800 dark:text-gray-200 truncate">
                                {{ step.approver_name }}
                            </div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400 truncate">
                                {{ step.title }} ({{ step.role_type }})
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </el-form>

        <template #footer>
            <div class="flex justify-end gap-2">
                <el-button @click="visible = false">{{ $t('common.cancel') }}</el-button>
                <el-button
                    type="primary"
                    :loading="form.processing"
                    @click="submit"
                >
                    {{ $t('workflow.submit_request_btn') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<style scoped>
:deep(.el-form-item) {
    margin-bottom: 12px;
}
</style>
