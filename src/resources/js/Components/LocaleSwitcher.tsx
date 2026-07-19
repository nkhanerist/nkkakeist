import { applyLocale } from '@/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export default function LocaleSwitcher({
    inverted = false,
}: {
    inverted?: boolean;
}) {
    const { locale, supported_locales: supportedLocales } =
        usePage<PageProps>().props;
    const { t } = useTranslation('common');

    const changeLocale = (nextLocale: 'ja' | 'en') => {
        if (nextLocale === locale) {
            return;
        }

        router.put(
            route('locale.update'),
            { locale: nextLocale },
            {
                preserveScroll: true,
                onSuccess: () => {
                    void applyLocale(nextLocale);
                },
            },
        );
    };

    return (
        <div
            className={`inline-flex rounded-full border p-1 ${
                inverted
                    ? 'border-white/15 bg-white/5'
                    : 'border-slate-200 bg-white/80 shadow-sm'
            }`}
            aria-label={t('locale.label')}
        >
            {supportedLocales.map((supportedLocale) => {
                const active = locale === supportedLocale;

                return (
                    <button
                        key={supportedLocale}
                        type="button"
                        onClick={() => changeLocale(supportedLocale)}
                        aria-pressed={active}
                        className={`rounded-full px-3 py-1.5 text-xs font-semibold transition ${
                            active
                                ? inverted
                                    ? 'bg-white text-slate-950 shadow-sm'
                                    : 'bg-slate-900 text-white shadow-sm'
                                : inverted
                                  ? 'text-slate-300 hover:text-white'
                                  : 'text-slate-500 hover:text-slate-900'
                        }`}
                    >
                        {t(`locale.short.${supportedLocale}`)}
                    </button>
                );
            })}
        </div>
    );
}
