export function toggleItem<T>(items: T[], item: T): T[] {
    return items.includes(item) ? items.filter((value) => value !== item) : [...items, item];
}

export function toggleGroupItems(selected: string[], groupItems: string[]): string[] {
    const allSelected = groupItems.every((item) => selected.includes(item));

    if (allSelected) {
        return selected.filter((item) => !groupItems.includes(item));
    }

    return Array.from(new Set([...selected, ...groupItems]));
}
