<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; color: #111827; margin: 0; font-size: 13px; }
        .toolbar { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .toolbar button { border: 0; background: #111827; color: white; padding: 9px 18px; border-radius: 5px; cursor: pointer; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #111827; padding-bottom: 18px; margin-bottom: 20px; }
        h1 { margin: 0 0 8px; font-size: 25px; }
        .muted { color: #6b7280; }
        .meta { text-align: left; direction: ltr; line-height: 1.8; }
        .summary { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .box { border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; min-height: 80px; }
        .box strong { display: block; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #f3f4f6; font-weight: bold; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 10px 8px; text-align: right; }
        .totals { width: 42%; margin-right: auto; margin-top: 18px; }
        .totals td { border: 0; padding: 5px; }
        .total { font-size: 17px; font-weight: bold; border-top: 2px solid #111827 !important; }
        .footer { margin-top: 44px; border-top: 1px solid #d1d5db; padding-top: 12px; text-align: center; color: #6b7280; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">طباعة</button><button onclick="window.close()">إغلاق</button></div>
    <section class="header">
        <div><h1>فاتورة ضريبية</h1><div class="muted">E-commerce Platform</div></div>
        <div class="meta"><strong>{{ $invoice->number }}</strong><br>تاريخ الإصدار: {{ optional($invoice->issued_at)->format('Y-m-d H:i') }}<br>الحالة: {{ $invoice->status }}</div>
    </section>
    <section class="summary">
        <div class="box"><strong>بيانات العميل</strong>{{ $invoice->order->user->name ?? $invoice->order->guest_email ?? 'عميل' }}<br>{{ $invoice->order->user->email ?? $invoice->order->guest_email ?? '' }}<br>{{ $invoice->order->guest_phone ?? '' }}</div>
        <div class="box"><strong>بيانات الطلب</strong>رقم الطلب: #{{ $invoice->order_id }}<br>العملة: {{ $invoice->currency }}<br>نوع الفاتورة: {{ $invoice->type }}</div>
    </section>
    <table><thead><tr><th>المنتج</th><th>الكمية</th><th>سعر الوحدة</th><th>الضريبة</th><th>الإجمالي</th></tr></thead><tbody>
    @foreach($invoice->items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->quantity }}</td><td>{{ number_format($item->unit_price / 100, 2) }}</td><td>{{ number_format($item->tax_amount / 100, 2) }}</td><td>{{ number_format($item->total_amount / 100, 2) }}</td></tr>@endforeach
    </tbody></table>
    <table class="totals"><tr><td>الإجمالي قبل الضريبة</td><td>{{ number_format($invoice->subtotal_amount / 100, 2) }} {{ $invoice->currency }}</td></tr><tr><td>الضريبة</td><td>{{ number_format($invoice->tax_amount / 100, 2) }} {{ $invoice->currency }}</td></tr><tr class="total"><td>الإجمالي</td><td>{{ number_format($invoice->total_amount / 100, 2) }} {{ $invoice->currency }}</td></tr></table>
    <div class="footer">هذه الفاتورة صادرة إلكترونيًا ويمكن التحقق منها باستخدام رقم الفاتورة.</div>
</body>
</html>
