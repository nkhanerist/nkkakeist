import AppPage from '@/Components/AppPage';
import {
    TransactionAccountOption,
    TransactionCategoryOption,
    TransactionSubcategoryOption,
    TransactionTypeOption,
} from '@/types/transaction';
import TransactionForm from './Partials/TransactionForm';

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
    return (
        <AppPage
            title="Create Transaction"
            description="新しい取引を登録します。"
        >
            <TransactionForm
                submitLabel="登録する"
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
