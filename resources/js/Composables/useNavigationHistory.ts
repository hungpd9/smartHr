import { ref, watch, onMounted, onUnmounted, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Composable quản lý điều hướng Back / Forward trình duyệt
 */
export function useBrowserNavigation() {
    const canGoBack = ref(window.history.length > 1);
    const canGoForward = ref(true);

    const updateHistoryStatus = () => {
        canGoBack.value = window.history.length > 1;
    };

    const goBack = () => {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            router.visit('/dashboard');
        }
    };

    const goForward = () => {
        window.history.forward();
    };

    onMounted(() => {
        window.addEventListener('popstate', updateHistoryStatus);
    });

    onUnmounted(() => {
        window.removeEventListener('popstate', updateHistoryStatus);
    });

    return {
        canGoBack,
        canGoForward,
        goBack,
        goForward,
    };
}

/**
 * Composable đồng bộ Tab với URL Query Parameter và Lịch sử Duyệt Web (Back/Forward)
 *
 * @param defaultTab Tab mặc định nếu URL không chứa query param
 * @param paramKey Tên tham số query (mặc định là 'tab')
 * @param pushToHistory true để tạo lịch sử duyệt (Back/Forward chuyển tab), false nếu chỉ thay thế URL
 */
export function useSyncedTab(
    defaultTab: string,
    paramKey: string = 'tab',
    pushToHistory: boolean = true
): Ref<string> {
    const getTabFromUrl = (): string => {
        if (typeof window === 'undefined') return defaultTab;
        const params = new URLSearchParams(window.location.search);
        return params.get(paramKey) || defaultTab;
    };

    const activeTab = ref<string>(getTabFromUrl());

    // Cập nhật URL khi tab thay đổi bởi người dùng thao tác
    let isPopstateNavigation = false;

    watch(activeTab, (newTab) => {
        if (isPopstateNavigation) {
            isPopstateNavigation = false;
            return;
        }

        const url = new URL(window.location.href);
        if (newTab === defaultTab) {
            url.searchParams.delete(paramKey);
        } else {
            url.searchParams.set(paramKey, newTab);
        }

        const newRelativePathQuery = url.pathname + url.search;
        const currentRelativePathQuery = window.location.pathname + window.location.search;

        if (newRelativePathQuery !== currentRelativePathQuery) {
            if (pushToHistory) {
                // Tạo entry trong history stack để hỗ trợ nút Back / Forward
                window.history.pushState({ [paramKey]: newTab }, '', newRelativePathQuery);
            } else {
                window.history.replaceState({ [paramKey]: newTab }, '', newRelativePathQuery);
            }
        }
    });

    // Lắng nghe sự kiện Back / Forward từ trình duyệt
    const handlePopstate = () => {
        const tabFromUrl = getTabFromUrl();
        if (tabFromUrl !== activeTab.value) {
            isPopstateNavigation = true;
            activeTab.value = tabFromUrl;
        }
    };

    onMounted(() => {
        window.addEventListener('popstate', handlePopstate);
    });

    onUnmounted(() => {
        window.removeEventListener('popstate', handlePopstate);
    });

    return activeTab;
}

/**
 * Composable hỗ trợ đóng Modal/Drawer khi người dùng bấm nút Back của trình duyệt
 * Tránh việc bấm Back bị nhảy trang ra ngoài khi đang xem popup
 */
export function useModalHistory(isOpen: Ref<boolean>, modalName: string) {
    let isHandlingPopstate = false;

    watch(isOpen, (newVal, oldVal) => {
        if (isHandlingPopstate) {
            isHandlingPopstate = false;
            return;
        }

        if (newVal && !oldVal) {
            // Khi mở modal: push 1 state vào history
            const url = new URL(window.location.href);
            url.hash = `modal-${modalName}`;
            window.history.pushState({ modal: modalName }, '', url.href);
        } else if (!newVal && oldVal) {
            // Khi người dùng đóng modal bằng nút X hoặc Cancel:
            // Nếu hash hiện tại vẫn là modal này thì back lại 1 bước trong history
            if (window.location.hash === `#modal-${modalName}`) {
                window.history.back();
            }
        }
    });

    const handlePopstate = (event: PopStateEvent) => {
        // Khi người dùng bấm Back: nếu modal đang mở thì đóng modal
        if (isOpen.value && window.location.hash !== `#modal-${modalName}`) {
            isHandlingPopstate = true;
            isOpen.value = false;
        }
    };

    onMounted(() => {
        window.addEventListener('popstate', handlePopstate);
    });

    onUnmounted(() => {
        window.removeEventListener('popstate', handlePopstate);
    });
}
