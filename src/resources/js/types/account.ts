export type AccountTypeOption = {
    value: string;
    label: string;
};

export type AccountBalanceRole = 'asset' | 'liability' | 'clearing';
export type AccountBalanceMethod = 'ledger' | 'snapshot';

export type AccountListItem = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    balance_role: AccountBalanceRole;
    balance_role_label: string;
    balance_method: AccountBalanceMethod;
    balance_method_label: string;
    include_in_net_worth: boolean;
    currency: string;
    initial_balance: string;
    opening_balance_date: string | null;
    display_order: number;
    is_active: boolean;
    note: string | null;
    import_aliases?: string[] | null;
};

export type AccountFormValues = {
    name: string;
    type: string;
    balance_role: AccountBalanceRole;
    balance_method: AccountBalanceMethod;
    include_in_net_worth: boolean;
    currency: string;
    initial_balance: string;
    opening_balance_date: string;
    display_order: string;
    is_active: boolean;
    note: string;
    import_aliases: string;
};

export type EditableAccount = {
    id: number;
    name: string;
    type: string;
    balance_role: AccountBalanceRole;
    balance_method: AccountBalanceMethod;
    include_in_net_worth: boolean;
    currency: string;
    initial_balance: string;
    opening_balance_date: string | null;
    display_order: number;
    is_active: boolean;
    note: string | null;
    import_aliases: string[] | null;
};
