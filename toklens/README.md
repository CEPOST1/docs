# TokLens — TikTok Profile Viewer (PHP)

موقع متكامل لجلب المعلومات العامة لحسابات تيك توك: المتابعون، الإعجابات، تاريخ إنشاء الحساب، تاريخ تغيير الاسم، الفيديوهات، الستوري، وتحميل الفيديو بدون علامة مائية — بتصميم **Liquid Glass** و 8 لغات.

A complete PHP website for public TikTok data: profile stats, account creation date, nickname-change date, videos, stories and watermark-free downloads. Liquid Glass UI, 8 languages, SEO-ready, no database.

---

## المميزات / Features

| | |
|---|---|
| **الأدوات** | عارض الحسابات · فاحص عمر الحساب (من الـ User ID) · عارض الستوري المجهول · تحميل الفيديو HD/MP4/MP3 · صور الكاروسيل |
| **اللغات** | العربية، English، کوردی (سۆرانی)، Kurdî (Kurmancî)، Türkçe، فارسی، Français، Español — مع دعم RTL كامل |
| **SEO** | روابط لكل لغة (`/ar/…`)، hreflang، canonical، sitemap.xml مع البدائل، robots.txt، Open Graph، JSON-LD (WebSite, WebApplication, FAQPage, BlogPosting, ProfilePage, Breadcrumb)، 5 مقالات × 4 لغات + نصوص SEO وأسئلة شائعة لكل أداة |
| **التصميم** | Liquid Glass (backdrop-filter + انكسار SVG حقيقي في Chrome)، وضع فاتح/داكن، شريط سفلي زجاجي للجوال، View Transitions، Scroll-driven animations، Popover API، `<dialog>`، PWA |
| **الأمان** | انظر القسم أدناه |

## المتطلبات / Requirements

- PHP **8.1+** مع الإضافات: `curl`, `json`, `mbstring` (و `intl` اختيارية لتنسيق التواريخ بكل لغة)
- Apache (مع `mod_rewrite`) أو Nginx
- **لا يحتاج قاعدة بيانات**

## التثبيت / Installation

1. ارفع مجلد `toklens` كاملاً إلى الاستضافة.
2. **الأفضل:** اجعل جذر الدومين (Document Root) يشير إلى مجلد `toklens/public`.
   إذا كانت الاستضافة لا تسمح بذلك، ارفع المحتوى إلى `public_html` وسيتكفّل ملف `.htaccess` الرئيسي بتحويل كل شيء إلى `public/` ومنع الوصول للمجلدات الحساسة.
3. اعطِ صلاحية الكتابة لمجلد `storage/` (مثلاً `chmod -R 750 storage`).
4. عدّل `app/config.php`:
   - `base_url` ← رابط موقعك مثل `https://example.com` (مهم للـ SEO والأمان)
   - `contact_email`
   - `trust_proxy` ← `true` فقط إذا كنت تستخدم Cloudflare
   - `google_site_verification` (اختياري)
5. فعّل HTTPS ثم أزل التعليق عن سطور "Force HTTPS" في `public/.htaccess`.
6. أرسل `https://example.com/sitemap.xml` إلى Google Search Console.

لـ Nginx راجع `nginx.conf.example`.

### تجربة محلية / Local preview

```bash
cd toklens
TOKLENS_MOCK=1 php -S 127.0.0.1:8080 -t public public/index.php   # بيانات تجريبية
php -S 127.0.0.1:8080 -t public public/index.php                  # بيانات حقيقية
```

## مصادر البيانات / Data sources — مهم

تيك توك لا يوفّر API عامة رسمية لهذه البيانات، لذلك:

- **معلومات الحساب:** تُقرأ أولاً من صفحة الحساب العامة على tiktok.com (تتضمن `nickNameModifyTime`)، وإن فشلت يُستخدم `tikwm.com` كبديل.
- **تاريخ الإنشاء:** يُحسب رياضياً من الـ User ID (`id >> 32`) — لا يحتاج أي طلب.
- **الفيديوهات / تحميل الفيديو / الستوري:** عبر API العامة لـ `tikwm.com` (الخطة المجانية ~ طلب/ثانية، والموقع ينظّم الطلبات تلقائياً ويخزّنها مؤقتاً).

ملاحظات صريحة:
- تيك توك قد يحجب عناوين IP الخاصة بمراكز البيانات أو يغيّر بنية صفحاته. عندها استخدم `outbound_proxy` في الإعدادات (بروكسي سكني) أو أضف مزوّد بيانات مدفوع بإنشاء كلاس جديد يطبّق `App\TikTok\Provider`.
- endpoint الستوري قابل للتغيير من `providers.stories_endpoint`.
- مع زيارات كبيرة ستحتاج مزوّداً مدفوعاً لأن الخطة المجانية محدودة.

## الأمان / Security

- **لا قاعدة بيانات ولا نماذج POST** → لا SQL Injection ولا CSRF على عمليات تغيّر الحالة (كل المسارات GET/HEAD فقط، وغيرها 405).
- **CSP صارم** بـ nonce لكل طلب (بدون `unsafe-inline`)، مع HSTS و X-Frame-Options و nosniff و Referrer-Policy و Permissions-Policy و COOP.
- **ترميز كل المخرجات** (`e()` = `htmlspecialchars`)، و JSON آمن داخل `<script>`، والـ JavaScript يبني الواجهة بـ `textContent` فقط (لا `innerHTML` لبيانات خارجية) ويقبل فقط روابط الوسيط الموقّعة.
- **حماية SSRF:** كل طلب صادر (وكل إعادة توجيه) يجب أن يطابق قائمة نطاقات مسموحة، HTTPS فقط، بلا IP مباشر أو منافذ غريبة، مع حدود للحجم والوقت.
- **وسيط الوسائط موقّع بـ HMAC** مع تاريخ انتهاء → لا يمكن استخدام موقعك كبروكسي مفتوح. يتحقق من نوع المحتوى (صور/فيديو/صوت فقط) ويرسل `CSP: sandbox`.
- **الـ API محمية** برمز HMAC مرتبط بالوقت والمتصفح + فحص Same-Origin → لا يمكن لمواقع أخرى استخدام الـ API.
- **Rate limiting** لكل IP (مخزّن كـ hash وليس IP خام) لكل نوع طلب.
- **التحقق من المدخلات:** أسماء المستخدمين بنمط صارم `[A-Za-z0-9._]{2,24}`، والروابط يجب أن تكون من `tiktok.com`.
- الكاش يخزّن JSON فقط (لا `unserialize`)، الأخطاء لا تُعرض للزوار وتُسجّل في `storage/logs`، ومجلدات الكود محمية بـ `.htaccess`.
- مفتاح التوقيع يُولَّد تلقائياً في `storage/.app_key` (لا تشاركه).

## الهيكل / Structure

```
toklens/
├── public/            ← جذر الموقع (index.php, assets, manifest, sw.js)
├── app/
│   ├── config.php     ← الإعدادات
│   └── src/           ← App (router), Security, Http, Cache, RateLimiter, I18n, Seo, Articles, TikTok/*
├── templates/         ← layout + pages + partials
├── lang/              ← ملفات الترجمة (en.php هو المرجع)
├── content/articles/  ← المقالات (ملف لكل مقال، بعدة لغات)
└── storage/           ← cache / ratelimit / logs (يجب أن يكون قابلاً للكتابة)
```

### إضافة لغة / Add a language
انسخ `lang/en.php` إلى `lang/<code>.php`، ترجمه، ثم أضف سطراً في `App\I18n::LANGS`.

### إضافة مقال / Add an article
أنشئ `content/articles/<slug>.php` بنفس شكل المقالات الموجودة. يظهر تلقائياً في القائمة والـ sitemap مع hreflang.

---
TokLens is not affiliated with TikTok or ByteDance. Use responsibly and respect creators' rights.
