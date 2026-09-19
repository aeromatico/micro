<?php namespace Aero\Sms\Http\Controllers\Api;

use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Message;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MessagesController extends ApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'to'           => 'required|string|max:30',
            'body'         => 'required_without:template|nullable|string|max:1600',
            'template'     => 'nullable|string',
            'vars'         => 'nullable|array',
            'reference'    => 'nullable|string|max:191',
            'scheduled_at' => 'nullable|date',
        ]);

        try {
            $message = Sms::send($data, $this->consumer($request));
        }
        catch (InvalidArgumentException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422);
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->insufficient($e);
        }

        return response()->json(['data' => $this->present($message)], 201);
    }

    public function show(Request $request, string $uuid)
    {
        $message = $this->scoped(Message::where('uuid', $uuid), $request)->first();

        return $message
            ? response()->json(['data' => $this->present($message)])
            : $this->error('not_found', 'Mensaje no encontrado.', 404);
    }

    public function index(Request $request)
    {
        $query = $this->scoped(Message::query(), $request)->orderByDesc('id');

        foreach (['status', 'reference', 'to'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        $page = $query->paginate(min((int) $request->input('per_page', 50), 200));

        return response()->json([
            'data' => $page->getCollection()->map(fn ($m) => $this->present($m)),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function cancel(Request $request, string $uuid)
    {
        $message = $this->scoped(Message::where('uuid', $uuid), $request)->first();

        if (!$message) {
            return $this->error('not_found', 'Mensaje no encontrado.', 404);
        }

        return Sms::cancel($message)
            ? response()->json(['data' => $this->present($message->fresh())])
            : $this->error('not_cancellable', 'Solo se puede cancelar un mensaje en cola.', 409);
    }

    public static function present(Message $m): array
    {
        return [
            'id'           => $m->uuid,
            'batch_id'     => $m->batch?->uuid,
            'to'           => $m->to,
            'body'         => $m->body,
            'reference'    => $m->reference,
            'status'       => $m->status,
            'segments'     => $m->segments,
            'encoding'     => $m->encoding,
            'credits'      => $m->credits_charged - ($m->refunded_at ? $m->credits_charged : 0),
            'error'        => $m->error_message ? ['code' => $m->error_code, 'message' => $m->error_message] : null,
            'scheduled_at' => $m->scheduled_at?->toIso8601String(),
            'sent_at'      => $m->sent_at?->toIso8601String(),
            'delivered_at' => $m->delivered_at?->toIso8601String(),
            'created_at'   => $m->created_at?->toIso8601String(),
        ];
    }
}
