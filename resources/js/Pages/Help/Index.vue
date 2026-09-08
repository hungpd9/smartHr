<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';
import { marked } from 'marked';
import {
    QuestionFilled,
    Search,
    Document,
    User,
    Reading,
    Clock,
    ArrowRight,
    ArrowLeft,
    Collection,
} from '@element-plus/icons-vue';

interface GuideItem {
    slug: string;
    title: string;
    role: string;
    file_path: string;
}

interface Category {
    role: string;
    label: string;
    guides: GuideItem[];
}

interface ActiveGuide {
    role: string;
    role_label: string;
    slug: string;
    title: string;
    content: string;
    updated_at: string;
}

const props = defineProps<{
    catalog: Category[];
    activeGuide: ActiveGuide | null;
    currentRole: string;
}>();
const { t } = useThemeAndLocale();

const searchQuery = ref('');
const currentActiveGuide = ref<ActiveGuide | null>(props.activeGuide);

// Tự động đồng bộ bài viết khi người dùng bấm Back / Forward trình duyệt
watch(() => props.activeGuide, (newVal) => {
    currentActiveGuide.value = newVal;
});

// Tìm kiếm danh mục bài viết theo từ khóa
const filteredCatalog = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.catalog;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.catalog
        .map((cat) => ({
            ...cat,
            guides: cat.guides.filter((g) => g.title.toLowerCase().includes(q)),
        }))
        .filter((cat) => cat.guides.length > 0);
});

// Chuyển bài viết khi click
const selectGuide = (guide: GuideItem) => {
    router.get(
        '/help',
        { role: guide.role, slug: guide.slug },
        {
            preserveState: true,
            preserveScroll: false,
            onSuccess: (page: any) => {
                currentActiveGuide.value = page.props.activeGuide;
            },
        }
    );
};

// Định dạng lại các alert Markdown GitHub thành HTML giao diện phong phú
const formatMarkdownAlerts = (html: string): string => {
    return html
        .replace(
            /<blockquote>\s*<p>\[!NOTE\]/g,
            '<blockquote class="markdown-alert alert-note"><p class="alert-title font-bold flex items-center gap-1.5 text-blue-600 dark:text-blue-400"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> ' + t('help.alert_note') + '</p>'
        )
        .replace(
            /<blockquote>\s*<p>\[!IMPORTANT\]/g,
            '<blockquote class="markdown-alert alert-important"><p class="alert-title font-bold flex items-center gap-1.5 text-indigo-600 dark:text-indigo-400"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg> ' + t('help.alert_important') + '</p>'
        )
        .replace(
            /<blockquote>\s*<p>\[!TIP\]/g,
            '<blockquote class="markdown-alert alert-tip"><p class="alert-title font-bold flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg> ' + t('help.alert_tip') + '</p>'
        )
        .replace(
            /<blockquote>\s*<p>\[!WARNING\]/g,
            '<blockquote class="markdown-alert alert-warning"><p class="alert-title font-bold flex items-center gap-1.5 text-amber-600 dark:text-amber-400"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> ' + t('help.alert_warning') + '</p>'
        )
        .replace(
            /<blockquote>\s*<p>\[!CAUTION\]/g,
            '<blockquote class="markdown-alert alert-caution"><p class="alert-title font-bold flex items-center gap-1.5 text-rose-600 dark:text-rose-400"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg> ' + t('help.alert_caution') + '</p>'
        );
};

// Render HTML từ nội dung Markdown
const renderedMarkdown = computed(() => {
    if (!props.activeGuide?.content) return '';
    try {
        const rawHtml = marked.parse(props.activeGuide.content) as string;
        return formatMarkdownAlerts(rawHtml);
    } catch {
        return '<p class="text-rose-500">' + t('help.markdown_compile_error') + '</p>';
    }
});

// Danh sách phẳng tất cả bài viết để hỗ trợ nút Trang trước / Trang sau
const allGuidesFlat = computed(() => {
    const list: GuideItem[] = [];
    props.catalog.forEach((cat) => list.push(...cat.guides));
    return list;
});

const currentGuideIndex = computed(() => {
    if (!props.activeGuide) return -1;
    return allGuidesFlat.value.findIndex(
        (g) => g.role === props.activeGuide?.role && g.slug === props.activeGuide?.slug
    );
});

const prevGuide = computed(() => {
    const idx = currentGuideIndex.value;
    return idx > 0 ? allGuidesFlat.value[idx - 1] : null;
});

const nextGuide = computed(() => {
    const idx = currentGuideIndex.value;
    return idx >= 0 && idx < allGuidesFlat.value.length - 1 ? allGuidesFlat.value[idx + 1] : null;
});
</script>

<template>
    <AppLayout>
        <Head :title="`${activeGuide?.title || $t('help.title_fallback')} - Smart-HR`" />

        <div class="space-y-6 pb-12">
            <!-- Header Banner -->
            <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <span class="p-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400">
                            <el-icon :size="20"><Reading /></el-icon>
                        </span>
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-800 dark:text-white tracking-tight">
                            {{ $t('help.header_title') }}
                        </h1>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('help.header_subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <el-tag type="primary" effect="dark" size="small" class="font-mono">
                        {{ $t('help.role_label') }} {{ currentRole }}
                    </el-tag>
                </div>
            </div>

            <!-- Content Layout: 2 Columns (Sidebar + Content) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <!-- Sidebar: Danh mục tài liệu theo vai trò -->
                <div class="lg:col-span-4 space-y-4">
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-4 shadow-xs">
                        <div class="mb-4">
                            <el-input
                                v-model="searchQuery"
                                :placeholder="$t('help.search_placeholder')"
                                :prefix-icon="Search"
                                clearable
                                class="w-full"
                            />
                        </div>

                        <div class="space-y-6 max-h-[calc(100vh-280px)] overflow-y-auto pr-1">
                            <div v-for="category in filteredCatalog" :key="category.role" class="space-y-2">
                                <div class="flex items-center justify-between px-2 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                    <span>{{ category.label }}</span>
                                    <span class="text-[10px] bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded font-mono">
                                        {{ category.guides.length }}
                                    </span>
                                </div>

                                <div class="space-y-1">
                                    <button
                                        v-for="guide in category.guides"
                                        :key="guide.slug"
                                        @click="selectGuide(guide)"
                                        class="w-full text-left p-2.5 rounded-xl text-xs font-medium transition-all flex items-start gap-2.5"
                                        :class="activeGuide?.slug === guide.slug && activeGuide?.role === guide.role
                                            ? 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 font-bold border border-indigo-200/60 dark:border-indigo-800/60 shadow-xs'
                                            : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/60 hover:text-slate-900 dark:hover:text-slate-200'"
                                    >
                                        <el-icon class="mt-0.5 shrink-0 text-slate-400"><Document /></el-icon>
                                        <span class="flex-1 leading-relaxed">{{ guide.title }}</span>
                                    </button>
                                </div>
                            </div>

                            <div v-if="filteredCatalog.length === 0" class="py-8 text-center text-slate-400 text-xs">
                                {{ $t('help.no_results') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content: Trình đọc tài liệu Markdown -->
                <div class="lg:col-span-8">
                    <div v-if="activeGuide" class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xs space-y-6">
                        <!-- Top Meta Header -->
                        <div class="border-b border-slate-100 dark:border-slate-800 pb-5 space-y-2">
                            <div class="flex items-center gap-2 text-xs text-slate-400">
                                <span>{{ activeGuide.role_label }}</span>
                                <span>•</span>
                                <span class="flex items-center gap-1">
                                    <el-icon><Clock /></el-icon>
                                    {{ $t('help.updated_label') }} {{ activeGuide.updated_at }}
                                </span>
                            </div>
                            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-800 dark:text-white tracking-tight">
                                {{ activeGuide.title }}
                            </h2>
                        </div>

                        <!-- Markdown Content View -->
                        <div
                            class="prose prose-slate dark:prose-invert max-w-none text-xs sm:text-sm leading-relaxed"
                            v-html="renderedMarkdown"
                        ></div>

                        <!-- Pagination Footer: Trang trước / Trang sau -->
                        <div class="pt-6 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-4">
                            <div>
                                <button
                                    v-if="prevGuide"
                                    @click="selectGuide(prevGuide)"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 transition"
                                >
                                    <el-icon><ArrowLeft /></el-icon>
                                    <span class="truncate max-w-[200px]">{{ prevGuide.title }}</span>
                                </button>
                            </div>
                            <div>
                                <button
                                    v-if="nextGuide"
                                    @click="selectGuide(nextGuide)"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold bg-indigo-50 dark:bg-indigo-950/60 hover:bg-indigo-100 dark:hover:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 transition"
                                >
                                    <span class="truncate max-w-[200px]">{{ nextGuide.title }}</span>
                                    <el-icon><ArrowRight /></el-icon>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 rounded-2xl p-16 text-center text-slate-400 space-y-3">
                        <el-icon class="text-5xl text-slate-300 dark:text-slate-600"><Reading /></el-icon>
                        <p class="text-sm font-medium">{{ $t('help.empty_state_title') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Custom typography & styling cho Markdown bài viết */
.prose h1 {
    display: none; /* Ẩn H1 vì đã render ở top header */
}
.prose h2 {
    font-size: 1.15rem;
    font-weight: 700;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
    color: var(--el-text-color-primary);
    border-bottom: 1px solid var(--el-border-color-lighter);
    padding-bottom: 0.35rem;
}
.prose h3 {
    font-size: 0.95rem;
    font-weight: 600;
    margin-top: 1.25rem;
    margin-bottom: 0.5rem;
    color: var(--el-text-color-primary);
}
.prose p {
    margin-bottom: 0.85rem;
    line-height: 1.65;
}
.prose ul, .prose ol {
    padding-left: 1.4rem;
    margin-bottom: 0.85rem;
}
.prose li {
    margin-bottom: 0.35rem;
}
.prose table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    margin-bottom: 1.25rem;
    font-size: 0.8rem;
}
.prose th, .prose td {
    padding: 0.6rem 0.8rem;
    border: 1px solid var(--el-border-color-light);
}
.prose th {
    background-color: var(--el-fill-color-light);
    font-weight: 600;
}
.prose code {
    font-family: monospace;
    font-size: 0.85em;
    background-color: var(--el-fill-color-light);
    padding: 0.15rem 0.35rem;
    border-radius: 0.35rem;
}

/* GitHub Alert Callouts */
.markdown-alert {
    border-left-width: 4px;
    padding: 0.85rem 1rem;
    border-radius: 0.75rem;
    margin: 1.25rem 0;
    font-size: 0.825rem;
}
.alert-note {
    background-color: rgba(59, 130, 246, 0.08);
    border-color: #3b82f6;
}
.alert-important {
    background-color: rgba(99, 102, 241, 0.08);
    border-color: #6366f1;
}
.alert-tip {
    background-color: rgba(16, 185, 129, 0.08);
    border-color: #10b981;
}
.alert-warning {
    background-color: rgba(245, 158, 11, 0.08);
    border-color: #f59e0b;
}
.alert-caution {
    background-color: rgba(244, 63, 94, 0.08);
    border-color: #f43f5e;
}
</style>
