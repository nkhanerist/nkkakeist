import ApplicationLogo from '@/Components/ApplicationLogo';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';
import { useTranslation } from 'react-i18next';

export default function Guest({ children }: PropsWithChildren) {
    const { t } = useTranslation('common');
    const { t: tAuth } = useTranslation('auth');

    return (
        <div className="min-h-screen bg-slate-950 text-slate-900">
            <div className="grid min-h-screen lg:grid-cols-[minmax(0,1fr)_minmax(32rem,0.78fr)]">
                <section className="relative hidden overflow-hidden px-12 py-10 text-white lg:flex lg:flex-col xl:px-20 xl:py-14">
                    <div
                        className="absolute inset-0 bg-[radial-gradient(circle_at_72%_25%,rgba(16,185,129,0.17),transparent_32%),radial-gradient(circle_at_14%_88%,rgba(59,130,246,0.12),transparent_34%)]"
                        aria-hidden="true"
                    />
                    <div
                        className="absolute inset-0 opacity-[0.045] [background-image:linear-gradient(rgba(255,255,255,0.9)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.9)_1px,transparent_1px)] [background-size:64px_64px]"
                        aria-hidden="true"
                    />

                    <Link
                        href="/"
                        className="relative z-10 flex w-fit items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300 focus-visible:ring-offset-4 focus-visible:ring-offset-slate-950"
                    >
                        <ApplicationLogo className="h-11 w-11 text-slate-100" />
                        <div>
                            <p className="text-base font-semibold tracking-wide">
                                {t('app.name')}
                            </p>
                            <p className="text-xs text-slate-400">
                                {t('app.tagline')}
                            </p>
                        </div>
                    </Link>

                    <div className="relative z-10 my-auto max-w-xl py-16">
                        <p className="mb-6 text-xs font-semibold uppercase tracking-[0.28em] text-emerald-300">
                            {tAuth('guest.eyebrow')}
                        </p>
                        <h2 className="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
                            {tAuth('guest.headline')}
                        </h2>
                        <p className="mt-6 max-w-lg text-base leading-8 text-slate-300">
                            {tAuth('guest.description')}
                        </p>

                        <div className="mt-10 grid max-w-lg grid-cols-3 gap-3">
                            {[
                                ['guest.metrics.accounts', '01'],
                                ['guest.metrics.transactions', '02'],
                                ['guest.metrics.assets', '03'],
                            ].map(([label, number]) => (
                                <div
                                    key={label}
                                    className="rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-4 backdrop-blur-sm"
                                >
                                    <span className="text-xs font-semibold text-emerald-300">
                                        {number}
                                    </span>
                                    <p className="mt-2 text-sm font-medium text-slate-200">
                                        {tAuth(label)}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>

                    <p className="relative z-10 text-xs leading-5 text-slate-500">
                        {tAuth('guest.footer')}
                    </p>
                </section>

                <main className="relative flex min-h-screen flex-col bg-slate-50">
                    <div className="absolute right-5 top-5 z-10 sm:right-8 sm:top-8">
                        <LocaleSwitcher />
                    </div>

                    <div className="flex items-center px-5 pb-4 pt-5 lg:hidden">
                        <Link
                            href="/"
                            className="flex items-center gap-2.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500"
                        >
                            <ApplicationLogo className="h-9 w-9 text-slate-900" />
                            <span className="text-sm font-semibold text-slate-900">
                                {t('app.name')}
                            </span>
                        </Link>
                    </div>

                    <div className="flex flex-1 items-center justify-center px-5 py-8 sm:px-10 lg:px-14 xl:px-20">
                        <div className="w-full max-w-md rounded-3xl border border-slate-200/80 bg-white p-6 shadow-[0_24px_80px_-32px_rgba(15,23,42,0.28)] sm:p-8">
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
