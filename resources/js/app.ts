import './bootstrap';
import '../css/app.css';
import 'element-plus/dist/index.css';
import 'element-plus/theme-chalk/dark/css-vars.css';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ElementPlus from 'element-plus';
import vi from 'element-plus/es/locale/lang/vi';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import { initTheme } from './Composables/useThemeAndLocale';

const appName = import.meta.env.VITE_APP_NAME || 'Smart-HR';

// Khởi tạo theme sáng/tối từ localStorage ngay lập tức
initTheme();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Đăng ký toàn bộ biểu tượng Element Plus Icons
        for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
            app.component(key, component);
        }

        app.use(plugin)
            .use(ElementPlus, {
                locale: vi,
            })
            .mount(el);
    },
    progress: {
        color: '#4f46e5',
        showSpinner: true,
    },
});
