@extends('layouts.app')

@section('title', 'Ticket')

@section('content')
    <p class="text-sm">
        <a href="{{ route(\App\Routing\WebRoute::IndexTickets) }}" class="text-zinc-600 hover:text-zinc-900">Back to inbox</a>
    </p>

    <h1 class="mt-6 text-2xl font-semibold tracking-tight">{{ $ticket->name }}</h1>
    <p class="mt-1 text-sm text-zinc-600">
        {{ $ticket->email }}
        · {{ $ticket->status->value }}
        @if ($ticket->intent)
            · {{ $ticket->intent->value }}
        @endif
    </p>
    <p class="mt-6 text-sm whitespace-pre-wrap">{{ $ticket->message }}</p>

    @php
        $draftedReply = (string) $ticket->reply;
    @endphp

    @if ($ticket->isOpen())
        <form method="POST" action="{{ route(\App\Routing\WebRoute::SendTicket, $ticket) }}" class="mt-8 space-y-4">
            @csrf
            <div>
                <label for="reply" class="block text-sm font-medium">Reply</label>
                <textarea
                    id="reply"
                    name="reply"
                    rows="6"
                    class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
                >{{ old('reply', $ticket->reply) }}</textarea>
                @error('reply')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @if ($draftNotice)
                    <p class="mt-1 text-sm text-zinc-600">{{ $draftNotice }}</p>
                @endif
            </div>
            @if ($ticket->reply_quotes)
                <div
                    id="reply-quotes"
                    data-draft='@json($draftedReply)'
                    data-stale="{{ (string) old('reply', $ticket->reply) !== $draftedReply ? '1' : '0' }}"
                    @if ((string) old('reply', $ticket->reply) !== $draftedReply) hidden @endif
                    class="rounded-md border border-zinc-200 bg-white px-4 py-3"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">From the handbook</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($ticket->reply_quotes as $quote)
                            <li class="text-sm text-zinc-700">"{{ $quote }}"</li>
                        @endforeach
                    </ul>
                </div>
                <script>
                    {
                        const reply = document.getElementById('reply');
                        const quotes = document.getElementById('reply-quotes');
                        const drafted = JSON.parse(quotes.dataset.draft);

                        const sync = () => {
                            quotes.hidden = reply.value !== drafted;
                        };

                        reply.addEventListener('input', sync);
                        sync();
                    }
                </script>
            @endif
            <div class="flex gap-3">
                <button type="submit" class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white">
                    Send
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route(\App\Routing\WebRoute::DiscardTicket, $ticket) }}" class="mt-3">
            @csrf
            <button type="submit" class="text-sm text-zinc-600 hover:text-zinc-900">
                Discard
            </button>
        </form>
    @elseif ($ticket->reply)
        <div class="mt-8 rounded-md border border-zinc-200 bg-white px-4 py-3">
            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Reply</p>
            <p class="mt-2 text-sm whitespace-pre-wrap">{{ $ticket->reply }}</p>
        </div>
    @endif

    <section class="mt-10 space-y-4">
        <h2 class="text-sm font-medium uppercase tracking-wide text-zinc-500">Model log</h2>

        @forelse ($ticket->aiPromptLogs as $log)
            <div class="rounded-md border border-zinc-200 bg-white px-4 py-3 space-y-3">
                <p class="text-xs text-zinc-500">
                    {{ $log->status->value }}
                    · {{ class_basename($log->agent) }}
                </p>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Instructions</p>
                    <p class="mt-1 text-sm whitespace-pre-wrap">{{ $log->instructions }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Input</p>
                    <p class="mt-1 text-sm whitespace-pre-wrap">{{ $log->input }}</p>
                </div>
                @if ($log->response)
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">Response</p>
                        <p class="mt-1 text-sm whitespace-pre-wrap">{{ json_encode($log->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</p>
                    </div>
                @endif
                @if ($log->error)
                    <p class="text-sm text-red-700">{{ $log->error }}</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-zinc-600">No model calls yet.</p>
        @endforelse
    </section>
@endsection
