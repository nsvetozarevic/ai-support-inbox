<?php

declare(strict_types=1);

use App\Http\Controllers\CreateTicketController;
use App\Http\Controllers\DiscardTicketController;
use App\Http\Controllers\IndexTicketsController;
use App\Http\Controllers\SendTicketController;
use App\Http\Controllers\ShowTicketController;
use App\Http\Controllers\ShowTicketCreatedController;
use App\Http\Controllers\StoreTicketController;
use App\Routing\WebRoute;
use Illuminate\Support\Facades\Route;

Route::get('/', CreateTicketController::class)->name(WebRoute::CreateTicket);
Route::post('/', StoreTicketController::class)->middleware('throttle:tickets')->name(WebRoute::StoreTicket);
Route::get('/submitted', ShowTicketCreatedController::class)->name(WebRoute::ShowTicketCreated);
Route::get('/inbox', IndexTicketsController::class)->name(WebRoute::IndexTickets);
Route::get('/inbox/{ticket}', ShowTicketController::class)->name(WebRoute::ShowTicket);
Route::post('/inbox/{ticket}/send', SendTicketController::class)->name(WebRoute::SendTicket);
Route::post('/inbox/{ticket}/discard', DiscardTicketController::class)->name(WebRoute::DiscardTicket);
