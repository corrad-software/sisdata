@props([
    'name' => null,
    'range' => false,
    'max' => null,
    'selected' => null,
])

@php
$weekdays = [__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')];
$monthsShort = [__('Jan'), __('Feb'), __('Mar'), __('Apr'), __('May'), __('Jun'), __('Jul'), __('Aug'), __('Sep'), __('Oct'), __('Nov'), __('Dec')];
$monthsLong = [__('January'), __('February'), __('March'), __('April'), __('May'), __('June'), __('July'), __('August'), __('September'), __('October'), __('November'), __('December')];
@endphp

<div
    x-data="{
        view: 'day',
        viewDate: {{ $selected ? "new Date('{$selected}')" : 'new Date()' }},
        selected: {{ $selected ? "new Date('{$selected}')" : 'null' }},
        rangeStart: null,
        rangeEnd: null,
        range: @js($range),
        max: {{ $max ? "new Date('{$max}')" : 'null' }},
        yearRangeStart: (new Date()).getFullYear() - 5,
        weekdays: @js($weekdays),
        monthsShort: @js($monthsShort),
        monthsLong: @js($monthsLong),

        get monthLabel() { return this.monthsLong[this.viewDate.getMonth()] },
        get yearLabel() { return this.viewDate.getFullYear() },
        get years() { return Array.from({ length: 12 }, (_, i) => this.yearRangeStart + i) },

        get days() {
            const year = this.viewDate.getFullYear();
            const month = this.viewDate.getMonth();
            const startOffset = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            const daysInPrevMonth = new Date(year, month, 0).getDate();
            const cells = [];
            for (let i = startOffset - 1; i >= 0; i--) {
                cells.push({ date: new Date(year, month - 1, daysInPrevMonth - i), current: false });
            }
            for (let d = 1; d <= daysInMonth; d++) {
                cells.push({ date: new Date(year, month, d), current: true });
            }
            let next = 1;
            while (cells.length < 42) {
                cells.push({ date: new Date(year, month + 1, next), current: false });
                next++;
            }
            return cells;
        },

        isSameDay(a, b) {
            return a && b && a.toDateString() === b.toDateString();
        },
        isDisabled(date) {
            return this.max && date > this.max;
        },
        isSelected(date) {
            if (this.range) {
                return this.isSameDay(date, this.rangeStart) || this.isSameDay(date, this.rangeEnd);
            }
            return this.isSameDay(date, this.selected);
        },
        isInRange(date) {
            if (! this.range || ! this.rangeStart || ! this.rangeEnd) return false;
            return date > this.rangeStart && date < this.rangeEnd;
        },
        isRowStart(date) { return date.getDay() === 0 },
        isRowEnd(date) { return date.getDay() === 6 },
        cellClasses(cell) {
            const date = cell.date;
            if (this.isSelected(date)) {
                return 'rounded-md bg-primary-600 text-white font-semibold';
            }
            if (this.isInRange(date)) {
                const rounding = this.isRowStart(date) ? 'rounded-l-md' : (this.isRowEnd(date) ? 'rounded-r-md' : 'rounded-none');
                return 'bg-bg-primary-50 text-primary-700 ' + rounding;
            }
            return (cell.current ? 'text-txt-black-900' : 'text-txt-black-300') + ' rounded-md hover:bg-bg-washed';
        },
        formatDate(date) {
            return date.getDate() + ' ' + this.monthsShort[date.getMonth()] + ' ' + date.getFullYear();
        },
        selectDay(date) {
            if (this.isDisabled(date)) return;
            if (this.range) {
                if (! this.rangeStart || this.rangeEnd) {
                    this.rangeStart = date;
                    this.rangeEnd = null;
                } else if (date < this.rangeStart) {
                    this.rangeEnd = this.rangeStart;
                    this.rangeStart = date;
                } else {
                    this.rangeEnd = date;
                }
            } else {
                this.selected = date;
            }
        },

        prevMonth() { this.viewDate = new Date(this.viewDate.getFullYear(), this.viewDate.getMonth() - 1, 1) },
        nextMonth() { this.viewDate = new Date(this.viewDate.getFullYear(), this.viewDate.getMonth() + 1, 1) },
        selectMonth(index) {
            this.viewDate = new Date(this.viewDate.getFullYear(), index, 1);
            this.view = 'day';
        },
        isMonthDisabled(index) {
            return this.max && new Date(this.viewDate.getFullYear(), index, 1) > new Date(this.max.getFullYear(), this.max.getMonth(), 1);
        },
        prevYearPage() { this.yearRangeStart -= 12 },
        nextYearPage() { this.yearRangeStart += 12 },
        selectYear(y) {
            this.viewDate = new Date(y, this.viewDate.getMonth(), 1);
            this.view = 'month';
        },

        prev() {
            if (this.view === 'day') this.prevMonth();
            else if (this.view === 'month') this.viewDate = new Date(this.viewDate.getFullYear() - 1, this.viewDate.getMonth(), 1);
            else this.prevYearPage();
        },
        next() {
            if (this.view === 'day') this.nextMonth();
            else if (this.view === 'month') this.viewDate = new Date(this.viewDate.getFullYear() + 1, this.viewDate.getMonth(), 1);
            else this.nextYearPage();
        },
    }"
    {{ $attributes->merge(['class' => 'w-full max-w-[15rem] rounded-lg border border-otl-gray-200 bg-bg-white p-2 shadow-card']) }}
>
    @if ($name)
        <input type="hidden" name="{{ $name }}" x-bind:value="selected ? selected.toISOString().slice(0, 10) : ''">
    @endif

    @if ($range)
        <div class="mb-3 flex items-center gap-2">
            <div class="flex-1 rounded-md border border-otl-gray-300 px-2.5 py-1.5 text-body-xs">
                <span class="text-txt-black-500">{{ __('From:') }}</span>
                <span class="font-medium text-txt-black-900" x-text="rangeStart ? formatDate(rangeStart) : '—'"></span>
            </div>
            <span class="text-txt-black-400">-</span>
            <div class="flex-1 rounded-md border border-otl-gray-300 px-2.5 py-1.5 text-body-xs">
                <span class="text-txt-black-500">{{ __('To:') }}</span>
                <span class="font-medium text-txt-black-900" x-text="rangeEnd ? formatDate(rangeEnd) : '—'"></span>
            </div>
        </div>
    @endif

    {{-- Header --}}
    <div class="mb-3 flex items-center justify-between">
        <div class="flex items-center gap-1 text-body-sm font-semibold">
            <button
                type="button"
                x-on:click="view = (view === 'month' ? 'day' : 'month')"
                class="rounded px-1 py-0.5"
                :class="view === 'month' ? 'bg-bg-primary-50 text-primary-600' : 'text-txt-black-900 hover:bg-bg-washed'"
                x-text="monthLabel"
            ></button>
            <span class="text-txt-black-500">/</span>
            <button
                type="button"
                x-on:click="view = (view === 'year' ? 'day' : 'year')"
                class="rounded px-1 py-0.5"
                :class="view === 'year' ? 'bg-bg-primary-50 text-primary-600' : 'text-txt-black-900 hover:bg-bg-washed'"
                x-text="yearLabel"
            ></button>
        </div>

        <div class="flex items-center gap-1">
            <button type="button" x-on:click="prev()" class="flex h-6 w-6 items-center justify-center rounded-md border border-otl-gray-300 text-txt-black-500 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>
            <button type="button" x-on:click="next()" class="flex h-6 w-6 items-center justify-center rounded-md border border-otl-gray-300 text-txt-black-500 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary">
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Day view --}}
    <div x-show="view === 'day'">
        <div class="mb-1 grid grid-cols-7 text-center text-body-xs text-txt-black-500">
            <template x-for="wd in weekdays" :key="wd">
                <span x-text="wd"></span>
            </template>
        </div>
        <div class="grid grid-cols-7">
            <template x-for="(cell, i) in days" :key="i">
                <button
                    type="button"
                    x-on:click="selectDay(cell.date)"
                    x-text="cell.date.getDate()"
                    :disabled="isDisabled(cell.date)"
                    class="flex aspect-square w-full items-center justify-center text-body-xs disabled:cursor-not-allowed disabled:opacity-40"
                    :class="cellClasses(cell)"
                ></button>
            </template>
        </div>
    </div>

    {{-- Month view --}}
    <div x-show="view === 'month'" style="display: none" class="grid grid-cols-3 gap-1.5">
        <template x-for="(m, index) in monthsShort" :key="m">
            <button
                type="button"
                x-on:click="selectMonth(index)"
                x-text="m"
                :disabled="isMonthDisabled(index)"
                class="rounded-md py-2 text-body-xs disabled:cursor-not-allowed disabled:opacity-40"
                :class="index === viewDate.getMonth() ? 'bg-primary-600 text-white font-semibold' : 'text-txt-black-900 hover:bg-bg-washed'"
            ></button>
        </template>
    </div>

    {{-- Year view --}}
    <div x-show="view === 'year'" style="display: none" class="grid max-h-56 grid-cols-3 gap-1.5 overflow-y-auto">
        <template x-for="y in years" :key="y">
            <button
                type="button"
                x-on:click="selectYear(y)"
                x-text="y"
                class="rounded-md py-2 text-body-xs"
                :class="y === viewDate.getFullYear() ? 'bg-primary-600 text-white font-semibold' : 'text-txt-black-900 hover:bg-bg-washed'"
            ></button>
        </template>
    </div>
</div>
