# Paymob Payment Provider

يعتمد المشروع على **Paymob Unified Checkout** كمزود الدفع الإلكتروني الأساسي. يوجد مسار بديل لـ Kashier، لكن Paymob هو المسار الموثق والمختبر هنا للسوق المصري.

## المتطلبات

يجب إنشاء حساب تاجر في بيئة Paymob التجريبية والحصول على `Secret Key` و`Public Key` و`HMAC Secret` وIntegration ID متوافق مع حالة المفتاح (`Test`). لا تُحفظ هذه القيم في Git.

أضف القيم التالية إلى `.env`:

```dotenv
PAYMOB_ENABLED=true
PAYMOB_BASE_URL=https://accept.paymob.com
PAYMOB_SECRET_KEY=replace-with-test-secret-key
PAYMOB_PUBLIC_KEY=replace-with-test-public-key
PAYMOB_HMAC_SECRET=replace-with-test-hmac-secret
PAYMOB_INTEGRATION_IDS=replace-with-test-integration-id
PAYMOB_NOTIFICATION_URL=https://your-public-host.example/api/v1/webhooks/paymob
PAYMOB_REDIRECTION_URL=https://your-public-host.example/payment/return
PAYMOB_TIMEOUT=15
```

يجب أن يكون `PAYMOB_NOTIFICATION_URL` متاحًا عبر HTTPS من الإنترنت؛ لا يستطيع Paymob إرسال callback إلى `localhost`. بعد تشغيل التطبيق، امسح cache الإعدادات عبر `php artisan config:clear`.

## دورة الدفع

1. ينشئ `CreatePayment` سجل الدفع محليًا مع idempotency key.
2. ينفذ `PaymobGateway::createPayment` طلب Intention Creation باستخدام المبلغ بوحدة القروش كما يتطلب Paymob.
3. يُعاد `payment_url` في response، وهو رابط Unified Checkout المبني من `client_secret`.
4. يرسل Paymob transaction callback إلى `POST /api/v1/webhooks/paymob`.
5. يتحقق `PaymobWebhookVerifier` من HMAC-SHA512 بالترتيب الرسمي للحقول، ثم يحدّث الدفع والطلب داخل transaction idempotent.
6. يستخدم refund endpoint في Paymob بعد توفر transaction ID المؤكد في callback.

لا يُسمح بتأكيد Paymob يدويًا من endpoint الإدارة؛ التأكيد يتم من callback أو reconciliation حتى لا يصبح سجل المتجر مختلفًا عن حالة المزود.

## الاختبار المحلي

يمكن تشغيل الاختبارات دون استدعاء Paymob الحقيقي:

```bash
php artisan test tests/Unit/PaymobWebhookVerifierTest.php tests/Feature/PaymentApiTest.php
```

للاختبار التجريبي الحقيقي، استخدم مفاتيح Test وIntegration ID من حساب Paymob التجريبي، ثم استخدم أداة webhook الرسمية أو عملية checkout كاملة عبر `payment_url`. لا تستخدم مفاتيح Live في بيئة التطوير.

## مراجع

[1]: https://developers.paymob.com/paymob-docs/intention-apis/create-intention "Paymob Create Intention"
[2]: https://developers.paymob.com/paymob-docs/developers/webhook-callbacks-and-hmac/hmac/hmac-transaction-callback "Paymob Transaction Callback HMAC"
[3]: https://developers.paymob.com/paymob-docs/developers/manage-payment-apis/refund "Paymob Refund"
