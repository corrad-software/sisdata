'use client';

import { useState } from 'react';

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const MONTHS_LONG = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

interface Cell {
    date: Date;
    current: boolean;
}

function isSameDay(a: Date | null, b: Date | null) {
    return !!a && !!b && a.toDateString() === b.toDateString();
}

function formatDate(date: Date) {
    return `${date.getDate()} ${MONTHS_SHORT[date.getMonth()]} ${date.getFullYear()}`;
}

function getDays(viewDate: Date): Cell[] {
    const year = viewDate.getFullYear();
    const month = viewDate.getMonth();
    const startOffset = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();
    const cells: Cell[] = [];

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
}

interface CalendarProps {
    selected?: string;
    max?: string;
    range?: boolean;
    className?: string;
}

export function Calendar({ selected: selectedProp, max: maxProp, range = false, className }: CalendarProps) {
    const initialSelected = selectedProp ? new Date(selectedProp) : null;
    const max = maxProp ? new Date(maxProp) : null;

    const [view, setView] = useState<'day' | 'month' | 'year'>('day');
    const [viewDate, setViewDate] = useState(initialSelected ?? new Date());
    const [selected, setSelected] = useState<Date | null>(initialSelected);
    const [rangeStart, setRangeStart] = useState<Date | null>(null);
    const [rangeEnd, setRangeEnd] = useState<Date | null>(null);
    const [yearRangeStart, setYearRangeStart] = useState(new Date().getFullYear() - 5);

    const monthLabel = MONTHS_LONG[viewDate.getMonth()];
    const yearLabel = viewDate.getFullYear();
    const years = Array.from({ length: 12 }, (_, i) => yearRangeStart + i);
    const days = getDays(viewDate);

    function isDisabled(date: Date) {
        return !!max && date > max;
    }

    function isSelected(date: Date) {
        if (range) return isSameDay(date, rangeStart) || isSameDay(date, rangeEnd);
        return isSameDay(date, selected);
    }

    function isInRange(date: Date) {
        if (!range || !rangeStart || !rangeEnd) return false;
        return date > rangeStart && date < rangeEnd;
    }

    function cellClasses(cell: Cell) {
        const date = cell.date;
        if (isSelected(date)) return 'rounded-md bg-primary-600 text-white font-semibold';
        if (isInRange(date)) {
            const rounding = date.getDay() === 0 ? 'rounded-l-md' : date.getDay() === 6 ? 'rounded-r-md' : 'rounded-none';
            return `bg-bg-primary-50 text-primary-700 ${rounding}`;
        }
        return `${cell.current ? 'text-txt-black-900' : 'text-txt-black-300'} rounded-md hover:bg-bg-washed`;
    }

    function selectDay(date: Date) {
        if (isDisabled(date)) return;
        if (range) {
            if (!rangeStart || rangeEnd) {
                setRangeStart(date);
                setRangeEnd(null);
            } else if (date < rangeStart) {
                setRangeEnd(rangeStart);
                setRangeStart(date);
            } else {
                setRangeEnd(date);
            }
        } else {
            setSelected(date);
        }
    }

    function selectMonth(index: number) {
        setViewDate(new Date(viewDate.getFullYear(), index, 1));
        setView('day');
    }

    function isMonthDisabled(index: number) {
        return !!max && new Date(viewDate.getFullYear(), index, 1) > new Date(max.getFullYear(), max.getMonth(), 1);
    }

    function selectYear(y: number) {
        setViewDate(new Date(y, viewDate.getMonth(), 1));
        setView('month');
    }

    function prev() {
        if (view === 'day') setViewDate(new Date(viewDate.getFullYear(), viewDate.getMonth() - 1, 1));
        else if (view === 'month') setViewDate(new Date(viewDate.getFullYear() - 1, viewDate.getMonth(), 1));
        else setYearRangeStart((y) => y - 12);
    }

    function next() {
        if (view === 'day') setViewDate(new Date(viewDate.getFullYear(), viewDate.getMonth() + 1, 1));
        else if (view === 'month') setViewDate(new Date(viewDate.getFullYear() + 1, viewDate.getMonth(), 1));
        else setYearRangeStart((y) => y + 12);
    }

    return (
        <div className={`w-full max-w-[15rem] rounded-lg border border-otl-gray-200 bg-bg-white p-2 shadow-card ${className ?? ''}`}>
            {range && (
                <div className="mb-3 flex items-center gap-2">
                    <div className="flex-1 rounded-md border border-otl-gray-300 px-2.5 py-1.5 text-body-xs">
                        <span className="text-txt-black-500">From: </span>
                        <span className="font-medium text-txt-black-900">{rangeStart ? formatDate(rangeStart) : '—'}</span>
                    </div>
                    <span className="text-txt-black-400">-</span>
                    <div className="flex-1 rounded-md border border-otl-gray-300 px-2.5 py-1.5 text-body-xs">
                        <span className="text-txt-black-500">To: </span>
                        <span className="font-medium text-txt-black-900">{rangeEnd ? formatDate(rangeEnd) : '—'}</span>
                    </div>
                </div>
            )}

            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-1 text-body-sm font-semibold">
                    <button
                        type="button"
                        onClick={() => setView(view === 'month' ? 'day' : 'month')}
                        className={`rounded px-1 py-0.5 ${view === 'month' ? 'bg-bg-primary-50 text-primary-600' : 'text-txt-black-900 hover:bg-bg-washed'}`}
                    >
                        {monthLabel}
                    </button>
                    <span className="text-txt-black-500">/</span>
                    <button
                        type="button"
                        onClick={() => setView(view === 'year' ? 'day' : 'year')}
                        className={`rounded px-1 py-0.5 ${view === 'year' ? 'bg-bg-primary-50 text-primary-600' : 'text-txt-black-900 hover:bg-bg-washed'}`}
                    >
                        {yearLabel}
                    </button>
                </div>

                <div className="flex items-center gap-1">
                    <button
                        type="button"
                        onClick={prev}
                        className="flex h-6 w-6 items-center justify-center rounded-md border border-otl-gray-300 text-txt-black-500 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary"
                    >
                        <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        onClick={next}
                        className="flex h-6 w-6 items-center justify-center rounded-md border border-otl-gray-300 text-txt-black-500 hover:bg-bg-washed focus:outline-none focus-visible:ring-2 focus-visible:ring-fr-primary"
                    >
                        <svg className="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>
            </div>

            {view === 'day' && (
                <div>
                    <div className="mb-1 grid grid-cols-7 text-center text-body-xs text-txt-black-500">
                        {WEEKDAYS.map((wd) => (
                            <span key={wd}>{wd}</span>
                        ))}
                    </div>
                    <div className="grid grid-cols-7">
                        {days.map((cell, i) => (
                            <button
                                key={i}
                                type="button"
                                onClick={() => selectDay(cell.date)}
                                disabled={isDisabled(cell.date)}
                                className={`flex aspect-square w-full items-center justify-center text-body-xs disabled:cursor-not-allowed disabled:opacity-40 ${cellClasses(cell)}`}
                            >
                                {cell.date.getDate()}
                            </button>
                        ))}
                    </div>
                </div>
            )}

            {view === 'month' && (
                <div className="grid grid-cols-3 gap-1.5">
                    {MONTHS_SHORT.map((m, index) => (
                        <button
                            key={m}
                            type="button"
                            onClick={() => selectMonth(index)}
                            disabled={isMonthDisabled(index)}
                            className={`rounded-md py-2 text-body-xs disabled:cursor-not-allowed disabled:opacity-40 ${
                                index === viewDate.getMonth()
                                    ? 'bg-primary-600 text-white font-semibold'
                                    : 'text-txt-black-900 hover:bg-bg-washed'
                            }`}
                        >
                            {m}
                        </button>
                    ))}
                </div>
            )}

            {view === 'year' && (
                <div className="grid max-h-56 grid-cols-3 gap-1.5 overflow-y-auto">
                    {years.map((y) => (
                        <button
                            key={y}
                            type="button"
                            onClick={() => selectYear(y)}
                            className={`rounded-md py-2 text-body-xs ${
                                y === viewDate.getFullYear() ? 'bg-primary-600 text-white font-semibold' : 'text-txt-black-900 hover:bg-bg-washed'
                            }`}
                        >
                            {y}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
