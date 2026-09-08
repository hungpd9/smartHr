<script setup lang="ts">
import { ref, computed } from 'vue';
import {
    Printer,
    Download,
    Close,
    Document,
    Money,
    InfoFilled,
    Check,
    OfficeBuilding,
    User,
    Calendar,
    Clock,
    Lock
} from '@element-plus/icons-vue';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

const { t } = useThemeAndLocale();

const props = defineProps<{
    modelValue: boolean;
    record: any;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: boolean): void;
}>();

const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val),
});

const formatVND = (val: number | string | null | undefined): string => {
    if (val === null || val === undefined || isNaN(Number(val))) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val));
};

const printPayslip = () => {
    window.print();
};

const breakdown = computed(() => props.record?.salary_breakdown || {});
const earnings = computed(() => breakdown.value.earnings || {});
const deductions = computed(() => breakdown.value.deductions || {});
const companyCosts = computed(() => breakdown.value.company_contributions || {});
</script>

<template>
    <el-dialog
        v-model="visible"
        :title="$t('payroll.payslip_title')"
        width="820px"
        destroy-on-close
        class="payslip-modal print:border-none print:shadow-none"
    >
        <div id="printable-payslip" class="p-4 sm:p-6 bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-100 rounded-xl space-y-6">
            <!-- Header Phiếu Lương -->
            <div class="border-b-2 border-indigo-600 dark:border-indigo-500 pb-4 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-lg bg-indigo-600 text-white font-bold flex items-center justify-center text-base shadow">
                            SH
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white uppercase tracking-wide">
                                {{ $t('payroll.payslip_title') }}
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t('payroll.payslip_subtitle') }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-right">
                    <span class="inline-block px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-bold font-mono">
                        {{ $t('payroll.payslip_period_label') }} {{ record?.month_year }}
                    </span>
                    <div class="text-[11px] text-gray-400 mt-1">
                        {{ $t('payroll.payslip_issued_label') }} {{ new Date().toLocaleDateString('vi-VN') }}
                    </div>
                </div>
            </div>

            <!-- Thông tin Nhân viên -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 text-xs">
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('payroll.payslip_emp_code') }}</span>
                    <strong class="font-mono text-gray-900 dark:text-gray-100 text-sm">{{ record?.employee?.user_code }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('payroll.payslip_emp_name') }}</span>
                    <strong class="text-gray-900 dark:text-gray-100 text-sm">{{ record?.employee?.full_name }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('common.department') }}:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        {{ record?.employee?.current_timeline?.department?.current_version?.name || $t('payroll.payslip_dept_fallback') }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('common.position') }}:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        {{ record?.employee?.current_timeline?.position?.name || $t('payroll.position_fallback') }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('payroll.payslip_emp_contract_salary') }}</span>
                    <strong class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ formatVND(earnings.contract_base_salary || record?.gross_salary) }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('payroll.payslip_standard_workdays') }}:</span>
                    <strong class="text-gray-800 dark:text-gray-200">
                        {{ $t('payroll.payslip_workdays_fmt', { actual: earnings.actual_work_days ?? '--', standard: earnings.standard_work_days ?? '22' }) }}
                    </strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('payroll.payslip_field_ot_hours') }}</span>
                    <span class="font-semibold text-amber-600 dark:text-amber-400">
                        {{ $t('payroll.payslip_hours_fmt', { hours: (Number(earnings.ot_details?.day_hours || 0) + Number(earnings.ot_details?.night_hours || 0) + Number(earnings.ot_details?.weekend_hours || 0) + Number(earnings.ot_details?.holiday_hours || 0)).toFixed(1) }) }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">{{ $t('payroll.payslip_field_dependents') }}</span>
                    <strong class="text-gray-800 dark:text-gray-200">{{ $t('payroll.payslip_dependents_fmt', { count: deductions.family_deduction?.dependents_count || 0 }) }}</strong>
                </div>
            </div>

            <!-- Bảng So Sánh 2 Cột: Thu Nhập & Khấu Trừ -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <!-- CỘT 1: CÁC KHOẢN THU NHẬP (EARNINGS) -->
                <div class="p-4 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/30 dark:bg-blue-950/10 space-y-2.5">
                    <div class="font-bold text-blue-900 dark:text-blue-300 uppercase pb-1.5 border-b border-blue-200 dark:border-blue-800/60 flex items-center justify-between">
                        <span>{{ $t('payroll.payslip_section_income') }}</span>
                        <Money class="w-4 h-4 text-blue-600" />
                    </div>

                    <div class="space-y-1.5 divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">{{ $t('payroll.payslip_row_time_salary') }}</span>
                            <span class="font-semibold">{{ formatVND(earnings.base_pay_earned) }}</span>
                        </div>

                        <div v-if="Number(record?.position_allowance_amount) > 0" class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">{{ $t('payroll.payslip_allowance_position') }}</span>
                            <span class="font-semibold">{{ formatVND(record?.position_allowance_amount) }}</span>
                        </div>

                        <div v-if="Number(record?.child_allowance_amount) > 0" class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">{{ $t('payroll.payslip_allowance_child') }}</span>
                            <span class="font-semibold">{{ formatVND(record?.child_allowance_amount) }}</span>
                        </div>

                        <div v-if="Number(record?.certificate_allowance_amount) > 0" class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">{{ $t('payroll.payslip_allowance_certificate') }}</span>
                            <span class="font-semibold">{{ formatVND(record?.certificate_allowance_amount) }}</span>
                        </div>

                        <!-- Chi tiết làm thêm giờ -->
                        <div v-if="Number(earnings.total_ot_pay) > 0" class="pt-1.5 space-y-1">
                            <div class="flex justify-between font-semibold text-amber-700 dark:text-amber-300">
                                <span>{{ $t('payroll.payslip_ot_total') }}</span>
                                <span>{{ formatVND(earnings.total_ot_pay) }}</span>
                            </div>
                            <div class="pl-2.5 text-[11px] text-gray-500 space-y-0.5 border-l-2 border-amber-300 dark:border-amber-700">
                                <div v-if="earnings.ot_details?.day_hours > 0" class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_ot_day', { hours: earnings.ot_details.day_hours }) }}</span>
                                    <span>{{ formatVND(earnings.ot_details.day_pay) }}</span>
                                </div>
                                <div v-if="earnings.ot_details?.night_hours > 0" class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_ot_night', { hours: earnings.ot_details.night_hours }) }}</span>
                                    <span>{{ formatVND(earnings.ot_details.night_pay) }}</span>
                                </div>
                                <div v-if="earnings.ot_details?.weekend_hours > 0" class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_ot_weekend', { hours: earnings.ot_details.weekend_hours }) }}</span>
                                    <span>{{ formatVND(earnings.ot_details.weekend_pay) }}</span>
                                </div>
                                <div v-if="earnings.ot_details?.holiday_hours > 0" class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_ot_holiday', { hours: earnings.ot_details.holiday_hours }) }}</span>
                                    <span>{{ formatVND(earnings.ot_details.holiday_pay) }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="Number(earnings.penalty_amount) > 0" class="flex justify-between pt-1 text-red-600">
                            <span>{{ $t('payroll.payslip_penalty') }}</span>
                            <span>-{{ formatVND(earnings.penalty_amount) }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-blue-200 dark:border-blue-800 flex justify-between items-center text-sm font-bold text-blue-950 dark:text-blue-200">
                        <span>{{ $t('payroll.payslip_total_gross') }}</span>
                        <span>{{ formatVND(record?.gross_salary) }}</span>
                    </div>
                </div>

                <!-- CỘT 2: CÁC KHOẢN KHẤU TRỪ (DEDUCTIONS) -->
                <div class="p-4 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/30 dark:bg-red-950/10 space-y-2.5">
                    <div class="font-bold text-red-900 dark:text-red-300 uppercase pb-1.5 border-b border-red-200 dark:border-red-800/60 flex items-center justify-between">
                        <span>{{ $t('payroll.payslip_section_withholding_tax') }}</span>
                        <Shield class="w-4 h-4 text-red-600" />
                    </div>

                    <div class="space-y-1.5 divide-y divide-gray-100 dark:divide-gray-800">
                        <!-- Bảo hiểm NLĐ -->
                        <div class="space-y-1 pt-1">
                            <div class="flex justify-between font-semibold text-gray-700 dark:text-gray-300">
                                <span>{{ $t('payroll.payslip_insurance_employee') }}</span>
                                <span class="text-red-600 font-semibold">
                                    -{{ formatVND(Number(record?.social_insurance_employee) + Number(record?.health_insurance_employee) + Number(record?.unemployment_insurance_employee)) }}
                                </span>
                            </div>
                            <div class="pl-2.5 text-[11px] text-gray-500 space-y-0.5 border-l-2 border-red-300 dark:border-red-800">
                                <div class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_insurance_bhxh') }}</span>
                                    <span>{{ formatVND(record?.social_insurance_employee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_insurance_bhyt') }}</span>
                                    <span>{{ formatVND(record?.health_insurance_employee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_insurance_bhtn') }}</span>
                                    <span>{{ formatVND(record?.unemployment_insurance_employee) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bóc tách thuế TNCN -->
                        <div class="space-y-1 pt-1.5">
                            <div class="flex justify-between font-semibold text-gray-700 dark:text-gray-300">
                                <span>{{ $t('payroll.payslip_pit_label') }}</span>
                                <span class="text-red-600 font-bold">-{{ formatVND(record?.pit_tax_amount) }}</span>
                            </div>
                            <div class="pl-2.5 text-[11px] text-gray-500 space-y-0.5 border-l-2 border-red-300 dark:border-red-800">
                                <div v-if="Number(record?.tax_exempt_ot_amount) > 0" class="flex justify-between text-emerald-600 dark:text-emerald-400 font-medium">
                                    <span>{{ $t('payroll.payslip_tax_exempt_ot') }}</span>
                                    <span>{{ formatVND(record?.tax_exempt_ot_amount) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_deduction_self') }}</span>
                                    <span>{{ formatVND(deductions.family_deduction?.personal || 11000000) }}</span>
                                </div>
                                <div v-if="Number(deductions.family_deduction?.dependents_amount) > 0" class="flex justify-between">
                                    <span>{{ $t('payroll.payslip_deduction_dependent') }}</span>
                                    <span>{{ formatVND(deductions.family_deduction?.dependents_amount) }}</span>
                                </div>
                                <div class="flex justify-between font-medium text-gray-700 dark:text-gray-300">
                                    <span>{{ $t('payroll.payslip_taxable_income') }}</span>
                                    <span>{{ formatVND(deductions.assessable_income) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-red-200 dark:border-red-800 flex justify-between items-center text-sm font-bold text-red-950 dark:text-red-200">
                        <span>{{ $t('payroll.payslip_total_deductions') }}</span>
                        <span class="text-red-600">
                            -{{ formatVND(Number(record?.social_insurance_employee) + Number(record?.health_insurance_employee) + Number(record?.unemployment_insurance_employee) + Number(record?.pit_tax_amount)) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Tổng kết THỰC LĨNH NỔI BẬT (NET TAKE-HOME) -->
            <div class="p-4 sm:p-5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 text-white shadow-md flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-xs uppercase tracking-wider text-emerald-100 font-semibold block">
                        {{ $t('payroll.payslip_net_takehome') }}
                    </span>
                    <p class="text-[11px] text-emerald-100/90 mt-0.5">
                        {{ $t('payroll.payslip_net_takehome_desc') }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="text-2xl sm:text-3xl font-extrabold tracking-tight">
                        {{ formatVND(record?.net_salary) }}
                    </span>
                </div>
            </div>

            <!-- Khối Chi Phí Doanh Nghiệp Chi Trả (NSDLĐ Gánh Thêm) -->
            <div class="p-3.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-800/40 text-xs">
                <div class="font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center justify-between">
                    <span>{{ $t('payroll.payslip_employer_cost_title') }}</span>
                    <strong class="text-indigo-600 dark:text-indigo-400 font-bold">{{ formatVND(companyCosts.total) }}</strong>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] text-gray-500">
                    <div>{{ $t('payroll.payslip_cost_bhxh') }} <strong>{{ formatVND(companyCosts.bhxh) }}</strong></div>
                    <div>{{ $t('payroll.payslip_cost_bhyt') }} <strong>{{ formatVND(companyCosts.bhyt) }}</strong></div>
                    <div>{{ $t('payroll.payslip_cost_bhtn') }} <strong>{{ formatVND(companyCosts.bhtn) }}</strong></div>
                    <div>{{ $t('payroll.payslip_cost_union') }} <strong>{{ formatVND(companyCosts.union_fee) }}</strong></div>
                </div>
            </div>

            <!-- Footer Ký tên / Bản quyền -->
            <div class="pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-between items-center text-[11px] text-gray-400">
                <span>{{ $t('payroll.payslip_footer_security') }}</span>
                <span>{{ $t('payroll.payslip_footer_prepared') }}</span>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2.5 print:hidden">
                <el-button @click="visible = false">{{ $t('common.close') }}</el-button>
                <el-button type="primary" :icon="Printer" @click="printPayslip">
                    {{ $t('payroll.payslip_print_btn') }}
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<style scoped>
@media print {
    body * {
        visibility: hidden;
    }
    #printable-payslip, #printable-payslip * {
        visibility: visible;
    }
    #printable-payslip {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 20px;
    }
}
</style>
