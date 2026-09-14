# APIs العمليات الإدارية

## سجل التدقيق

```http
GET /api/v1/admin/audit-logs?action=payment.refunded&actor_id=5&from=2026-09-01&to=2026-09-14
```

يتطلب صلاحية `audit.view`، ويدعم pagination والفلترة حسب العملية، المستخدم، نوع الهدف، رقم الهدف، والفترة الزمنية.

## تنبيهات الإدارة

```http
GET /api/v1/admin/notifications?unread_only=1
PATCH /api/v1/admin/notifications/{notification}/read
PATCH /api/v1/admin/notifications/read-all
```

يتطلب صلاحية `admin.notifications.view`. كل مستخدم يرى تنبيهاته فقط، ولا يستطيع قراءة تنبيه مستخدم آخر.

## تصدير CSV

```http
GET /api/v1/reports/exports/sales.csv?from=2026-09-01&to=2026-09-14
GET /api/v1/reports/exports/payments.csv?from=2026-09-01&to=2026-09-14
```

يتطلب صلاحية `reports.view`. التصدير حاليًا يشمل الطلبات والمدفوعات، مع الحفاظ على عدم تصدير الحقول السرية مثل أسعار الشراء.

## لوحة التحكم الموحدة

```http
GET /api/v1/admin/dashboard?from=2026-09-01&to=2026-09-14&threshold=5
```

يتطلب صلاحية `dashboard.view`، ويجمع في استجابة واحدة:

- المبيعات والطلبات ومتوسط قيمة الطلب والتوزيع حسب الحالة.
- المدفوعات المحصلة والمعلقة والفاشلة والتوزيع حسب الحالة.
- الشحنات المفتوحة والفاشلة والمرتجعة والتوزيع حسب الحالة.
- عدد المرتجعات وقيمة الاسترداد.
- المخزون الموجود والمحجوز والمتاح ومنخفض المخزون.
- التنبيهات التشغيلية: failed jobs، dead-lettered outbox، التنبيهات غير المقروءة، المدفوعات المعلقة، والشحنات المفتوحة.

الفترة الافتراضية هي اليوم الحالي إذا لم يتم إرسال `from` و`to`.

## إدارة العملاء

### قائمة العملاء

```http
GET /api/v1/admin/customers?q=ahmed&status=active&from=2026-09-01&to=2026-09-14&per_page=25
```

يتطلب صلاحية `customers.view`، ويدعم البحث بالاسم والبريد والهاتف، والفلترة بالحالة والتاريخ، مع عدد الطلبات وإجمالي الإنفاق.

### تفاصيل العميل

```http
GET /api/v1/admin/customers/{customer}
```

يعرض بيانات العميل الأساسية، وعدد الطلبات، إجمالي الإنفاق، متوسط قيمة الطلب، المرتجعات، قيمة الاسترداد، عدد المدفوعات، وآخر الطلبات.

## إدارة الطلبات

### قائمة الطلبات

```http
GET /api/v1/admin/orders?q=ahmed&status=processing&payment_status=paid&shipment_status=in_transit&from=2026-09-01&to=2026-09-14&per_page=25
```

يتطلب صلاحية `orders.view`، ويدعم البحث برقم الطلب أو بيانات العميل، والفلترة حسب حالة الطلب والدفع والشحن والفترة الزمنية.

### تفاصيل الطلب

```http
GET /api/v1/admin/orders/{order}
```

تعرض التفاصيل العميل، العناصر، المدفوعات وعملياتها، الشحنات وأحداثها وعملياتها، والمرتجعات وعناصرها. لا يتم كشف سعر الشراء في استجابة الطلب.

## إدارة المخزون

### قائمة المخزون

```http
GET /api/v1/admin/inventory?q=phone&product_id=10&low_stock=1&threshold=5&per_page=25
```

تعرض الكمية الموجودة والمحجوزة والمتاحة، وتدعم البحث بالمنتج أو SKU، والفلترة حسب المنتج أو variant، وعرض المخزون المنخفض.

### تفاصيل عنصر المخزون

```http
GET /api/v1/admin/inventory/{item}
```

### سجل حركات المخزون

```http
GET /api/v1/admin/inventory/{item}/movements?reason=purchase&from=2026-09-01&to=2026-09-14
```

تعرض الحركات، الكمية المتغيرة، الرصيد بعد الحركة، السبب، الملاحظة، والموظف الذي نفذ الحركة.

تتطلب هذه APIs صلاحية `inventory.view`.

## إدارة المدفوعات

### قائمة المدفوعات

```http
GET /api/v1/admin/payments?q=pay-ref-1&status=failed&method=card&from=2026-09-01&to=2026-09-14
```

### تفاصيل الدفع

```http
GET /api/v1/admin/payments/{payment}
```

تعرض بيانات الدفع، الطلب والعميل، محاولات التنفيذ الآمنة، وأحداث Webhook المرتبطة. لا يتم إرجاع request/response payloads الحساسة.

### استثناءات المدفوعات

```http
GET /api/v1/admin/payments/exceptions?status=failed&method=card
```

تعرض المدفوعات الفاشلة أو المتروكة، وعمليات الدفع التي فشلت أو دخلت dead-letter، وأخطاء المحاولة، بالإضافة إلى حالة دوائر حماية مزودي الدفع.

تتطلب هذه APIs صلاحية `payments.view`.
