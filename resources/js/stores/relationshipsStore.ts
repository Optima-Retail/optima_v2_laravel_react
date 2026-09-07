import { create } from 'zustand';

export type RelationshipsFilters = {
    search: string;
    kind: string;
    sort: string;
    direction: string;
    per_page: string;
};

type RelationshipsStore = {
    filters: RelationshipsFilters;
    setFilter: (name: keyof RelationshipsFilters, value: string) => void;
    setFilters: (filters: Partial<RelationshipsFilters>) => void;
    resetFilters: () => void;
    syncFilters: (filters: Partial<RelationshipsFilters>) => void;
};

const emptyFilters: RelationshipsFilters = {
    search: '',
    kind: '',
    sort: 'id',
    direction: 'desc',
    per_page: '12',
};

export const useRelationshipsStore = create<RelationshipsStore>((set) => ({
    filters: emptyFilters,
    setFilter: (name, value) => set((state) => ({ filters: { ...state.filters, [name]: value } })),
    setFilters: (filters) => set((state) => ({ filters: { ...state.filters, ...filters } })),
    resetFilters: () => set({ filters: emptyFilters }),
    syncFilters: (filters) => set({ filters: { ...emptyFilters, ...filters } }),
}));
