import AppPage from '@/Components/AppPage';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import {
    ImportAccountOption,
    ImportSourceOption,
    ImportSuggestedAccountIds,
} from '@/types/import';
import { jrePointBookmarklet } from '@/utils/jrePointBookmarklet';
import { moneyForwardBalanceBookmarklet } from '@/utils/moneyForwardBalanceBookmarklet';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

type CreateProps = {
    sourceOptions: ImportSourceOption[];
    accountOptions: ImportAccountOption[];
    selectedSource: string;
    suggestedAccountIds: ImportSuggestedAccountIds;
};

export default function Create({
    sourceOptions,
    accountOptions,
    selectedSource,
    suggestedAccountIds,
}: CreateProps) {
    const { t } = useTranslation('imports');
    const initialAccountId =
        selectedSource === 'mobile_suica'
            ? suggestedAccountIds.mobile_suica
            : selectedSource === 'jre_point'
              ? suggestedAccountIds.jre_point
              : undefined;
    const [copiedBookmarklet, setCopiedBookmarklet] = useState<
        'jre_point' | 'money_forward' | null
    >(null);
    const { data, setData, post, processing, errors } = useForm({
        source_name: selectedSource,
        account_id: initialAccountId ? String(initialAccountId) : '',
        csv_file: null as File | null,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(route('imports.store'));
    };

    const isMobileSuica = data.source_name === 'mobile_suica';
    const isJrePoint = data.source_name === 'jre_point';
    const isBalanceSnapshot = data.source_name === 'balance_snapshot';
    const isAssetHistory = data.source_name === 'asset_history';
    const selectableAccounts = isMobileSuica
        ? accountOptions.filter((account) => account.type === 'e_money')
        : isJrePoint
          ? accountOptions.filter((account) => account.type === 'point')
          : accountOptions;
    const handleSourceChange = (sourceName: string) => {
        setData({
            source_name: sourceName,
            account_id:
                sourceName === 'mobile_suica' &&
                suggestedAccountIds.mobile_suica
                    ? String(suggestedAccountIds.mobile_suica)
                    : sourceName === 'jre_point' &&
                        suggestedAccountIds.jre_point
                      ? String(suggestedAccountIds.jre_point)
                      : '',
            csv_file: null,
        });
    };

    const copyBookmarklet = async (
        bookmarklet: string,
        type: 'jre_point' | 'money_forward',
    ) => {
        await navigator.clipboard.writeText(bookmarklet);
        setCopiedBookmarklet(type);
        window.setTimeout(() => setCopiedBookmarklet(null), 2000);
    };

    return (
        <AppPage
            title={t('create.title')}
            description={t('create.description')}
        >
            <Head title={t('create.title')} />

            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="source_name"
                            value={t('create.source')}
                        />
                        <select
                            id="source_name"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.source_name}
                            onChange={(event) =>
                                handleSourceChange(event.target.value)
                            }
                        >
                            {sourceOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError
                            className="mt-2"
                            message={errors.source_name}
                        />
                    </div>

                    <div>
                        {isBalanceSnapshot || isAssetHistory ? (
                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                {isBalanceSnapshot
                                    ? t(
                                          'create.automaticAccount.balanceSnapshot',
                                      )
                                    : t('create.automaticAccount.assetHistory')}
                            </div>
                        ) : (
                            <>
                                <InputLabel
                                    htmlFor="account_id"
                                    value={
                                        isMobileSuica
                                            ? t('create.account.mobileSuica')
                                            : isJrePoint
                                              ? t('create.account.jrePoint')
                                              : t('create.account.optional')
                                    }
                                />
                                <select
                                    id="account_id"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.account_id}
                                    onChange={(event) =>
                                        setData(
                                            'account_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        {t('create.selectAccount')}
                                    </option>
                                    {selectableAccounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.name} ({account.currency})
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    className="mt-2"
                                    message={errors.account_id}
                                />
                            </>
                        )}
                    </div>
                </div>

                {isMobileSuica ? (
                    <div className="space-y-4 rounded-xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-950">
                        <div>
                            <p className="font-semibold">
                                {t('guides.mobileSuica.title')}
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-sky-900">
                                {(
                                    t('guides.mobileSuica.steps', {
                                        returnObjects: true,
                                    }) as string[]
                                ).map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ul>
                        </div>
                        <a
                            href="https://www.mobilesuica.com/iq/ir/SuicaDisp.aspx"
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex w-fit items-center rounded-md border border-sky-300 bg-white px-4 py-2 font-semibold text-sky-800 shadow-sm hover:bg-sky-100"
                        >
                            {t('guides.mobileSuica.open')}
                        </a>
                        <p className="text-xs text-sky-800">
                            {t('guides.mobileSuica.hint')}
                        </p>
                    </div>
                ) : null}

                {isJrePoint ? (
                    <div className="space-y-4 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-950">
                        <div>
                            <p className="font-semibold">
                                {t('guides.jrePoint.title')}
                            </p>
                            <ol className="mt-2 list-decimal space-y-1 pl-5 text-emerald-900">
                                {(
                                    t('guides.jrePoint.steps', {
                                        returnObjects: true,
                                    }) as string[]
                                ).map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ol>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <a
                                href={jrePointBookmarklet}
                                className="cursor-grab rounded-md border border-emerald-300 bg-white px-4 py-2 font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100"
                                title={t('actions.dragBookmarklet')}
                            >
                                {t('guides.jrePoint.export')}
                            </a>
                            <button
                                type="button"
                                onClick={() =>
                                    copyBookmarklet(
                                        jrePointBookmarklet,
                                        'jre_point',
                                    )
                                }
                                className="rounded-md px-3 py-2 font-medium text-emerald-800 hover:bg-emerald-100"
                            >
                                {copiedBookmarklet === 'jre_point'
                                    ? t('actions.copied')
                                    : t('actions.copyBookmarklet')}
                            </button>
                            <a
                                href="https://www.jrepoint.jp/member/pointlog/"
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-md border border-emerald-300 bg-white px-4 py-2 font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100"
                            >
                                {t('guides.jrePoint.open')}
                            </a>
                        </div>
                        <p className="text-xs text-emerald-800">
                            {t('guides.jrePoint.privacy')}
                        </p>
                    </div>
                ) : null}

                {isBalanceSnapshot ? (
                    <div className="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-sm text-indigo-950">
                        <div>
                            <p className="font-semibold">
                                {t('guides.balanceSnapshot.title')}
                            </p>
                            <ol className="mt-2 list-decimal space-y-1 pl-5 text-indigo-900">
                                {(
                                    t('guides.balanceSnapshot.steps', {
                                        returnObjects: true,
                                    }) as string[]
                                ).map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ol>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <a
                                href={moneyForwardBalanceBookmarklet}
                                className="cursor-grab rounded-md border border-indigo-300 bg-white px-4 py-2 font-semibold text-indigo-800 shadow-sm hover:bg-indigo-100"
                                title={t('actions.dragBookmarklet')}
                            >
                                {t('guides.balanceSnapshot.export')}
                            </a>
                            <button
                                type="button"
                                onClick={() =>
                                    copyBookmarklet(
                                        moneyForwardBalanceBookmarklet,
                                        'money_forward',
                                    )
                                }
                                className="rounded-md px-3 py-2 font-medium text-indigo-800 hover:bg-indigo-100"
                            >
                                {copiedBookmarklet === 'money_forward'
                                    ? t('actions.copied')
                                    : t('actions.copyBookmarklet')}
                            </button>
                            <a
                                href="https://moneyforward.com/bs/portfolio"
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-md px-3 py-2 font-medium text-indigo-800 hover:bg-indigo-100"
                            >
                                {t('guides.balanceSnapshot.open')}
                            </a>
                        </div>
                        <ul className="list-disc space-y-1 pl-5 text-indigo-900">
                            {(
                                t('guides.balanceSnapshot.details', {
                                    returnObjects: true,
                                }) as string[]
                            ).map((detail) => (
                                <li key={detail}>{detail}</li>
                            ))}
                        </ul>
                        <p className="text-xs font-semibold text-indigo-900">
                            {t('guides.balanceSnapshot.update')}
                        </p>
                        <p className="text-xs text-indigo-800">
                            {t('guides.balanceSnapshot.privacy')}
                        </p>
                    </div>
                ) : null}

                {isAssetHistory ? (
                    <div className="space-y-3 rounded-xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-950">
                        <div>
                            <p className="font-semibold">
                                {t('guides.assetHistory.title')}
                            </p>
                            <ol className="mt-2 list-decimal space-y-1 pl-5 text-violet-900">
                                {(
                                    t('guides.assetHistory.steps', {
                                        returnObjects: true,
                                    }) as string[]
                                ).map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ol>
                        </div>
                        <a
                            href="https://moneyforward.com/bs/history"
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex w-fit items-center rounded-md border border-violet-300 bg-white px-4 py-2 font-semibold text-violet-800 shadow-sm hover:bg-violet-100"
                        >
                            {t('guides.assetHistory.open')}
                        </a>
                        <p className="text-xs text-violet-800">
                            {t('guides.assetHistory.hint')}
                        </p>
                    </div>
                ) : null}

                <div>
                    <InputLabel
                        htmlFor="csv_file"
                        value={
                            isMobileSuica
                                ? t('create.file.mobileSuica')
                                : isJrePoint
                                  ? t('create.file.jrePoint')
                                  : isBalanceSnapshot
                                    ? t('create.file.balanceSnapshot')
                                    : isAssetHistory
                                      ? t('create.file.assetHistory')
                                      : t('create.file.default')
                        }
                    />
                    <input
                        key={data.source_name}
                        id="csv_file"
                        type="file"
                        accept={
                            isMobileSuica
                                ? '.pdf,application/pdf'
                                : isJrePoint || isBalanceSnapshot
                                  ? '.json,application/json,text/plain'
                                  : isAssetHistory
                                    ? '.csv,text/csv,.txt'
                                    : '.csv,text/csv,.txt'
                        }
                        className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                        onChange={(event) =>
                            setData('csv_file', event.target.files?.[0] ?? null)
                        }
                    />
                    <InputError className="mt-2" message={errors.csv_file} />
                </div>

                <div className="flex items-center gap-3">
                    <PrimaryButton disabled={processing}>
                        {t('actions.upload')}
                    </PrimaryButton>
                    <Link
                        href={route('imports.index')}
                        className="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        {t('actions.back')}
                    </Link>
                </div>
            </form>
        </AppPage>
    );
}
