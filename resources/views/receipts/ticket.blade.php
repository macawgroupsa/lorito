<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8px 9px; }
        body { color: #17291e; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .center { text-align: center; }
        .brand { color: #25633f; font-size: 19px; font-weight: bold; letter-spacing: .4px; }
        .muted { color: #6d7d70; }
        .rule { border-top: 1px dashed #9eaa9f; margin: 8px 0; }
        .row { width: 100%; }
        .row td { padding: 3px 0; vertical-align: top; }
        .number { font-size: 15px; font-weight: bold; }
        .amount { text-align: right; font-weight: bold; white-space: nowrap; }
        .total { color: #25633f; font-size: 15px; font-weight: bold; }
        .code { font-size: 8px; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="center"><div class="brand">LORITO</div><div class="muted">COMPROBANTE DE VENTA</div></div>
    <div class="rule"></div>
    <div><strong>{{ $ticket->point->name }}</strong></div>
    <div class="muted">{{ $ticket->issued_at->timezone('America/Guatemala')->format('d/m/Y h:i A') }}</div>
    <div class="muted">Vendedor: {{ $ticket->seller?->name ?? '—' }}</div>
    @if($ticket->customer_name)<div>Cliente: {{ $ticket->customer_name }}</div>@endif
    <div class="rule"></div>
    <table class="row">
        @foreach($ticket->items as $item)
            <tr><td><span class="number">{{ $item->number }}</span><br><strong>{{ $item->play->name }}</strong><br><span class="muted">{{ $item->list->name }} · {{ number_format((float)$item->amount_quetzales * $item->pieces_per_quetzal, 0) }} pedazos</span></td><td class="amount">Q{{ number_format((float)$item->amount_quetzales, 2) }}</td></tr>
        @endforeach
    </table>
    <div class="rule"></div>
    <table class="row"><tr><td class="total">TOTAL</td><td class="amount total">Q{{ number_format((float)$ticket->total_quetzales, 2) }}</td></tr></table>
    <div class="rule"></div>
    <div class="center muted">Conserve este comprobante para consultar el resultado.</div>
    <div class="center code muted">{{ $ticket->ticket_code }}</div>
</body>
</html>
