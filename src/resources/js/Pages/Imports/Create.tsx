import AppPage from '@/Components/AppPage';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { ImportAccountOption, ImportSourceOption } from '@/types/import';
import { jrePointBookmarklet } from '@/utils/jrePointBookmarklet';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type CreateProps = {
    sourceOptions: ImportSourceOption[];
    accountOptions: ImportAccountOption[];
    selectedSource: string;
};

export default function Create({ sourceOptions, accountOptions, selectedSource }: CreateProps) {
    const [bookmarkletCopied, setBookmarkletCopied] = useState(false);
    const { data, setData, post, processing, errors } = useForm({
        source_name: selectedSource,
        account_id: '',
        csv_file: null as File | null,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        post(route('imports.store'));
    };

    const isMobileSuica = data.source_name === 'mobile_suica';
    const isJrePoint = data.source_name === 'jre_point';
    const isBalanceSnapshot = data.source_name === 'balance_snapshot';
    const selectableAccounts = isMobileSuica
        ? accountOptions.filter((account) => account.type === 'e_money')
        : isJrePoint
          ? accountOptions.filter((account) => account.type === 'point')
          : accountOptions;
    const mobileSuicaAccount = accountOptions.find(
        (account) => account.type === 'e_money' && account.name === 'モバイルSuica',
    );
    const jrePointAccount = accountOptions.find(
        (account) => account.type === 'point' && account.name.replace(/\s/g, '') === 'JREポイント',
    );

    const handleSourceChange = (sourceName: string) => {
        setData({
            source_name: sourceName,
            account_id:
                sourceName === 'mobile_suica' && mobileSuicaAccount
                    ? String(mobileSuicaAccount.id)
                    : sourceName === 'jre_point' && jrePointAccount
                      ? String(jrePointAccount.id)
                    : '',
            csv_file: null,
        });
    };

    const copyBookmarklet = async () => {
        await navigator.clipboard.writeText(jrePointBookmarklet);
        setBookmarkletCopied(true);
        window.setTimeout(() => setBookmarkletCopied(false), 2000);
    };

    return (
        <AppPage
            title="取引取込"
            description="取込元のファイルをアップロードして、確認後に取引へ反映します。"
        >
            <Head title="取引取込" />

            <form onSubmit={submit} className="space-y-6">
                <div className="grid gap-6 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="source_name" value="取込元" />
                        <select
                            id="source_name"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.source_name}
                            onChange={(event) => handleSourceChange(event.target.value)}
                        >
                            {sourceOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <InputError className="mt-2" message={errors.source_name} />
                    </div>

                    <div>
                        {isBalanceSnapshot ? (
                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                JSON内の口座名から取込先を自動判定し、プレビューで変更できます。
                            </div>
                        ) : (
                            <>
                                <InputLabel
                                    htmlFor="account_id"
                                    value={
                                        isMobileSuica
                                            ? 'モバイルSuica口座'
                                            : isJrePoint
                                              ? 'JRE POINT口座'
                                              : '共通適用口座（任意）'
                                    }
                                />
                                <select
                                    id="account_id"
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={data.account_id}
                                    onChange={(event) => setData('account_id', event.target.value)}
                                >
                                    <option value="">選択してください</option>
                                    {selectableAccounts.map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.name} ({account.currency})
                                        </option>
                                    ))}
                                </select>
                                <InputError className="mt-2" message={errors.account_id} />
                            </>
                        )}
                    </div>
                </div>

                {isMobileSuica ? (
                    <div className="rounded-xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-950">
                        <p className="font-semibold">モバイルSuica PDFの取込内容</p>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-sky-900">
                            <li>利用額がマイナスの履歴を支出として取り込みます。</li>
                            <li>チャージ・繰越・0円の履歴は取引を作成しません。</li>
                            <li>電車とバスは交通費へ分類し、物販はカテゴリ未確定にします。</li>
                            <li>期間が重なるPDFは、既に取り込んだ履歴を重複候補として除外します。</li>
                        </ul>
                    </div>
                ) : null}

                {isJrePoint ? (
                    <div className="space-y-4 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-950">
                        <div>
                            <p className="font-semibold">JRE POINT半自動取込</p>
                            <ol className="mt-2 list-decimal space-y-1 pl-5 text-emerald-900">
                                <li>
                                    下の「JRE POINT履歴を書き出す」をブックマークバーへドラッグします。
                                </li>
                                <li>
                                    JRE POINTのポイント履歴で第2パスワード認証後、そのブックマークを実行します。
                                </li>
                                <li>保存されたJSONをこの画面で選択します。</li>
                            </ol>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <a
                                href={jrePointBookmarklet}
                                className="cursor-grab rounded-md border border-emerald-300 bg-white px-4 py-2 font-semibold text-emerald-800 shadow-sm hover:bg-emerald-100"
                                title="ブックマークバーへドラッグしてください"
                            >
                                JRE POINT履歴を書き出す
                            </a>
                            <button
                                type="button"
                                onClick={copyBookmarklet}
                                className="rounded-md px-3 py-2 font-medium text-emerald-800 hover:bg-emerald-100"
                            >
                                {bookmarkletCopied ? 'コピーしました' : 'ブックマークレットをコピー'}
                            </button>
                        </div>
                        <p className="text-xs text-emerald-800">
                            ログイン情報やCookieは保存せず、履歴・残高・有効期限だけをJSONへ書き出します。
                        </p>
                    </div>
                ) : null}

                {isBalanceSnapshot ? (
                    <div className="space-y-2 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-sm text-indigo-950">
                        <p className="font-semibold">公式残高・評価額の取込</p>
                        <ul className="list-disc space-y-1 pl-5 text-indigo-900">
                            <li>証券口座は時価評価額として保存します。</li>
                            <li>カード利用残高は負数の公式負債残高として保存します。</li>
                            <li>日々の取得では期首残高を変更しません。</li>
                            <li>口座対応と重複を確認してから一括反映します。</li>
                        </ul>
                        <p className="text-xs text-indigo-800">
                            Money Forward取得ツールは次の段階で接続します。現在は同じ形式のJSONで受け皿を確認できます。
                        </p>
                    </div>
                ) : null}

                <div>
                    <InputLabel
                        htmlFor="csv_file"
                        value={
                            isMobileSuica
                                ? 'モバイルSuica利用履歴 PDF'
                                : isJrePoint
                                  ? 'JRE POINT書き出し JSON'
                                  : isBalanceSnapshot
                                    ? '公式残高・評価額 JSON'
                                    : 'CSV ファイル'
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
                                  : '.csv,text/csv,.txt'
                        }
                        className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-slate-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-slate-700 hover:file:bg-slate-200"
                        onChange={(event) => setData('csv_file', event.target.files?.[0] ?? null)}
                    />
                    <InputError className="mt-2" message={errors.csv_file} />
                </div>

                <div className="flex items-center gap-3">
                    <PrimaryButton disabled={processing}>
                        アップロードして解析
                    </PrimaryButton>
                    <Link
                        href={route('imports.index')}
                        className="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        一覧へ戻る
                    </Link>
                </div>
            </form>
        </AppPage>
    );
}
