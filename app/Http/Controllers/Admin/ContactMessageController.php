<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));

        // Chaque mot doit figurer dans le nom, l'e-mail, l'objet ou le message (« Jean Dupont » trouve prénom + nom).
        $words = array_filter(preg_split('/\s+/u', $search) ?: []);

        $messages = ContactMessage::query()
            ->when($words, function ($query) use ($words) {
                foreach ($words as $word) {
                    $like = "%{$word}%";
                    $query->where(fn ($q) => $q
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('message', 'like', $like));
                }
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'search'   => $search,
        ]);
    }

    public function show(ContactMessage $message): View
    {
        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return view('admin.messages.show', ['message' => $message]);
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('success', 'Message supprimé.');
    }
}
