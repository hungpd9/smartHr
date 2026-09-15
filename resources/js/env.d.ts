/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<{}, {}, any>;
    export default component;
}

declare module '@vue/runtime-core' {
    export interface ComponentCustomProperties {
        $t: (key: string, params?: Record<string, string | number>, fallback?: string) => string;
    }
}

export {};

