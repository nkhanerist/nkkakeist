const currencyFractionDigits: Record<string, number> = {
    BHD: 3,
    IQD: 3,
    JOD: 3,
    KWD: 3,
    LYD: 3,
    OMR: 3,
    TND: 3,
    CLP: 0,
    DJF: 0,
    GNF: 0,
    ISK: 0,
    JPY: 0,
    KMF: 0,
    KRW: 0,
    PYG: 0,
    RWF: 0,
    UGX: 0,
    UYI: 0,
    VND: 0,
    VUV: 0,
    XAF: 0,
    XOF: 0,
    XPF: 0,
};

export function getCurrencyFractionDigits(currency: string): number {
    return currencyFractionDigits[currency.toUpperCase()] ?? 2;
}

export function formatMoney(
    amount: number | string,
    currency: string,
    locale = 'ja-JP',
): string {
    const fractionDigits = getCurrencyFractionDigits(currency);

    return new Intl.NumberFormat(locale, {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(Number(amount));
}
