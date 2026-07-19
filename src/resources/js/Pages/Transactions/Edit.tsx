import AppPage from '@/Components/AppPage';
import {
    EditableTransaction,
    TransactionAccountOption,
    TransactionCategoryOption,
    TransactionSubcategoryOption,
    TransactionTypeOption,
} from '@/types/transaction';
import TransactionForm from './Partials/TransactionForm';
import { useTranslation } from 'react-i18next';

type EditProps = {
    transaction: EditableTransaction;
    typeOptions: TransactionTypeOption[];
    accountOptions: TransactionAccountOption[];
    categoryOptions: TransactionCategoryOption[];
    subcategoryOptions: TransactionSubcategoryOption[];
};

export default function Edit({
    transaction,
    typeOptions,
    accountOptions,
    categoryOptions,
    subcategoryOptions,
}: EditProps) {
    const { t } = useTranslation('transactions');

    return (
        <AppPage title={t('edit.title')} description={t('edit.description')}>
            <TransactionForm
                transaction={transaction}
                submitLabel={t('edit.submit')}
                submitRoute={route('transactions.update', transaction.id)}
                method="put"
                typeOptions={typeOptions}
                accountOptions={accountOptions}
                categoryOptions={categoryOptions}
                subcategoryOptions={subcategoryOptions}
            />
        </AppPage>
    );
}
