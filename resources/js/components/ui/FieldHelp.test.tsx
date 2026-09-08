import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { I18nextProvider } from 'react-i18next';
import i18n from '@/i18n';
import { FieldHelp } from '@/components/ui/FieldHelp';
import { useFieldHelpStore } from '@/stores/fieldHelpStore';
import { fieldHelpService } from '@/services/fieldHelp';

vi.mock('@/services/fieldHelp', () => ({
    fieldHelpService: {
        resolvePath: '/field-help',
        resolve: vi.fn(),
    },
}));

describe('FieldHelp', () => {
    beforeEach(() => {
        useFieldHelpStore.getState().reset();
        useFieldHelpStore.getState().setLocale('en');
        vi.mocked(fieldHelpService.resolve).mockReset();
    });

    afterEach(() => {
        cleanup();
    });

    it('renders nothing when help is missing', async () => {
        vi.mocked(fieldHelpService.resolve).mockResolvedValue({});

        await act(async () => {
            render(
                <I18nextProvider i18n={i18n}>
                    <FieldHelp field="companies.unknown" />
                </I18nextProvider>,
            );
            await useFieldHelpStore.getState().ensureKeys(['companies.unknown']);
        });

        expect(screen.queryByRole('button')).toBeNull();
    });

    it('shows icon and tooltip content when help exists', async () => {
        vi.mocked(fieldHelpService.resolve).mockResolvedValue({
            'companies.tax_id': {
                title: 'Tax ID',
                description: 'Company tax identification number.',
            },
        });

        await act(async () => {
            useFieldHelpStore.setState({
                locale: 'en',
                entries: {
                    'companies.tax_id': {
                        title: 'Tax ID',
                        description: 'Company tax identification number.',
                    },
                },
                missing: {},
                pending: {},
                error: null,
            });

            render(
                <I18nextProvider i18n={i18n}>
                    <FieldHelp field="companies.tax_id" />
                </I18nextProvider>,
            );
        });

        const button = screen.getByRole('button', { name: /Help for Tax ID/i });
        expect(button).toBeTruthy();

        await userEvent.click(button);
        expect(screen.getByRole('tooltip')).toHaveTextContent('Company tax identification number.');
    });
});

describe('fieldHelpStore batching', () => {
    beforeEach(() => {
        useFieldHelpStore.getState().reset();
        useFieldHelpStore.getState().setLocale('es');
        vi.mocked(fieldHelpService.resolve).mockReset();
        vi.mocked(fieldHelpService.resolve).mockResolvedValue({
            'companies.tax_id': { title: 'NIF / CIF', description: '…' },
            'companies.email': { title: 'Email', description: '…' },
        });
    });

    it('batches multiple keys into one resolve call', async () => {
        await act(async () => {
            const first = useFieldHelpStore.getState().ensureKeys(['companies.tax_id']);
            const second = useFieldHelpStore.getState().ensureKeys(['companies.email']);
            await Promise.all([first, second]);
        });

        expect(fieldHelpService.resolve).toHaveBeenCalledTimes(1);
        const [keys, locale] = vi.mocked(fieldHelpService.resolve).mock.calls[0];
        expect(locale).toBe('es');
        expect(keys).toEqual(expect.arrayContaining(['companies.tax_id', 'companies.email']));
        expect(useFieldHelpStore.getState().entries['companies.tax_id']?.title).toBe('NIF / CIF');
    });

    it('reloads when locale changes', async () => {
        vi.mocked(fieldHelpService.resolve)
            .mockResolvedValueOnce({
                'companies.tax_id': { title: 'NIF / CIF', description: 'ES' },
            })
            .mockResolvedValueOnce({
                'companies.tax_id': { title: 'Tax ID', description: 'EN' },
            });

        await act(async () => {
            await useFieldHelpStore.getState().ensureKeys(['companies.tax_id']);
        });
        expect(useFieldHelpStore.getState().entries['companies.tax_id']?.title).toBe('NIF / CIF');

        await act(async () => {
            useFieldHelpStore.getState().setLocale('en');
            await useFieldHelpStore.getState().ensureKeys(['companies.tax_id']);
        });

        expect(fieldHelpService.resolve).toHaveBeenCalledTimes(2);
        expect(useFieldHelpStore.getState().entries['companies.tax_id']?.title).toBe('Tax ID');
    });
});
