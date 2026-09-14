# تقارير وتسوية شركات الشحن

أضيفت واجهات إدارية تحت `/api/v1`:

## تقرير شركات الشحن

```http
GET /api/v1/shipping-reports?from=2026-09-01&to=2026-09-14&carrier=Bosta
```

يتطلب صلاحية `shipping.view`. التقرير يجمع عدد الشحنات حسب الحالة، رسوم الشحن، رسوم المرتجعات/الإلغاء، إجمالي COD للشحنات المسلّمة، والمبلغ المتوقع تسويته. الحساب يعتمد على snapshot لحظة الطلب، لذلك لا يتأثر بتعديل إعدادات شركة الشحن لاحقًا.

## إنشاء تسوية

```http
POST /api/v1/shipping-settlements
Content-Type: application/json

{
  "carrier": "Bosta",
  "period_start": "2026-09-01",
  "period_end": "2026-09-14",
  "currency": "EGP",
  "paid_amount": 125000,
  "other_adjustments": -500,
  "provider_reference": "BOSTA-SETTLEMENT-2026-09-14",
  "notes": "Settlement statement reviewed by finance"
}
```

يتطلب صلاحية `shipping.manage`. النظام يعيد حساب التقرير للفترة والشركة، يحفظ snapshot داخل التسوية، ثم يقارن `paid_amount` مع `expected_amount`. الحالة `settled` عند التطابق، و`disputed` عند وجود فرق، مع حفظ قيمة `difference` بالموجب أو السالب.

لعرض التسويات السابقة استخدم:

```http
GET /api/v1/shipping-settlements?carrier=Bosta&status=disputed
```

ويتطلب صلاحية `shipping.reports.view` ويعيد النتائج مع pagination.

> القيمة `expected_amount` = إجمالي COD للشحنات المسلّمة − رسوم الشحن − رسوم الشحنات الملغاة/المرتجعة + التعديلات اليدوية.

## تقرير Shipping Reconciliation التفصيلي

```http
GET /api/v1/shipping-reconciliation?from=2026-09-01&to=2026-09-14&carrier=Bosta
```

يتطلب صلاحية `shipping.reports.view`، ويعرض لكل شركة شحن:

- إجمالي الشحنات والمسلّمة والمرتجعة والملغاة.
- `gross_cod_amount`: قيمة COD المرتبطة بالشحنات المسلّمة أو المرتجعة.
- `collected_cod_amount`: الأموال المحصلة فعليًا من مدفوعات COD ذات الحالات `succeeded` أو `paid` أو `confirmed`.
- `pending_cod_amount`: قيمة COD المتوقع تحصيلها ولم تُسجل كمحصلة.
- قيمة المبالغ المستردة للمرتجعات المعتمدة أو المستلمة أو المكتملة أو المستردة.
- رسوم الشحن ورسوم المرتجعات.
- `expected_amount`: المحصل من COD ناقص الرسوم والمبالغ المستردة.
- التسويات المسجلة للفترة، مع إجمالي المتوقع والمدفوع والفروقات وحالات التسوية.

ويعرض التقرير أيضًا شركات لديها تسويات مسجلة بدون شحنات مطابقة في الفترة من خلال `settlements_without_shipments`، لتسهيل اكتشاف التسويات اليتيمة أو الفترات الخاطئة.

هذه هي **تسوية تشغيلية أولية** وليست قيدًا محاسبيًا عامًا. قبل الاعتماد المالي النهائي، ينبغي إضافة استيراد كشف شركة الشحن، وربط كل سطر برقم التتبع، ومراجعة الضرائب والعمولات والمرتجعات الجزئية من فريق المالية.
