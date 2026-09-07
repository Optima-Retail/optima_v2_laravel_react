import { create } from 'zustand';

export type ToastTone = 'success' | 'error' | 'info';

export type ToastItem = {
    id: string;
    message: string;
    tone: ToastTone;
};

type ToastStore = {
    toasts: ToastItem[];
    push: (message: string, tone?: ToastTone) => void;
    dismiss: (id: string) => void;
    clear: () => void;
};

export const useToastStore = create<ToastStore>((set) => ({
    toasts: [],
    push: (message, tone = 'info') =>
        set((state) => ({
            toasts: [
                ...state.toasts,
                {
                    id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
                    message,
                    tone,
                },
            ],
        })),
    dismiss: (id) => set((state) => ({ toasts: state.toasts.filter((toast) => toast.id !== id) })),
    clear: () => set({ toasts: [] }),
}));
