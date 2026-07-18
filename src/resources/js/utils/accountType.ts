export const accountTypeLabels: Record<string, string> = {
    cash: '現金',
    bank: '銀行口座',
    credit_card: 'クレジットカード',
    e_money: '電子マネー',
    securities: '証券',
    point: 'ポイント',
    other: 'その他',
};

export const accountTypeBalanceLabels: Record<string, string> = {
    credit_card: '未払残高相当',
    e_money: '利用残高 / 請求待ち残高相当',
    other: '利用残高 / 請求待ち残高相当',
};

export const accountTypeDescriptions: Record<string, string> = {
    credit_card:
        'カード利用は expense、カード引落は transfer として扱うため、残高は未払残高に近い意味になります。',
    e_money:
        'コード決済利用は expense、請求付替やチャージは transfer として扱うため、残高は利用残高や請求待ち残高として確認します。',
    other:
        'コード決済や請求管理用の口座として使う場合、残高は利用残高や請求待ち残高として確認します。',
    securities:
        '証券口座への積立や資金移動は transfer として扱い、収支には重複計上しません。',
};

export function getAccountTypeLabel(type: string): string {
    return accountTypeLabels[type] ?? type;
}

export function getAccountTypeDescription(type: string): string | null {
    return accountTypeDescriptions[type] ?? null;
}

export function getAccountBalanceLabel(type: string): string {
    return accountTypeBalanceLabels[type] ?? '現在残高相当';
}
