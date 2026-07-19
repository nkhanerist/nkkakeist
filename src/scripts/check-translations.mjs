import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';

const namespaces = [
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
];
const availableKeys = new Map();

const flattenKeys = (value, prefix = '') =>
    Object.entries(value).flatMap(([key, child]) => {
        const path = prefix ? `${prefix}.${key}` : key;

        return typeof child === 'object' && child !== null
            ? [path, ...flattenKeys(child, path)]
            : [path];
    });

for (const namespace of namespaces) {
    const load = async (locale) =>
        JSON.parse(
            await readFile(
                resolve('resources/js/locales', locale, `${namespace}.json`),
                'utf8',
            ),
        );
    const [ja, en] = await Promise.all([load('ja'), load('en')]);
    const jaKeys = flattenKeys(ja).sort();
    const enKeys = flattenKeys(en).sort();
    availableKeys.set(namespace, new Set(jaKeys));

    if (JSON.stringify(jaKeys) !== JSON.stringify(enKeys)) {
        throw new Error(
            `${namespace}: Japanese and English translation keys do not match.`,
        );
    }

    const englishText = JSON.stringify(en);

    if (/[ぁ-んァ-ヶ一-龠]/u.test(englishText)) {
        throw new Error(
            `${namespace}: English resources contain Japanese characters.`,
        );
    }
}

const migratedSources = [
    ['accounts', 'resources/js/Pages/Accounts/Create.tsx'],
    ['accounts', 'resources/js/Pages/Accounts/Edit.tsx'],
    ['accounts', 'resources/js/Pages/Accounts/Index.tsx'],
    ['accounts', 'resources/js/Pages/Accounts/Partials/AccountForm.tsx'],
    ['accounts', 'resources/js/Pages/Accounts/Reconciliation/Index.tsx'],
    ['accounts', 'resources/js/Pages/Accounts/Snapshots/Index.tsx'],
    ['accounts', 'resources/js/utils/accountType.ts'],
    ['transactions', 'resources/js/Pages/Transactions/Create.tsx'],
    ['transactions', 'resources/js/Pages/Transactions/Edit.tsx'],
    ['transactions', 'resources/js/Pages/Transactions/Index.tsx'],
    ['transactions', 'resources/js/Pages/Transactions/Show.tsx'],
    [
        'transactions',
        'resources/js/Pages/Transactions/Partials/TransactionForm.tsx',
    ],
    ['transactions', 'resources/js/Pages/Transactions/CategoryReview.tsx'],
    [
        'transactions',
        'resources/js/Pages/Transactions/Partials/CategoryReviewCard.tsx',
    ],
    [
        'classificationRules',
        'resources/js/Pages/ClassificationRules/Create.tsx',
    ],
    ['classificationRules', 'resources/js/Pages/ClassificationRules/Edit.tsx'],
    ['classificationRules', 'resources/js/Pages/ClassificationRules/Index.tsx'],
    [
        'classificationRules',
        'resources/js/Pages/ClassificationRules/Partials/ClassificationRuleForm.tsx',
    ],
    ['categories', 'resources/js/Pages/Categories/Create.tsx'],
    ['categories', 'resources/js/Pages/Categories/Edit.tsx'],
    ['categories', 'resources/js/Pages/Categories/Index.tsx'],
    ['categories', 'resources/js/Pages/Categories/Partials/CategoryForm.tsx'],
    [
        'categories',
        'resources/js/Pages/Categories/Partials/SubcategoryManager.tsx',
    ],
    ['imports', 'resources/js/Pages/Imports/Create.tsx'],
    ['imports', 'resources/js/Pages/Imports/Index.tsx'],
    ['imports', 'resources/js/Pages/Imports/Show.tsx'],
    ['imports', 'resources/js/Components/Imports/BalanceSnapshotPreview.tsx'],
    ['imports', 'resources/js/Components/Imports/AssetHistoryPreview.tsx'],
    ['profile', 'resources/js/Pages/Profile/Edit.tsx'],
    [
        'profile',
        'resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.tsx',
    ],
    ['profile', 'resources/js/Pages/Profile/Partials/UpdatePasswordForm.tsx'],
    ['profile', 'resources/js/Pages/Profile/Partials/DeleteUserForm.tsx'],
    ['dashboard', 'resources/js/Pages/Dashboard/Index.tsx'],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/DashboardPeriodSelector.tsx',
    ],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/NetWorthTrendSection.tsx',
    ],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/AssetHistoryTrendSection.tsx',
    ],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/DailySnapshotStatusCard.tsx',
    ],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/WeeklyImportStatusCard.tsx',
    ],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/MonthlyReportSection.tsx',
    ],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/MonthlyCategoryExpenseFactors.tsx',
    ],
    [
        'dashboard',
        'resources/js/Pages/Dashboard/Partials/MonthlyClosingPanel.tsx',
    ],
    ['securities', 'resources/js/Pages/Securities/Index.tsx'],
    ['securities', 'resources/js/Pages/Securities/Show.tsx'],
    ['securities', 'resources/js/Components/Charts/LineTrendChart.tsx'],
    ['securities', 'resources/js/Components/Charts/Sparkline.tsx'],
    ['securities', 'resources/js/Components/Charts/StackedAreaTrendChart.tsx'],
    [
        'securities',
        'resources/js/Components/Charts/ValueComparisonAreaChart.tsx',
    ],
];

for (const [namespace, source] of migratedSources) {
    const sourceText = await readFile(resolve(source), 'utf8');

    if (/[ぁ-んァ-ヶ一-龠]/u.test(sourceText)) {
        throw new Error(
            `${source}: migrated source contains a hard-coded Japanese string.`,
        );
    }

    const usedKeys = [...sourceText.matchAll(/\bt\(\s*['"]([^'"]+)['"]/gu)].map(
        (match) => match[1],
    );
    const namespaceKeys = availableKeys.get(namespace);

    for (const key of usedKeys) {
        if (!namespaceKeys?.has(key)) {
            throw new Error(`${source}: ${namespace}.${key} is not defined.`);
        }
    }
}

console.log('Translation resources are consistent.');
