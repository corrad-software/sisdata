import { Breadcrumb, type BreadcrumbItem } from './Breadcrumb';

export function PageHeader({ title, breadcrumbs }: { title: string; breadcrumbs: BreadcrumbItem[] }) {
    return (
        <div className="bg-bg-white border-b border-otl-divider px-4 sm:px-6 py-4 space-y-3">
            <Breadcrumb items={breadcrumbs} />
            <h2 className="font-heading font-semibold text-heading-2xs text-txt-black-900 leading-tight">{title}</h2>
        </div>
    );
}
