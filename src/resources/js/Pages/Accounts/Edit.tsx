import AppPage from '@/Components/AppPage';
import { AccountTypeOption, EditableAccount } from '@/types/account';
import AccountForm from './Partials/AccountForm';

type EditProps = {
    account: EditableAccount;
    typeOptions: AccountTypeOption[];
    balanceRoleOptions: AccountTypeOption[];
    balanceMethodOptions: AccountTypeOption[];
};

export default function Edit({
    account,
    typeOptions,
    balanceRoleOptions,
    balanceMethodOptions,
}: EditProps) {
    return (
        <AppPage
            title="Edit Account"
            description="既存の口座情報を更新します。"
        >
            <AccountForm
                account={account}
                submitLabel="更新する"
                typeOptions={typeOptions}
                balanceRoleOptions={balanceRoleOptions}
                balanceMethodOptions={balanceMethodOptions}
                submitRoute={route('accounts.update', account.id)}
                method="put"
            />
        </AppPage>
    );
}
