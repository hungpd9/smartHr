import { ref, computed } from 'vue';
import axios from 'axios';
import vi from 'element-plus/es/locale/lang/vi';
import en from 'element-plus/es/locale/lang/en';

// Theme state
const isDark = ref(false);

export const initTheme = () => {
    const savedTheme = localStorage.getItem('smart_hr_theme');
    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    } else {
        isDark.value = false;
        document.documentElement.classList.remove('dark');
    }
};

// Gửi ngầm cập nhật tùy chọn vào DB nếu người dùng đã đăng nhập
const syncPreferenceToServer = (data: { theme_preference?: string; locale_preference?: string }) => {
    try {
        axios.patch('/user/preferences', data).catch(() => {});
    } catch (e) {}
};

export const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('smart_hr_theme', 'dark');
        syncPreferenceToServer({ theme_preference: 'dark' });
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('smart_hr_theme', 'light');
        syncPreferenceToServer({ theme_preference: 'light' });
    }
};

// Language state
export type SupportedLocale = 'vi' | 'en';
const currentLang = ref<SupportedLocale>((localStorage.getItem('smart_hr_lang') as SupportedLocale) || 'vi');

export const currentElLocale = computed(() => (currentLang.value === 'en' ? en : vi));

export const setLanguage = (lang: SupportedLocale) => {
    currentLang.value = lang;
    localStorage.setItem('smart_hr_lang', lang);
    syncPreferenceToServer({ locale_preference: lang });
};

// Từ điển dịch thuật gọn gàng cho giao diện dùng chung
const translations: Record<string, Record<SupportedLocale, string>> = {
    // Navigation
    dashboard: { vi: 'Bàn làm việc', en: 'Dashboard' },
    core_hr: { vi: 'Hồ sơ & Tổ chức (CoreHR)', en: 'Core HR & Career' },
    employee_children: { vi: 'Con nhỏ & Phúc lợi', en: 'Children & Family Welfare' },
    policy: { vi: 'Quy chế & Thuế (Policy)', en: 'Policy & Tax Engine' },
    attendance: { vi: 'Chấm công (Attendance)', en: 'Time & Attendance' },
    rostering: { vi: 'Phân ca (Rostering)', en: 'Rostering & Shift' },
    leaves: { vi: 'Nghỉ phép & OT', en: 'Leave & Overtime' },
    workflow: { vi: 'Phê duyệt (Workflow)', en: 'Approval Workflow' },
    payroll: { vi: 'Bảng lương (Payroll)', en: 'Payroll Engine' },
    compliance: { vi: 'Tuân thủ (Compliance)', en: 'Compliance & Audit' },
    access_control: { vi: 'Phân quyền & Tài nguyên', en: 'Access Control & Matrix' },
    notifications: { vi: 'Thông báo', en: 'Notifications' },
    licensing: { vi: 'Bản quyền & Máy chấm công', en: 'Licensing & Edge' },

    // Header & Actions
    headquarters: { vi: 'Hội sở chính (HN)', en: 'Hanoi HQ Branch' },
    compliance_badge: { vi: 'Tuân thủ Bộ luật Lao động & Thuế 2026', en: 'Labor Code 2019 & Tax 2026 Compliant' },
    light_mode: { vi: 'Chuyển giao diện Sáng', en: 'Switch to Light Mode' },
    dark_mode: { vi: 'Chuyển giao diện Tối', en: 'Switch to Dark Mode' },
    system_notifications: { vi: 'Thông báo hệ thống', en: 'System Notifications' },
    mark_all_read: { vi: 'Đánh dấu đã đọc', en: 'Mark all as read' },
    no_new_notifications: { vi: 'Hiện không có thông báo mới', en: 'No new notifications' },

    // License footer
    enterprise_licensed: { vi: 'Bản quyền Doanh nghiệp', en: 'Enterprise Licensed' },
    data_anchored: { vi: 'Bảo mật & Toàn vẹn CSDL', en: 'Cryptographically Secured' },
};

export const t = (key: string): string => {
    return translations[key]?.[currentLang.value] || key;
};

export function useThemeAndLocale() {
    return {
        isDark,
        initTheme,
        toggleTheme,
        currentLang,
        currentElLocale,
        setLanguage,
        t,
    };
}
