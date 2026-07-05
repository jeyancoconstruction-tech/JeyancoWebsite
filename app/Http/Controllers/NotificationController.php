<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** GET /notifications — unread count + latest 20 (unread first). */
    public function index()
    {
        $user  = auth()->user();
        $items = $user->notifications()->latest()->take(20)->get();

        $formatted = $items->map(fn ($n) => [
            'id'         => $n->id,
            'title'      => $n->data['title']   ?? 'Notification',
            'message'    => $n->data['message']  ?? '',
            'link'       => $n->data['link']     ?? '#',
            'icon'       => $n->data['icon']     ?? 'fa-bell',
            'color'      => $n->data['color']    ?? '#1e3a8a',
            'read'       => ! is_null($n->read_at),
            'created_at' => $n->created_at->diffForHumans(),
        ]);

        return response()->json([
            'unread_count'  => $user->unreadNotifications()->count(),
            'notifications' => $formatted,
        ]);
    }

    /** PATCH /notifications/{id}/read — mark one as read. */
    public function markRead(string $id)
    {
        $notif = auth()->user()->notifications()->findOrFail($id);
        $notif->markAsRead();

        return response()->json(['success' => true]);
    }

    /** PATCH /notifications/read-all — mark all unread as read. */
    public function readAll()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /** DELETE /notifications/delete-all — permanently delete all notifications. */
    public function deleteAll()
    {
        auth()->user()->notifications()->delete();

        return response()->json(['success' => true]);
    }
}
