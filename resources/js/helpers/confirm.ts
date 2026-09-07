import type { ConfirmOptions } from '@/stores/confirmStore';
import { useConfirmStore } from '@/stores/confirmStore';

/**
 * Opens the shared confirmation modal and resolves when the user chooses.
 */
export function confirmAction(options: string | ConfirmOptions): Promise<boolean> {
    return useConfirmStore.getState().ask(options);
}
