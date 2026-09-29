<x-app-layout>
    <x-slot name="title">Messages</x-slot>
    <x-slot name="header">Messages reçus</x-slot>

    @if ($messages->isEmpty())
        <x-empty-state icon="inbox" message="Aucun message reçu pour le moment." />
    @else
        <div class="card divide-y divide-slate-100">
            @foreach ($messages as $message)
                <a href="{{ route('admin.messages.show', $message) }}" class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50">
                    <span class="mt-2 h-2 w-2 flex-none rounded-full {{ $message->read_at ? 'bg-transparent' : 'bg-primary-500' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-baseline justify-between gap-3">
                            <p class="truncate text-sm {{ $message->read_at ? 'text-slate-700' : 'font-semibold text-slate-900' }}">{{ $message->fullName() }}</p>
                            <time class="flex-none text-xs text-slate-400" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('d/m/Y H:i') }}</time>
                        </div>
                        <p class="truncate text-sm {{ $message->read_at ? 'text-slate-600' : 'font-medium text-slate-800' }}">{{ $message->subject }}</p>
                        <p class="truncate text-xs text-slate-400">{{ \Illuminate\Support\Str::limit($message->message, 120) }}</p>
                    </div>
                </a>
            @endforeach
        </div>
        {{ $messages->links() }}
    @endif
</x-app-layout>
