import AppPage from '@/Components/AppPage';
import { AccountTypeOption } from '@/types/account';
import AccountForm from './Partials/AccountForm';

type CreateProps = {
    typeOptions: AccountTypeOption[];
    balanceRoleOptions: AccountTypeOption[];
    balanceMethodOptions: AccountTypeOption[];
};

export default function Create({
    typeOptions,
    balanceRoleOptions,
    balanceMethodOptions,
}: CreateProps) {
    return (
        <AppPage
            title="Create Account"
            description="新しい口座を作成します。"
        >
            <AccountForm
                submitLabel="作成する"
                typeOptions={typeOptions}
                balanceRoleOptions={balanceRoleOptions}
                balanceMethodOptions={balanceMethodOptions}
                submitRoute={route('accounts.store')}
                method="post"
            />
        </AppPage>
    );
}
