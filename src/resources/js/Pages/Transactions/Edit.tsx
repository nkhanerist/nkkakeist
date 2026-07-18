import AppPage from '@/Components/AppPage';
import {
    EditableTransaction,
    TransactionAccountOption,
    TransactionCategoryOption,
    TransactionSubcategoryOption,
    TransactionTypeOption,
} from '@/types/transaction';
import TransactionForm from './Partials/TransactionForm';

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
    return (
        <AppPage
            title="Edit Transaction"
            description="取引内容を更新します。"
        >
            <TransactionForm
                transaction={transaction}
                submitLabel="更新する"
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
