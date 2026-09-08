import { ref, computed } from 'vue';
import axios from 'axios';
import viEl from 'element-plus/es/locale/lang/vi';
import enEl from 'element-plus/es/locale/lang/en';
import { vi as viDict } from '../locales/vi';
import { en as enDict } from '../locales/en';
import type { SupportedLocale } from '../locales';

export type { SupportedLocale };

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
export const currentLang = ref<SupportedLocale>((localStorage.getItem('smart_hr_lang') as SupportedLocale) || 'vi');

export const currentElLocale = computed(() => (currentLang.value === 'en' ? enEl : viEl));

export const setLanguage = (lang: SupportedLocale) => {
    currentLang.value = lang;
    localStorage.setItem('smart_hr_lang', lang);
    syncPreferenceToServer({ locale_preference: lang });
};

// Tra cứu giá trị theo key phẳng, dot-notation, hoặc tự động quét các nhóm phổ biến
const getNestedValue = (obj: any, path: string): string | undefined => {
    if (!obj || typeof obj !== 'object') return undefined;

    // 1. Kiểm tra trực tiếp nếu giá trị là string
    if (path in obj && typeof obj[path] === 'string') {
        return obj[path];
    }

    // 2. Tra cứu phân cấp theo dot-notation (ví dụ: 'nav.dashboard', 'common.save')
    if (path.includes('.')) {
        const keys = path.split('.');
        let current = obj;
        for (const k of keys) {
            if (current && typeof current === 'object' && k in current) {
                current = current[k];
            } else {
                current = undefined;
                break;
            }
        }
        if (typeof current === 'string') return current;
    }

    // 3. Hỗ trợ tra cứu nhanh khóa đơn trong các nhóm phổ biến (Auto-lookup)
    const scanSections = ['nav', 'header', 'footer', 'common', 'status', 'auth', 'dashboard', 'attendance', 'payroll', 'licensing', 'ot_caps', 'leave', 'workflow', 'core_hr', 'rostering', 'policy', 'compliance', 'employee_children', 'access_control', 'notifications', 'reports', 'help'];
    for (const sec of scanSections) {
        if (obj[sec] && typeof obj[sec] === 'object' && typeof obj[sec][path] === 'string') {
            return obj[sec][path];
        }
    }

    return undefined;
};

// Hàm dịch thuật chuẩn mực, hỗ trợ fallback và tham số nội suy
export const t = (key: string, params?: Record<string, string | number>, fallback?: string): string => {
    const lang = currentLang.value;
    const currentDict = lang === 'en' ? enDict : viDict;

    // Tra cứu trong ngôn ngữ hiện tại
    let text = getNestedValue(currentDict, key);

    // Tự động fallback sang tiếng Việt nếu không có bản dịch tiếng Anh
    if (!text && lang !== 'vi') {
        text = getNestedValue(viDict, key);
    }

    // Nếu vẫn không có, trả về fallback hoặc chính key
    if (!text) {
        text = fallback !== undefined ? fallback : key;
    }

    // Thay thế tham số {paramName} nếu có
    if (params && typeof text === 'string') {
        Object.entries(params).forEach(([paramKey, paramVal]) => {
            text = (text as string).replace(new RegExp(`\\{${paramKey}\\}`, 'g'), String(paramVal));
        });
    }

    return text;
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
