import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import {
    AccountFormValues,
    AccountTypeOption,
    EditableAccount,
} from '@/types/account';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';

type AccountFormProps = {
    account?: EditableAccount;
    submitLabel: string;
    submitRoute: string;
    method: 'post' | 'put';
    typeOptions: AccountTypeOption[];
    balanceRoleOptions: AccountTypeOption[];
    balanceMethodOptions: AccountTypeOption[];
};

const defaultValues: AccountFormValues = {
    name: '',
    type: 'cash',
    balance_role: 'asset',
    balance_method: 'ledger',
    include_in_net_worth: true,
    monthly_close_required: false,
    currency: 'JPY',
    initial_balance: '0',
    opening_balance_date: '',
    display_order: '0',
    is_active: true,
    note: '',
    import_aliases: '',
};

export default function AccountForm({
    account,
    submitLabel,
    submitRoute,
    method,
    typeOptions,
    balanceRoleOptions,
    balanceMethodOptions,
}: AccountFormProps) {
    const { t } = useTranslation('accounts');
    const { data, setData, post, put, processing, errors } =
        useForm<AccountFormValues>({
            name: account?.name ?? defaultValues.name,
            type: account?.type ?? defaultValues.type,
            balance_role: account?.balance_role ?? defaultValues.balance_role,
            balance_method:
                account?.balance_method ?? defaultValues.balance_method,
            include_in_net_worth:
                account?.include_in_net_worth ??
                defaultValues.include_in_net_worth,
            monthly_close_required:
                account?.monthly_close_required ??
                defaultValues.monthly_close_required,
            currency: account?.currency ?? defaultValues.currency,
            initial_balance:
                account?.initial_balance ?? defaultValues.initial_balance,
            opening_balance_date:
                account?.opening_balance_date ??
                defaultValues.opening_balance_date,
            display_order: String(
                account?.display_order ?? defaultValues.display_order,
            ),
            is_active: account?.is_active ?? defaultValues.is_active,
            note: account?.note ?? defaultValues.note,
            import_aliases: account?.import_aliases?.join('\n') ?? defaultValues.import_aliases,
        });

    const handleTypeChange = (type: string) => {
        const balanceRole =
            type === 'credit_card'
                ? 'liability'
                : type === 'other'
                  ? 'clearing'
                  : 'asset';

        setData({
            ...data,
            type,
            balance_role: balanceRole,
            balance_method: type === 'securities' ? 'snapshot' : 'ledger',
            include_in_net_worth: balanceRole !== 'clearing',
            monthly_close_required: [
                'bank',
                'credit_card',
                'e_money',
                'securities',
                'point',
            ].includes(type),
        });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
        };

        if (method === 'put') {
            put(submitRoute, options);

            return;
        }

        post(submitRoute, options);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="name" value={t('form.fields.name')} />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        required
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="type" value={t('form.fields.type')} />
                    <select
                        id="type"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.type}
                        onChange={(event) => handleTypeChange(event.target.value)}
                    >
                        {typeOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.type} />
                </div>

                <div>
                    <InputLabel htmlFor="currency" value={t('form.fields.currency')} />
                    <TextInput
                        id="currency"
                        className="mt-1 block w-full uppercase"
                        maxLength={3}
                        value={data.currency}
                        onChange={(event) =>
                            setData('currency', event.target.value.toUpperCase())
                        }
                        required
                    />
                    <InputError className="mt-2" message={errors.currency} />
                </div>

                <div>
                    <InputLabel htmlFor="initial_balance" value={t('form.fields.initialBalance')} />
                    <TextInput
                        id="initial_balance"
                        type="number"
                        step="0.01"
                        className="mt-1 block w-full"
                        value={data.initial_balance}
                        onChange={(event) =>
                            setData('initial_balance', event.target.value)
                        }
                        required
                    />
                    <InputError
                        className="mt-2"
                        message={errors.initial_balance}
                    />
                </div>

                <div>
                    <InputLabel
                        htmlFor="opening_balance_date"
                        value={t('form.fields.openingBalanceDate')}
                    />
                    <TextInput
                        id="opening_balance_date"
                        type="date"
                        className="mt-1 block w-full"
                        value={data.opening_balance_date}
                        onChange={(event) =>
                            setData('opening_balance_date', event.target.value)
                        }
                    />
                    <p className="mt-2 text-xs leading-5 text-slate-500">
                        {t('form.openingBalanceHint')}
                    </p>
                    <InputError
                        className="mt-2"
                        message={errors.opening_balance_date}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="display_order" value={t('form.fields.displayOrder')} />
                    <TextInput
                        id="display_order"
                        type="number"
                        min="0"
                        className="mt-1 block w-full"
                        value={data.display_order}
                        onChange={(event) =>
                            setData('display_order', event.target.value)
                        }
                    />
                    <InputError
                        className="mt-2"
                        message={errors.display_order}
                    />
                </div>

                <div className="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                    <input
                        id="is_active"
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(event) =>
                            setData('is_active', event.target.checked)
                        }
                        className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <InputLabel htmlFor="is_active" value={t('form.fields.active')} />
                        <p className="text-xs text-slate-500">
                            {t('form.activeHint')}
                        </p>
                    </div>
                </div>
            </div>

            <section className="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div>
                    <h2 className="font-semibold text-slate-900">
                        {t('form.balanceSectionTitle')}
                    </h2>
                    <p className="mt-1 text-sm leading-6 text-slate-500">
                        {t('form.balanceSectionDescription')}
                    </p>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="balance_role" value={t('form.fields.balanceRole')} />
                        <select
                            id="balance_role"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.balance_role}
                            onChange={(event) => {
                                const balanceRole = event.target.value as AccountFormValues['balance_role'];
                                setData({
                                    ...data,
                                    balance_role: balanceRole,
                                    include_in_net_worth:
                                        balanceRole === 'clearing'
                                            ? false
                                            : data.include_in_net_worth,
                                });
                            }}
                        >
                            {balanceRoleOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-2 text-xs leading-5 text-slate-500">
                            {t('form.clearingHint')}
                        </p>
                        <InputError
                            className="mt-2"
                            message={errors.balance_role}
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="balance_method"
                            value={t('form.fields.balanceMethod')}
                        />
                        <select
                            id="balance_method"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.balance_method}
                            onChange={(event) =>
                                setData(
                                    'balance_method',
                                    event.target.value as AccountFormValues['balance_method'],
                                )
                            }
                        >
                            {balanceMethodOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-2 text-xs leading-5 text-slate-500">
                            {t('form.snapshotHint')}
                        </p>
                        <InputError
                            className="mt-2"
                            message={errors.balance_method}
                        />
                    </div>
                </div>

                <div className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input
                        id="include_in_net_worth"
                        type="checkbox"
                        checked={data.include_in_net_worth}
                        disabled={data.balance_role === 'clearing'}
                        onChange={(event) =>
                            setData('include_in_net_worth', event.target.checked)
                        }
                        className="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50"
                    />
                    <div>
                        <InputLabel
                            htmlFor="include_in_net_worth"
                            value={t('form.fields.includeInNetWorth')}
                        />
                        <p className="mt-1 text-xs leading-5 text-slate-500">
                            {t('form.netWorthHint')}
                        </p>
                        <InputError
                            className="mt-2"
                            message={errors.include_in_net_worth}
                        />
                    </div>
                </div>

                <div className="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input
                        id="monthly_close_required"
                        type="checkbox"
                        checked={data.monthly_close_required}
                        onChange={(event) =>
                            setData('monthly_close_required', event.target.checked)
                        }
                        className="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <InputLabel
                            htmlFor="monthly_close_required"
                            value={t('form.fields.monthlyCloseRequired')}
                        />
                        <p className="mt-1 text-xs leading-5 text-slate-500">
                            {t('form.monthlyCloseHint')}
                        </p>
                        <InputError
                            className="mt-2"
                            message={errors.monthly_close_required}
                        />
                    </div>
                </div>
            </section>

            <div>
                <InputLabel htmlFor="note" value={t('form.fields.note')} />
                <textarea
                    id="note"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={4}
                    value={data.note}
                    onChange={(event) => setData('note', event.target.value)}
                />
                <InputError className="mt-2" message={errors.note} />
            </div>

            <div>
                <InputLabel htmlFor="import_aliases" value={t('form.fields.importAliases')} />
                <textarea
                    id="import_aliases"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={4}
                    value={data.import_aliases}
                    onChange={(event) =>
                        setData('import_aliases', event.target.value)
                    }
                />
                <p className="mt-2 text-xs text-slate-500">
                    {t('form.importAliasesHint')}
                </p>
                <InputError
                    className="mt-2"
                    message={errors.import_aliases}
                />
            </div>

            <div className="flex items-center justify-end gap-3">
                <Link
                    href={route('accounts.index')}
                    className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    {t('actions.cancel')}
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
