import { create } from 'zustand';
import { persist } from 'zustand/middleware';

type UiStore = {
    sidebarOpen: boolean;
    toggleSidebar: () => void;
    setSidebarOpen: (open: boolean) => void;
};

export const useUiStore = create<UiStore>()(
    persist(
        (set) => ({
            sidebarOpen: true,
            toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
            setSidebarOpen: (open) => set({ sidebarOpen: open }),
        }),
        {
            name: 'optima-ui',
            partialize: (state) => ({ sidebarOpen: state.sidebarOpen }),
        },
    ),
);
