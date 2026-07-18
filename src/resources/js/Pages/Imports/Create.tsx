import AppPage from '@/Components/AppPage';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { ImportAccountOption, ImportSourceOption } from '@/types/import';
import { jrePointBookmarklet } from '@/utils/jrePointBookmarklet';
import { moneyForwardBalanceBookmarklet } from '@/utils/moneyForwardBalanceBookmarklet';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type CreateProps = {
    sourceOptions: ImportSourceOption[];
    accountOptions: ImportAccountOption[];
    selectedSource: string;
};

export default function Create({ sourceOptions, accountOptions, selectedSource }: CreateProps) {
    const [copiedBookmarklet, setCopiedBookmarklet] = useState<
        'jre_point' | 'money_forward' | null
    >(null);
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
    const isAssetHistory = data.source_name === 'asset_history';
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
                        {isBalanceSnapshot || isAssetHistory ? (
                            <div className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                {isBalanceSnapshot
                                    ? 'JSON内の口座名から取込先を自動判定し、プレビューで変更できます。'
                                    : '資産推移は口座へ按分せず、Money Forwardの公式合計として保存します。'}
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
                                onClick={() =>
                                    copyBookmarklet(jrePointBookmarklet, 'jre_point')
                                }
                                className="rounded-md px-3 py-2 font-medium text-emerald-800 hover:bg-emerald-100"
                            >
                                {copiedBookmarklet === 'jre_point'
                                    ? 'コピーしました'
                                    : 'ブックマークレットをコピー'}
                            </button>
                        </div>
                        <p className="text-xs text-emerald-800">
                            ログイン情報やCookieは保存せず、履歴・残高・有効期限だけをJSONへ書き出します。
                        </p>
                    </div>
                ) : null}

                {isBalanceSnapshot ? (
                    <div className="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-4 text-sm text-indigo-950">
                        <div>
                            <p className="font-semibold">Money Forward残高の半自動取込</p>
                            <ol className="mt-2 list-decimal space-y-1 pl-5 text-indigo-900">
                                <li>Money Forwardで連携口座を最新状態へ更新します。</li>
                                <li>
                                    下の「Money Forward残高を書き出す」をブックマークバーへドラッグします。
                                </li>
                                <li>
                                    ログイン済みのMoney Forward Web版でブックマークを実行します。
                                </li>
                                <li>確認後に保存されたJSONをこの画面で選択します。</li>
                            </ol>
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <a
                                href={moneyForwardBalanceBookmarklet}
                                className="cursor-grab rounded-md border border-indigo-300 bg-white px-4 py-2 font-semibold text-indigo-800 shadow-sm hover:bg-indigo-100"
                                title="ブックマークバーへドラッグしてください"
                            >
                                Money Forward残高を書き出す
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
                                    ? 'コピーしました'
                                    : 'ブックマークレットをコピー'}
                            </button>
                            <a
                                href="https://moneyforward.com/bs/portfolio"
                                target="_blank"
                                rel="noreferrer"
                                className="rounded-md px-3 py-2 font-medium text-indigo-800 hover:bg-indigo-100"
                            >
                                Money Forwardを開く
                            </a>
                        </div>
                        <ul className="list-disc space-y-1 pl-5 text-indigo-900">
                            <li>銀行預金は金融機関ごとの公式口座残高として集計します。</li>
                            <li>投資信託は口座合計に加えて、銘柄ごとの保有数・現在値・評価額・評価損益も保存します。</li>
                            <li>THEOは銘柄評価額と現金部分を口座評価額として合算します。</li>
                            <li>年金は「Money Forward 年金」として書き出し、ユーザーごとの年金口座へ対応付けます。</li>
                            <li>カード利用残高はカードごとに集計し、負数の負債残高として保存します。</li>
                            <li>期首残高や取引は変更せず、プレビュー確認後に残高だけを記録します。</li>
                        </ul>
                        <p className="text-xs text-indigo-800">
                            ログイン情報やCookieは保存しません。取得処理はMoney Forwardのログイン済み画面内で実行し、残高JSONだけを端末へ保存します。
                        </p>
                    </div>
                ) : null}

                {isAssetHistory ? (
                    <div className="space-y-3 rounded-xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-950">
                        <div>
                            <p className="font-semibold">Money Forward 資産推移の初期取込</p>
                            <ol className="mt-2 list-decimal space-y-1 pl-5 text-violet-900">
                                <li>Money Forward Web版の「資産推移」を開きます。</li>
                                <li>画面下部のCSVダウンロードから資産推移CSVを保存します。</li>
                                <li>保存したCSVを選択し、過去の総資産・資産分類を確認して反映します。</li>
                            </ol>
                        </div>
                        <a href="https://moneyforward.com/bs/history" target="_blank" rel="noreferrer" className="w-fit rounded-md px-3 py-2 font-medium text-violet-800 hover:bg-violet-100">
                            Money Forward 資産推移を開く
                        </a>
                        <p className="text-xs text-violet-800">
                            銘柄別の過去データは公式CSVに含まれません。現在の銘柄を残高取得で起点登録し、以後の日次取得で銘柄別推移を蓄積します。
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
                                      : isAssetHistory
                                        ? 'Money Forward 資産推移 CSV'
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
                                  : isAssetHistory
                                    ? '.csv,text/csv,.txt'
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
