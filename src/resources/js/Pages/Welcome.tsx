import ApplicationLogo from '@/Components/ApplicationLogo';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type WelcomeProps = PageProps<{
    canLogin: boolean;
    canRegister: boolean;
}>;

const ArrowIcon = () => (
    <svg
        viewBox="0 0 20 20"
        fill="none"
        className="h-4 w-4"
        aria-hidden="true"
    >
        <path
            d="M4 10h12m-4-4 4 4-4 4"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
        />
    </svg>
);

export default function Welcome({
    auth,
    canLogin,
    canRegister,
}: WelcomeProps) {
    const { t } = useTranslation('auth');
    const { t: tCommon } = useTranslation('common');

    const destination = auth.user ? route('dashboard') : route('login');

    return (
        <div className="min-h-screen overflow-hidden bg-slate-50 text-slate-950">
            <Head title={t('welcome.pageTitle')} />

            <header className="relative z-20 border-b border-slate-200/80 bg-slate-50/90 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:px-10">
                    <Link
                        href="/"
                        className="flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                    >
                        <ApplicationLogo className="h-10 w-10 text-slate-950" />
                        <div>
                            <p className="text-sm font-semibold tracking-wide text-slate-950 sm:text-base">
                                {tCommon('app.name')}
                            </p>
                            <p className="hidden text-xs text-slate-500 sm:block">
                                {tCommon('app.tagline')}
                            </p>
                        </div>
                    </Link>

                    <div className="flex items-center gap-2 sm:gap-4">
                        <LocaleSwitcher />
                        {canLogin && (
                            <Link
                                href={destination}
                                className="hidden rounded-full px-4 py-2 text-sm font-semibold text-slate-600 transition hover:bg-white hover:text-slate-950 sm:block"
                            >
                                {auth.user
                                    ? tCommon('navigation.dashboard')
                                    : t('welcome.login')}
                            </Link>
                        )}
                    </div>
                </div>
            </header>

            <main>
                <section className="relative">
                    <div
                        className="absolute inset-x-0 top-0 -z-0 h-[46rem] bg-[radial-gradient(circle_at_78%_16%,rgba(16,185,129,0.13),transparent_26%),radial-gradient(circle_at_10%_32%,rgba(59,130,246,0.08),transparent_30%)]"
                        aria-hidden="true"
                    />
                    <div className="relative z-10 mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:px-10 lg:py-28">
                        <div className="max-w-2xl">
                            <div className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                                {t('welcome.eyebrow')}
                            </div>
                            <h1 className="mt-7 text-4xl font-semibold leading-[1.12] tracking-[-0.035em] text-slate-950 sm:text-5xl lg:text-6xl">
                                {t('welcome.headline.before')}
                                <span className="block text-emerald-700">
                                    {t('welcome.headline.accent')}
                                </span>
                            </h1>
                            <p className="mt-7 max-w-xl text-base leading-8 text-slate-600 sm:text-lg">
                                {t('welcome.description')}
                            </p>

                            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                {canLogin && (
                                    <Link
                                        href={destination}
                                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-950 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/10 transition hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                                    >
                                        {auth.user
                                            ? t('welcome.openDashboard')
                                            : t('welcome.start')}
                                        <ArrowIcon />
                                    </Link>
                                )}
                                {!auth.user && canRegister && (
                                    <Link
                                        href={route('register')}
                                        className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                                    >
                                        {t('welcome.register')}
                                    </Link>
                                )}
                            </div>

                            <div className="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-500">
                                {['records', 'review', 'history'].map((item) => (
                                    <span key={item} className="flex items-center gap-2">
                                        <svg
                                            className="h-4 w-4 text-emerald-600"
                                            viewBox="0 0 20 20"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <path
                                                d="m5 10 3 3 7-7"
                                                stroke="currentColor"
                                                strokeWidth="2"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                            />
                                        </svg>
                                        {t(`welcome.points.${item}`)}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="relative mx-auto w-full max-w-2xl lg:mx-0">
                            <div
                                className="absolute -inset-6 -z-10 rounded-[2.5rem] bg-gradient-to-br from-emerald-200/45 via-sky-100/40 to-transparent blur-2xl"
                                aria-hidden="true"
                            />
                            <div className="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-[0_30px_90px_-32px_rgba(15,23,42,0.35)]">
                                <div className="flex items-center justify-between border-b border-slate-100 px-5 py-4 sm:px-7">
                                    <div>
                                        <p className="text-xs font-medium text-slate-400">
                                            {t('welcome.preview.period')}
                                        </p>
                                        <p className="mt-0.5 text-sm font-semibold text-slate-800">
                                            {t('welcome.preview.title')}
                                        </p>
                                    </div>
                                    <div className="flex gap-1.5" aria-hidden="true">
                                        <span className="h-2 w-2 rounded-full bg-slate-200" />
                                        <span className="h-2 w-2 rounded-full bg-slate-200" />
                                        <span className="h-2 w-2 rounded-full bg-emerald-400" />
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 sm:p-7">
                                    {[
                                        ['netAssets', '¥12,480,000', '+3.2%'],
                                        ['income', '¥428,000', '+1.8%'],
                                        ['expense', '¥286,400', '−4.6%'],
                                    ].map(([label, value, change], index) => (
                                        <div
                                            key={label}
                                            className={`rounded-2xl border border-slate-100 bg-slate-50 p-4 ${
                                                index === 0 ? 'col-span-2 sm:col-span-1' : ''
                                            }`}
                                        >
                                            <p className="text-xs font-medium text-slate-500">
                                                {t(`welcome.preview.${label}`)}
                                            </p>
                                            <p className="mt-2 text-lg font-semibold tracking-tight text-slate-900 sm:text-xl">
                                                {value}
                                            </p>
                                            <p
                                                className={`mt-1 text-xs font-semibold ${
                                                    label === 'expense'
                                                        ? 'text-sky-600'
                                                        : 'text-emerald-600'
                                                }`}
                                            >
                                                {change}
                                            </p>
                                        </div>
                                    ))}
                                </div>

                                <div className="px-5 pb-5 sm:px-7 sm:pb-7">
                                    <div className="rounded-2xl border border-slate-100 p-4 sm:p-5">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm font-semibold text-slate-800">
                                                {t('welcome.preview.assetTrend')}
                                            </p>
                                            <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                                {t('welcome.preview.daily')}
                                            </span>
                                        </div>
                                        <svg
                                            viewBox="0 0 520 160"
                                            className="mt-5 h-32 w-full"
                                            preserveAspectRatio="none"
                                            role="img"
                                            aria-label={t('welcome.preview.chartLabel')}
                                        >
                                            <defs>
                                                <linearGradient
                                                    id="welcome-chart-fill"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop offset="0%" stopColor="#10B981" stopOpacity="0.28" />
                                                    <stop offset="100%" stopColor="#10B981" stopOpacity="0" />
                                                </linearGradient>
                                            </defs>
                                            {[28, 68, 108, 148].map((y) => (
                                                <line
                                                    key={y}
                                                    x1="0"
                                                    y1={y}
                                                    x2="520"
                                                    y2={y}
                                                    stroke="#E2E8F0"
                                                    strokeWidth="1"
                                                />
                                            ))}
                                            <path
                                                d="M0 129 C48 122 62 110 105 115 C150 120 176 91 218 96 C261 102 289 72 326 78 C373 85 398 48 438 57 C477 65 493 37 520 28 L520 160 L0 160 Z"
                                                fill="url(#welcome-chart-fill)"
                                            />
                                            <path
                                                d="M0 129 C48 122 62 110 105 115 C150 120 176 91 218 96 C261 102 289 72 326 78 C373 85 398 48 438 57 C477 65 493 37 520 28"
                                                fill="none"
                                                stroke="#059669"
                                                strokeWidth="4"
                                                strokeLinecap="round"
                                            />
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="border-y border-slate-200 bg-white">
                    <div className="mx-auto grid max-w-7xl gap-px bg-slate-200 sm:grid-cols-3">
                        {[
                            ['unify', 'M8 4h8M6 8h12M5 12h14M8 16h8'],
                            ['classify', 'M4 6h7M4 10h12M4 14h9M15 5v3m-1.5-1.5h3'],
                            ['trace', 'M4 15V9m4 6V5m4 10v-3m4 3V7'],
                        ].map(([feature, path]) => (
                            <article
                                key={feature}
                                className="bg-white px-7 py-9 sm:px-8 lg:px-10 lg:py-12"
                            >
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-950 text-emerald-300">
                                    <svg
                                        viewBox="0 0 20 20"
                                        fill="none"
                                        className="h-5 w-5"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d={path}
                                            stroke="currentColor"
                                            strokeWidth="1.7"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                        />
                                    </svg>
                                </div>
                                <h2 className="mt-5 text-lg font-semibold text-slate-900">
                                    {t(`welcome.features.${feature}.title`)}
                                </h2>
                                <p className="mt-2 text-sm leading-7 text-slate-600">
                                    {t(`welcome.features.${feature}.description`)}
                                </p>
                            </article>
                        ))}
                    </div>
                </section>
            </main>

            <footer className="bg-slate-950 px-5 py-6 text-center text-xs text-slate-500">
                {t('welcome.footer')}
            </footer>
        </div>
    );
}
