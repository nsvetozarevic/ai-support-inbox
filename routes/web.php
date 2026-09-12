<?php

declare(strict_types=1);

use App\Http\Controllers\CreateTicketController;
use App\Http\Controllers\ShowTicketCreatedController;
use App\Http\Controllers\StoreTicketController;
use App\Routing\WebRoute;
use Illuminate\Support\Facades\Route;

Route::get('/', CreateTicketController::class)->name(WebRoute::CreateTicket);
Route::post('/', StoreTicketController::class)->name(WebRoute::StoreTicket);
Route::get('/submitted', ShowTicketCreatedController::class)->name(WebRoute::ShowTicketCreated);
