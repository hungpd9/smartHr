<script setup lang="ts">
import { computed } from 'vue';
import { useThemeAndLocale } from '@/Composables/useThemeAndLocale';

interface Props {
    isCollapsed?: boolean;
    variant?: 'full' | 'compact' | 'icon-only';
    size?: 'sm' | 'md' | 'lg' | 'xl';
    showSlogan?: boolean;
    customSlogan?: string;
    showBadge?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isCollapsed: false,
    variant: 'full',
    size: 'md',
    showSlogan: false,
    customSlogan: undefined,
    showBadge: true,
});

const { t } = useThemeAndLocale();

const iconSizes = computed(() => {
    switch (props.size) {
        case 'sm':
            return {
                box: 'w-8 h-8',
                svgSize: 32,
                titleSize: 'text-base',
                subSize: 'text-[10px]',
                badgeSize: 'text-[9px] px-1.5 py-0.5',
            };
        case 'lg':
            return {
                box: 'w-14 h-14',
                svgSize: 56,
                titleSize: 'text-2xl',
                subSize: 'text-xs',
                badgeSize: 'text-[11px] px-2 py-0.5',
            };
        case 'xl':
            return {
                box: 'w-20 h-20',
                svgSize: 80,
                titleSize: 'text-4xl',
                subSize: 'text-sm',
                badgeSize: 'text-xs px-2.5 py-1',
            };
        case 'md':
        default:
            return {
                box: 'w-10 h-10',
                svgSize: 40,
                titleSize: 'text-lg',
                subSize: 'text-[11px]',
                badgeSize: 'text-[10px] px-1.5 py-0.5',
            };
    }
});

const activeSlogan = computed(() => {
    if (props.customSlogan) return props.customSlogan;
    return t('brand.slogan');
});
</script>

<template>
    <div class="flex items-center space-x-3 select-none group">
        <!-- Brand Icon Mark (Pure Vector SVG with Dynamic Glow) -->
        <div 
            :class="[
                iconSizes.box,
                'relative flex items-center justify-center shrink-0 rounded-2xl transition-transform duration-300 group-hover:scale-105'
            ]"
        >
            <!-- Ambient Glow for Dark/Light Mode -->
            <div 
                class="absolute inset-0 rounded-2xl bg-gradient-to-tr from-indigo-600/30 via-violet-600/30 to-cyan-400/20 blur-md group-hover:blur-lg transition-all opacity-70 dark:opacity-90"
            ></div>

            <!-- Crisp Vector Emblem -->
            <svg
                viewBox="0 0 100 100"
                class="w-full h-full relative z-10 drop-shadow-sm transition-transform duration-300"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <linearGradient id="vueGradMain" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#4F46E5" />
                        <stop offset="50%" stop-color="#7C3AED" />
                        <stop offset="100%" stop-color="#A855F7" />
                    </linearGradient>
                    <linearGradient id="vueGradCyan" x1="0%" y1="100%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#06B6D4" />
                        <stop offset="50%" stop-color="#0284C7" />
                        <stop offset="100%" stop-color="#4F46E5" />
                    </linearGradient>
                    <linearGradient id="vueGradHighlight" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#38BDF8" stop-opacity="0.9" />
                        <stop offset="100%" stop-color="#818CF8" stop-opacity="0.2" />
                    </linearGradient>
                    <filter id="vueEmblemShadow" x="-10%" y="-10%" width="120%" height="130%">
                        <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#4338CA" flood-opacity="0.25" />
                    </filter>
                </defs>

                <g filter="url(#vueEmblemShadow)">
                    <!-- Upright Pillar of 'H' -->
                    <path
                        d="M68 34 C68 31.8 69.8 30 72 30 C74.2 30 76 31.8 76 34 L76 72 C76 74.2 74.2 76 72 76 C69.8 76 68 74.2 68 72 Z"
                        fill="url(#vueGradMain)"
                        opacity="0.88"
                    />
                    <!-- Crossbar H -->
                    <path d="M52 52 H72" stroke="url(#vueGradMain)" stroke-width="6" stroke-linecap="round" />

                    <!-- Human Figure Head -->
                    <circle cx="50" cy="22" r="7.5" fill="url(#vueGradCyan)" />
                    <circle cx="48" cy="20" r="2.5" fill="#FFFFFF" opacity="0.75" />

                    <!-- Dynamic 'S' Ribbon Body -->
                    <path
                        d="M33 34 C35 28 44 27 50 31 C57 34 58 40 53 47 C47 54 30 55 29 65 C28 73 35 79 45 79 C54 79 60 74 63 69"
                        stroke="url(#vueGradCyan)"
                        stroke-width="7"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />

                    <!-- Rising Arm/Arrow (AI Precision & Workforce Growth) -->
                    <path
                        d="M51 36 C55 33 63 26 69 22 C70.5 21 72 22.5 71.2 24 C67 31 61 40 57 45"
                        stroke="url(#vueGradHighlight)"
                        stroke-width="4.5"
                        stroke-linecap="round"
                    />

                    <!-- Dynamic Sync Sparkle -->
                    <circle cx="71" cy="21" r="2.2" fill="#38BDF8" />
                </g>
            </svg>
        </div>

        <!-- Typography & Brand Titles (Hidden if collapsed or icon-only) -->
        <div 
            v-if="!isCollapsed && variant !== 'icon-only'" 
            class="flex flex-col min-w-0 transition-opacity duration-200"
        >
            <div class="flex items-center space-x-1.5">
                <span 
                    :class="[
                        iconSizes.titleSize,
                        'font-extrabold text-slate-800 dark:text-white leading-none tracking-tight'
                    ]"
                >
                    Smart<span class="text-indigo-600 dark:text-indigo-400">-</span>HR
                </span>

                <span 
                    v-if="showBadge"
                    :class="[
                        iconSizes.badgeSize,
                        'rounded-md font-bold tracking-wider uppercase bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800/80 leading-none shrink-0'
                    ]"
                >
                    PRO
                </span>
            </div>

            <!-- Subtitle or Slogan -->
            <p 
                v-if="showSlogan" 
                class="mt-1 text-slate-500 dark:text-slate-400 font-medium leading-tight"
                :class="iconSizes.subSize"
            >
                {{ activeSlogan }}
            </p>
            <span 
                v-else-if="variant !== 'compact'" 
                :class="[
                    iconSizes.subSize,
                    'text-slate-400 dark:text-slate-500 font-medium tracking-wide uppercase mt-0.5 truncate'
                ]"
            >
                {{ t('brand.tagline') }}
            </span>
        </div>
    </div>
</template>
