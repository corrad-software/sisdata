'use client';

import { useState } from 'react';
import { PageContainer } from '@/components/PageContainer';
import { Table } from '@/components/ui/Table';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Pagination } from '@/components/ui/Pagination';
import { Breadcrumb } from '@/components/Breadcrumb';
import { SummaryList } from '@/components/ui/SummaryList';
import { TaskList } from '@/components/ui/TaskList';
import { Tooltip } from '@/components/ui/Tooltip';
import { Dropdown } from '@/components/ui/Dropdown';

const BASIC_ROWS: Array<[string, string, boolean]> = [
    ['Ahmad Ali', 'ahmad@example.com', true],
    ['Siti Aminah', 'siti@example.com', false],
];

interface Row {
    name: string;
    email: string;
    role: string;
    active: boolean;
}

const KS_ROWS: Row[] = [
    { name: 'Ahmad Ali', email: 'ahmad@example.com', role: 'Admin', active: true },
    { name: 'Siti Aminah', email: 'siti@example.com', role: 'User', active: true },
    { name: 'Kumar Raj', email: 'kumar@example.com', role: 'User', active: false },
];

interface EditableRow {
    name: string;
    email: string;
    role: string;
}

type TicketStatus = 'open' | 'pending' | 'closed' | 'archived';

interface Ticket {
    id: number;
    title: string;
    priority: string;
    status: TicketStatus;
}

const TICKETS: Ticket[] = [
    { id: 1, title: 'Login page not loading', priority: 'High', status: 'open' },
    { id: 2, title: 'Fix broken pagination', priority: 'High', status: 'open' },
    { id: 3, title: 'Typo in email template', priority: 'Low', status: 'open' },
    { id: 4, title: 'Update user profile picture', priority: 'Medium', status: 'pending' },
    { id: 5, title: 'Slow dashboard load time', priority: 'High', status: 'pending' },
    { id: 6, title: 'Export report to CSV', priority: 'Low', status: 'closed' },
    { id: 7, title: 'Add dark mode toggle', priority: 'Medium', status: 'closed' },
    { id: 8, title: 'Rename workspace', priority: 'Low', status: 'closed' },
];

const ALL_PRIORITIES = ['High', 'Medium', 'Low'];

const STATUS_BADGE_CLASS: Record<TicketStatus, string> = {
    open: 'bg-bg-primary-50 text-txt-primary',
    pending: 'bg-bg-warning-50 text-txt-warning',
    closed: 'bg-bg-success-50 text-txt-success',
    archived: 'bg-bg-washed text-txt-black-500',
};

const STATUS_LABEL: Record<TicketStatus, string> = {
    open: 'Open',
    pending: 'Pending',
    closed: 'Closed',
    archived: 'Archived',
};

function TableWithActionsSection() {
    const [page, setPage] = useState(1);
    const perPage = 3;
    const total = 30;

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Table with Actions</h3>
            <p className="text-body-sm text-txt-black-500">
                The exact search + row-actions + pagination pattern used by the User Management table.
            </p>

            <div className="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-end">
                <input
                    type="search"
                    placeholder="Search by name or email..."
                    className="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm w-full sm:w-64"
                />
            </div>

            <Table
                head={
                    <>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th className="text-right">Actions</th>
                    </>
                }
            >
                {KS_ROWS.map((row) => (
                    <tr key={row.email}>
                        <td className="font-medium">{row.name}</td>
                        <td>{row.email}</td>
                        <td>{row.role}</td>
                        <td>{row.active ? <Badge variant="success">Active</Badge> : <Badge variant="neutral">Inactive</Badge>}</td>
                        <td className="text-right whitespace-nowrap">
                            <button type="button" className="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                Edit
                            </button>
                            <button type="button" className="ms-3 text-danger-600 hover:text-danger-700 text-body-sm font-medium">
                                Delete
                            </button>
                        </td>
                    </tr>
                ))}
            </Table>

            <Pagination
                currentPage={page}
                lastPage={Math.ceil(total / perPage)}
                total={total}
                firstItem={(page - 1) * perPage + 1}
                lastItem={Math.min(page * perPage, total)}
                onPageChange={setPage}
            />
        </section>
    );
}

function BulkSelectionSection() {
    const [selected, setSelected] = useState<number[]>([]);
    const allSelected = selected.length > 0 && selected.length === KS_ROWS.length;

    function toggleAll() {
        setSelected(allSelected ? [] : KS_ROWS.map((_, i) => i));
    }

    function toggleRow(index: number) {
        setSelected((prev) => (prev.includes(index) ? prev.filter((i) => i !== index) : [...prev, index]));
    }

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Bulk Selection</h3>
            <p className="text-body-sm text-txt-black-500">
                Row checkboxes with a select-all header checkbox; a bulk action bar appears once at least one row is
                selected.
            </p>

            {selected.length > 0 && (
                <div className="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between rounded-md bg-bg-primary-50 border border-otl-primary-200 px-4 py-3">
                    <p className="text-body-sm text-txt-black-900">{selected.length} selected</p>
                    <div className="flex gap-2">
                        <Button variant="secondary" size="sm" onClick={() => setSelected([])}>
                            Clear
                        </Button>
                        <Button variant="danger" size="sm">
                            Delete Selected ({selected.length})
                        </Button>
                    </div>
                </div>
            )}

            <Table
                head={
                    <>
                        <th className="w-10">
                            <input
                                type="checkbox"
                                onChange={toggleAll}
                                checked={allSelected}
                                className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                aria-label="Select all"
                            />
                        </th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                    </>
                }
            >
                {KS_ROWS.map((row, i) => (
                    <tr key={row.email} className={selected.includes(i) ? 'bg-bg-primary-50' : ''}>
                        <td>
                            <input
                                type="checkbox"
                                onChange={() => toggleRow(i)}
                                checked={selected.includes(i)}
                                className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                aria-label="Select row"
                            />
                        </td>
                        <td className="font-medium">{row.name}</td>
                        <td>{row.email}</td>
                        <td>{row.role}</td>
                        <td>{row.active ? <Badge variant="success">Active</Badge> : <Badge variant="neutral">Inactive</Badge>}</td>
                    </tr>
                ))}
            </Table>
        </section>
    );
}

function EditableTableSection() {
    const [rows, setRows] = useState<EditableRow[]>([{ name: '', email: '', role: 'user' }]);

    function addRow() {
        setRows((prev) => [...prev, { name: '', email: '', role: 'user' }]);
    }

    function removeRow(index: number) {
        setRows((prev) => prev.filter((_, i) => i !== index));
    }

    function update(index: number, field: keyof EditableRow, value: string) {
        setRows((prev) => prev.map((row, i) => (i === index ? { ...row, [field]: value } : row)));
    }

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">
                Editable Table (Row-by-Row Entry)
            </h3>
            <p className="text-body-sm text-txt-black-500">
                Add several rows of data inline, then submit them all at once — useful for bulk data entry.
            </p>

            <Table
                head={
                    <>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th className="w-16" />
                    </>
                }
            >
                {rows.map((row, index) => (
                    <tr key={index}>
                        <td>
                            <input
                                type="text"
                                value={row.name}
                                onChange={(e) => update(index, 'name', e.target.value)}
                                placeholder="Name"
                                className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                            />
                        </td>
                        <td>
                            <input
                                type="email"
                                value={row.email}
                                onChange={(e) => update(index, 'email', e.target.value)}
                                placeholder="Email"
                                className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                            />
                        </td>
                        <td>
                            <select
                                value={row.role}
                                onChange={(e) => update(index, 'role', e.target.value)}
                                className="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                            >
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </td>
                        <td className="text-right whitespace-nowrap">
                            {rows.length > 1 && (
                                <button
                                    type="button"
                                    onClick={() => removeRow(index)}
                                    className="text-danger-600 hover:text-danger-700 text-body-sm font-medium"
                                >
                                    Remove
                                </button>
                            )}
                        </td>
                    </tr>
                ))}
            </Table>

            <div className="flex items-center justify-between">
                <Button variant="secondary" size="sm" type="button" onClick={addRow}>
                    + Add Row
                </Button>
                <Button variant="primary" size="sm" type="button">
                    Save All ({rows.length})
                </Button>
            </div>
        </section>
    );
}

function StatusTabsSection() {
    const [activeStatus, setActiveStatus] = useState<'all' | TicketStatus>('all');
    const [rows, setRows] = useState<Ticket[]>(TICKETS);
    const [priorityFilter, setPriorityFilter] = useState<string[]>(ALL_PRIORITIES);

    const filteredRows = rows.filter(
        (r) => (activeStatus === 'all' || r.status === activeStatus) && priorityFilter.includes(r.priority),
    );

    function countFor(status: 'all' | TicketStatus) {
        return status === 'all' ? rows.length : rows.filter((r) => r.status === status).length;
    }

    function togglePriorityValue(value: string) {
        setPriorityFilter((prev) => (prev.includes(value) ? prev.filter((v) => v !== value) : [...prev, value]));
    }

    function setTicketStatus(id: number, status: TicketStatus) {
        setRows((prev) => prev.map((r) => (r.id === id ? { ...r, status } : r)));
    }

    const isPriorityFiltered = priorityFilter.length < ALL_PRIORITIES.length;

    const tabs: Array<{ key: 'all' | TicketStatus; label: string; countClass: string }> = [
        { key: 'all', label: 'All', countClass: 'bg-bg-washed text-txt-black-500' },
        { key: 'open', label: 'Open', countClass: 'bg-bg-primary-50 text-txt-primary' },
        { key: 'pending', label: 'Pending', countClass: 'bg-bg-warning-50 text-txt-warning' },
        { key: 'closed', label: 'Closed', countClass: 'bg-bg-success-50 text-txt-success' },
        { key: 'archived', label: 'Archived', countClass: 'bg-bg-washed text-txt-black-500' },
    ];

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <div>
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Table with Status Tabs</h3>
                <p className="text-body-sm text-txt-black-500">
                    Each tab filters the table to one status, with a live count badge in the tab title — plus a
                    spreadsheet-style column filter on Priority.
                </p>
            </div>

            <div className="border-b border-otl-divider">
                <nav className="flex flex-wrap gap-6 -mb-px">
                    {tabs.map((tab) => (
                        <button
                            key={tab.key}
                            type="button"
                            onClick={() => setActiveStatus(tab.key)}
                            className={`inline-flex items-center gap-2 whitespace-nowrap border-b-2 py-3 text-body-sm font-medium ${
                                activeStatus === tab.key
                                    ? 'border-primary-600 text-primary-600'
                                    : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'
                            }`}
                        >
                            {tab.label}
                            <span className={`rounded-full px-2 py-0.5 text-body-xs font-medium ${tab.countClass}`}>
                                {countFor(tab.key)}
                            </span>
                        </button>
                    ))}
                </nav>
            </div>

            <Table
                head={
                    <>
                        <th className="w-12">#</th>
                        <th>Title</th>
                        <th>
                            <div className="relative flex items-center gap-1.5">
                                <span>Priority</span>
                                <Dropdown
                                    align="left"
                                    width="w-40"
                                    contentClassName="bg-bg-white p-2 text-start shadow-context-menu"
                                    trigger={
                                        <button
                                            type="button"
                                            className={`rounded p-0.5 ${isPriorityFiltered ? 'text-primary-600' : 'text-txt-black-500 hover:text-txt-black-900'}`}
                                            aria-label="Filter Priority"
                                        >
                                            <svg className="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    fillRule="evenodd"
                                                    clipRule="evenodd"
                                                    d="M3.792 2.938A49.069 49.069 0 0112 2.25c2.797 0 5.54.236 8.209.688a1.857 1.857 0 011.541 1.836v1.044a3 3 0 01-.879 2.121l-6.182 6.182a1.5 1.5 0 00-.439 1.061v2.927a3 3 0 01-1.658 2.684l-1.757.878A.75.75 0 019.75 21v-6.815a1.5 1.5 0 00-.44-1.06L3.13 6.938a3 3 0 01-.879-2.121V3.774a1.857 1.857 0 011.541-1.836z"
                                                />
                                            </svg>
                                        </button>
                                    }
                                >
                                    <div className="flex items-center justify-between px-1 pb-2 text-body-xs font-medium">
                                        <button
                                            type="button"
                                            onClick={() => setPriorityFilter([...ALL_PRIORITIES])}
                                            className="text-primary-600 hover:underline"
                                        >
                                            Select All
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setPriorityFilter([])}
                                            className="text-primary-600 hover:underline"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                    <div className="space-y-1">
                                        {ALL_PRIORITIES.map((value) => (
                                            <label
                                                key={value}
                                                className="flex items-center gap-2 rounded px-1 py-1 font-normal normal-case hover:bg-bg-washed"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={priorityFilter.includes(value)}
                                                    onChange={() => togglePriorityValue(value)}
                                                    className="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                                />
                                                <span className="text-body-xs leading-none text-txt-black-900">{value}</span>
                                            </label>
                                        ))}
                                    </div>
                                </Dropdown>
                            </div>
                        </th>
                        <th>Status</th>
                        <th className="text-right">Actions</th>
                    </>
                }
            >
                {filteredRows.length === 0 ? (
                    <tr>
                        <td colSpan={5} className="py-10 text-center">
                            <svg className="mx-auto h-10 w-10 text-primary-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.24A2.25 2.25 0 0017.15 3.75H6.85a2.25 2.25 0 00-2.09 1.49L2.35 13.177a2.25 2.25 0 00-.1.661z"
                                />
                            </svg>
                            <p className="mt-2 text-body-sm font-medium text-txt-black-900">No tickets here</p>
                            <p className="mt-1 text-body-xs text-txt-black-500">Tickets in this status will show up here.</p>
                        </td>
                    </tr>
                ) : (
                    filteredRows.map((row, index) => (
                        <tr key={row.id}>
                            <td className="text-txt-black-500">{index + 1}</td>
                            <td className="font-medium">{row.title}</td>
                            <td>{row.priority}</td>
                            <td>
                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-body-xs font-medium ${STATUS_BADGE_CLASS[row.status]}`}>
                                    {STATUS_LABEL[row.status]}
                                </span>
                            </td>
                            <td className="text-right whitespace-nowrap">
                                <div className="flex items-center justify-end gap-1">
                                    <Tooltip text="View">
                                        <button
                                            type="button"
                                            className="inline-flex h-6 w-6 items-center justify-center rounded-md text-txt-black-500 hover:bg-bg-washed hover:text-txt-black-900"
                                            aria-label="View"
                                        >
                                            <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                                />
                                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </button>
                                    </Tooltip>

                                    {row.status !== 'closed' ? (
                                        <Tooltip text="Close">
                                            <button
                                                type="button"
                                                onClick={() => setTicketStatus(row.id, 'closed')}
                                                className="inline-flex h-6 w-6 items-center justify-center rounded-md text-danger-600 hover:bg-bg-danger-50"
                                                aria-label="Close"
                                            >
                                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                        </Tooltip>
                                    ) : (
                                        <Tooltip text="Reopen">
                                            <button
                                                type="button"
                                                onClick={() => setTicketStatus(row.id, 'open')}
                                                className="inline-flex h-6 w-6 items-center justify-center rounded-md text-success-600 hover:bg-bg-success-50"
                                                aria-label="Reopen"
                                            >
                                                <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                                    <path
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
                                                    />
                                                </svg>
                                            </button>
                                        </Tooltip>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))
                )}
            </Table>
        </section>
    );
}

function PageNumbersSection() {
    const [page, setPage] = useState(5);
    const perPage = 10;
    const total = 97;
    const lastPage = Math.ceil(total / perPage);

    return (
        <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Page Numbers</h3>
            <p className="text-body-sm text-txt-black-500">
                The compact numbered pagination style, shown standalone with a larger dataset.
            </p>

            <Pagination
                currentPage={page}
                lastPage={lastPage}
                total={total}
                firstItem={(page - 1) * perPage + 1}
                lastItem={Math.min(page * perPage, total)}
                onPageChange={setPage}
            />
        </section>
    );
}

export default function DataDisplayPage() {
    return (
        <PageContainer className="space-y-8">
            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Table</h3>

                <Table
                    head={
                        <>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                        </>
                    }
                >
                    {BASIC_ROWS.map(([name, email, active]) => (
                        <tr key={email}>
                            <td className="font-medium">{name}</td>
                            <td>{email}</td>
                            <td>{active ? <Badge variant="success">Active</Badge> : <Badge variant="neutral">Inactive</Badge>}</td>
                        </tr>
                    ))}
                </Table>
            </section>

            <TableWithActionsSection />
            <BulkSelectionSection />
            <EditableTableSection />
            <StatusTabsSection />

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Breadcrumb</h3>

                <Breadcrumb items={[{ label: 'Kitchen Sink', href: '/admin/kitchen-sink/components/buttons' }, { label: 'Components', href: '/admin/kitchen-sink/components/buttons' }, { label: 'Data Display' }]} />
            </section>

            <PageNumbersSection />

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Summary List</h3>
                <p className="text-body-sm text-txt-black-500">
                    A key/value review pattern — commonly used on a &quot;check your answers&quot; or record-detail
                    page.
                </p>

                <SummaryList
                    className="max-w-2xl"
                    items={[
                        { label: 'Name', value: 'Ahmad Ali', action: { label: 'Change', url: '#' } },
                        { label: 'Email', value: 'ahmad@example.com', action: { label: 'Change', url: '#' } },
                        { label: 'Role', value: 'Admin' },
                        { label: 'Status', value: 'Active' },
                    ]}
                />
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Task List</h3>
                <p className="text-body-sm text-txt-black-500">
                    A numbered multi-step checklist with a status tag per step — useful for onboarding or application
                    flows.
                </p>

                <TaskList
                    className="max-w-2xl"
                    items={[
                        { title: 'Create your account', status: 'completed' },
                        { title: 'Verify your email', status: 'completed' },
                        { title: 'Complete your profile', status: 'in_progress' },
                        { title: 'Add payment details', status: 'not_started' },
                    ]}
                />
            </section>

            <section className="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
                <h3 className="font-heading font-semibold text-heading-2xs text-txt-black-900">Tag & Pill</h3>
                <p className="text-body-sm text-txt-black-500">
                    Badge supports two shapes — pill (rounded-full) for statuses, tag (rounded-md) for
                    categories/labels.
                </p>

                <div className="space-y-3">
                    <div>
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">Pill</p>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="success" shape="pill">
                                Active
                            </Badge>
                            <Badge variant="info" shape="pill">
                                New
                            </Badge>
                            <Badge variant="warning" shape="pill">
                                Pending
                            </Badge>
                        </div>
                    </div>

                    <div>
                        <p className="mb-2 text-body-xs font-medium text-txt-black-500">Tag</p>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="neutral" shape="tag">
                                Government
                            </Badge>
                            <Badge variant="neutral" shape="tag">
                                Healthcare
                            </Badge>
                            <Badge variant="neutral" shape="tag">
                                Education
                            </Badge>
                        </div>
                    </div>
                </div>
            </section>
        </PageContainer>
    );
}
