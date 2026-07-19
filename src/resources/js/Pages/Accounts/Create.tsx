import AppPage from '@/Components/AppPage';
import { AccountTypeOption } from '@/types/account';
import { useTranslation } from 'react-i18next';
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
    const { t } = useTranslation('accounts');

    return (
        <AppPage
            title={t('create.title')}
            description={t('create.description')}
        >
            <AccountForm
                submitLabel={t('create.submit')}
                typeOptions={typeOptions}
                balanceRoleOptions={balanceRoleOptions}
                balanceMethodOptions={balanceMethodOptions}
                submitRoute={route('accounts.store')}
                method="post"
            />
        </AppPage>
    );
}
