@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Inbox</h1>
    <p class="mt-2 text-sm text-zinc-600">Tickets from the contact form.</p>

    @forelse ($tickets as $ticket)
        <article class="mt-6 rounded-md border border-zinc-200 bg-white px-4 py-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-sm font-medium">{{ $ticket->name }}</h2>
                <p class="text-xs text-zinc-500">{{ $ticket->status }}</p>
            </div>
            <p class="mt-1 text-sm text-zinc-600">{{ $ticket->email }}</p>
            <p class="mt-3 text-sm whitespace-pre-wrap">{{ $ticket->message }}</p>
        </article>
    @empty
        <p class="mt-8 text-sm text-zinc-600">No tickets yet.</p>
    @endforelse
@endsection
