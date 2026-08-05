'use client';

import { useState } from 'react';
import { Dropdown } from './Dropdown';

interface DemoNotification {
    id: string;
    message: string;
    time: string;
    read: boolean;
}

const INITIAL_NOTIFICATIONS: DemoNotification[] = [
    { id: '1', message: 'New user Jane Doe (jane@example.com) was added.', time: '2 hours ago', read: false },
    { id: '2', message: 'New user Ahmad Ali (ahmad@example.com) was added.', time: '1 day ago', read: false },
    { id: '3', message: 'New user Siti Aminah (siti@example.com) was added.', time: '3 days ago', read: true },
];

// UI-only: no notifications API is wired up yet, so this seeds a small local
// demo list rather than fetching real data. Read state is local-only too.
export function NotificationBell() {
    const [notifications, setNotifications] = useState(INITIAL_NOTIFICATIONS);
    const unreadCount = notifications.filter((n) => !n.read).length;

    function markAsRead(id: string) {
        setNotifications((prev) => prev.map((n) => (n.id === id ? { ...n, read: true } : n)));
    }

    function markAllAsRead() {
        setNotifications((prev) => prev.map((n) => ({ ...n, read: true })));
    }

    return (
        <Dropdown
            align="right"
            width="w-80"
            contentClassName="bg-bg-white"
            trigger={
                <button
                    type="button"
                    className="relative inline-flex items-center justify-center rounded-md p-2 text-txt-black-500 hover:text-txt-black-900 hover:bg-bg-washed focus:outline-none transition ease-in-out duration-150"
                    aria-label="Notifications"
                >
                    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"
                        />
                    </svg>

                    {unreadCount > 0 && (
                        <span className="absolute -top-0.5 -right-0.5 inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-semibold leading-none text-white">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </button>
            }
        >
            <div className="flex items-center justify-between px-4 py-2 border-b border-otl-divider">
                <span className="text-body-sm font-semibold text-txt-black-900">Notifications</span>

                {unreadCount > 0 && (
                    <button type="button" onClick={markAllAsRead} className="text-body-xs text-primary-600 hover:underline">
                        Mark all as read
                    </button>
                )}
            </div>

            <div className="max-h-80 overflow-y-auto">
                {notifications.length === 0 ? (
                    <p className="px-4 py-6 text-center text-body-sm text-txt-black-500">No notifications yet.</p>
                ) : (
                    notifications.map((notification) => (
                        <button
                            key={notification.id}
                            type="button"
                            onClick={() => markAsRead(notification.id)}
                            className={`block w-full px-4 py-3 text-start text-body-sm border-b border-otl-divider last:border-0 hover:bg-bg-washed ${
                                notification.read ? 'text-txt-black-500' : 'text-txt-black-900 bg-bg-primary-50'
                            }`}
                        >
                            <p>{notification.message}</p>
                            <p className="mt-0.5 text-body-xs text-txt-black-500">{notification.time}</p>
                        </button>
                    ))
                )}
            </div>
        </Dropdown>
    );
}
