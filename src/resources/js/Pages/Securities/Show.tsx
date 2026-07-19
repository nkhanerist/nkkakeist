import AppPage from "@/Components/AppPage";
import LineTrendChart from "@/Components/Charts/LineTrendChart";
import ValueComparisonAreaChart from "@/Components/Charts/ValueComparisonAreaChart";
import {
    SecuritiesAccountDetail,
    SecuritiesPeriodOption,
    SecuritiesPositionDetail,
    SecuritiesPositionItem,
    SecuritiesSnapshotRow,
} from "@/types/securities";
import { TrendSeries } from "@/types/chart";
import { formatMoney } from "@/utils/currency";
import { Link, router } from "@inertiajs/react";
import { useTranslation } from "react-i18next";

type ShowProps = {
    selected_period: string;
    period_options: SecuritiesPeriodOption[];
    period_label: string;
    account: SecuritiesAccountDetail;
    account_series: TrendSeries;
    snapshots: SecuritiesSnapshotRow[];
    positions_as_of_date: string | null;
    latest_positions: SecuritiesPositionItem[];
    selected_position_key: string | null;
    selected_position: SecuritiesPositionDetail | null;
};

const signedMoney = (amount: string | null, currency: string) => {
    if (amount === null) {
        return "—";
    }

    return `${Number(amount) > 0 ? "+" : ""}${formatMoney(amount, currency)} ${currency}`;
};

const amountTone = (amount: string | null) => {
    if (amount === null || Number(amount) === 0) {
        return "text-slate-500";
    }

    return Number(amount) > 0 ? "text-emerald-700" : "text-rose-700";
};

const decimalValue = (value: string | null, locale: string) => {
    if (value === null) {
        return "—";
    }

    return Number(value).toLocaleString(locale, {
        maximumFractionDigits: 8,
    });
};

const priceValue = (value: string | null, currency: string) => {
    if (value === null || Number(value) === 0) {
        return "—";
    }

    return `${formatMoney(value, currency)} ${currency}`;
};

export default function Show({
    selected_period,
    period_options,
    period_label,
    account,
    account_series,
    snapshots,
    positions_as_of_date,
    latest_positions,
    selected_position_key,
    selected_position,
}: ShowProps) {
    const { t, i18n } = useTranslation("securities");
    const numberLocale = i18n.language.startsWith("en") ? "en-US" : "ja-JP";
    const assetClassLabel = (assetClass: string | null) => {
        if (!assetClass) {
            return "—";
        }

        const translationKeys: Record<string, string> = {
            security: "assetClasses.security",
            investment_fund: "assetClasses.investmentFund",
            defined_contribution_pension:
                "assetClasses.definedContributionPension",
        };
        const translationKey = translationKeys[assetClass];

        return translationKey ? t(translationKey) : assetClass;
    };
    const changePeriod = (period: string) => {
        router.get(
            route("securities.show", account.id),
            {
                period,
                ...(selected_position_key
                    ? { position: selected_position_key }
                    : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppPage title={account.name} description={t("show.description")}>
            <div className="space-y-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={route("securities.index", {
                            period: selected_period,
                        })}
                        className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                    >
                        {t("show.back")}
                    </Link>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route("accounts.snapshots.index", account.id)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
                        >
                            {t("show.manage")}
                        </Link>
                        <Link
                            href={route("transactions.index", {
                                account_id: account.id,
                                calculation_target: "all",
                            })}
                            className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                        >
                            {t("show.transactions")}
                        </Link>
                    </div>
                </div>

                <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">
                            {t("show.period.title")}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {t("show.period.description")}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {period_options.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => changePeriod(option.value)}
                                className={`rounded-lg border px-4 py-2 text-sm font-medium transition ${
                                    selected_period === option.value
                                        ? "border-indigo-600 bg-indigo-600 text-white"
                                        : "border-slate-300 bg-white text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </section>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            {t("show.valuation.title")}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {t("show.valuation.description", {
                                period: period_label,
                            })}
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">
                                {t("show.valuation.latest")}
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900">
                                {account.latest_valuation === null
                                    ? "—"
                                    : `${formatMoney(account.latest_valuation, account.currency)} ${account.currency}`}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {account.latest_date ??
                                    t("show.valuation.noData")}
                            </p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">
                                {t("show.valuation.change")}
                            </p>
                            <p
                                className={`mt-2 text-xl font-semibold ${amountTone(account.change_amount)}`}
                            >
                                {signedMoney(
                                    account.change_amount,
                                    account.currency,
                                )}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {account.snapshot_count < 2
                                    ? t("show.valuation.needTwoDays")
                                    : t("show.valuation.daysCompared", {
                                          count: account.snapshot_count,
                                      })}
                            </p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">
                                {t("show.valuation.snapshotDays")}
                            </p>
                            <p className="mt-2 text-xl font-semibold text-slate-900">
                                {t("show.valuation.days", {
                                    count: account.snapshot_count,
                                })}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {t("show.valuation.selectedPeriod")}
                            </p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">
                                {t("show.valuation.latestSource")}
                            </p>
                            <p className="mt-2 text-lg font-semibold text-slate-900">
                                {account.latest_source ?? "—"}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {positions_as_of_date
                                    ? t("show.valuation.positionsAsOf", {
                                          date: positions_as_of_date,
                                      })
                                    : t("show.valuation.noPositions")}
                            </p>
                        </div>
                    </div>
                    <LineTrendChart
                        series={[account_series]}
                        currency={account.currency}
                        emptyMessage={t("show.valuation.chartEmpty")}
                    />
                </section>

                {selected_position ? (
                    <section
                        id="position-detail"
                        className="space-y-5 rounded-3xl border border-indigo-200 bg-indigo-50/40 p-5 sm:p-6"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600">
                                    {t("show.positionDetail.eyebrow")}
                                </p>
                                <h2 className="mt-1 text-xl font-semibold text-slate-900">
                                    {selected_position.instrument_name}
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {[
                                        selected_position.instrument_code,
                                        selected_position.asset_class
                                            ? assetClassLabel(
                                                  selected_position.asset_class,
                                              )
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(" · ") ||
                                        t("show.positionDetail.noMetadata")}
                                </p>
                            </div>
                            <Link
                                href={route("securities.show", {
                                    account: account.id,
                                    period: selected_period,
                                })}
                                className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                            >
                                {t("show.positionDetail.clear")}
                            </Link>
                        </div>

                        <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                            {[
                                [
                                    t("show.positionDetail.valuation"),
                                    `${formatMoney(selected_position.latest.valuation, selected_position.currency)} ${selected_position.currency}`,
                                ],
                                [
                                    t("show.positionDetail.unrealizedGain"),
                                    signedMoney(
                                        selected_position.latest
                                            .unrealized_gain,
                                        selected_position.currency,
                                    ),
                                ],
                                [
                                    t("show.positionDetail.acquisitionCost"),
                                    priceValue(
                                        selected_position.latest
                                            .acquisition_cost,
                                        selected_position.currency,
                                    ),
                                ],
                                [
                                    t("show.positionDetail.quantity"),
                                    decimalValue(
                                        selected_position.latest.quantity,
                                        numberLocale,
                                    ),
                                ],
                                [
                                    t("show.positionDetail.unitPrice"),
                                    priceValue(
                                        selected_position.latest.unit_price,
                                        selected_position.currency,
                                    ),
                                ],
                                [
                                    t(
                                        "show.positionDetail.averageAcquisitionPrice",
                                    ),
                                    priceValue(
                                        selected_position.latest
                                            .average_acquisition_price,
                                        selected_position.currency,
                                    ),
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="rounded-xl border border-white bg-white p-3 shadow-sm"
                                >
                                    <dt className="text-xs text-slate-500">
                                        {label}
                                    </dt>
                                    <dd className="mt-1 font-semibold text-slate-900">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <ValueComparisonAreaChart
                            series={selected_position.comparison_series}
                            currency={selected_position.currency}
                            emptyMessage={t("show.positionDetail.chartEmpty")}
                            height={240}
                        />
                        {selected_position.comparison_series[1]?.points
                            .length === 0 ? (
                            <p className="text-sm text-amber-700">
                                {t("show.positionDetail.acquisitionMissing")}
                            </p>
                        ) : null}

                        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                            {t("show.positionDetail.date")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positionDetail.valuation")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t(
                                                "show.positionDetail.acquisitionCost",
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positionDetail.change")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positionDetail.quantity")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positionDetail.unitPrice")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t(
                                                "show.positionDetail.unrealizedGain",
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                            {t("show.positionDetail.source")}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200">
                                    {selected_position.history.map((row) => (
                                        <tr key={row.date}>
                                            <td className="px-4 py-3 text-slate-700">
                                                {row.date}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {formatMoney(
                                                    row.valuation,
                                                    selected_position.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {priceValue(
                                                    row.acquisition_cost,
                                                    selected_position.currency,
                                                )}
                                            </td>
                                            <td
                                                className={`px-4 py-3 text-right font-medium ${amountTone(row.change_amount)}`}
                                            >
                                                {signedMoney(
                                                    row.change_amount,
                                                    selected_position.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {decimalValue(
                                                    row.quantity,
                                                    numberLocale,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {priceValue(
                                                    row.unit_price,
                                                    selected_position.currency,
                                                )}
                                            </td>
                                            <td
                                                className={`px-4 py-3 text-right font-medium ${amountTone(row.unrealized_gain)}`}
                                            >
                                                {signedMoney(
                                                    row.unrealized_gain,
                                                    selected_position.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {row.source_name ?? "—"}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : null}

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            {t("show.positions.title")}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {positions_as_of_date
                                ? t("show.positions.description", {
                                      date: positions_as_of_date,
                                  })
                                : t("show.positions.noData")}
                        </p>
                    </div>
                    {latest_positions.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            {t("show.positions.importPrompt")}
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-2xl border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                            {t("show.positions.instrument")}
                                        </th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                            {t("show.positions.assetClass")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positions.share")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t(
                                                "show.positions.acquisitionCost",
                                            )}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positions.valuation")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positions.unrealizedGain")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.positions.periodChange")}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white">
                                    {latest_positions.map((position) => (
                                        <tr
                                            key={position.position_key}
                                            className={
                                                selected_position_key ===
                                                position.position_key
                                                    ? "bg-indigo-50/70"
                                                    : "hover:bg-slate-50"
                                            }
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`${route(
                                                        "securities.show",
                                                        {
                                                            account: account.id,
                                                            period: selected_period,
                                                            position:
                                                                position.position_key,
                                                        },
                                                    )}#position-detail`}
                                                    className="font-medium text-indigo-700 hover:text-indigo-900"
                                                >
                                                    {position.instrument_name}
                                                    <span className="ml-2 text-xs font-normal text-indigo-500">
                                                        {t(
                                                            "show.positions.history",
                                                        )}
                                                    </span>
                                                </Link>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {t(
                                                        "show.positions.codeAndDays",
                                                        {
                                                            code:
                                                                position.instrument_code ??
                                                                t(
                                                                    "show.positions.noCode",
                                                                ),
                                                            count: position.history_count,
                                                        },
                                                    )}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {assetClassLabel(
                                                    position.asset_class,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {position.share_percent === null
                                                    ? "—"
                                                    : `${position.share_percent}%`}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {priceValue(
                                                    position.acquisition_cost,
                                                    position.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {formatMoney(
                                                    position.valuation,
                                                    position.currency,
                                                )}{" "}
                                                {position.currency}
                                            </td>
                                            <td
                                                className={`px-4 py-3 text-right font-medium ${amountTone(position.unrealized_gain)}`}
                                            >
                                                {signedMoney(
                                                    position.unrealized_gain,
                                                    position.currency,
                                                )}
                                            </td>
                                            <td
                                                className={`px-4 py-3 text-right font-medium ${amountTone(position.change_amount)}`}
                                            >
                                                {signedMoney(
                                                    position.change_amount,
                                                    position.currency,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            {t("show.snapshots.title")}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {t("show.snapshots.description")}
                        </p>
                    </div>
                    {snapshots.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            {t("show.snapshots.empty")}
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-2xl border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                            {t("show.snapshots.date")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.snapshots.valuation")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.snapshots.change")}
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                            {t("show.snapshots.positions")}
                                        </th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                            {t("show.snapshots.source")}
                                        </th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                            {t("show.snapshots.import")}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white">
                                    {snapshots.map((snapshot) => (
                                        <tr key={snapshot.id}>
                                            <td className="px-4 py-3 text-slate-700">
                                                {snapshot.date}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {formatMoney(
                                                    snapshot.valuation,
                                                    account.currency,
                                                )}{" "}
                                                {account.currency}
                                            </td>
                                            <td
                                                className={`px-4 py-3 text-right font-medium ${amountTone(snapshot.change_amount)}`}
                                            >
                                                {signedMoney(
                                                    snapshot.change_amount,
                                                    account.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {t(
                                                    "show.snapshots.positionCount",
                                                    {
                                                        count: snapshot.position_count,
                                                    },
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {snapshot.source_name ?? "—"}
                                            </td>
                                            <td className="px-4 py-3">
                                                {snapshot.import_id === null ? (
                                                    <span className="text-slate-400">
                                                        {t(
                                                            "show.snapshots.manual",
                                                        )}
                                                    </span>
                                                ) : (
                                                    <Link
                                                        href={route(
                                                            "imports.show",
                                                            snapshot.import_id,
                                                        )}
                                                        className="font-medium text-indigo-700 hover:text-indigo-900"
                                                    >
                                                        {t(
                                                            "show.snapshots.viewImport",
                                                            {
                                                                id: snapshot.import_id,
                                                            },
                                                        )}
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AppPage>
    );
}
