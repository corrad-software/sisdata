import { PageContainer } from '@/components/PageContainer';
import { PageHeader } from '@/components/PageHeader';
import { LinkButton } from '@/components/ui/Button';

export default function AdminDashboardPage() {
    return (
        <>
            <PageHeader title="Admin" breadcrumbs={[{ label: 'Admin' }]} />

            <PageContainer>
                <div className="bg-bg-white overflow-hidden shadow-card sm:rounded-lg border border-otl-gray-200">
                    <div className="p-6">
                        <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900 mb-2">
                            User Management
                        </h3>
                        <p className="text-body-sm text-txt-black-500 mb-4">
                            Search, create, edit, and remove users from the sidebar admin area.
                        </p>
                        <LinkButton href="/admin/users">Manage Users</LinkButton>
                    </div>
                </div>
            </PageContainer>
        </>
    );
}
