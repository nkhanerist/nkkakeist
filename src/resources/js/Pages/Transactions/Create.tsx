import AppPage from '@/Components/AppPage';
import {
    TransactionAccountOption,
    TransactionCategoryOption,
    TransactionSubcategoryOption,
    TransactionTypeOption,
} from '@/types/transaction';
import TransactionForm from './Partials/TransactionForm';
import { useTranslation } from 'react-i18next';

type CreateProps = {
    typeOptions: TransactionTypeOption[];
    accountOptions: TransactionAccountOption[];
    categoryOptions: TransactionCategoryOption[];
    subcategoryOptions: TransactionSubcategoryOption[];
};

export default function Create({
    typeOptions,
    accountOptions,
    categoryOptions,
    subcategoryOptions,
}: CreateProps) {
    const { t } = useTranslation('transactions');

    return (
        <AppPage
            title={t('create.title')}
            description={t('create.description')}
        >
            <TransactionForm
                submitLabel={t('create.submit')}
                submitRoute={route('transactions.store')}
                method="post"
                typeOptions={typeOptions}
                accountOptions={accountOptions}
                categoryOptions={categoryOptions}
                subcategoryOptions={subcategoryOptions}
            />
        </AppPage>
    );
}
