@extends('layouts.app')

@section('title', 'Ticket')

@section('content')
    <p class="text-sm">
        <a href="{{ route(\App\Routing\WebRoute::IndexTickets) }}" class="text-zinc-600 hover:text-zinc-900">Back to inbox</a>
    </p>

    <h1 class="mt-6 text-2xl font-semibold tracking-tight">{{ $ticket->name }}</h1>
    <p class="mt-1 text-sm text-zinc-600">{{ $ticket->email }} · {{ $ticket->status->value }}</p>
    <p class="mt-6 text-sm whitespace-pre-wrap">{{ $ticket->message }}</p>

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
                >{{ old('reply') }}</textarea>
                @error('reply')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
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
@endsection
