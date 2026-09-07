<?php

declare(strict_types=1);

/**
 * Optima series catalog (SerieEnum).
 * Values preserved as-is; columns renamed to English.
 *
 * @return list<array{id: int, key: string, color: string, is_selectable: bool, credit_note_series_id: int|null}>
 */
return [
    ['id' => 1, 'key' => 'A', 'color' => '#ffffff', 'is_selectable' => true, 'credit_note_series_id' => 5],
    ['id' => 2, 'key' => 'B', 'color' => '#ffffff', 'is_selectable' => true, 'credit_note_series_id' => null],
    ['id' => 3, 'key' => 'C', 'color' => '#ffffff', 'is_selectable' => true, 'credit_note_series_id' => 6],
    ['id' => 4, 'key' => 'D', 'color' => '#ffffff', 'is_selectable' => true, 'credit_note_series_id' => 7],
    ['id' => 5, 'key' => 'RA', 'color' => '#ffffff', 'is_selectable' => true, 'credit_note_series_id' => 5],
    ['id' => 6, 'key' => 'RC', 'color' => '#ffffff', 'is_selectable' => true, 'credit_note_series_id' => 6],
    ['id' => 7, 'key' => 'RD', 'color' => '#ffffff', 'is_selectable' => true, 'credit_note_series_id' => 7],
];
