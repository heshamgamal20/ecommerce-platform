# التقارير التشغيلية

أضيفت تقارير إدارية تحت `/api/v1/reports`، وكلها تتطلب صلاحية `reports.view` وفلاتر `from` و`to` بصيغة `Y-m-d`.

| Endpoint | المحتوى |
|---|---|
| `GET /reports/sales` | الطلبات حسب الحالة، إجمالي المبيعات، الخصومات، الضرائب، الشحن، صافي المبيعات ومتوسط قيمة الطلب |
| `GET /reports/payments` | المدفوعات حسب الحالة وطريقة الدفع، والمبالغ التي تحتاج reconciliation |
| `GET /reports/returns` | المرتجعات حسب الحالة، قيمة الاسترداد، أسباب الإرجاع والمنتجات الأكثر إرجاعًا |
| `GET /reports/carriers/performance` | نسبة التسليم والإلغاء والفشل، الشحنات المفتوحة ومتوسط زمن التسليم |
| `GET /reports/inventory` | المخزون المتاح والمحجوز والمنخفض وحركة المخزون حسب السبب |
| `GET /reports/customers` | عدد الطلبات وإنفاق ومتوسط قيمة الطلب لكل عميل |
| `GET /reports/products` | الكميات والإيرادات وعدد الطلبات لكل منتج |
| `GET /reports/coupons` | مرات الاستخدام، قيمة الخصم والطلبات لكل كوبون |
| `GET /reports/taxes` | المبيعات الخاضعة للضريبة والضريبة حسب المعدل/القاعدة |
| `GET /reports/cashflow` | المقبوضات، الاستردادات، تسويات الشحن، وصافي الحركة النقدية |
| `GET /reports/payment-exceptions` | المدفوعات المعلقة والفاشلة والتي تحتاج reconciliation |
| `GET /reports/operations` | صحة outbox وعمليات الدفع والشحن وfailed jobs |

أمثلة:

```http
GET /api/v1/reports/sales?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/payments?from=2026-09-01&to=2026-09-14&method=cash_on_delivery
GET /api/v1/reports/returns?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/carriers/performance?from=2026-09-01&to=2026-09-14&carrier=Bosta
GET /api/v1/reports/inventory?from=2026-09-01&to=2026-09-14&threshold=5
GET /api/v1/reports/customers?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/products?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/coupons?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/taxes?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/cashflow?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/payment-exceptions?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/operations?from=2026-09-01&to=2026-09-14
```

القيم المالية بوحدات العملة الصغرى المستخدمة في المشروع، مثل القروش عند استخدام EGP. التقارير الحالية تشغيلية وتُحسب عند الطلب؛ أما التقارير المالية المعتمدة فتظل التسويات المحفوظة هي المصدر القابل للتدقيق.
