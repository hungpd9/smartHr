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
        title="Phiếu Lương Điện Tử Chi Tiết (Payslip Breakdown)"
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
                                Phiếu Lương Điện Tử &bull; Smart-HR
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Bóc tách thu nhập Gross-to-Net, Bảo hiểm bắt buộc & Thuế TNCN
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-right">
                    <span class="inline-block px-3 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs font-bold font-mono">
                        KỲ LƯƠNG: {{ record?.month_year }}
                    </span>
                    <div class="text-[11px] text-gray-400 mt-1">
                        Ngày xuất phiếu: {{ new Date().toLocaleDateString('vi-VN') }}
                    </div>
                </div>
            </div>

            <!-- Thông tin Nhân viên -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 text-xs">
                <div>
                    <span class="text-gray-400 block text-[11px]">Mã nhân viên:</span>
                    <strong class="font-mono text-gray-900 dark:text-gray-100 text-sm">{{ record?.employee?.user_code }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">Họ và tên:</span>
                    <strong class="text-gray-900 dark:text-gray-100 text-sm">{{ record?.employee?.full_name }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">Phòng ban:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        {{ record?.employee?.current_timeline?.department?.current_version?.name || 'Hội sở chính' }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">Chức danh / Vị trí:</span>
                    <span class="font-medium text-gray-800 dark:text-gray-200">
                        {{ record?.employee?.current_timeline?.position?.name || 'Nhân viên' }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">Lương thỏa thuận hợp đồng:</span>
                    <strong class="text-indigo-600 dark:text-indigo-400 font-semibold">{{ formatVND(earnings.contract_base_salary || record?.gross_salary) }}</strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">Ngày công thực tế / Chuẩn:</span>
                    <strong class="text-gray-800 dark:text-gray-200">
                        {{ earnings.actual_work_days ?? '--' }} / {{ earnings.standard_work_days ?? '22' }} công
                    </strong>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">Tổng giờ làm thêm (OT):</span>
                    <span class="font-semibold text-amber-600 dark:text-amber-400">
                        {{ (Number(earnings.ot_details?.day_hours || 0) + Number(earnings.ot_details?.night_hours || 0) + Number(earnings.ot_details?.weekend_hours || 0) + Number(earnings.ot_details?.holiday_hours || 0)).toFixed(1) }} giờ
                    </span>
                </div>
                <div>
                    <span class="text-gray-400 block text-[11px]">Người phụ thuộc giảm trừ:</span>
                    <strong class="text-gray-800 dark:text-gray-200">{{ deductions.family_deduction?.dependents_count || 0 }} người</strong>
                </div>
            </div>

            <!-- Bảng So Sánh 2 Cột: Thu Nhập & Khấu Trừ -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <!-- CỘT 1: CÁC KHOẢN THU NHẬP (EARNINGS) -->
                <div class="p-4 rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/30 dark:bg-blue-950/10 space-y-2.5">
                    <div class="font-bold text-blue-900 dark:text-blue-300 uppercase pb-1.5 border-b border-blue-200 dark:border-blue-800/60 flex items-center justify-between">
                        <span>I. Các Khoản Thu Nhập (Gross)</span>
                        <Money class="w-4 h-4 text-blue-600" />
                    </div>

                    <div class="space-y-1.5 divide-y divide-gray-100 dark:divide-gray-800">
                        <div class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">Lương thời gian (ngày công chuẩn):</span>
                            <span class="font-semibold">{{ formatVND(earnings.base_pay_earned) }}</span>
                        </div>

                        <div v-if="Number(record?.position_allowance_amount) > 0" class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">Phụ cấp chức vụ / trách nhiệm:</span>
                            <span class="font-semibold">{{ formatVND(record?.position_allowance_amount) }}</span>
                        </div>

                        <div v-if="Number(record?.child_allowance_amount) > 0" class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">Trợ cấp con nhỏ hàng tháng:</span>
                            <span class="font-semibold">{{ formatVND(record?.child_allowance_amount) }}</span>
                        </div>

                        <div v-if="Number(record?.certificate_allowance_amount) > 0" class="flex justify-between pt-1">
                            <span class="text-gray-600 dark:text-gray-300">Phụ cấp chứng chỉ chuyên môn:</span>
                            <span class="font-semibold">{{ formatVND(record?.certificate_allowance_amount) }}</span>
                        </div>

                        <!-- Chi tiết làm thêm giờ -->
                        <div v-if="Number(earnings.total_ot_pay) > 0" class="pt-1.5 space-y-1">
                            <div class="flex justify-between font-semibold text-amber-700 dark:text-amber-300">
                                <span>Tiền làm thêm giờ (OT):</span>
                                <span>{{ formatVND(earnings.total_ot_pay) }}</span>
                            </div>
                            <div class="pl-2.5 text-[11px] text-gray-500 space-y-0.5 border-l-2 border-amber-300 dark:border-amber-700">
                                <div v-if="earnings.ot_details?.day_hours > 0" class="flex justify-between">
                                    <span>&bull; Ngày thường (150%): {{ earnings.ot_details.day_hours }}h</span>
                                    <span>{{ formatVND(earnings.ot_details.day_pay) }}</span>
                                </div>
                                <div v-if="earnings.ot_details?.night_hours > 0" class="flex justify-between">
                                    <span>&bull; Ca đêm (200%): {{ earnings.ot_details.night_hours }}h</span>
                                    <span>{{ formatVND(earnings.ot_details.night_pay) }}</span>
                                </div>
                                <div v-if="earnings.ot_details?.weekend_hours > 0" class="flex justify-between">
                                    <span>&bull; Cuối tuần (200%): {{ earnings.ot_details.weekend_hours }}h</span>
                                    <span>{{ formatVND(earnings.ot_details.weekend_pay) }}</span>
                                </div>
                                <div v-if="earnings.ot_details?.holiday_hours > 0" class="flex justify-between">
                                    <span>&bull; Ngày lễ tết (300%): {{ earnings.ot_details.holiday_hours }}h</span>
                                    <span>{{ formatVND(earnings.ot_details.holiday_pay) }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-if="Number(earnings.penalty_amount) > 0" class="flex justify-between pt-1 text-red-600">
                            <span>Phạt vi phạm chấm công:</span>
                            <span>-{{ formatVND(earnings.penalty_amount) }}</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-blue-200 dark:border-blue-800 flex justify-between items-center text-sm font-bold text-blue-950 dark:text-blue-200">
                        <span>TỔNG THU NHẬP (GROSS):</span>
                        <span>{{ formatVND(record?.gross_salary) }}</span>
                    </div>
                </div>

                <!-- CỘT 2: CÁC KHOẢN KHẤU TRỪ (DEDUCTIONS) -->
                <div class="p-4 rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/30 dark:bg-red-950/10 space-y-2.5">
                    <div class="font-bold text-red-900 dark:text-red-300 uppercase pb-1.5 border-b border-red-200 dark:border-red-800/60 flex items-center justify-between">
                        <span>II. Các Khoản Khấu Trừ & Thuế</span>
                        <Shield class="w-4 h-4 text-red-600" />
                    </div>

                    <div class="space-y-1.5 divide-y divide-gray-100 dark:divide-gray-800">
                        <!-- Bảo hiểm NLĐ -->
                        <div class="space-y-1 pt-1">
                            <div class="flex justify-between font-semibold text-gray-700 dark:text-gray-300">
                                <span>Bảo hiểm bắt buộc NLĐ (10.5%):</span>
                                <span class="text-red-600 font-semibold">
                                    -{{ formatVND(Number(record?.social_insurance_employee) + Number(record?.health_insurance_employee) + Number(record?.unemployment_insurance_employee)) }}
                                </span>
                            </div>
                            <div class="pl-2.5 text-[11px] text-gray-500 space-y-0.5 border-l-2 border-red-300 dark:border-red-800">
                                <div class="flex justify-between">
                                    <span>&bull; BHXH (8.0%):</span>
                                    <span>{{ formatVND(record?.social_insurance_employee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>&bull; BHYT (1.5%):</span>
                                    <span>{{ formatVND(record?.health_insurance_employee) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>&bull; BHTN (1.0%):</span>
                                    <span>{{ formatVND(record?.unemployment_insurance_employee) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Bóc tách thuế TNCN -->
                        <div class="space-y-1 pt-1.5">
                            <div class="flex justify-between font-semibold text-gray-700 dark:text-gray-300">
                                <span>Thuế Thu Nhập Cá Nhân (TNCN):</span>
                                <span class="text-red-600 font-bold">-{{ formatVND(record?.pit_tax_amount) }}</span>
                            </div>
                            <div class="pl-2.5 text-[11px] text-gray-500 space-y-0.5 border-l-2 border-red-300 dark:border-red-800">
                                <div v-if="Number(record?.tax_exempt_ot_amount) > 0" class="flex justify-between text-emerald-600 dark:text-emerald-400 font-medium">
                                    <span>&bull; OT miễn thuế (NĐ 145/2020):</span>
                                    <span>{{ formatVND(record?.tax_exempt_ot_amount) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>&bull; Giảm trừ bản thân:</span>
                                    <span>{{ formatVND(deductions.family_deduction?.personal || 11000000) }}</span>
                                </div>
                                <div v-if="Number(deductions.family_deduction?.dependents_amount) > 0" class="flex justify-between">
                                    <span>&bull; Giảm trừ người phụ thuộc:</span>
                                    <span>{{ formatVND(deductions.family_deduction?.dependents_amount) }}</span>
                                </div>
                                <div class="flex justify-between font-medium text-gray-700 dark:text-gray-300">
                                    <span>&bull; Thu nhập tính thuế:</span>
                                    <span>{{ formatVND(deductions.assessable_income) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-red-200 dark:border-red-800 flex justify-between items-center text-sm font-bold text-red-950 dark:text-red-200">
                        <span>TỔNG CỘNG KHẤU TRỪ:</span>
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
                        Lương Thực Lĩnh Kỳ Này (Net Salary)
                    </span>
                    <p class="text-[11px] text-emerald-100/90 mt-0.5">
                        Số tiền đã khấu trừ bảo hiểm bắt buộc và thuế TNCN theo luật định
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
                    <span>Chi Phí Doanh Nghiệp (Người sử dụng lao động đóng thêm 23.5%):</span>
                    <strong class="text-indigo-600 dark:text-indigo-400 font-bold">{{ formatVND(companyCosts.total) }}</strong>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] text-gray-500">
                    <div>BHXH DN (17.5%): <strong>{{ formatVND(companyCosts.bhxh) }}</strong></div>
                    <div>BHYT DN (3.0%): <strong>{{ formatVND(companyCosts.bhyt) }}</strong></div>
                    <div>BHTN DN (1.0%): <strong>{{ formatVND(companyCosts.bhtn) }}</strong></div>
                    <div>Kinh phí CĐ (2.0%): <strong>{{ formatVND(companyCosts.union_fee) }}</strong></div>
                </div>
            </div>

            <!-- Footer Ký tên / Bản quyền -->
            <div class="pt-4 border-t border-gray-200 dark:border-gray-800 flex justify-between items-center text-[11px] text-gray-400">
                <span>Smart-HR Enterprise &bull; Xác thực bảo mật toàn vẹn CSDL</span>
                <span>Người lập phiếu: Phòng Kế toán / Nhân sự</span>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2.5 print:hidden">
                <el-button @click="visible = false">Đóng</el-button>
                <el-button type="primary" :icon="Printer" @click="printPayslip">
                    In Phiếu Lương
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
