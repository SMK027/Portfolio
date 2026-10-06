<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public const STATUSES = ['non-lus' => 'Non lus', 'lus' => 'Lus'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q'      => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', 'in:'.implode(',', array_keys(self::STATUSES))],
            'du'     => ['nullable', 'date_format:Y-m-d'],
            'au'     => ['nullable', 'date_format:Y-m-d'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));

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
            ->when($filters['statut'] ?? null, fn ($query, $status) => $status === 'non-lus' ? $query->whereNull('read_at') : $query->whereNotNull('read_at'))
            ->when($filters['du'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['au'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $to.' 23:59:59'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'search'   => $search,
            'filters'  => $filters,
            'filtered' => (bool) array_filter($filters),
        ]);
    }

    public function show(ContactMessage $message): View
    {
        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        return view('admin.messages.show', ['message' => $message]);
    }

    /** Remet le message dans les non lus (à traiter plus tard). */
    public function markUnread(ContactMessage $message): RedirectResponse
    {
        $message->update(['read_at' => null]);

        return redirect()->route('admin.messages.index')->with('success', 'Message marqué comme non lu.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('admin.messages.index')->with('success', 'Message supprimé.');
    }
}
