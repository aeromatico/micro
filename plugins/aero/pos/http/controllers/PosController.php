<?php namespace Aero\Pos\Http\Controllers;

use Aero\Pos\Classes\SaleService;
use Aero\Pos\Classes\ShiftService;
use Aero\Pos\Models\PaymentMethod;
use Aero\Pos\Models\PosSettings;
use Aero\Pos\Models\PosTable;
use Aero\Pos\Models\Sale;
use Aero\Pos\Models\Shift;
use Aero\Pos\Models\Terminal;
use Aero\Shop\Classes\CatalogService;
use Aero\Shop\Classes\Exceptions\InsufficientStockException;
use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Models\Collection;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ShopSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PosController extends Controller
{
    /** GET me — todo lo que la app necesita para arrancar. */
    public function me(Request $request)
    {
        $tid = $request->attributes->get('tenant_id');
        $tenant = $request->attributes->get('tenant');
        $settings = PosSettings::forTenant($tid)->first();
        $shop = ShopSettings::where('tenant_id', $tid)->with('base_currency')->first();
        $terminals = Terminal::forTenant($tid)->where('is_active', true)->orderBy('name')->get();

        return response()->json(['data' => [
            'user'   => AuthController::user($request->attributes->get('pos_user'), $request->attributes->get('pos_cashier')),
            'tenant' => ['handle' => $tenant->handle, 'name' => $tenant->name],
            'currency' => ['symbol' => $shop?->base_currency?->symbol ?: 'Bs', 'decimals' => (int) ($shop?->base_currency?->decimal_places ?? 2)],
            'settings' => [
                'profile' => $settings->profile ?? 'retail', 'ticket_width' => $settings->ticket_width ?? '80',
                'tables' => (bool) ($settings->feat_tables ?? false), 'kitchen' => (bool) ($settings->feat_kitchen ?? false),
                'barcode' => (bool) ($settings->feat_barcode ?? true), 'tips' => (bool) ($settings->feat_tips ?? false),
                'require_shift' => (bool) ($settings->require_shift ?? true),
                'ticket_header' => $settings->ticket_header ?? null, 'ticket_footer' => $settings->ticket_footer ?? null,
            ],
            'terminals' => $terminals->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'shift' => $this->shiftPayload($t->openShift())])->all(),
        ]]);
    }

    /** GET catalog — categorías, productos, métodos de pago y mesas. */
    public function catalog(Request $request)
    {
        $tid = $request->attributes->get('tenant_id');

        $products = Product::forTenant($tid)->active()->with(['images', 'variants' => fn ($v) => $v->where('is_active', true)->with('option_values')])
            ->orderBy('name')->limit(2000)->get()->map(fn ($p) => CatalogService::present($p));

        return response()->json(['data' => [
            'collections' => Collection::where('tenant_id', $tid)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name'])->all(),
            'products'    => $products->all(),
            'methods'     => PaymentMethod::forTenant($tid)->where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn ($m) => ['id' => $m->id, 'label' => $m->label, 'kind' => $m->kind])->all(),
            'tables'      => PosTable::forTenant($tid)->get(['id', 'name'])->all(),
        ]]);
    }

    /** POST shift/open */
    public function shiftOpen(Request $request)
    {
        $data = $request->validate(['terminal_id' => 'required|integer', 'opening_cash' => 'nullable|numeric|min:0']);

        return $this->guard(fn () => ['shift' => $this->shiftPayload(
            app(ShiftService::class)->open($request->attributes->get('tenant_id'), (int) $data['terminal_id'], $request->attributes->get('pos_user')->id, (float) ($data['opening_cash'] ?? 0))
        )], 201);
    }

    /** POST shift/close — requiere ser supervisor o quien abrió el turno. */
    public function shiftClose(Request $request)
    {
        $data = $request->validate(['shift_id' => 'required|integer', 'counted_cash' => 'required|numeric|min:0', 'notes' => 'nullable|string|max:500']);
        $tid = $request->attributes->get('tenant_id');
        $user = $request->attributes->get('pos_user');
        $shift = Shift::forTenant($tid)->find($data['shift_id']);
        if (!$shift) {
            return $this->json(['error' => 'not_found', 'message' => 'Turno no encontrado.'], 404);
        }
        if (!$request->attributes->get('pos_cashier')->is_supervisor && $shift->opened_by_user_id !== $user->id) {
            return $this->json(['error' => 'forbidden', 'message' => 'Solo quien abrió el turno o un supervisor puede cerrarlo.'], 403);
        }

        return $this->guard(function () use ($shift, $data, $user) {
            $closed = app(ShiftService::class)->close($shift, (float) $data['counted_cash'], $user->id, $data['notes'] ?? null);

            return ['shift' => $this->shiftPayload($closed), 'summary' => $closed->summary];
        });
    }

    /** POST shift/movement — ingreso o egreso de caja. */
    public function movement(Request $request)
    {
        $data = $request->validate(['shift_id' => 'required|integer', 'type' => 'required|in:in,out', 'amount' => 'required|numeric|gt:0', 'reason' => 'required|string|max:160']);
        $shift = Shift::forTenant($request->attributes->get('tenant_id'))->find($data['shift_id']);
        if (!$shift) {
            return $this->json(['error' => 'not_found', 'message' => 'Turno no encontrado.'], 404);
        }

        return $this->guard(function () use ($shift, $data, $request) {
            app(ShiftService::class)->addMovement($shift, $data['type'], (float) $data['amount'], $data['reason'], $request->attributes->get('pos_user')->id);

            return ['shift' => $this->shiftPayload($shift->fresh())];
        }, 201);
    }

    /** POST sales — crea y cobra una venta. Idempotente por client_uuid. */
    public function sale(Request $request)
    {
        $data = $request->validate([
            'client_uuid' => 'required|uuid', 'shift_id' => 'nullable|integer',
            'items' => 'required|array|min:1|max:200', 'items.*.product_id' => 'required|integer', 'items.*.variant_id' => 'nullable|integer', 'items.*.quantity' => 'required|integer|min:1|max:9999',
            'discount_percent' => 'nullable|numeric|min:0|max:100', 'nit' => 'nullable|string|max:30', 'tax_name' => 'nullable|string|max:150',
            'table_id' => 'nullable|integer',
            'payments' => 'required|array|min:1|max:6', 'payments.*.method_id' => 'required|integer', 'payments.*.amount' => 'nullable|numeric|min:0',
            'payments.*.tendered' => 'nullable|numeric|min:0', 'payments.*.reference' => 'nullable|string|max:120',
        ]);
        $tid = $request->attributes->get('tenant_id');
        $shift = !empty($data['shift_id']) ? Shift::forTenant($tid)->find($data['shift_id']) : null;
        $cashier = $request->attributes->get('pos_cashier');

        return $this->guard(function () use ($tid, $request, $shift, $data, $cashier) {
            $svc = app(SaleService::class);
            $sale = $svc->create($tid, $request->attributes->get('pos_user')->id, $shift, $data, (float) ($cashier->is_supervisor ? 100 : $cashier->discount_limit_percent));

            return ['receipt' => $svc->receipt($sale)];
        }, 201);
    }

    /** GET sales/{id}/receipt */
    public function receipt(Request $request, int $id)
    {
        $sale = Sale::forTenant($request->attributes->get('tenant_id'))->find($id);

        return $sale
            ? response()->json(['data' => ['receipt' => app(SaleService::class)->receipt($sale)]])
            : $this->json(['error' => 'not_found', 'message' => 'Venta no encontrada.'], 404);
    }

    protected function shiftPayload(?Shift $shift): ?array
    {
        if (!$shift) {
            return null;
        }
        $svc = app(ShiftService::class);

        return [
            'id' => $shift->id, 'terminal_id' => $shift->terminal_id, 'status' => $shift->status, 'opened_at' => $shift->opened_at?->toIso8601String(),
            'opening_cash' => (float) $shift->opening_cash,
            'expected_cash' => $shift->isOpen() ? $svc->expectedCash($shift) : (float) $shift->expected_cash,
            'counted_cash' => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
            'difference' => $shift->difference !== null ? (float) $shift->difference : null,
        ];
    }

    /** Convierte los errores de negocio en 422 con mensaje claro. */
    protected function guard(callable $fn, int $status = 200)
    {
        try {
            return response()->json(['data' => $fn()], $status);
        } catch (InsufficientStockException $e) {
            return $this->json(['error' => 'insufficient_stock', 'message' => $e->getMessage()], 409);
        } catch (OrderException $e) {
            return $this->json(['error' => 'rejected', 'message' => $e->getMessage()], 422);
        }
    }

    protected function json(array $body, int $status)
    {
        return response()->json($body, $status);
    }
}
