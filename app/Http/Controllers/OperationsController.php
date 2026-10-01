<?php

namespace App\Http\Controllers;

use App\Models\DrawResult;
use App\Models\Play;
use App\Models\Sale;
use App\Models\SalesList;
use App\Models\SalesPoint;
use App\Models\SaleTicket;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OperationsController extends Controller
{
    public function bootstrap(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()))->toDateString();
        $user = $request->user();
        $isAdmin = $user->role === 'admin';
        $pointId = $isAdmin ? null : $user->sales_point_id;
        $permissions = $isAdmin ? [] : array_values(array_intersect($user->permissions ?? ['sales'], ['overview', 'sales', 'plays', 'results', 'settlements']));
        $canSeeSales = $isAdmin || in_array('sales', $permissions, true);
        $canSeeSettlements = $isAdmin || in_array('settlements', $permissions, true);
        $canSeeResults = $isAdmin || in_array('results', $permissions, true);
        $canSeeReports = $isAdmin || (bool) array_intersect($permissions, ['overview', 'plays', 'results', 'settlements']);
        $plays = Play::with(['results' => fn ($q) => $q->whereDate('draw_date', $date)])->orderBy('draw_time')->get();
        $salesQuery = Sale::with(['list.point', 'play', 'seller', 'ticket'])->whereDate('sale_date', $date);
        $listQuery = SalesList::with(['point', 'play']);
        if (! $isAdmin) {
            $salesQuery->whereHas('list', fn ($q) => $q->where('sales_point_id', $pointId));
            $listQuery->where('sales_point_id', $pointId);
        }
        $sales = $salesQuery->latest('sold_at')->get();
        $lists = $listQuery->orderBy('name')->get();
        $summary = $plays->map(function (Play $play) use ($date, $pointId) {
            $salesQuery = Sale::where('play_id', $play->id)->whereDate('sale_date', $date);
            if ($pointId) {
                $salesQuery->whereHas('list', fn ($q) => $q->where('sales_point_id', $pointId));
            }
            $rows = $salesQuery->get();
            $sold = (float) $rows->sum('amount_quetzales');
            $commission = $rows->sum(fn (Sale $sale) => (float) $sale->amount_quetzales * (float) $sale->commission_percentage / 100);
            $pieces = (float) $rows->sum(fn (Sale $sale) => (float) $sale->amount_quetzales * $sale->pieces_per_quetzal);
            $result = $play->results->first();
            $matched = $result ? $rows->where('number', $result->winning_number)->sum('amount_quetzales') : 0;
            $prizes = $result
                ? $rows->where('number', $result->winning_number)->sum(fn (Sale $sale) => (float) $sale->amount_quetzales * (float) $sale->prize_per_quetzal)
                : 0;

            return [
                'id' => $play->id, 'name' => $play->name, 'draw_time' => substr((string) $play->draw_time, 0, 5),
                'lock_minutes' => $play->lock_minutes, 'pieces_per_quetzal' => $play->pieces_per_quetzal,
                'prize_per_quetzal' => (float) $play->prize_per_quetzal, 'active' => $play->active,
                'sold' => round($sold, 2), 'commission' => round($commission, 2), 'pieces' => $pieces,
                'winning_number' => $result?->winning_number, 'matched_amount' => round((float) $matched, 2), 'prizes' => round($prizes, 2),
                'state' => $this->playState($play, $date, $result !== null),
            ];
        });

        $summaryById = $summary->keyBy('id');
        $lists->each(function (SalesList $list) use ($summaryById) {
            if ($list->relationLoaded('play') && $list->play) {
                $list->play->setAttribute('state', $summaryById->get($list->play_id)['state'] ?? 'Pausada');
            }
        });

        return response()->json([
            'user' => array_merge($request->user()->only('id', 'name', 'email', 'role', 'sales_point_id'), ['permissions' => $permissions]),
            'date' => $date,
            'plays' => $canSeeReports ? $summary : $summary->map(fn (array $play) => collect($play)->only(['id', 'name', 'draw_time', 'lock_minutes', 'pieces_per_quetzal', 'prize_per_quetzal', 'active', 'state']))->values(),
            'points' => $canSeeSales ? SalesPoint::withCount('lists')->when(! $isAdmin, fn ($q) => $q->whereKey($pointId))->orderBy('name')->get() : [],
            'lists' => ($canSeeSales || $canSeeSettlements) ? $lists : [],
            'sales' => ($canSeeSales || $canSeeSettlements) ? $sales : [],
            'results' => $canSeeResults ? DrawResult::with('play')->whereDate('draw_date', $date)->latest()->get() : [],
            'users' => $isAdmin ? User::with('salesPoint')->orderBy('name')->get(['id', 'name', 'email', 'role', 'sales_point_id', 'permissions']) : [],
            'totals' => [
                'sales' => $canSeeReports || $canSeeSales ? round($summary->sum('sold'), 2) : 0,
                'pieces' => $canSeeReports || $canSeeSales ? $summary->sum('pieces') : 0,
                'commission' => $canSeeReports || $canSeeSales ? round($summary->sum('commission'), 2) : 0,
                'prizes' => $canSeeReports ? round($summary->sum('prizes'), 2) : 0,
                'net' => $canSeeReports ? round($summary->sum('sold') - $summary->sum('commission') - $summary->sum('prizes'), 2) : 0,
            ],
        ]);
    }

    public function storePlay(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'draw_time' => ['required', 'date_format:H:i'],
            'lock_minutes' => ['required', 'integer', 'min:0', 'max:180'], 'pieces_per_quetzal' => ['required', 'integer', 'min:1', 'max:100000'],
            'prize_per_quetzal' => ['required', 'numeric', 'min:0', 'max:10000000'], 'active' => ['boolean'],
        ]);

        return response()->json(Play::create($data), 201);
    }

    public function updatePlay(Request $request, Play $play)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'], 'draw_time' => ['required', 'date_format:H:i'],
            'lock_minutes' => ['required', 'integer', 'min:0', 'max:180'], 'pieces_per_quetzal' => ['required', 'integer', 'min:1', 'max:100000'],
            'prize_per_quetzal' => ['required', 'numeric', 'min:0', 'max:10000000'], 'active' => ['boolean'],
        ]);
        $play->update($data);

        return response()->json($play);
    }

    public function deletePlay(Play $play)
    {
        if ($play->sales()->exists() || $play->results()->exists()) {
            return response()->json(['message' => 'Esta jugada ya tiene movimientos. Desactívala para conservar el historial.'], 422);
        }
        $play->delete();

        return response()->noContent();
    }

    public function storePoint(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'contact_name' => ['nullable', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:30'], 'active' => ['boolean']]);

        return response()->json(SalesPoint::create($data), 201);
    }

    public function updatePoint(Request $request, SalesPoint $point)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'contact_name' => ['nullable', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:30'], 'active' => ['boolean']]);
        $point->update($data);

        return response()->json($point);
    }

    public function deletePoint(SalesPoint $point)
    {
        if ($point->lists()->exists()) {
            return response()->json(['message' => 'El punto tiene listas asignadas. Desactívalo para conservar el historial.'], 422);
        }
        $point->delete();

        return response()->noContent();
    }

    public function storeList(Request $request)
    {
        $data = $request->validate([
            'sales_point_id' => ['required', 'exists:sales_points,id'], 'play_id' => ['required', 'exists:plays,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('sales_lists', 'name')->where('sales_point_id', $request->sales_point_id)->where('play_id', $request->play_id)], 'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'], 'active' => ['boolean'],
        ]);

        return response()->json(SalesList::create($data)->load(['point', 'play']), 201);
    }

    public function updateList(Request $request, SalesList $salesList)
    {
        $data = $request->validate([
            'sales_point_id' => ['required', 'exists:sales_points,id'], 'play_id' => ['required', 'exists:plays,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('sales_lists', 'name')->where('sales_point_id', $request->sales_point_id)->where('play_id', $request->play_id)->ignore($salesList->id)], 'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'], 'active' => ['boolean'],
        ]);
        $salesList->update($data);

        return response()->json($salesList->load(['point', 'play']));
    }

    public function deleteList(SalesList $salesList)
    {
        if ($salesList->sales()->exists()) {
            return response()->json(['message' => 'Esta lista ya tiene ventas. Desactívala para conservar el historial.'], 422);
        }
        $salesList->delete();

        return response()->noContent();
    }

    public function storeSale(Request $request)
    {
        $data = $request->validate([
            'sales_list_id' => ['required', 'exists:sales_lists,id'], 'number' => ['required', 'regex:/^\d{1,10}$/'],
            'amount_quetzales' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ]);
        $list = SalesList::with('play')->findOrFail($data['sales_list_id']);
        abort_unless($request->user()->hasPermission('sales'), 403, 'Tu cuenta no tiene acceso a ventas.');
        $today = today()->toDateString();
        if (! $list->active || ! $list->point->active || ! $list->play->active) {
            return response()->json(['message' => 'La lista, el punto o la jugada están inactivos.'], 422);
        }
        if ($request->user()->role === 'seller' && (int) $request->user()->sales_point_id !== (int) $list->sales_point_id) {
            return response()->json(['message' => 'Solo puedes vender en tu punto asignado.'], 403);
        }
        $cutoff = Carbon::parse($today.' '.$list->play->draw_time)->subMinutes($list->play->lock_minutes);
        if (now()->greaterThanOrEqualTo($cutoff)) {
            return response()->json(['message' => 'La venta de esta jugada ya cerró.'], 422);
        }
        $sale = Sale::create([
            'sales_list_id' => $list->id, 'play_id' => $list->play_id, 'sale_date' => $today,
            'number' => str_pad($data['number'], 2, '0', STR_PAD_LEFT), 'amount_quetzales' => $data['amount_quetzales'],
            'commission_percentage' => $list->commission_percentage,
            'pieces_per_quetzal' => $list->play->pieces_per_quetzal,
            'prize_per_quetzal' => $list->play->prize_per_quetzal,
            'sold_by' => $request->user()->id,
        ]);

        return response()->json($sale->load(['list.point', 'play']), 201);
    }

    public function storeTicket(Request $request)
    {
        $data = $request->validate([
            'sales_point_id' => ['required', 'exists:sales_points,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.sales_list_id' => ['required', 'integer', 'exists:sales_lists,id'],
            'items.*.number' => ['required', 'regex:/^\d{2}$/'],
            'items.*.amount_quetzales' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000000'],
        ]);

        $user = $request->user();
        abort_unless($user->hasPermission('sales'), 403, 'Tu cuenta no tiene acceso a ventas.');
        if ($user->role === 'seller' && (int) $user->sales_point_id !== (int) $data['sales_point_id']) {
            return response()->json(['message' => 'Solo puedes vender en tu punto asignado.'], 403);
        }

        $point = SalesPoint::findOrFail($data['sales_point_id']);
        if (! $point->active) {
            return response()->json(['message' => 'Este punto está inactivo.'], 422);
        }

        $lineKeys = [];
        foreach ($data['items'] as $item) {
            $key = $item['sales_list_id'].'-'.$item['number'];
            if (isset($lineKeys[$key])) {
                return response()->json(['message' => 'El comprobante contiene un número repetido para la misma jugada.'], 422);
            }
            $lineKeys[$key] = true;
        }

        return DB::transaction(function () use ($data, $user, $point) {
            $prepared = [];
            foreach ($data['items'] as $item) {
                $list = SalesList::with(['play', 'point'])->lockForUpdate()->findOrFail($item['sales_list_id']);
                if ((int) $list->sales_point_id !== (int) $point->id || ! $list->active || ! $list->point->active || ! $list->play->active) {
                    return response()->json(['message' => 'Una de las listas seleccionadas no está disponible. Actualiza la pantalla e inténtalo de nuevo.'], 422);
                }
                $cutoff = Carbon::parse(today()->toDateString().' '.$list->play->draw_time)->subMinutes($list->play->lock_minutes);
                if (now()->greaterThanOrEqualTo($cutoff)) {
                    return response()->json(['message' => 'La jugada «'.$list->play->name.'» ya cerró. Quita esa jugada y vuelve a emitir el comprobante.'], 422);
                }
                $prepared[] = [$list, $item];
            }

            $total = array_sum(array_map(fn ($entry) => (float) $entry[1]['amount_quetzales'], $prepared));
            $ticket = SaleTicket::create([
                'ticket_code' => 'LOR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'sales_point_id' => $point->id,
                'sold_by' => $user->id,
                'customer_name' => $data['customer_name'] ?? null,
                'total_quetzales' => $total,
                'sale_date' => today()->toDateString(),
            ]);

            foreach ($prepared as [$list, $item]) {
                Sale::create([
                    'sale_ticket_id' => $ticket->id,
                    'sales_list_id' => $list->id,
                    'play_id' => $list->play_id,
                    'sale_date' => today()->toDateString(),
                    'number' => $item['number'],
                    'amount_quetzales' => $item['amount_quetzales'],
                    'commission_percentage' => $list->commission_percentage,
                    'pieces_per_quetzal' => $list->play->pieces_per_quetzal,
                    'prize_per_quetzal' => $list->play->prize_per_quetzal,
                    'sold_by' => $user->id,
                ]);
            }

            return response()->json([
                'id' => $ticket->id,
                'ticket_code' => $ticket->ticket_code,
                'total_quetzales' => number_format($total, 2, '.', ''),
                'receipt_url' => route('tickets.receipt', $ticket).'?tenant='.urlencode((string) app('currentTenant')->slug),
            ], 201);
        });
    }

    public function receipt(Request $request, SaleTicket $ticket)
    {
        if ($request->user()->role === 'seller' && (int) $request->user()->sales_point_id !== (int) $ticket->sales_point_id) {
            abort(403);
        }
        $ticket->load(['point', 'seller', 'items.play', 'items.list']);

        return Pdf::loadView('receipts.ticket', compact('ticket'))
            ->setPaper([0, 0, 226.77, 850], 'portrait')
            ->stream($ticket->ticket_code.'.pdf');
    }

    public function deleteSale(Sale $sale)
    {
        abort_unless(auth()->user()->hasPermission('sales'), 403, 'Tu cuenta no tiene acceso a ventas.');
        if (auth()->user()->role !== 'admin' && ((int) $sale->sold_by !== (int) auth()->id() || (int) $sale->list()->value('sales_point_id') !== (int) auth()->user()->sales_point_id)) {
            return response()->json(['message' => 'No tienes permiso para anular esta venta.'], 403);
        }
        $cutoff = Carbon::parse($sale->sale_date->format('Y-m-d').' '.$sale->play->draw_time)->subMinutes($sale->play->lock_minutes);
        if (now()->greaterThanOrEqualTo($cutoff)) {
            return response()->json(['message' => 'No se puede anular una venta después del cierre.'], 422);
        }
        $sale->delete();

        return response()->noContent();
    }

    public function storeResult(Request $request)
    {
        $data = $request->validate([
            'play_id' => ['required', 'exists:plays,id'],
            'draw_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'winning_number' => ['required', 'regex:/^\d{1,2}$/'],
        ]);
        $drawDate = $data['draw_date'] ?: today()->toDateString();
        if (Carbon::parse($drawDate)->startOfDay()->greaterThan(today())) {
            return response()->json(['message' => 'No se puede guardar el resultado de una fecha futura.'], 422);
        }
        $play = Play::findOrFail($data['play_id']);
        $result = DrawResult::updateOrCreate(
            ['play_id' => $play->id, 'draw_date' => $drawDate],
            ['winning_number' => str_pad($data['winning_number'], 2, '0', STR_PAD_LEFT), 'entered_by' => $request->user()->id, 'entered_at' => now()],
        );

        return response()->json($result->load('play'));
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'sales_point_id' => ['required', 'exists:sales_points,id'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in(['overview', 'sales', 'plays', 'results', 'settlements'])],
        ]);
        $createdUser = DB::transaction(function () use ($data) {
            User::where('role', 'admin')->lockForUpdate()->first();
            if (User::where('role', 'seller')->count() >= 2) {
                abort(response()->json(['message' => 'Este negocio ya alcanzó el máximo de 2 vendedores.'], 422));
            }

            return User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']),
                'role' => 'seller', 'sales_point_id' => $data['sales_point_id'], 'permissions' => array_values(array_unique($data['permissions'])),
            ]);
        });

        return response()->json($createdUser->only('id', 'name', 'email', 'role', 'sales_point_id', 'permissions'), 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'sales_point_id' => ['required', 'exists:sales_points,id'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in(['overview', 'sales', 'plays', 'results', 'settlements'])],
        ]);
        abort_unless($user->role === 'seller', 422, 'La cuenta administradora pertenece al propietario del negocio.');
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }
        $data['permissions'] = array_values(array_unique($data['permissions']));
        $user->update($data);

        return response()->json($user->only('id', 'name', 'email', 'role', 'sales_point_id', 'permissions'));
    }

    public function deleteUser(User $user)
    {
        if ($user->role !== 'seller') {
            return response()->json(['message' => 'La cuenta administradora del propietario no se puede eliminar.'], 422);
        }
        $user->delete();

        return response()->noContent();
    }

    private function playState(Play $play, string $date, bool $hasResult): string
    {
        if (! $play->active) {
            return 'Pausada';
        }
        $draw = Carbon::parse($date.' '.$play->draw_time);
        if (now()->lt($draw->copy()->subMinutes($play->lock_minutes))) {
            return 'En venta';
        }
        if (now()->lt($draw)) {
            return 'Cerrada';
        }

        return $hasResult ? 'Con resultado' : 'Pendiente de resultado';
    }
}
