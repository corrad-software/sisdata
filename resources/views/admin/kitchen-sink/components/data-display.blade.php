<x-admin-layout :breadcrumbs="[
    ['label' => __('Admin'), 'url' => route('admin.dashboard')],
    ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
    ['label' => __('Data Display')],
]">
    @include('admin.kitchen-sink._tabs', ['group' => 'components'])

    <div class="py-8 px-4 sm:px-6 lg:px-8 space-y-8">
        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Table') }}</h3>

            <x-table>
                <x-slot name="head">
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Status') }}</th>
                </x-slot>

                @foreach ([['Ahmad Ali', 'ahmad@example.com', true], ['Siti Aminah', 'siti@example.com', false]] as [$name, $email, $active])
                    <tr>
                        <td class="font-medium">{{ $name }}</td>
                        <td>{{ $email }}</td>
                        <td>
                            @if ($active)
                                <x-badge variant="success">{{ __('Active') }}</x-badge>
                            @else
                                <x-badge variant="neutral">{{ __('Inactive') }}</x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </section>

        @php
            $ksRows = collect([
                ['name' => 'Ahmad Ali', 'email' => 'ahmad@example.com', 'role' => 'Admin', 'active' => true],
                ['name' => 'Siti Aminah', 'email' => 'siti@example.com', 'role' => 'User', 'active' => true],
                ['name' => 'Kumar Raj', 'email' => 'kumar@example.com', 'role' => 'User', 'active' => false],
            ]);
            $ksPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $ksRows, 30, 3, 1, ['path' => request()->url()]
            );
        @endphp

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Table with Actions') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('The exact search + row-actions + pagination pattern used by the User Management table.') }}</p>

            <div class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-end">
                <input
                    type="search"
                    placeholder="{{ __('Search by name or email...') }}"
                    class="rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm w-full sm:w-64"
                >
            </div>

            <x-table>
                <x-slot name="head">
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </x-slot>

                @foreach ($ksRows as $row)
                    <tr>
                        <td class="font-medium">{{ $row['name'] }}</td>
                        <td>{{ $row['email'] }}</td>
                        <td>{{ $row['role'] }}</td>
                        <td>
                            @if ($row['active'])
                                <x-badge variant="success">{{ __('Active') }}</x-badge>
                            @else
                                <x-badge variant="neutral">{{ __('Inactive') }}</x-badge>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="text-primary-600 hover:text-primary-700 text-body-sm font-medium">
                                {{ __('Edit') }}
                            </button>
                            <button type="button" class="ms-3 text-danger-600 hover:text-danger-700 text-body-sm font-medium">
                                {{ __('Delete') }}
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-table>

            <div>
                {{ $ksPaginator->onEachSide(1)->links() }}
            </div>
        </section>

        <section
            class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4"
            x-data="{
                rows: [{{ $ksRows->keys()->implode(',') }}],
                selected: [],
                get allSelected() { return this.rows.length > 0 && this.selected.length === this.rows.length },
                toggleAll() { this.selected = this.allSelected ? [] : [...this.rows] },
                toggleRow(id) {
                    this.selected = this.selected.includes(id)
                        ? this.selected.filter(i => i !== id)
                        : [...this.selected, id];
                },
            }"
        >
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Bulk Selection') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Row checkboxes with a select-all header checkbox; a bulk action bar appears once at least one row is selected.') }}</p>

            <div
                x-show="selected.length > 0"
                x-transition
                style="display: none"
                class="flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between rounded-md bg-bg-primary-50 border border-otl-primary-200 px-4 py-3"
            >
                <p class="text-body-sm text-txt-black-900">
                    <span x-text="selected.length"></span> {{ __('selected') }}
                </p>
                <div class="flex gap-2">
                    <x-button variant="secondary" size="sm" x-on:click="selected = []">{{ __('Clear') }}</x-button>
                    <x-button variant="danger" size="sm">
                        {{ __('Delete Selected') }} (<span x-text="selected.length"></span>)
                    </x-button>
                </div>
            </div>

            <x-table>
                <x-slot name="head">
                    <th class="w-10">
                        <input
                            type="checkbox"
                            x-on:click="toggleAll()"
                            :checked="allSelected"
                            class="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                            aria-label="{{ __('Select all') }}"
                        >
                    </th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th>{{ __('Status') }}</th>
                </x-slot>

                @foreach ($ksRows as $i => $row)
                    <tr :class="selected.includes({{ $i }}) ? 'bg-bg-primary-50' : ''">
                        <td>
                            <input
                                type="checkbox"
                                x-on:click="toggleRow({{ $i }})"
                                :checked="selected.includes({{ $i }})"
                                class="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                aria-label="{{ __('Select row') }}"
                            >
                        </td>
                        <td class="font-medium">{{ $row['name'] }}</td>
                        <td>{{ $row['email'] }}</td>
                        <td>{{ $row['role'] }}</td>
                        <td>
                            @if ($row['active'])
                                <x-badge variant="success">{{ __('Active') }}</x-badge>
                            @else
                                <x-badge variant="neutral">{{ __('Inactive') }}</x-badge>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </section>

        <section
            class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4"
            x-data="{
                rows: [{ name: '', email: '', role: 'user' }],
                addRow() { this.rows.push({ name: '', email: '', role: 'user' }) },
                removeRow(index) { this.rows.splice(index, 1) },
            }"
        >
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Editable Table (Row-by-Row Entry)') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('Add several rows of data inline, then submit them all at once — useful for bulk data entry.') }}</p>

            <x-table>
                <x-slot name="head">
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Role') }}</th>
                    <th class="w-16"></th>
                </x-slot>

                <template x-for="(row, index) in rows" :key="index">
                    <tr>
                        <td>
                            <input
                                type="text"
                                x-model="row.name"
                                placeholder="{{ __('Name') }}"
                                class="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                            >
                        </td>
                        <td>
                            <input
                                type="email"
                                x-model="row.email"
                                placeholder="{{ __('Email') }}"
                                class="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                            >
                        </td>
                        <td>
                            <select
                                x-model="row.role"
                                class="block w-full rounded-md border-otl-gray-300 bg-bg-white text-txt-black-900 focus:border-primary-600 focus:ring-fr-primary text-body-sm"
                            >
                                <option value="user">{{ __('User') }}</option>
                                <option value="admin">{{ __('Admin') }}</option>
                            </select>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button
                                type="button"
                                x-on:click="removeRow(index)"
                                x-show="rows.length > 1"
                                class="text-danger-600 hover:text-danger-700 text-body-sm font-medium"
                            >
                                {{ __('Remove') }}
                            </button>
                        </td>
                    </tr>
                </template>
            </x-table>

            <div class="flex items-center justify-between">
                <x-button variant="secondary" size="sm" type="button" x-on:click="addRow()">
                    + {{ __('Add Row') }}
                </x-button>
                <x-button variant="primary" size="sm" type="button">
                    {{ __('Save All') }} (<span x-text="rows.length"></span>)
                </x-button>
            </div>
        </section>

        @php
            $ksTickets = [
                ['id' => 1, 'title' => __('Login page not loading'), 'priority' => __('High'), 'status' => 'open'],
                ['id' => 2, 'title' => __('Fix broken pagination'), 'priority' => __('High'), 'status' => 'open'],
                ['id' => 3, 'title' => __('Typo in email template'), 'priority' => __('Low'), 'status' => 'open'],
                ['id' => 4, 'title' => __('Update user profile picture'), 'priority' => __('Medium'), 'status' => 'pending'],
                ['id' => 5, 'title' => __('Slow dashboard load time'), 'priority' => __('High'), 'status' => 'pending'],
                ['id' => 6, 'title' => __('Export report to CSV'), 'priority' => __('Low'), 'status' => 'closed'],
                ['id' => 7, 'title' => __('Add dark mode toggle'), 'priority' => __('Medium'), 'status' => 'closed'],
                ['id' => 8, 'title' => __('Rename workspace'), 'priority' => __('Low'), 'status' => 'closed'],
            ];
            $ksPriorities = [__('High'), __('Medium'), __('Low')];
        @endphp

        <section
            class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4"
            x-data="{
                activeStatus: 'all',
                rows: @js($ksTickets),
                allPriorities: @js($ksPriorities),
                priorityFilter: @js($ksPriorities),
                openPriorityFilter: false,
                get filteredRows() {
                    return this.rows.filter(r =>
                        (this.activeStatus === 'all' || r.status === this.activeStatus)
                        && this.priorityFilter.includes(r.priority)
                    );
                },
                countFor(status) {
                    return status === 'all' ? this.rows.length : this.rows.filter(r => r.status === status).length;
                },
                statusLabel(status) {
                    return { open: '{{ __('Open') }}', pending: '{{ __('Pending') }}', closed: '{{ __('Closed') }}', archived: '{{ __('Archived') }}' }[status];
                },
                togglePriorityValue(value) {
                    const index = this.priorityFilter.indexOf(value);
                    index === -1 ? this.priorityFilter.push(value) : this.priorityFilter.splice(index, 1);
                },
                selectAllPriority() {
                    this.priorityFilter = [...this.allPriorities];
                },
                clearPriorityFilter() {
                    this.priorityFilter = [];
                },
                isPriorityFiltered() {
                    return this.priorityFilter.length < this.allPriorities.length;
                },
            }"
        >
            <div>
                <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Table with Status Tabs') }}</h3>
                <p class="text-body-sm text-txt-black-500">{{ __('Each tab filters the table to one status, with a live count badge in the tab title — plus a spreadsheet-style column filter on Priority.') }}</p>
            </div>

            <div class="border-b border-otl-divider">
                <nav class="flex flex-wrap gap-6 -mb-px">
                    <button
                        type="button"
                        x-on:click="activeStatus = 'all'"
                        :class="activeStatus === 'all' ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'"
                        class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 py-3 text-body-sm font-medium"
                    >
                        {{ __('All') }}
                        <span class="rounded-full bg-bg-washed px-2 py-0.5 text-body-xs font-medium text-txt-black-500" x-text="countFor('all')"></span>
                    </button>
                    <button
                        type="button"
                        x-on:click="activeStatus = 'open'"
                        :class="activeStatus === 'open' ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'"
                        class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 py-3 text-body-sm font-medium"
                    >
                        {{ __('Open') }}
                        <span class="rounded-full bg-bg-primary-50 px-2 py-0.5 text-body-xs font-medium text-txt-primary" x-text="countFor('open')"></span>
                    </button>
                    <button
                        type="button"
                        x-on:click="activeStatus = 'pending'"
                        :class="activeStatus === 'pending' ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'"
                        class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 py-3 text-body-sm font-medium"
                    >
                        {{ __('Pending') }}
                        <span class="rounded-full bg-bg-warning-50 px-2 py-0.5 text-body-xs font-medium text-txt-warning" x-text="countFor('pending')"></span>
                    </button>
                    <button
                        type="button"
                        x-on:click="activeStatus = 'closed'"
                        :class="activeStatus === 'closed' ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'"
                        class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 py-3 text-body-sm font-medium"
                    >
                        {{ __('Closed') }}
                        <span class="rounded-full bg-bg-success-50 px-2 py-0.5 text-body-xs font-medium text-txt-success" x-text="countFor('closed')"></span>
                    </button>
                    <button
                        type="button"
                        x-on:click="activeStatus = 'archived'"
                        :class="activeStatus === 'archived' ? 'border-primary-600 text-primary-600' : 'border-transparent text-txt-black-500 hover:text-txt-black-900 hover:border-otl-gray-300'"
                        class="inline-flex items-center gap-2 whitespace-nowrap border-b-2 py-3 text-body-sm font-medium"
                    >
                        {{ __('Archived') }}
                        <span class="rounded-full bg-bg-washed px-2 py-0.5 text-body-xs font-medium text-txt-black-500" x-text="countFor('archived')"></span>
                    </button>
                </nav>
            </div>

            <x-table>
                <x-slot name="head">
                    <th class="w-12">{{ __('#') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>
                        <div class="relative flex items-center gap-1.5">
                            <span>{{ __('Priority') }}</span>

                            <button
                                type="button"
                                x-on:click="openPriorityFilter = !openPriorityFilter"
                                :class="isPriorityFiltered() ? 'text-primary-600' : 'text-txt-black-500 hover:text-txt-black-900'"
                                class="rounded p-0.5"
                                aria-label="{{ __('Filter :column', ['column' => __('Priority')]) }}"
                            >
                                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M3.792 2.938A49.069 49.069 0 0112 2.25c2.797 0 5.54.236 8.209.688a1.857 1.857 0 011.541 1.836v1.044a3 3 0 01-.879 2.121l-6.182 6.182a1.5 1.5 0 00-.439 1.061v2.927a3 3 0 01-1.658 2.684l-1.757.878A.75.75 0 019.75 21v-6.815a1.5 1.5 0 00-.44-1.06L3.13 6.938a3 3 0 01-.879-2.121V3.774a1.857 1.857 0 011.541-1.836z" clip-rule="evenodd" />
                                </svg>
                            </button>

                            <div
                                x-show="openPriorityFilter"
                                x-on:click.outside="openPriorityFilter = false"
                                x-transition
                                style="display: none;"
                                class="absolute start-0 top-full z-10 mt-1 w-40 rounded-md border border-otl-gray-200 bg-bg-white p-2 text-start shadow-context-menu"
                            >
                                <div class="flex items-center justify-between px-1 pb-2 text-body-xs font-medium">
                                    <button type="button" x-on:click="selectAllPriority()" class="text-primary-600 hover:underline">{{ __('Select All') }}</button>
                                    <button type="button" x-on:click="clearPriorityFilter()" class="text-primary-600 hover:underline">{{ __('Clear') }}</button>
                                </div>

                                <div class="space-y-1">
                                    <template x-for="value in allPriorities" :key="value">
                                        <label class="flex items-center gap-2 rounded px-1 py-1 font-normal normal-case hover:bg-bg-washed">
                                            <input
                                                type="checkbox"
                                                :checked="priorityFilter.includes(value)"
                                                x-on:change="togglePriorityValue(value)"
                                                class="rounded border-otl-gray-300 text-primary-600 focus:ring-fr-primary"
                                            >
                                            <span class="text-body-xs leading-none text-txt-black-900" x-text="value"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Actions') }}</th>
                </x-slot>

                <template x-for="(row, index) in filteredRows" :key="row.id">
                    <tr>
                        <td class="text-txt-black-500" x-text="index + 1"></td>
                        <td class="font-medium" x-text="row.title"></td>
                        <td x-text="row.priority"></td>
                        <td>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-body-xs font-medium"
                                :class="{
                                    'bg-bg-primary-50 text-txt-primary': row.status === 'open',
                                    'bg-bg-warning-50 text-txt-warning': row.status === 'pending',
                                    'bg-bg-success-50 text-txt-success': row.status === 'closed',
                                    'bg-bg-washed text-txt-black-500': row.status === 'archived',
                                }"
                                x-text="statusLabel(row.status)"
                            ></span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <x-tooltip text="{{ __('View') }}">
                                    <button type="button" class="inline-flex h-6 w-6 items-center justify-center rounded-md text-txt-black-500 hover:bg-bg-washed hover:text-txt-black-900" aria-label="{{ __('View') }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </button>
                                </x-tooltip>

                                <x-tooltip text="{{ __('Close') }}" x-show="row.status !== 'closed'">
                                    <button type="button" x-on:click="row.status = 'closed'" class="inline-flex h-6 w-6 items-center justify-center rounded-md text-danger-600 hover:bg-bg-danger-50" aria-label="{{ __('Close') }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                </x-tooltip>

                                <x-tooltip text="{{ __('Reopen') }}" x-show="row.status === 'closed'">
                                    <button type="button" x-on:click="row.status = 'open'" class="inline-flex h-6 w-6 items-center justify-center rounded-md text-success-600 hover:bg-bg-success-50" aria-label="{{ __('Reopen') }}">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                    </button>
                                </x-tooltip>
                            </div>
                        </td>
                    </tr>
                </template>

                <tr x-show="filteredRows.length === 0">
                    <td colspan="5" class="py-10 text-center">
                        <svg class="mx-auto h-10 w-10 text-primary-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 13.5h3.86a2.25 2.25 0 012.012 1.244l.256.512a2.25 2.25 0 002.013 1.244h3.218a2.25 2.25 0 002.013-1.244l.256-.512a2.25 2.25 0 012.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.24A2.25 2.25 0 0017.15 3.75H6.85a2.25 2.25 0 00-2.09 1.49L2.35 13.177a2.25 2.25 0 00-.1.661z" />
                        </svg>
                        <p class="mt-2 text-body-sm font-medium text-txt-black-900">{{ __('No tickets here') }}</p>
                        <p class="mt-1 text-body-xs text-txt-black-500">{{ __('Tickets in this status will show up here.') }}</p>
                    </td>
                </tr>
            </x-table>
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Breadcrumb') }}</h3>

            <x-breadcrumb :items="[
                ['label' => __('Admin'), 'url' => route('admin.dashboard')],
                ['label' => __('Kitchen Sink'), 'url' => route('admin.kitchen-sink.components.buttons')],
                ['label' => __('Components'), 'url' => route('admin.kitchen-sink.components.buttons')],
                ['label' => __('Data Display')],
            ]" />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Page Numbers') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('The compact numbered pagination style, shown standalone with a larger dataset.') }}</p>

            @php
                $ksBigPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                    range(1, 10), 97, 10, 5, ['path' => request()->url()]
                );
            @endphp

            {{ $ksBigPaginator->onEachSide(1)->links() }}
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Summary List') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A key/value review pattern — commonly used on a "check your answers" or record-detail page.') }}</p>

            <x-summary-list class="max-w-2xl" :items="[
                ['label' => __('Name'), 'value' => 'Ahmad Ali', 'action' => ['label' => __('Change'), 'url' => '#']],
                ['label' => __('Email'), 'value' => 'ahmad@example.com', 'action' => ['label' => __('Change'), 'url' => '#']],
                ['label' => __('Role'), 'value' => __('Admin')],
                ['label' => __('Status'), 'value' => __('Active')],
            ]" />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Task List') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('A numbered multi-step checklist with a status tag per step — useful for onboarding or application flows.') }}</p>

            <x-task-list class="max-w-2xl" :items="[
                ['title' => __('Create your account'), 'status' => 'completed'],
                ['title' => __('Verify your email'), 'status' => 'completed'],
                ['title' => __('Complete your profile'), 'status' => 'in_progress'],
                ['title' => __('Add payment details'), 'status' => 'not_started'],
            ]" />
        </section>

        <section class="bg-bg-white shadow-card sm:rounded-lg border border-otl-gray-200 p-6 space-y-4">
            <h3 class="font-heading font-semibold text-heading-2xs text-txt-black-900">{{ __('Tag & Pill') }}</h3>
            <p class="text-body-sm text-txt-black-500">{{ __('x-badge supports two shapes — pill (rounded-full) for statuses, tag (rounded-md) for categories/labels.') }}</p>

            <div class="space-y-3">
                <div>
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">{{ __('Pill') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <x-badge variant="success" shape="pill">{{ __('Active') }}</x-badge>
                        <x-badge variant="info" shape="pill">{{ __('New') }}</x-badge>
                        <x-badge variant="warning" shape="pill">{{ __('Pending') }}</x-badge>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-body-xs font-medium text-txt-black-500">{{ __('Tag') }}</p>
                    <div class="flex flex-wrap gap-2">
                        <x-badge variant="neutral" shape="tag">{{ __('Government') }}</x-badge>
                        <x-badge variant="neutral" shape="tag">{{ __('Healthcare') }}</x-badge>
                        <x-badge variant="neutral" shape="tag">{{ __('Education') }}</x-badge>
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-admin-layout>
