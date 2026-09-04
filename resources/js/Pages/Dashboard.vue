<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import Timeline from 'primevue/timeline';
import { useToast } from 'primevue/usetoast';

const toast = useToast();

// Demo data for Dashboard
const stats = ref([
    { title: 'Tổng số Nhân sự', value: '1,248', change: '+12 tháng này', icon: 'pi pi-users', color: 'indigo' },
    { title: 'Quẹt thẻ đúng giờ', value: '94.6%', change: 'Hôm nay: 1,180 / 1,248', icon: 'pi pi-check-circle', color: 'emerald' },
    { title: 'Đơn chờ phê duyệt', value: '18', change: '5 đơn cần duyệt gấp', icon: 'pi pi-inbox', color: 'amber' },
    { title: 'Cảnh báo Tuân thủ (BLLĐ)', value: '3', change: '2 chạm trần 35h OT, 1 HĐLĐ', icon: 'pi pi-exclamation-triangle', color: 'rose' },
]);

// Demo Today Attendance Records
const todayAttendances = ref([
    { code: 'EMP001', name: 'Nguyễn Văn An', dept: 'Kỹ thuật Phần mềm', shift: 'Hành chính (08:00 - 17:00)', in: '07:55', out: '17:05', status: 'ON_TIME', ot: '0h' },
    { code: 'EMP002', name: 'Trần Thị Mai', dept: 'Kinh doanh & Bán lẻ', shift: 'Ca Sáng (06:00 - 14:00)', in: '06:12', out: '14:00', status: 'LATE', ot: '0h' },
    { code: 'EMP003', name: 'Lê Hoàng Nam', dept: 'Kho bãi & Vận tải', shift: 'Ca Chiều (14:00 - 22:00)', in: '13:58', out: '--:--', status: 'WORKING', ot: '0h' },
    { code: 'EMP004', name: 'Phạm Thị Lan', dept: 'Tài chính - Kế toán', shift: 'Hành chính (08:00 - 17:00)', in: '08:00', out: '19:30', status: 'OVERTIME', ot: '2.5h' },
]);

// Demo Career Timeline (Effective Dating)
const careerEvents = ref([
    { date: '15/01/2026', status: 'Active', title: 'Bổ nhiệm Trưởng nhóm Dev', desc: 'Quyết định số 05/QĐ-BN ký bởi Tổng Giám đốc' },
    { date: '01/07/2025', status: 'Historical', title: 'Tăng bậc lương kỹ sư cấp 2', desc: 'Điều chỉnh theo đánh giá năng lực định kỳ' },
    { date: '15/03/2024', status: 'Historical', title: 'Chính thức tiếp nhận nhân sự', desc: 'Hoàn tất thời gian thử việc 60 ngày' },
    { date: '15/01/2024', status: 'Historical', title: 'Gia nhập doanh nghiệp (Onboarding)', desc: 'Ký Hợp đồng thử việc 02 tháng' },
]);

// Consent Modal for Article 137 Labor Code
const isConsentModalOpen = ref(false);
const isConsented = ref(false);
const consentUrl = ref('');

const openConsentModal = () => {
    isConsentModalOpen.value = true;
};

const submitConsent = () => {
    if (!isConsented.value) {
        toast.add({ severity: 'error', summary: 'Chưa đủ điều kiện', detail: 'Người lao động bắt buộc phải tích cam kết tự nguyện!', life: 3000 });
        return;
    }
    isConsentModalOpen.value = false;
    toast.add({
        severity: 'success',
        summary: 'Đã ghi nhận cam kết tự nguyện',
        detail: 'Hệ thống đã lưu vết cam kết tuân thủ Điều 137 BLLĐ 2019.',
        life: 4000
    });
};
</script>

<template>
    <AppLayout>
        <Head title="Tổng quan Điều hành Nhân sự" />

        <div class="space-y-6">
            <!-- Hero Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 text-white p-6 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="space-y-2 relative z-10">
                    <div class="inline-flex items-center space-x-2 bg-white/10 backdrop-blur-md px-3 py-1 rounded-full text-xs font-medium text-indigo-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        <span>Smart-HR v1.0 • Laravel 13 Core • Vue 3 PrimeVue</span>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Trung tâm Điều hành Nguồn Nhân lực</h1>
                    <p class="text-sm text-indigo-200/90 max-w-2xl leading-relaxed">
                        Chấm công lũy tiến tự động, phân ca thông minh bảo đảm tuyệt đối quy định BLLĐ 2019 và biểu thuế TNCN 5 bậc mới.
                    </p>
                </div>
                <div class="flex items-center space-x-3 relative z-10">
                    <Button
                        label="Ký cam kết Điều 137 (Demo)"
                        icon="pi pi-file-edit"
                        severity="secondary"
                        class="p-button-outlined text-white border-white/30 hover:bg-white/10"
                        @click="openConsentModal"
                    />
                    <Button
                        label="Phân ca Tự động"
                        icon="pi pi-bolt"
                        class="bg-indigo-500 hover:bg-indigo-600 border-none shadow-md font-semibold text-white px-4 py-2.5 rounded-xl"
                    />
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <div
                    v-for="stat in stats"
                    :key="stat.title"
                    class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs hover:shadow-md transition"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">{{ stat.title }}</span>
                        <div
                            class="w-10 h-10 rounded-xl flex items-center justify-center"
                            :class="{
                                'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400': stat.color === 'indigo',
                                'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400': stat.color === 'emerald',
                                'bg-amber-50 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400': stat.color === 'amber',
                                'bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400': stat.color === 'rose',
                            }"
                        >
                            <i :class="[stat.icon, 'text-lg']" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <div class="text-2xl font-bold text-slate-800 dark:text-white tracking-tight">{{ stat.value }}</div>
                        <div class="text-xs text-slate-400 mt-1 flex items-center space-x-1">
                            <span>{{ stat.change }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two-Column Layout: Realtime Attendance Table & Career Timeline -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Today Attendance List (2 Cols) -->
                <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs p-5">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-base font-bold text-slate-800 dark:text-white">Điểm danh & Quẹt thẻ Hôm nay</h2>
                            <p class="text-xs text-slate-400">Dữ liệu thời gian thực từ Edge Collector Daemon qua HMAC-SHA256</p>
                        </div>
                        <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 font-medium">
                            Live Sync Active
                        </span>
                    </div>

                    <DataTable :value="todayAttendances" responsiveLayout="scroll" class="p-datatable-sm">
                        <Column field="code" header="Mã NV" :style="{ width: '100px' }">
                            <template #body="{ data }">
                                <span class="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ data.code }}</span>
                            </template>
                        </Column>
                        <Column field="name" header="Họ và Tên">
                            <template #body="{ data }">
                                <span class="font-medium text-slate-800 dark:text-slate-200 text-sm">{{ data.name }}</span>
                                <span class="block text-[11px] text-slate-400">{{ data.dept }}</span>
                            </template>
                        </Column>
                        <Column field="in" header="Giờ Vào" :style="{ width: '90px' }">
                            <template #body="{ data }">
                                <span class="font-mono text-xs">{{ data.in }}</span>
                            </template>
                        </Column>
                        <Column field="out" header="Giờ Ra" :style="{ width: '90px' }">
                            <template #body="{ data }">
                                <span class="font-mono text-xs">{{ data.out }}</span>
                            </template>
                        </Column>
                        <Column field="status" header="Trạng thái" :style="{ width: '130px' }">
                            <template #body="{ data }">
                                <Tag v-if="data.status === 'ON_TIME'" severity="success" value="Đúng giờ" rounded />
                                <Tag v-else-if="data.status === 'LATE'" severity="warn" value="Đi muộn (12p)" rounded />
                                <Tag v-else-if="data.status === 'WORKING'" severity="info" value="Đang làm việc" rounded />
                                <Tag v-else-if="data.status === 'OVERTIME'" severity="danger" value="OT 2.5h" rounded />
                            </template>
                        </Column>
                    </DataTable>
                </div>

                <!-- Career Timeline Preview (1 Col) -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs p-5">
                    <div class="mb-4">
                        <h2 class="text-base font-bold text-slate-800 dark:text-white">Cây Timeline Sự nghiệp</h2>
                        <p class="text-xs text-slate-400">Effective Dating: Quản lý lịch sử nhân sự đa thời điểm</p>
                    </div>

                    <Timeline :value="careerEvents" class="text-xs">
                        <template #opposite="{ item }">
                            <small class="text-slate-400 font-mono">{{ item.date }}</small>
                        </template>
                        <template #content="{ item }">
                            <div class="pb-4">
                                <div class="font-semibold text-slate-700 dark:text-slate-200 text-xs">{{ item.title }}</div>
                                <div class="text-[11px] text-slate-400 mt-0.5">{{ item.desc }}</div>
                            </div>
                        </template>
                    </Timeline>
                </div>
            </div>
        </div>

        <!-- Consent Modal (Article 137.1.b Labor Code 2019) -->
        <Dialog
            v-model:visible="isConsentModalOpen"
            modal
            header="Đơn Thỏa thuận Đồng thuận Tự nguyện (Điều 137 BLLĐ 2019)"
            :style="{ width: '560px' }"
        >
            <div class="space-y-4 text-sm text-slate-600 dark:text-slate-300">
                <div class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-200 text-xs leading-relaxed">
                    <p class="font-semibold mb-1">CĂN CỨ ĐIỀU 137 KHOẢN 1 ĐIỂM B BỘ LUẬT LAO ĐỘNG 2019:</p>
                    Người sử dụng lao động không được bố trí làm việc ban đêm (22:00 - 06:00) hoặc làm thêm giờ đối với người lao động đang nuôi con dưới 12 tháng tuổi, <strong>TRỪ TRƯỜNG HỢP ĐƯỢC NGƯỜI LAO ĐỘNG ĐỒNG Ý</strong>.
                </div>

                <div class="space-y-2">
                    <label class="flex items-start space-x-2.5 cursor-pointer">
                        <input
                            type="checkbox"
                            v-model="isConsented"
                            class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-xs leading-relaxed text-slate-800 dark:text-slate-200 font-medium">
                            Tôi xác nhận tự nguyện đồng thuận làm thêm giờ / làm việc ban đêm trong thời gian nuôi con dưới 12 tháng tuổi theo quy định tại Điều 137 BLLĐ 2019.
                        </span>
                    </label>
                </div>

                <div class="space-y-1 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                        Đường dẫn URL văn bản thỏa thuận (Tùy chọn - Giảm tải lưu trữ):
                    </label>
                    <input
                        type="url"
                        v-model="consentUrl"
                        placeholder="https://drive.google.com/file/d/... hoặc link OneDrive nội bộ"
                        class="w-full text-xs px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <span class="text-[11px] text-slate-400">
                        Hỗ trợ đính kèm URL Google Drive/OneDrive hoặc tải tệp trực tiếp để người duyệt đối soát.
                    </span>
                </div>
            </div>

            <template #footer>
                <div class="flex items-center justify-end space-x-2">
                    <Button label="Hủy bỏ" text severity="secondary" @click="isConsentModalOpen = false" />
                    <Button label="Xác nhận & Gửi cam kết" icon="pi pi-check" :disabled="!isConsented" @click="submitConsent" class="bg-indigo-600 text-white font-medium text-xs px-4 py-2 rounded-lg" />
                </div>
            </template>
        </Dialog>
    </AppLayout>
</template>
