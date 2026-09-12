<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', config('app.name'))</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased">
        <header class="mx-auto flex max-w-2xl gap-4 px-4 pt-8 text-sm">
            <a href="{{ route(\App\Routing\WebRoute::CreateTicket) }}" class="text-zinc-600 hover:text-zinc-900">Contact</a>
            <a href="{{ route(\App\Routing\WebRoute::IndexTickets) }}" class="text-zinc-600 hover:text-zinc-900">Inbox</a>
        </header>
        <main class="mx-auto max-w-2xl px-4 py-12">
            @yield('content')
        </main>
    </body>
</html>
