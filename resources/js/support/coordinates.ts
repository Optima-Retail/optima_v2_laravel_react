/** Max digits after the decimal separator for latitude / longitude. */
export const COORDINATE_DECIMAL_PLACES = 16;

/**
 * Keep at most `maxDecimals` digits after `.` or `,` while typing.
 */
export function clampDecimalPlaces(value: string, maxDecimals = COORDINATE_DECIMAL_PLACES): string {
    const separatorIndex = Math.max(value.indexOf('.'), value.indexOf(','));

    if (separatorIndex === -1) {
        return value;
    }

    const whole = value.slice(0, separatorIndex + 1);
    const fraction = value.slice(separatorIndex + 1).replace(/[^\d]/g, '');

    return `${whole}${fraction.slice(0, maxDecimals)}`;
}
