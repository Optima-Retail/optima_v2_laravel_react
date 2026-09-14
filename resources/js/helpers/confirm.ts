import type { ConfirmOptions, ConfirmResult } from '@/stores/confirmStore';
import { useConfirmStore } from '@/stores/confirmStore';

/**
 * Opens the shared confirmation modal and resolves when the user chooses.
 */
export async function confirmAction(options: string | ConfirmOptions): Promise<boolean> {
    const result = await useConfirmStore.getState().ask(options);

    return result.confirmed;
}

/**
 * Same modal as `confirmAction`, including an optional justification field.
 */
export function confirmWithComment(options: string | ConfirmOptions): Promise<ConfirmResult> {
    return useConfirmStore.getState().ask(options);
}
