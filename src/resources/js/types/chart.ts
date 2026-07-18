export type TrendPoint = {
    date: string;
    value: string;
};

export type TrendSeries = {
    key: string;
    label: string;
    currency: string;
    points: TrendPoint[];
    color?: string;
    href?: string;
};
