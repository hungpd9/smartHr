<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Plus,
    Edit,
    Delete,
    Clock,
    Moon,
    Sunny,
    Check,
    Close,
    InfoFilled,
    Calendar,
    Star,
    StarFilled,
} from '@element-plus/icons-vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

interface ShiftItem {
    id: number;
    code: string;
    name: string;
    start_time: string;
    end_time: string;
    break_minutes: number;
    work_hours: number;
    is_night_shift: boolean;
    is_default: boolean;
    night_allowance_rate: number;
    night_bonus_amount: number;
    is_active: boolean;
}

const props = defineProps<{
    shifts: ShiftItem[];
}>();

const { t } = useThemeAndLocale();

// Thống kê nhanh
const totalShifts = computed(() => props.shifts.length);
const nightShiftsCount = computed(() => props.shifts.filter(s => s.is_night_shift).length);
const activeShiftsCount = computed(() => props.shifts.filter(s => s.is_active).length);
const defaultShift = computed(() => props.shifts.find(s => s.is_default));

// Dialog Thêm / Sửa ca
const dialogVisible = ref(false);
const isEditing = ref(false);
const editingShiftId = ref<number | null>(null);

const form = useForm({
    code: '',
    name: '',
    start_time: '08:00',
    end_time: '17:00',
    break_minutes: 60,
    is_night_shift: false,
    is_default: false,
    night_allowance_rate: 30.0,
    night_bonus_amount: 0,
    is_active: true,
});

// Tự động phát hiện ca đêm
const detectedIsNightShift = computed(() => {
    if (!form.start_time || !form.end_time) return false;
    const startH = parseInt(form.start_time.split(':')[0]);
    const endH = parseInt(form.end_time.split(':')[0]);
    return (startH >= 22 || startH < 6 || endH <= 6 || endH > 22);
});

// Tự động tính số giờ làm việc thực tế
const calculatedWorkHours = computed(() => {
    if (!form.start_time || !form.end_time) return 0;
    const [sH, sM] = form.start_time.split(':').map(Number);
    const [eH, eM] = form.end_time.split(':').map(Number);
    
    let startMinutes = sH * 60 + sM;
    let endMinutes = eH * 60 + eM;
    if (endMinutes <= startMinutes) {
        endMinutes += 24 * 60; // Ca vắt qua đêm
    }

    const net = Math.max(0, endMinutes - startMinutes - (form.break_minutes || 0));
    return (net / 60).toFixed(1);
});

const openCreateDialog = () => {
    isEditing.value = false;
    editingShiftId.value = null;
    form.reset();
    form.clearErrors();
    form.start_time = '08:00';
    form.end_time = '17:00';
    form.break_minutes = 60;
    form.is_default = false;
    form.night_allowance_rate = 30.0;
    form.night_bonus_amount = 0;
    form.is_active = true;
    dialogVisible.value = true;
};

const openEditDialog = (shift: ShiftItem) => {
    isEditing.value = true;
    editingShiftId.value = shift.id;
    form.clearErrors();
    form.code = shift.code;
    form.name = shift.name;
    form.start_time = shift.start_time.substring(0, 5);
    form.end_time = shift.end_time.substring(0, 5);
    form.break_minutes = shift.break_minutes;
    form.is_night_shift = shift.is_night_shift;
    form.is_default = shift.is_default;
    form.night_allowance_rate = Number(shift.night_allowance_rate ?? 30.0);
    form.night_bonus_amount = Number(shift.night_bonus_amount ?? 0);
    form.is_active = shift.is_active;
    dialogVisible.value = true;
};

const setDefaultShift = (shift: ShiftItem) => {
    router.post(`/rosters/shifts/${shift.id}/set-default`, {}, {
        onSuccess: () => {
            ElMessage.success(t('rostering.msg_set_default_success', { name: shift.name }));
        },
    });
};

const submitForm = () => {
    form.is_night_shift = detectedIsNightShift.value;

    if (isEditing.value && editingShiftId.value) {
        form.put(`/rosters/shifts/${editingShiftId.value}`, {
            onSuccess: () => {
                dialogVisible.value = false;
                ElMessage.success(t('rostering.msg_update_success'));
            },
        });
    } else {
        form.post('/rosters/shifts', {
            onSuccess: () => {
                dialogVisible.value = false;
                ElMessage.success(t('rostering.msg_create_success'));
            },
        });
    }
};

const handleDelete = (shift: ShiftItem) => {
    ElMessageBox.confirm(
        t('rostering.delete_confirm_msg', { name: shift.name, code: shift.code }),
        t('rostering.delete_confirm_title'),
        {
            confirmButtonText: t('rostering.btn_delete_shift'),
            cancelButtonText: t('common.cancel'),
            type: 'warning',
        }
    ).then(() => {
        router.delete(`/rosters/shifts/${shift.id}`, {
            onSuccess: () => {
                ElMessage.success(t('rostering.msg_delete_success'));
            },
        });
    }).catch(() => {});
};
</script>

<template>
    <AppLayout>
        <Head :title="t('rostering.shifts_page_title')" />

        <div class="p-6 max-w-7xl mx-auto space-y-6">
            <!-- Header & Title -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <div>
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <el-icon :size="22"><Clock /></el-icon>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 dark:text-white">
                                {{ $t('rostering.shifts_heading') }}
                            </h1>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $t('rostering.shifts_subtitle') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <el-button type="primary" :icon="Plus" @click="openCreateDialog" class="!rounded-xl !font-semibold shadow-sm">
                        {{ $t('rostering.btn_add_shift') }}
                    </el-button>
                </div>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('rostering.kpi_total_defined_shifts') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-1">{{ totalShifts }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-950/60 flex items-center justify-center text-blue-600 dark:text-blue-400">
                        <el-icon :size="20"><Calendar /></el-icon>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('rostering.kpi_night_shifts') }}</p>
                        <p class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">{{ nightShiftsCount }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-purple-50 dark:bg-purple-950/60 flex items-center justify-center text-purple-600 dark:text-purple-400">
                        <el-icon :size="20"><Moon /></el-icon>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">{{ $t('rostering.kpi_active_shifts') }}</p>
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{{ activeShiftsCount }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <el-icon :size="20"><Check /></el-icon>
                    </div>
                </div>
            </div>

            <!-- Banner Ca Tiêu Chuẩn Mặc Định -->
            <div v-if="defaultShift" class="p-4 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/60 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0">
                        <el-icon :size="20"><StarFilled /></el-icon>
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $t('rostering.default_banner_label') }}</span>
                            <span class="font-mono font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/60 px-2 py-0.5 rounded text-xs">
                                {{ defaultShift.code }}
                            </span>
                            <span class="text-slate-800 dark:text-slate-200 text-sm font-semibold">
                                {{ defaultShift.name }} ({{ defaultShift.start_time.substring(0, 5) }} - {{ defaultShift.end_time.substring(0, 5) }})
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $t('rostering.default_banner_note') }}
                        </p>
                    </div>
                </div>
                <div class="shrink-0 flex items-center space-x-2">
                    <el-tag type="success" effect="dark" size="small" class="!rounded-md !font-semibold">
                        {{ $t('rostering.tag_in_effect') }}
                    </el-tag>
                    <el-button size="small" :icon="Edit" @click="openEditDialog(defaultShift)">
                        {{ $t('rostering.btn_edit_default_shift') }}
                    </el-button>
                </div>
            </div>
            <div v-else class="p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl flex items-center justify-between text-xs text-amber-800 dark:text-amber-300">
                <div class="flex items-center space-x-2">
                    <el-icon :size="18"><InfoFilled /></el-icon>
                    <span>
                        {{ $t('rostering.no_default_shift_prefix') }}<strong>{{ $t('rostering.no_default_shift_action') }}</strong>{{ $t('rostering.no_default_shift_suffix') }}
                    </span>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                <el-table :data="shifts" stripe style="width: 100%" class="!text-sm">
                    <el-table-column prop="code" :label="$t('rostering.col_shift_code')" width="150">
                        <template #default="{ row }">
                            <div class="flex flex-col gap-1 items-start">
                                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/60 px-2 py-0.5 rounded text-xs">
                                    {{ row.code }}
                                </span>
                                <el-tag v-if="row.is_default" type="success" size="small" effect="plain" class="!text-[10px] !px-1.5 !h-5">
                                    {{ $t('rostering.tag_default') }}
                                </el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="name" :label="$t('rostering.col_shift_name')" min-width="180">
                        <template #default="{ row }">
                            <div class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ row.name }}
                            </div>
                            <div v-if="row.is_night_shift" class="text-[11px] text-purple-600 dark:text-purple-400 mt-0.5 flex items-center space-x-2">
                                <span>{{ $t('rostering.night_allowance_cell', { rate: row.night_allowance_rate }) }}</span>
                                <span v-if="row.night_bonus_amount > 0">{{ $t('rostering.night_bonus_cell', { amount: Number(row.night_bonus_amount).toLocaleString('vi-VN') }) }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.col_shift_time')" width="180">
                        <template #default="{ row }">
                            <div class="flex items-center space-x-2 font-mono text-xs">
                                <span class="text-slate-700 dark:text-slate-300 font-semibold">{{ row.start_time.substring(0, 5) }}</span>
                                <span class="text-slate-400">⟶</span>
                                <span class="text-slate-700 dark:text-slate-300 font-semibold">{{ row.end_time.substring(0, 5) }}</span>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.col_break_time')" width="110">
                        <template #default="{ row }">
                            <span class="text-slate-600 dark:text-slate-400 text-xs">
                                {{ row.break_minutes }} {{ $t('common.minutes') }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.col_total_hours')" width="120">
                        <template #default="{ row }">
                            <el-tag type="info" size="small" effect="plain" class="!font-mono !font-semibold">
                                {{ row.work_hours }} {{ $t('common.hours') }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('rostering.col_shift_category')" width="160">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_night_shift" type="warning" size="small" class="!rounded-md">
                                <div class="flex items-center space-x-1">
                                    <el-icon><Moon /></el-icon>
                                    <span>{{ $t('rostering.badge_night_shift') }}</span>
                                </div>
                            </el-tag>
                            <el-tag v-else type="success" size="small" class="!rounded-md">
                                <div class="flex items-center space-x-1">
                                    <el-icon><Sunny /></el-icon>
                                    <span>{{ $t('rostering.tag_day_shift') }}</span>
                                </div>
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('common.status')" width="120">
                        <template #default="{ row }">
                            <el-badge is-dot :type="row.is_active ? 'success' : 'danger'" class="mr-2" />
                            <span :class="row.is_active ? 'text-emerald-600 dark:text-emerald-400 font-medium' : 'text-slate-400'">
                                {{ row.is_active ? $t('rostering.tag_shift_active') : $t('rostering.tag_shift_hidden') }}
                            </span>
                        </template>
                    </el-table-column>

                    <el-table-column :label="$t('common.actions')" width="160" fixed="right">
                        <template #default="{ row }">
                            <div class="flex items-center space-x-2">
                                <el-button
                                    v-if="!row.is_default && row.is_active"
                                    size="small"
                                    type="warning"
                                    plain
                                    :icon="Star"
                                    circle
                                    @click="setDefaultShift(row)"
                                    :title="$t('rostering.title_set_default')"
                                />
                                <el-button size="small" :icon="Edit" circle @click="openEditDialog(row)" :title="$t('common.edit')" />
                                <el-button size="small" type="danger" :icon="Delete" circle @click="handleDelete(row)" :title="$t('rostering.btn_delete_shift')" />
                            </div>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>

        <!-- Modal Thêm / Chỉnh Sửa Ca -->
        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? $t('rostering.dialog_title_edit') : $t('rostering.dialog_title_create')"
            width="580px"
            destroy-on-close
            class="!rounded-2xl"
        >
            <el-form :model="form" label-position="top" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <el-form-item :label="$t('rostering.label_shift_code')" :error="form.errors.code" required>
                        <el-input v-model="form.code" :placeholder="$t('rostering.ph_shift_code')" :disabled="isEditing" />
                    </el-form-item>

                    <el-form-item :label="$t('rostering.label_shift_name')" :error="form.errors.name" required>
                        <el-input v-model="form.name" :placeholder="$t('rostering.ph_shift_name')" />
                    </el-form-item>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <el-form-item :label="$t('rostering.label_start_time')" :error="form.errors.start_time" required>
                        <el-time-select
                            v-model="form.start_time"
                            start="00:00"
                            step="00:15"
                            end="23:45"
                            :placeholder="$t('rostering.ph_start_time')"
                            style="width: 100%"
                        />
                    </el-form-item>

                    <el-form-item :label="$t('rostering.label_end_time')" :error="form.errors.end_time" required>
                        <el-time-select
                            v-model="form.end_time"
                            start="00:00"
                            step="00:15"
                            end="23:45"
                            :placeholder="$t('rostering.ph_end_time')"
                            style="width: 100%"
                        />
                    </el-form-item>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <el-form-item :label="$t('rostering.label_break_minutes')">
                        <el-input-number v-model="form.break_minutes" :min="0" :max="300" :step="15" style="width: 100%" />
                    </el-form-item>

                    <el-form-item :label="$t('rostering.label_total_work_hours')">
                        <div class="h-10 px-3 bg-slate-100 dark:bg-slate-800 rounded-lg flex items-center font-mono font-bold text-slate-800 dark:text-white">
                            {{ calculatedWorkHours }} {{ $t('common.hours') }}
                        </div>
                    </el-form-item>
                </div>

                <!-- Thiết lập ca mặc định -->
                <div class="p-3 bg-slate-50 dark:bg-slate-800/60 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 text-sm">{{ $t('rostering.default_setting_title') }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">{{ $t('rostering.default_setting_desc') }}</div>
                    </div>
                    <el-switch v-model="form.is_default" />
                </div>

                <!-- Cảnh báo & Cấu hình nếu là ca đêm -->
                <div v-if="detectedIsNightShift" class="space-y-3 p-4 bg-purple-50 dark:bg-purple-950/50 border border-purple-200 dark:border-purple-800 rounded-xl">
                    <div class="flex items-start space-x-2 text-xs text-purple-700 dark:text-purple-300">
                        <el-icon :size="16" class="mt-0.5 shrink-0"><Moon /></el-icon>
                        <div>
                            <span class="font-bold">{{ $t('rostering.night_detect_title') }}</span>
                            {{ $t('rostering.night_detect_desc') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-2 border-t border-purple-200/60 dark:border-purple-800/60">
                        <el-form-item :label="$t('rostering.label_night_allowance_rate')" class="!mb-0">
                            <el-input-number v-model="form.night_allowance_rate" :min="30" :max="100" :step="5" style="width: 100%" />
                            <span class="text-[10px] text-slate-500">{{ $t('rostering.night_allowance_min_hint') }}</span>
                        </el-form-item>
                        <el-form-item :label="$t('rostering.label_night_meal_bonus')" class="!mb-0">
                            <el-input-number v-model="form.night_bonus_amount" :min="0" :step="10000" style="width: 100%" />
                            <span class="text-[10px] text-slate-500">{{ $t('rostering.night_meal_bonus_hint') }}</span>
                        </el-form-item>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('rostering.label_activate_shift') }}</span>
                    <el-switch v-model="form.is_active" />
                </div>
            </el-form>

            <template #footer>
                <div class="flex justify-end space-x-2">
                    <el-button @click="dialogVisible = false">{{ $t('common.cancel') }}</el-button>
                    <el-button type="primary" :loading="form.processing" @click="submitForm">
                        {{ isEditing ? $t('rostering.btn_update_shift') : $t('common.create') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </AppLayout>
</template>
