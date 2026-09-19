<?php namespace Aero\Sms\Http\Controllers\Api;

use Aero\Sms\Classes\Sms;
use Aero\Sms\Models\Batch;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BatchesController extends ApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'nullable|string|max:191',
            'body'         => 'required_without:template|nullable|string|max:1600',
            'template'     => 'nullable|string',
            'recipients'   => 'required|array|min:1',
            'scheduled_at' => 'nullable|date',
            'reference'    => 'nullable|string|max:191',
        ]);

        try {
            $batch = Sms::sendBatch($data, $this->consumer($request));
        }
        catch (InvalidArgumentException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422);
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->insufficient($e);
        }

        return response()->json(['data' => $this->present($batch)], 202);
    }

    public function show(Request $request, string $uuid)
    {
        $batch = $this->find($request, $uuid);

        return $batch
            ? response()->json(['data' => $this->present($batch)])
            : $this->error('not_found', 'Lote no encontrado.', 404);
    }

    public function messages(Request $request, string $uuid)
    {
        $batch = $this->find($request, $uuid);

        if (!$batch) {
            return $this->error('not_found', 'Lote no encontrado.', 404);
        }

        $page = $batch->messages()->orderBy('id')->paginate(min((int) $request->input('per_page', 100), 500));

        return response()->json([
            'data' => $page->getCollection()->map(fn ($m) => MessagesController::present($m)),
            'meta' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function cancel(Request $request, string $uuid)
    {
        $batch = $this->find($request, $uuid);

        if (!$batch) {
            return $this->error('not_found', 'Lote no encontrado.', 404);
        }

        if (in_array($batch->status, ['completed', 'cancelled'], true)) {
            return $this->error('not_cancellable', 'El lote ya terminó.', 409);
        }

        $cancelled = Sms::cancelBatch($batch);

        return response()->json(['data' => $this->present($batch->fresh()), 'cancelled_messages' => $cancelled]);
    }

    protected function find(Request $request, string $uuid): ?Batch
    {
        return $this->scoped(Batch::where('uuid', $uuid), $request)->first();
    }

    protected function present(Batch $b): array
    {
        return [
            'id'           => $b->uuid,
            'name'         => $b->name,
            'status'       => $b->status,
            'stats'        => $b->stats(),
            'credits'      => (int) $b->credits_charged,
            'scheduled_at' => $b->scheduled_at?->toIso8601String(),
            'completed_at' => $b->completed_at?->toIso8601String(),
            'created_at'   => $b->created_at?->toIso8601String(),
        ];
    }
}
