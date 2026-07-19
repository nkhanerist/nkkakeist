import enAccounts from '@/locales/en/accounts.json';
import enAuth from '@/locales/en/auth.json';
import enCategories from '@/locales/en/categories.json';
import enClassificationRules from '@/locales/en/classificationRules.json';
import enCommon from '@/locales/en/common.json';
import enDashboard from '@/locales/en/dashboard.json';
import enImports from '@/locales/en/imports.json';
import enProfile from '@/locales/en/profile.json';
import enSecurities from '@/locales/en/securities.json';
import enTransactions from '@/locales/en/transactions.json';
import jaAccounts from '@/locales/ja/accounts.json';
import jaAuth from '@/locales/ja/auth.json';
import jaCategories from '@/locales/ja/categories.json';
import jaClassificationRules from '@/locales/ja/classificationRules.json';
import jaCommon from '@/locales/ja/common.json';
import jaDashboard from '@/locales/ja/dashboard.json';
import jaImports from '@/locales/ja/imports.json';
import jaProfile from '@/locales/ja/profile.json';
import jaSecurities from '@/locales/ja/securities.json';
import jaTransactions from '@/locales/ja/transactions.json';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

export type AppLocale = 'ja' | 'en';

void i18n.use(initReactI18next).init({
    resources: {
        ja: {
            common: jaCommon,
            auth: jaAuth,
            accounts: jaAccounts,
            categories: jaCategories,
            classificationRules: jaClassificationRules,
            dashboard: jaDashboard,
            imports: jaImports,
            profile: jaProfile,
            securities: jaSecurities,
            transactions: jaTransactions,
        },
        en: {
            common: enCommon,
            auth: enAuth,
            accounts: enAccounts,
            categories: enCategories,
            classificationRules: enClassificationRules,
            dashboard: enDashboard,
            imports: enImports,
            profile: enProfile,
            securities: enSecurities,
            transactions: enTransactions,
        },
    },
    lng: 'ja',
    fallbackLng: 'en',
    supportedLngs: ['ja', 'en'],
    defaultNS: 'common',
    ns: [
        'common',
        'auth',
        'accounts',
        'categories',
        'classificationRules',
        'dashboard',
        'imports',
        'profile',
        'securities',
        'transactions',
    ],
    interpolation: {
        escapeValue: false,
    },
    react: {
        useSuspense: false,
    },
});

export const applyLocale = async (locale: AppLocale): Promise<void> => {
    document.documentElement.lang = locale;

    if (i18n.resolvedLanguage !== locale) {
        await i18n.changeLanguage(locale);
    }
};

export default i18n;
