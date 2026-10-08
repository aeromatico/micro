<?php

use Aero\Chat\Http\Controllers\AuthController;
use Aero\Chat\Http\Controllers\CrmController;
use Aero\Chat\Http\Controllers\InboxController;
use Aero\Chat\Http\Controllers\PayController;
use Aero\Chat\Http\Controllers\PushController;
use Aero\Chat\Http\Controllers\QuickReplyController;
use Aero\Chat\Http\Controllers\ShopController;
use Aero\Chat\Http\Middleware\AuthenticateChatToken;
use Aero\Chat\Http\Middleware\ForceJson;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/chat')->middleware(['api', ForceJson::class])->group(function () {
    // Público: resuelve el nombre del tenant para la pantalla de acceso.
    Route::get('tenants/{handle}', [AuthController::class, 'tenant'])->middleware('throttle:30,1');
    Route::post('tenants/{handle}/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(AuthenticateChatToken::class)->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::get('accounts', [InboxController::class, 'accounts']);
        Route::get('agents', [InboxController::class, 'agents']);
        Route::get('conversations', [InboxController::class, 'conversations']);
        Route::post('conversations/new', [InboxController::class, 'start']);
        Route::get('conversations/code/{code}', [InboxController::class, 'byCode']);
        Route::get('conversations/{id}/messages', [InboxController::class, 'messages']);
        Route::post('conversations/{id}/reply', [InboxController::class, 'reply']);
        Route::post('conversations/{id}/attachment', [InboxController::class, 'attachment']);
        Route::post('conversations/{id}/poll', [InboxController::class, 'poll']);
        Route::post('conversations/{id}/location', [InboxController::class, 'location']);
        Route::post('conversations/{id}/note', [InboxController::class, 'note']);
        Route::post('conversations/{id}/read', [InboxController::class, 'markRead']);
        Route::post('conversations/{id}/archive', [InboxController::class, 'archive']);
        Route::post('conversations/{id}/mute', [InboxController::class, 'mute']);
        Route::post('conversations/{id}/delegate', [InboxController::class, 'delegate']);

        Route::get('push/key', [PushController::class, 'key']);
        Route::post('push/subscribe', [PushController::class, 'subscribe']);
        Route::post('push/test', [PushController::class, 'test']);
        Route::post('push/unsubscribe', [PushController::class, 'unsubscribe']);
        Route::get('notifications', [PushController::class, 'index']);
        Route::post('notifications/{id}/read', [PushController::class, 'read']);

        Route::get('quick-replies', [QuickReplyController::class, 'index']);
        Route::post('quick-replies/{id}/use', [QuickReplyController::class, 'use']);

        Route::get('shop/products', [ShopController::class, 'products']);
        Route::get('conversations/{id}/shop', [ShopController::class, 'show']);
        Route::post('conversations/{id}/shop/order', [ShopController::class, 'order']);
        Route::post('conversations/{id}/shop/products/{productId}/card', [ShopController::class, 'card']);
        Route::post('conversations/{id}/shop/orders/{orderId}/resend', [ShopController::class, 'resend']);
        Route::post('conversations/{id}/shop/orders/{orderId}/cancel', [ShopController::class, 'cancel']);

        Route::get('conversations/{id}/pay', [PayController::class, 'show']);
        Route::post('conversations/{id}/pay/charge', [PayController::class, 'charge']);

        Route::get('conversations/{id}/crm', [CrmController::class, 'show']);
        Route::post('conversations/{id}/crm/contact', [CrmController::class, 'updateContact']);
        Route::post('conversations/{id}/crm/lists', [CrmController::class, 'list']);
        Route::post('conversations/{id}/crm/ticket', [CrmController::class, 'createTicket']);
        Route::post('conversations/{id}/crm/ticket/{ticketId}', [CrmController::class, 'updateTicket']);
        Route::post('conversations/{id}/crm/lead', [CrmController::class, 'lead']);
        Route::post('conversations/{id}/crm/lead/convert', [CrmController::class, 'convertLead']);
        Route::post('conversations/{id}/crm/deal/create', [CrmController::class, 'createDeal']);
        Route::post('conversations/{id}/crm/deal', [CrmController::class, 'moveDeal']);
        Route::post('conversations/{id}/crm/collections/{itemId}/qr', [CrmController::class, 'sendQr']);
    });
});
