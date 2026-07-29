<?php

namespace App\Livewire\Layout;

use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    /**
     * Re-renders with fresh data whenever another component (e.g.
     * UserManagement) creates a notification for the current user.
     */
    #[On('notification-created')]
    public function refresh(): void
    {
        //
    }

    public function markAsRead(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        return view('livewire.layout.notification-bell', [
            'notifications' => auth()->user()->notifications()->latest()->take(10)->get(),
            'unreadCount' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
