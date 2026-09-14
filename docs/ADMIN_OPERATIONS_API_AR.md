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
