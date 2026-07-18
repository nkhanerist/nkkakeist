import { TrendSeries } from '@/types/chart';

export type SecuritiesAccount = {
    id: number;
    name: string;
    currency: string;
    latest_valuation: string | null;
    latest_date: string | null;
    latest_source: string | null;
};

export type SecuritiesPositionGroup = {
    account_id: number;
    account_name: string;
    currency: string;
    series: TrendSeries[];
};

export type SecuritiesPeriodOption = {
    value: string;
    label: string;
};

export type SecuritiesAccountDetail = SecuritiesAccount & {
    snapshot_count: number;
    change_amount: string | null;
};

export type SecuritiesSnapshotRow = {
    id: number;
    date: string;
    valuation: string;
    change_amount: string | null;
    source_name: string | null;
    import_id: number | null;
    position_count: number;
};

export type SecuritiesPositionItem = {
    position_key: string;
    instrument_name: string;
    instrument_code: string | null;
    asset_class: string | null;
    valuation: string;
    unrealized_gain: string | null;
    quantity: string | null;
    unit_price: string | null;
    currency: string;
    history_count: number;
    change_amount: string | null;
    share_percent: string | null;
};

export type SecuritiesPositionHistory = {
    date: string;
    valuation: string;
    change_amount: string | null;
    quantity: string | null;
    unit_price: string | null;
    unrealized_gain: string | null;
    source_name: string | null;
};

export type SecuritiesPositionDetail = {
    position_key: string;
    instrument_name: string;
    instrument_code: string | null;
    asset_class: string | null;
    currency: string;
    latest: {
        date: string;
        quantity: string | null;
        average_acquisition_price: string | null;
        unit_price: string | null;
        acquisition_cost: string | null;
        valuation: string;
        unrealized_gain: string | null;
        source_name: string | null;
    };
    series: TrendSeries;
    history: SecuritiesPositionHistory[];
};
