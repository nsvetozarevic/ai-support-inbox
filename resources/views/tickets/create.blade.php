@extends('layouts.app')

@section('title', 'Contact')

@section('content')
    <h1 class="text-2xl font-semibold tracking-tight">Contact</h1>
    <p class="mt-2 text-sm text-zinc-600">Send a message. We will get back to you by email.</p>

    <form method="POST" action="{{ route(\App\Routing\WebRoute::StoreTicket) }}" class="mt-8 space-y-4">
        @csrf

        <div>
            <label for="name" class="block text-sm font-medium">Name</label>
            <input
                id="name"
                name="name"
                type="text"
                value="{{ old('name') }}"
                class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
                autocomplete="name"
            >
            @error('name')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
                autocomplete="email"
            >
            @error('email')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="message" class="block text-sm font-medium">Message</label>
            <textarea
                id="message"
                name="message"
                rows="6"
                class="mt-1 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm"
            >{{ old('message') }}</textarea>
            @error('message')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white"
        >
            Send message
        </button>
    </form>
@endsection
