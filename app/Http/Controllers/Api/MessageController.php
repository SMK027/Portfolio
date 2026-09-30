<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lecture des messages de contact (données personnelles : chaque accès est journalisé).
 */
class MessageController extends Controller
{
    public function index(Request $request, AuditTrail $audit): JsonResponse
    {
        $request->validate(['unread' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $messages = ContactMessage::latest('id')
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate((int) $request->input('per_page', 20));

        $audit->record('api.messages_read', meta: ['messages' => $messages->pluck('id')->all()]);

        return response()->json([
            'data' => $messages->getCollection()->map(fn ($m) => $this->present($m)),
            'meta' => ['current_page' => $messages->currentPage(), 'last_page' => $messages->lastPage(), 'total' => $messages->total()],
        ]);
    }

    public function show(ContactMessage $message, AuditTrail $audit): JsonResponse
    {
        $audit->record('api.messages_read', $message, label: 'Message #'.$message->id);

        return response()->json(['data' => $this->present($message)]);
    }

    protected function present(ContactMessage $m): array
    {
        return [
            ...$m->only(['id', 'first_name', 'last_name', 'email', 'subject', 'message']),
            'consented_at' => $m->consented_at?->toIso8601String(),
            'read_at'      => $m->read_at?->toIso8601String(),
            'notified'     => $m->notified_at !== null,
            'created_at'   => $m->created_at?->toIso8601String(),
        ];
    }
}
