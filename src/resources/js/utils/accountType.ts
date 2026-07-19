import type { TFunction } from 'i18next';

const accountTypes = new Set([
    'cash',
    'bank',
    'credit_card',
    'e_money',
    'securities',
    'point',
    'other',
]);

const accountTypesWithDescription = new Set([
    'credit_card',
    'e_money',
    'other',
    'securities',
]);

const accountTypesWithSpecialBalanceLabel = new Set([
    'credit_card',
    'e_money',
    'other',
]);

export function getAccountTypeLabel(
    type: string,
    translate: TFunction<'accounts'>,
): string {
    return accountTypes.has(type) ? translate(`types.${type}`) : type;
}

export function getAccountTypeDescription(
    type: string,
    translate: TFunction<'accounts'>,
): string | null {
    return accountTypesWithDescription.has(type)
        ? translate(`typeDescriptions.${type}`)
        : null;
}

export function getAccountBalanceLabel(
    type: string,
    translate: TFunction<'accounts'>,
): string {
    return accountTypesWithSpecialBalanceLabel.has(type)
        ? translate(`balanceLabels.${type}`)
        : translate('balanceLabels.default');
}
