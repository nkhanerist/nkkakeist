import AppPage from '@/Components/AppPage';
import { AccountTypeOption, EditableAccount } from '@/types/account';
import { useTranslation } from 'react-i18next';
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
    const { t } = useTranslation('accounts');

    return (
        <AppPage
            title={t('edit.title')}
            description={t('edit.description')}
        >
            <AccountForm
                account={account}
                submitLabel={t('edit.submit')}
                typeOptions={typeOptions}
                balanceRoleOptions={balanceRoleOptions}
                balanceMethodOptions={balanceMethodOptions}
                submitRoute={route('accounts.update', account.id)}
                method="put"
            />
        </AppPage>
    );
}
