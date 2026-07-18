import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import {
    EditableTransaction,
    TransactionAccountOption,
    TransactionCategoryOption,
    TransactionFormValues,
    TransactionSubcategoryOption,
    TransactionTypeOption,
} from '@/types/transaction';
import { Link, useForm } from '@inertiajs/react';
import { FormEvent, useEffect } from 'react';

type TransactionFormProps = {
    transaction?: EditableTransaction;
    submitLabel: string;
    submitRoute: string;
    method: 'post' | 'put';
    typeOptions: TransactionTypeOption[];
    accountOptions: TransactionAccountOption[];
    categoryOptions: TransactionCategoryOption[];
    subcategoryOptions: TransactionSubcategoryOption[];
};

const defaultValues: TransactionFormValues = {
    transaction_date: new Date().toISOString().slice(0, 10),
    type: 'expense',
    account_id: '',
    transfer_account_id: '',
    amount: '',
    currency: 'JPY',
    merchant_name: '',
    description: '',
    category_id: '',
    subcategory_id: '',
    payment_method_label: '',
    is_confirmed: true,
    is_calculation_target: true,
    affects_account_balance: true,
    memo: '',
};

export default function TransactionForm({
    transaction,
    submitLabel,
    submitRoute,
    method,
    typeOptions,
    accountOptions,
    categoryOptions,
    subcategoryOptions,
}: TransactionFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<TransactionFormValues>({
            transaction_date:
                transaction?.transaction_date ?? defaultValues.transaction_date,
            type: transaction?.type ?? defaultValues.type,
            account_id: transaction?.account_id
                ? String(transaction.account_id)
                : defaultValues.account_id,
            transfer_account_id: transaction?.transfer_account_id
                ? String(transaction.transfer_account_id)
                : defaultValues.transfer_account_id,
            amount: transaction?.amount ?? defaultValues.amount,
            currency: transaction?.currency ?? defaultValues.currency,
            merchant_name:
                transaction?.merchant_name ?? defaultValues.merchant_name,
            description:
                transaction?.description ?? defaultValues.description,
            category_id: transaction?.category_id
                ? String(transaction.category_id)
                : defaultValues.category_id,
            subcategory_id: transaction?.subcategory_id
                ? String(transaction.subcategory_id)
                : defaultValues.subcategory_id,
            payment_method_label:
                transaction?.payment_method_label ??
                defaultValues.payment_method_label,
            is_confirmed:
                transaction?.is_confirmed ?? defaultValues.is_confirmed,
            is_calculation_target:
                transaction?.is_calculation_target ??
                defaultValues.is_calculation_target,
            affects_account_balance:
                transaction?.affects_account_balance ??
                defaultValues.affects_account_balance,
            memo: transaction?.memo ?? defaultValues.memo,
        });

    useEffect(() => {
        if (data.type === 'transfer') {
            if (!data.affects_account_balance) {
                setData('affects_account_balance', true);
            }

            if (data.category_id !== '') {
                setData('category_id', '');
            }

            if (data.subcategory_id !== '') {
                setData('subcategory_id', '');
            }

            return;
        }

        if (data.transfer_account_id !== '') {
            setData('transfer_account_id', '');
        }
    }, [data.type]);

    useEffect(() => {
        if (data.category_id === '') {
            if (data.subcategory_id !== '') {
                setData('subcategory_id', '');
            }

            return;
        }

        const availableSubcategoryIds = subcategoryOptions
            .filter((subcategory) => String(subcategory.category_id) === data.category_id)
            .map((subcategory) => String(subcategory.id));

        if (
            data.subcategory_id !== '' &&
            !availableSubcategoryIds.includes(data.subcategory_id)
        ) {
            setData('subcategory_id', '');
        }
    }, [data.category_id, data.subcategory_id, subcategoryOptions]);

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

    const filteredCategories = categoryOptions.filter((category) => {
        if (data.type === 'income') {
            return category.type === 'income' || category.type === 'both';
        }

        if (data.type === 'expense') {
            return category.type === 'expense' || category.type === 'both';
        }

        return true;
    });

    const filteredSubcategories = subcategoryOptions.filter(
        (subcategory) => String(subcategory.category_id) === data.category_id,
    );

    const handleAccountChange = (accountId: string) => {
        setData('account_id', accountId);

        const selectedAccount = accountOptions.find(
            (account) => String(account.id) === accountId,
        );

        if (!selectedAccount) {
            return;
        }

        setData('currency', selectedAccount.currency);
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="transaction_date" value="取引日" />
                    <TextInput
                        id="transaction_date"
                        type="date"
                        className="mt-1 block w-full"
                        value={data.transaction_date}
                        onChange={(event) =>
                            setData('transaction_date', event.target.value)
                        }
                        required
                    />
                    <InputError
                        className="mt-2"
                        message={errors.transaction_date}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="type" value="種別" />
                    <select
                        id="type"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.type}
                        onChange={(event) => setData('type', event.target.value)}
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
                    <InputLabel htmlFor="account_id" value="口座" />
                    <select
                        id="account_id"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.account_id}
                        onChange={(event) =>
                            handleAccountChange(event.target.value)
                        }
                        required
                    >
                        <option value="">選択してください</option>
                        {accountOptions.map((account) => (
                            <option key={account.id} value={account.id}>
                                {account.name} ({account.currency})
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-2" message={errors.account_id} />
                </div>

                {data.type === 'transfer' && (
                    <div>
                        <InputLabel
                            htmlFor="transfer_account_id"
                            value="振替先口座"
                        />
                        <select
                            id="transfer_account_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.transfer_account_id}
                            onChange={(event) =>
                                setData('transfer_account_id', event.target.value)
                            }
                        >
                            <option value="">選択してください</option>
                            {accountOptions.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name} ({account.currency})
                                </option>
                            ))}
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.transfer_account_id}
                        />
                        <p className="mt-2 text-xs text-slate-500">
                            振替は口座間移動・コード決済からカードへの請求付替・カード引落の登録に使います。
                        </p>
                    </div>
                )}

                <div>
                    <InputLabel htmlFor="amount" value="金額" />
                    <TextInput
                        id="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        className="mt-1 block w-full"
                        value={data.amount}
                        onChange={(event) => setData('amount', event.target.value)}
                        required
                    />
                    <InputError className="mt-2" message={errors.amount} />
                </div>

                <div>
                    <InputLabel htmlFor="currency" value="通貨" />
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

                {data.type !== 'transfer' && (
                    <>
                        <div>
                            <InputLabel htmlFor="category_id" value="カテゴリ" />
                            <select
                                id="category_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.category_id}
                                onChange={(event) =>
                                    setData('category_id', event.target.value)
                                }
                            >
                                <option value="">選択してください</option>
                                {filteredCategories.map((category) => (
                                    <option key={category.id} value={category.id}>
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                className="mt-2"
                                message={errors.category_id}
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="subcategory_id"
                                value="小分類"
                            />
                            <select
                                id="subcategory_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.subcategory_id}
                                onChange={(event) =>
                                    setData('subcategory_id', event.target.value)
                                }
                            >
                                <option value="">未選択</option>
                                {filteredSubcategories.map((subcategory) => (
                                    <option
                                        key={subcategory.id}
                                        value={subcategory.id}
                                    >
                                        {subcategory.name}
                                    </option>
                                ))}
                            </select>
                            <InputError
                                className="mt-2"
                                message={errors.subcategory_id}
                            />
                        </div>
                    </>
                )}

                <div>
                    <InputLabel htmlFor="merchant_name" value="店舗名" />
                    <TextInput
                        id="merchant_name"
                        className="mt-1 block w-full"
                        value={data.merchant_name}
                        onChange={(event) =>
                            setData('merchant_name', event.target.value)
                        }
                    />
                    <InputError
                        className="mt-2"
                        message={errors.merchant_name}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="payment_method_label" value="支払方法ラベル" />
                    <TextInput
                        id="payment_method_label"
                        className="mt-1 block w-full"
                        value={data.payment_method_label}
                        onChange={(event) =>
                            setData('payment_method_label', event.target.value)
                        }
                    />
                    <InputError
                        className="mt-2"
                        message={errors.payment_method_label}
                    />
                </div>
            </div>

            <div>
                <InputLabel htmlFor="description" value="摘要" />
                <textarea
                    id="description"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={3}
                    value={data.description}
                    onChange={(event) => setData('description', event.target.value)}
                />
                <InputError className="mt-2" message={errors.description} />
            </div>

            <div>
                <InputLabel htmlFor="memo" value="メモ" />
                <textarea
                    id="memo"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows={4}
                    value={data.memo}
                    onChange={(event) => setData('memo', event.target.value)}
                />
                <InputError className="mt-2" message={errors.memo} />
            </div>

            <div className="grid gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 md:grid-cols-3">
                <label className="flex items-start gap-3">
                    <input
                        id="is_confirmed"
                        type="checkbox"
                        checked={data.is_confirmed}
                        onChange={(event) =>
                            setData('is_confirmed', event.target.checked)
                        }
                        className="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <InputLabel htmlFor="is_confirmed" value="確認済みにする" />
                        <p className="text-xs text-slate-500">
                            確認済みフラグは一覧の絞り込みにも利用できます。
                        </p>
                    </div>
                </label>

                <label className="flex items-start gap-3">
                    <input
                        id="is_calculation_target"
                        type="checkbox"
                        checked={data.is_calculation_target}
                        onChange={(event) =>
                            setData('is_calculation_target', event.target.checked)
                        }
                        className="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <InputLabel htmlFor="is_calculation_target" value="集計対象に含める" />
                        <p className="text-xs text-slate-500">
                            収入・支出の集計に含める取引だけ有効にしてください。振替はこの設定に関係なく口座残高へ反映されます。
                        </p>
                    </div>
                </label>

                <label className="flex items-start gap-3">
                    <input
                        id="affects_account_balance"
                        type="checkbox"
                        checked={data.affects_account_balance}
                        disabled={data.type === 'transfer'}
                        onChange={(event) =>
                            setData('affects_account_balance', event.target.checked)
                        }
                        className="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div>
                        <InputLabel
                            htmlFor="affects_account_balance"
                            value="口座残高へ反映する"
                        />
                        <p className="text-xs text-slate-500">
                            ポイント獲得など、収支集計から除外しても資産残高を増減させる取引で有効にします。
                        </p>
                    </div>
                </label>
            </div>

            <div className="flex items-center justify-end gap-3">
                <Link
                    href={route('transactions.index')}
                    className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    キャンセル
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
