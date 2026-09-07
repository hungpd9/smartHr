<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    Document,
    Download,
    Search,
    RefreshRight,
    Clock,
    Wallet,
    User,
    Warning,
    Present,
    Calendar,
    Filter,
    Check,
    InfoFilled,
    DocumentCopy,
} from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

interface ReportTypeItem {
    value: string;
    label: string;
    description: string;
    category: string;
}

interface ColumnDef {
    key: string;
    label: string;
    type?: string;
    align?: 'left' | 'center' | 'right';
    width?: number;
}

interface PreviewResponse {
    report_type: string;
    report_label: string;
    columns: ColumnDef[];
    rows: Record<string, any>[];
    total: number;
    page: number;
    per_page: number;
    summary: Record<string, any>;
}

interface ExportHistoryItem {
    id: number;
    report_type: string;
    report_label: string;
    file_name: string;
    file_format: string;
    row_count: number;
    execution_time_ms: number;
    user_name: string;
    created_at: string;
}

const props = defineProps<{
    reportTypes: ReportTypeItem[];
    branches: Array<{ id: number; name: string; code: string }>;
    departments: Array<{ id: number; name: string }>;
    currentFilters: {
        month_year: string;
        branch_id?: string | number | null;
        department_id?: string | number | null;
        search?: string | null;
        severity?: string | null;
        is_resolved?: string | null;
        is_tax_dependent?: string | null;
    };
    selectedReportType: string;
    initialPreview: PreviewResponse;
    recentExports: ExportHistoryItem[];
}>();

// Active Tab
const activeTab = ref<'preview' | 'history'>('preview');

// Loại báo cáo đang chọn
const selectedType = ref<string>(props.selectedReportType);

// Bộ lọc
const filterForm = reactive({
    month_year: props.currentFilters.month_year || new Date().toISOString().slice(0, 7),
    branch_id: props.currentFilters.branch_id || null,
    department_id: props.currentFilters.department_id || null,
    search: props.currentFilters.search || '',
    severity: props.currentFilters.severity || null,
    is_resolved: props.currentFilters.is_resolved || 'ALL',
    is_tax_dependent: props.currentFilters.is_tax_dependent || 'ALL',
});

// Dữ liệu bảng xem trước
const previewData = ref<PreviewResponse>(props.initialPreview);
const isLoading = ref(false);
const currentPage = ref(props.initialPreview.page || 1);
const pageSize = ref(props.initialPreview.per_page || 15);

// Lịch sử xuất
const historyList = ref<ExportHistoryItem[]>(props.recentExports || []);
const isHistoryLoading = ref(false);

// Icon tương ứng cho từng loại báo cáo
const getReportIcon = (type: string) => {
    switch (type) {
        case 'TIMESHEET_SUMMARY': return Clock;
        case 'PAYROLL_ANALYSIS': return Wallet;
        case 'HEADCOUNT_TURNOVER': return User;
        case 'COMPLIANCE_AUDIT': return Warning;
        case 'CHILD_WELFARE': return Present;
        default: return Document;
    }
};

// Đổi loại báo cáo
const handleSelectReport = (type: string) => {
    selectedType.value = type;
    currentPage.value = 1;
    fetchPreviewData();
};

// Tải dữ liệu xem trước qua API Ajax
const fetchPreviewData = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get('/reports/preview', {
            params: {
                report_type: selectedType.value,
                filters: filterForm,
                page: currentPage.value,
                per_page: pageSize.value,
            },
        });
        previewData.value = response.data;
    } catch (error) {
        ElMessage.error('Không thể tải dữ liệu xem trước. Vui lòng thử lại!');
    } finally {
        isLoading.value = false;
    }
};

const handlePageChange = (page: number) => {
    currentPage.value = page;
    fetchPreviewData();
};

const handleResetFilters = () => {
    filterForm.month_year = new Date().toISOString().slice(0, 7);
    filterForm.branch_id = null;
    filterForm.department_id = null;
    filterForm.search = '';
    filterForm.severity = null;
    filterForm.is_resolved = 'ALL';
    filterForm.is_tax_dependent = 'ALL';
    currentPage.value = 1;
    fetchPreviewData();
};

// Kích hoạt xuất báo cáo Streaming Download
const handleExport = (format: 'CSV' | 'EXCEL_TSV') => {
    const params = new URLSearchParams({
        report_type: selectedType.value,
        format,
        month_year: filterForm.month_year || '',
        branch_id: filterForm.branch_id ? String(filterForm.branch_id) : '',
        department_id: filterForm.department_id ? String(filterForm.department_id) : '',
        search: filterForm.search || '',
        severity: filterForm.severity || '',
        is_resolved: filterForm.is_resolved || 'ALL',
        is_tax_dependent: filterForm.is_tax_dependent || 'ALL',
    });

    ElMessage.success(`Đang khởi tạo tệp ${format}... Quá trình tải sẽ bắt đầu ngay.`);

    // Mở URL download trực tiếp
    window.location.href = `/reports/export?${params.toString()}`;

    // Tự động làm mới danh sách lịch sử sau 2.5s
    setTimeout(() => {
        fetchHistoryData();
    }, 2500);
};

// Làm mới lịch sử
const fetchHistoryData = async () => {
    isHistoryLoading.value = true;
    try {
        const response = await axios.get('/reports/history');
        if (response.data?.data) {
            historyList.value = response.data.data.map((item: any) => ({
                id: item.id,
                report_type: item.report_type,
                report_label: item.report_type_label || item.report_type,
                file_name: item.file_name,
                file_format: item.file_format,
                row_count: item.row_count,
                execution_time_ms: item.execution_time_ms,
                user_name: item.user?.name ?? 'Hệ thống',
                created_at: new Date(item.created_at).toLocaleString('vi-VN'),
            }));
        }
    } catch (e) {
        // im lặng hoặc thông báo
    } finally {
        isHistoryLoading.value = false;
    }
};

// Format số tiền VNĐ
const formatCurrency = (val: any): string => {
    const num = Number(val);
    if (isNaN(num)) return '0 ₫';
    return num.toLocaleString('vi-VN') + ' ₫';
};

// Format số lượng / phần trăm
const formatCellValue = (val: any, col: ColumnDef): string => {
    if (val === null || val === undefined || val === '') return '---';
    if (col.type === 'currency') return formatCurrency(val);
    if (col.type === 'percent') return `${val}%`;
    if (col.type === 'number') return Number(val).toLocaleString('vi-VN');
    return String(val);
};

// Danh sách các thẻ Summary KPIs linh hoạt
const summaryCards = computed(() => {
    const s = previewData.value.summary || {};
    const type = selectedType.value;

    if (type === 'TIMESHEET_SUMMARY') {
        return [
            { label: 'Tổng nhân sự', value: s.total_employees ?? 0, unit: 'người', color: 'indigo' },
            { label: 'Tổng công thực tế', value: s.total_actual_days ?? 0, unit: 'công', color: 'emerald' },
            { label: 'Tổng giờ OT', value: s.total_ot_hours ?? 0, unit: 'giờ', color: 'amber' },
            { label: 'Tổng phút đi muộn', value: s.total_late_minutes ?? 0, unit: 'phút', color: 'rose' },
        ];
    }

    if (type === 'PAYROLL_ANALYSIS') {
        return [
            { label: 'Tổng thu nhập Gross', value: formatCurrency(s.total_gross ?? 0), unit: '', color: 'indigo' },
            { label: 'Tổng thực nhận (Net)', value: formatCurrency(s.total_net ?? 0), unit: '', color: 'emerald' },
            { label: 'Thuế TNCN khấu trừ', value: formatCurrency(s.total_tax ?? 0), unit: '', color: 'amber' },
            { label: 'Bảo hiểm NSDLĐ đóng', value: formatCurrency(s.total_employer_cost ?? 0), unit: '', color: 'blue' },
        ];
    }

    if (type === 'HEADCOUNT_TURNOVER') {
        return [
            { label: 'Quy mô hiện tại', value: s.total_headcount ?? 0, unit: 'nhân sự', color: 'indigo' },
            { label: 'Tuyển mới (Onboard)', value: s.total_onboard ?? 0, unit: 'nhân sự', color: 'emerald' },
            { label: 'Nghỉ việc (Offboard)', value: s.total_offboard ?? 0, unit: 'nhân sự', color: 'rose' },
            { label: 'Tăng trưởng ròng', value: s.net_growth ?? 0, unit: 'nhân sự', color: 'cyan' },
        ];
    }

    if (type === 'COMPLIANCE_AUDIT') {
        return [
            { label: 'Tổng cảnh báo', value: s.total_alerts ?? 0, unit: 'vụ việc', color: 'indigo' },
            { label: 'Vi phạm nghiêm trọng', value: s.critical_count ?? 0, unit: 'Critical', color: 'rose' },
            { label: 'Cảnh báo tuân thủ', value: s.warning_count ?? 0, unit: 'Warning', color: 'amber' },
            { label: 'Đã khắc phục xong', value: s.resolved_count ?? 0, unit: 'Resolved', color: 'emerald' },
        ];
    }

    if (type === 'CHILD_WELFARE') {
        return [
            { label: 'Tổng số con nhỏ', value: s.total_children ?? 0, unit: 'cháu', color: 'indigo' },
            { label: 'Con dưới 12 tháng (Đ.137)', value: s.under_12m_count ?? 0, unit: 'cháu', color: 'rose' },
            { label: 'Đăng ký giảm trừ NPT', value: s.tax_dependent_count ?? 0, unit: 'người', color: 'emerald' },
            { label: 'Tổng trợ cấp hàng tháng', value: formatCurrency(s.total_monthly_allowance ?? 0), unit: '', color: 'amber' },
        ];
    }

    return [];
});
</script>

<template>
    <AppLayout>
        <Head title="Trung Tâm Báo Cáo Phân Tích & Xuất Dữ Liệu" />

        <div class="space-y-6 pb-12">
            <!-- Banner Header Điều Hành -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white rounded-2xl p-6 shadow-xl border border-indigo-800/30 relative overflow-hidden">
                <div class="absolute right-0 top-0 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none -mr-20 -mt-20"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-2">
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-semibold border border-indigo-400/30">
                            <el-icon><Document /></el-icon>
                            <span>Executive Reporting & High-Performance Export Engine</span>
                        </div>
                        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-white">
                            Trung Tâm Báo Cáo Phân Tích & Xuất Dữ Liệu
                        </h1>
                        <p class="text-sm text-slate-300 max-w-2xl leading-relaxed">
                            Khung phân tích dữ liệu đa chiều hỗ trợ quản trị nhân lực, giám sát chi phí quỹ lương, kỷ luật công tác và kiểm toán tuân thủ Bộ luật Lao động 2019. Kỹ thuật xuất dữ liệu Streaming bảo đảm tốc độ tức thì và font tiếng Việt chuẩn xác 100%.
                        </p>
                    </div>

                    <div class="flex items-center space-x-3 shrink-0">
                        <el-button
                            type="primary"
                            color="#4f46e5"
                            size="large"
                            class="!rounded-xl shadow-lg !font-semibold"
                            @click="handleExport('CSV')"
                        >
                            <el-icon class="mr-1.5"><Download /></el-icon>
                            Xuất Excel (CSV)
                        </el-button>

                        <el-button
                            size="large"
                            class="!rounded-xl !bg-white/10 !text-white !border-white/20 hover:!bg-white/20 !font-medium"
                            @click="handleExport('EXCEL_TSV')"
                        >
                            <el-icon class="mr-1.5"><DocumentCopy /></el-icon>
                            Xuất Excel (TSV)
                        </el-button>
                    </div>
                </div>
            </div>

            <!-- Tab Chuyển Đổi: Báo Cáo / Lịch Sử Xuất -->
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <div class="flex items-center space-x-2 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl border border-slate-200/80 dark:border-slate-700/60">
                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition"
                        :class="activeTab === 'preview'
                            ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                        @click="activeTab = 'preview'"
                    >
                        <span class="flex items-center space-x-2">
                            <el-icon><Document /></el-icon>
                            <span>Danh Mục Báo Cáo & Xem Trước</span>
                        </span>
                    </button>

                    <button
                        type="button"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition"
                        :class="activeTab === 'history'
                            ? 'bg-white dark:bg-slate-700 text-indigo-600 dark:text-indigo-400 shadow-xs'
                            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200'"
                        @click="activeTab = 'history'"
                    >
                        <span class="flex items-center space-x-2">
                            <el-icon><Clock /></el-icon>
                            <span>Nhật Ký Xuất Dữ Liệu (Audit Trail)</span>
                            <span v-if="historyList.length" class="text-xs bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded-full">
                                {{ historyList.length }}
                            </span>
                        </span>
                    </button>
                </div>

                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center space-x-1.5">
                    <el-icon class="text-emerald-500"><Check /></el-icon>
                    <span>Chuẩn UTF-8 BOM • Tiêu thụ RAM &lt; 15MB</span>
                </div>
            </div>

            <!-- TAB 1: DANH MỤC BÁO CÁO & XEM TRƯỚC -->
            <div v-show="activeTab === 'preview'" class="space-y-6">
                <!-- Grid 5 Loại Báo Cáo Chuyên Sâu -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
                    <div
                        v-for="item in reportTypes"
                        :key="item.value"
                        class="p-4 rounded-xl border transition-all cursor-pointer flex flex-col justify-between"
                        :class="selectedType === item.value
                            ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 dark:border-indigo-500 shadow-md ring-2 ring-indigo-500/20'
                            : 'bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 shadow-xs'"
                        @click="handleSelectReport(item.value)"
                    >
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <div
                                    class="w-9 h-9 rounded-lg flex items-center justify-center"
                                    :class="selectedType === item.value
                                        ? 'bg-indigo-600 text-white shadow-xs'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400'"
                                >
                                    <el-icon :size="18">
                                        <component :is="getReportIcon(item.value)" />
                                    </el-icon>
                                </div>
                                <span
                                    class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                    :class="selectedType === item.value
                                        ? 'bg-indigo-200/60 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300'
                                        : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'"
                                >
                                    {{ item.category }}
                                </span>
                            </div>

                            <h3
                                class="font-bold text-sm leading-snug line-clamp-2"
                                :class="selectedType === item.value ? 'text-indigo-900 dark:text-indigo-200' : 'text-slate-800 dark:text-slate-200'"
                            >
                                {{ item.label }}
                            </h3>

                            <p class="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 leading-relaxed">
                                {{ item.description }}
                            </p>
                        </div>

                        <div class="pt-3 mt-2 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs">
                            <span :class="selectedType === item.value ? 'text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-slate-400'">
                                {{ selectedType === item.value ? 'Đang chọn' : 'Nhấp để xem' }}
                            </span>
                            <span v-if="selectedType === item.value" class="w-2 h-2 rounded-full bg-indigo-600"></span>
                        </div>
                    </div>
                </div>

                <!-- Thanh Bộ Lọc Dữ Liệu Tinh Chỉnh -->
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 text-sm font-bold text-slate-800 dark:text-slate-200">
                            <el-icon class="text-indigo-600"><Filter /></el-icon>
                            <span>Bộ Lọc Tham Số Báo Cáo</span>
                            <span class="text-xs font-normal text-slate-400">• {{ previewData.report_label }}</span>
                        </div>

                        <div class="flex items-center space-x-2">
                            <el-button size="small" @click="handleResetFilters">
                                <el-icon class="mr-1"><RefreshRight /></el-icon>
                                Đặt lại
                            </el-button>
                            <el-button type="primary" size="small" color="#4f46e5" @click="fetchPreviewData">
                                <el-icon class="mr-1"><Search /></el-icon>
                                Áp dụng bộ lọc
                            </el-button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5 pt-1">
                        <!-- Tháng báo cáo -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Tháng áp dụng
                            </label>
                            <el-date-picker
                                v-model="filterForm.month_year"
                                type="month"
                                placeholder="Chọn tháng"
                                format="MM/YYYY"
                                value-format="YYYY-MM"
                                class="!w-full"
                                @change="fetchPreviewData"
                            />
                        </div>

                        <!-- Chi nhánh -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Chi nhánh
                            </label>
                            <el-select
                                v-model="filterForm.branch_id"
                                placeholder="Tất cả chi nhánh"
                                clearable
                                class="!w-full"
                                @change="fetchPreviewData"
                            >
                                <el-option
                                    v-for="b in branches"
                                    :key="b.id"
                                    :label="b.name"
                                    :value="b.id"
                                />
                            </el-select>
                        </div>

                        <!-- Phòng ban -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Khối / Phòng ban
                            </label>
                            <el-select
                                v-model="filterForm.department_id"
                                placeholder="Tất cả phòng ban"
                                clearable
                                class="!w-full"
                                @change="fetchPreviewData"
                            >
                                <el-option
                                    v-for="d in departments"
                                    :key="d.id"
                                    :label="d.name"
                                    :value="d.id"
                                />
                            </el-select>
                        </div>

                        <!-- Tìm kiếm tên/mã -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">
                                Tìm kiếm nhân viên
                            </label>
                            <el-input
                                v-model="filterForm.search"
                                placeholder="Mã NV hoặc họ tên..."
                                clearable
                                :prefix-icon="Search"
                                @keyup.enter="fetchPreviewData"
                                @clear="fetchPreviewData"
                            />
                        </div>
                    </div>
                </div>

                <!-- 4 Thẻ Summary KPIs -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div
                        v-for="(card, idx) in summaryCards"
                        :key="idx"
                        class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center space-x-3.5"
                    >
                        <div
                            class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                            :class="{
                                'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400': card.color === 'indigo',
                                'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400': card.color === 'emerald',
                                'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400': card.color === 'amber',
                                'bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400': card.color === 'rose',
                                'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400': card.color === 'blue',
                                'bg-cyan-50 dark:bg-cyan-950/60 text-cyan-600 dark:text-cyan-400': card.color === 'cyan',
                            }"
                        >
                            <el-icon :size="20"><Document /></el-icon>
                        </div>

                        <div class="truncate">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">
                                {{ card.label }}
                            </p>
                            <div class="flex items-baseline space-x-1 mt-0.5">
                                <span class="text-lg font-bold text-slate-900 dark:text-slate-100">
                                    {{ card.value }}
                                </span>
                                <span v-if="card.unit" class="text-xs text-slate-400 font-normal">
                                    {{ card.unit }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bảng Xem Trước Dữ Liệu (Preview Table) -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden space-y-4 p-5">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
                        <div class="space-y-0.5">
                            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                                <span>Bản Xem Trước Dữ Liệu</span>
                                <span class="text-xs px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-normal">
                                    {{ previewData.total }} bản ghi tìm thấy
                                </span>
                            </h2>
                            <p class="text-xs text-slate-400">
                                Hiển thị trang {{ currentPage }} / {{ Math.ceil(previewData.total / pageSize) || 1 }}
                            </p>
                        </div>

                        <div class="flex items-center space-x-2">
                            <el-button
                                type="success"
                                size="default"
                                color="#10b981"
                                class="!rounded-lg !font-semibold"
                                @click="handleExport('CSV')"
                            >
                                <el-icon class="mr-1.5"><Download /></el-icon>
                                Tải file CSV
                            </el-button>
                        </div>
                    </div>

                    <!-- Table component -->
                    <el-table
                        v-loading="isLoading"
                        :data="previewData.rows"
                        stripe
                        border
                        style="width: 100%"
                        class="rounded-xl overflow-hidden"
                        empty-text="Không tìm thấy dữ liệu phù hợp với bộ lọc đã chọn"
                    >
                        <el-table-column
                            v-for="col in previewData.columns"
                            :key="col.key"
                            :prop="col.key"
                            :label="col.label"
                            :min-width="col.width || 120"
                            :align="col.align || 'left'"
                            show-overflow-tooltip
                        >
                            <template #default="{ row }">
                                <span
                                    v-if="col.key === 'submission_status'"
                                    class="px-2 py-0.5 rounded text-xs font-semibold"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400': ['MANAGER_APPROVED', 'HR_LOCKED'].includes(row[col.key]),
                                        'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400': row[col.key] === 'SUBMITTED',
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400': ['DRAFT', 'EMPLOYEE_REVIEW'].includes(row[col.key]),
                                        'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400': row[col.key] === 'REJECTED',
                                    }"
                                >
                                    {{ row[col.key] }}
                                </span>

                                <span
                                    v-else-if="col.key === 'severity'"
                                    class="px-2 py-0.5 rounded text-xs font-semibold"
                                    :class="{
                                        'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400': row[col.key] === 'CRITICAL',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400': row[col.key] === 'WARNING',
                                        'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400': row[col.key] === 'INFO',
                                    }"
                                >
                                    {{ row[col.key] }}
                                </span>

                                <span
                                    v-else-if="col.key === 'status' && selectedType === 'COMPLIANCE_AUDIT'"
                                    class="px-2 py-0.5 rounded text-xs font-semibold"
                                    :class="row[col.key] === 'ĐÃ KHẮC PHỤC'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400'"
                                >
                                    {{ row[col.key] }}
                                </span>

                                <span v-else :class="{ 'font-semibold text-slate-800 dark:text-slate-200': col.type === 'currency' }">
                                    {{ formatCellValue(row[col.key], col) }}
                                </span>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between pt-2">
                        <span class="text-xs text-slate-400">
                            Số dòng trên mỗi trang: {{ pageSize }}
                        </span>
                        <el-pagination
                            v-model:current-page="currentPage"
                            :page-size="pageSize"
                            :total="previewData.total"
                            background
                            layout="prev, pager, next"
                            @current-change="handlePageChange"
                        />
                    </div>
                </div>
            </div>

            <!-- TAB 2: NHẬT KÝ XUẤT DỮ LIỆU (EXPORT AUDIT TRAIL) -->
            <div v-show="activeTab === 'history'" class="space-y-6">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs p-5 space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                        <div>
                            <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">
                                Nhật Ký Kiểm Toán Xuất Báo Cáo
                            </h2>
                            <p class="text-xs text-slate-400">
                                Lưu vết minh bạch thông tin người xuất, loại báo cáo, thời gian xử lý và số dòng dữ liệu.
                            </p>
                        </div>

                        <el-button size="small" @click="fetchHistoryData" :loading="isHistoryLoading">
                            <el-icon class="mr-1"><RefreshRight /></el-icon>
                            Làm mới
                        </el-button>
                    </div>

                    <el-table
                        v-loading="isHistoryLoading"
                        :data="historyList"
                        stripe
                        border
                        style="width: 100%"
                        empty-text="Chưa có nhật ký xuất báo cáo nào được ghi nhận"
                    >
                        <el-table-column prop="created_at" label="Thời điểm xuất" min-width="150" />
                        <el-table-column prop="user_name" label="Người thực hiện" min-width="150" />
                        <el-table-column prop="report_label" label="Loại báo cáo" min-width="220" />
                        <el-table-column prop="file_format" label="Định dạng" width="110" align="center">
                            <template #default="{ row }">
                                <span class="px-2 py-0.5 rounded text-xs font-bold bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    {{ row.file_format }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="row_count" label="Số dòng" width="110" align="right">
                            <template #default="{ row }">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">
                                    {{ Number(row.row_count).toLocaleString('vi-VN') }}
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="execution_time_ms" label="Thời gian xử lý" width="140" align="right">
                            <template #default="{ row }">
                                <span class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                    {{ row.execution_time_ms }} ms
                                </span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="file_name" label="Tên file đã tạo" min-width="260" show-overflow-tooltip />
                    </el-table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
