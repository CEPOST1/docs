<?php

return [
    'date' => '2026-10-20',
    'updated' => '2026-11-01',
    'tool' => '/account-age',
    'i18n' => [
        'en' => [
            'title' => 'How to See When a TikTok Account Was Created (Exact Date)',
            'description' => 'TikTok hides account creation dates, but every user ID contains a timestamp. Here is how to find the exact date and time any TikTok account was created.',
            'body' => <<<'HTML'
<p>TikTok shows followers, likes and videos — but not the date an account was created. That date is useful: it helps you spot fake or freshly made accounts, verify a seller or brand, or simply learn how long your favourite creator has been on the platform.</p>
<h2>The secret is in the user ID</h2>
<p>Every TikTok account has a long numeric <strong>user ID</strong>, for example <code>6745191554350760966</code>. TikTok generates these IDs with a “snowflake” algorithm: the first 32 bits of the number are the <strong>Unix timestamp</strong> (seconds since 1 January 1970) of the moment the ID was created. Shift the ID 32 bits to the right and you get the creation time, accurate to the second.</p>
<blockquote>6745191554350760966 &gt;&gt; 32 = 1570487291 → 7 October 2019, 22:28 UTC</blockquote>
<h2>Method 1: use the TokLens account age checker</h2>
<ol>
<li>Open the <a href="/en/account-age">Account Age</a> tool.</li>
<li>Type the username (for example <code>@khaby.lame</code>) or paste the numeric ID.</li>
<li>Press <strong>Check date</strong>. You will see the creation date, time and the age in years, months and days.</li>
</ol>
<p>If you paste a numeric ID, the calculation runs instantly in your browser — nothing is sent anywhere.</p>
<h2>Method 2: check the full profile</h2>
<p>Search any username in the <a href="/en/">Profile Viewer</a>. Under <em>Account details</em> you will find the user ID, the creation date, the account age and — when TikTok provides it — the date the <strong>nickname was last changed</strong>.</p>
<h2>What if the date is “not available”?</h2>
<p>A handful of very early accounts (mostly migrated from musical.ly, including some official accounts) use short IDs that do not contain a timestamp. For those accounts no tool can calculate a reliable creation date.</p>
<h2>Why does this matter?</h2>
<ul>
<li><strong>Spot fakes:</strong> an account claiming to be a long-established brand but created last week is a red flag.</li>
<li><strong>Safer shopping:</strong> check the age of sellers before you pay.</li>
<li><strong>Research:</strong> compare how fast creators grew relative to the age of their accounts.</li>
</ul>
HTML,
        ],
        'ar' => [
            'title' => 'كيف تعرف تاريخ إنشاء حساب تيك توك بالضبط؟',
            'description' => 'يخفي تيك توك تاريخ إنشاء الحسابات، لكن معرّف كل مستخدم يحتوي على طابع زمني. إليك طريقة معرفة التاريخ والوقت الدقيق لإنشاء أي حساب.',
            'body' => <<<'HTML'
<p>يعرض تيك توك عدد المتابعين والإعجابات والفيديوهات، لكنه لا يعرض تاريخ إنشاء الحساب. وهذا التاريخ مفيد جداً: فهو يساعدك على كشف الحسابات الوهمية أو الجديدة، والتحقق من البائعين والعلامات التجارية، أو معرفة منذ متى يتواجد صانع المحتوى المفضل لديك على المنصة.</p>
<h2>السرّ في معرّف المستخدم</h2>
<p>لكل حساب تيك توك <strong>معرّف رقمي طويل (User ID)</strong> مثل <code>6745191554350760966</code>. يولّد تيك توك هذه المعرّفات بخوارزمية «Snowflake»، حيث تمثل أول 32 بت من الرقم <strong>الطابع الزمني Unix</strong> للحظة إنشاء المعرّف. وبإزاحة الرقم 32 بت إلى اليمين نحصل على وقت الإنشاء بدقة الثانية.</p>
<blockquote dir="ltr">6745191554350760966 &gt;&gt; 32 = 1570487291 → 7 October 2019, 22:28 UTC</blockquote>
<h2>الطريقة الأولى: أداة معرفة عمر الحساب في TokLens</h2>
<ol>
<li>افتح أداة <a href="/ar/account-age">عمر الحساب</a>.</li>
<li>اكتب اسم المستخدم (مثل <code>@khaby.lame</code>) أو الصق المعرّف الرقمي.</li>
<li>اضغط <strong>اكشف التاريخ</strong>، وسيظهر لك تاريخ ووقت الإنشاء وعمر الحساب بالسنوات والأشهر والأيام.</li>
</ol>
<p>عند لصق معرّف رقمي يتم الحساب فوراً داخل متصفحك دون إرسال أي بيانات.</p>
<h2>الطريقة الثانية: عرض الحساب كاملاً</h2>
<p>ابحث عن أي اسم مستخدم في <a href="/ar/">عارض الحسابات</a>. ضمن <em>تفاصيل الحساب</em> ستجد المعرّف وتاريخ الإنشاء وعمر الحساب، و<strong>تاريخ آخر تغيير للاسم</strong> عندما يوفّره تيك توك.</p>
<h2>ماذا لو ظهر «غير متوفر»؟</h2>
<p>بعض الحسابات القديمة جداً (غالباً المنقولة من musical.ly وبعض الحسابات الرسمية) تستخدم معرّفات قصيرة لا تحتوي على طابع زمني، ولا يمكن لأي أداة حساب تاريخ إنشائها بدقة.</p>
<h2>لماذا يهمّك ذلك؟</h2>
<ul>
<li><strong>كشف الحسابات المزيفة:</strong> حساب يدّعي أنه علامة تجارية عريقة لكنه أُنشئ الأسبوع الماضي إشارة تحذير.</li>
<li><strong>تسوّق أكثر أماناً:</strong> تحقّق من عمر حساب البائع قبل الدفع.</li>
<li><strong>البحث والتحليل:</strong> قارن سرعة نمو صنّاع المحتوى مع أعمار حساباتهم.</li>
</ul>
HTML,
        ],
        'ckb' => [
            'title' => 'چۆن بزانین هەژماری تیکتۆک کەی دروستکراوە (بەرواری ورد)',
            'description' => 'تیکتۆک بەرواری دروستکردنی هەژمار دەشارێتەوە، بەڵام ناسنامەی هەر بەکارهێنەرێک کاتێکی تێدایە. ئەمە ڕێگای زانینی بەروار و کاتی وردی دروستکردنی هەر هەژمارێکە.',
            'body' => <<<'HTML'
<p>تیکتۆک ژمارەی فۆڵۆوەر و لایک و ڤیدیۆ پیشان دەدات، بەڵام بەرواری دروستکردنی هەژمار پیشان نادات. ئەم بەروارە زۆر بەسوودە: یارمەتیت دەدات هەژماری ساختە یان نوێ بناسیتەوە، فرۆشیار و براندەکان پشتڕاست بکەیتەوە، یان بزانیت دروستکەری دڵخوازت لە کەیەوە لە پلاتفۆرمەکەدایە.</p>
<h2>نهێنییەکە لە ناسنامەی بەکارهێنەردایە</h2>
<p>هەر هەژمارێکی تیکتۆک <strong>ناسنامەیەکی ژمارەیی درێژی (User ID)</strong> هەیە، وەک <code>6745191554350760966</code>. تیکتۆک ئەم ناسنامانە بە ئەلگۆریتمی «Snowflake» دروست دەکات: یەکەم 32 بیتی ژمارەکە <strong>کاتی Unix</strong> ی ساتی دروستبوونەکەیە. بە جوڵاندنی ژمارەکە 32 بیت بۆ ڕاست، کاتی دروستکردن تا ئاستی چرکە دەزانین.</p>
<blockquote dir="ltr">6745191554350760966 &gt;&gt; 32 = 1570487291 → 7 October 2019, 22:28 UTC</blockquote>
<h2>ڕێگای یەکەم: ئامرازی تەمەنی هەژمار لە TokLens</h2>
<ol>
<li>ئامرازی <a href="/ckb/account-age">تەمەنی هەژمار</a> بکەرەوە.</li>
<li>ناوی بەکارهێنەر بنووسە (وەک <code>@khaby.lame</code>) یان ناسنامە ژمارەییەکە دابنێ.</li>
<li><strong>پشکنینی بەروار</strong> دابگرە. بەروار و کاتی دروستکردن و تەمەنی هەژمار بە ساڵ و مانگ و ڕۆژ دەبینیت.</li>
</ol>
<p>ئەگەر ناسنامەیەکی ژمارەیی دابنێیت، هەژمارکردنەکە یەکسەر لە وێبگەڕەکەتدا دەکرێت و هیچ داتایەک نانێردرێت.</p>
<h2>ڕێگای دووەم: بینینی تەواوی هەژمار</h2>
<p>لە <a href="/ckb/">پیشاندەری هەژمار</a> بەدوای هەر ناوێکدا بگەڕێ. لە بەشی <em>وردەکاریی هەژمار</em> ناسنامە، بەرواری دروستکردن، تەمەنی هەژمار و — کاتێک تیکتۆک بیدات — <strong>بەرواری دوایین گۆڕینی ناو</strong> دەبینیت.</p>
<h2>ئەگەر «بەردەست نییە» دەرکەوت؟</h2>
<p>هەندێک هەژماری زۆر کۆن (زۆربەیان لە musical.ly ەوە گوازراونەتەوە) ناسنامەی کورت بەکاردەهێنن کە کاتیان تێدا نییە، بۆیە هیچ ئامرازێک ناتوانێت بەرواری دروستکردنیان بە وردی دەربهێنێت.</p>
<h2>بۆچی گرنگە؟</h2>
<ul>
<li><strong>ناسینەوەی هەژماری ساختە:</strong> هەژمارێک کە دەڵێت براندێکی کۆنە بەڵام هەفتەی ڕابردوو دروستکراوە نیشانەیەکی مەترسییە.</li>
<li><strong>کڕینی پارێزراوتر:</strong> پێش پارەدان تەمەنی هەژماری فرۆشیار بپشکنە.</li>
<li><strong>توێژینەوە:</strong> خێرایی گەشەی دروستکەران بەراورد بکە بە تەمەنی هەژمارەکانیان.</li>
</ul>
HTML,
        ],
        'tr' => [
            'title' => 'TikTok Hesabının Ne Zaman Açıldığı Nasıl Öğrenilir? (Tam Tarih)',
            'description' => 'TikTok hesap açılış tarihlerini gizler, ancak her kullanıcı kimliği bir zaman damgası içerir. Herhangi bir hesabın tam açılış tarihini ve saatini bulmanın yolu.',
            'body' => <<<'HTML'
<p>TikTok takipçi, beğeni ve video sayılarını gösterir ancak hesabın açılış tarihini göstermez. Oysa bu tarih çok faydalıdır: sahte veya yeni açılmış hesapları fark etmenize, satıcıları ve markaları doğrulamanıza ya da sevdiğiniz içerik üreticisinin ne zamandır platformda olduğunu öğrenmenize yardımcı olur.</p>
<h2>Sır kullanıcı kimliğinde</h2>
<p>Her TikTok hesabının <code>6745191554350760966</code> gibi uzun bir <strong>sayısal kullanıcı kimliği</strong> vardır. TikTok bu kimlikleri “snowflake” algoritmasıyla üretir: sayının ilk 32 biti, kimliğin oluşturulduğu anın <strong>Unix zaman damgasıdır</strong>. Kimliği 32 bit sağa kaydırdığınızda açılış zamanını saniyesine kadar elde edersiniz.</p>
<blockquote>6745191554350760966 &gt;&gt; 32 = 1570487291 → 7 Ekim 2019, 22:28 UTC</blockquote>
<h2>Yöntem 1: TokLens hesap yaşı aracı</h2>
<ol>
<li><a href="/tr/account-age">Hesap Yaşı</a> aracını açın.</li>
<li>Kullanıcı adını (örneğin <code>@khaby.lame</code>) yazın veya sayısal kimliği yapıştırın.</li>
<li><strong>Tarihi bul</strong>’a basın. Açılış tarihini, saatini ve hesabın yaşını yıl, ay ve gün olarak görürsünüz.</li>
</ol>
<p>Sayısal kimlik yapıştırırsanız hesaplama anında tarayıcınızda yapılır; hiçbir veri gönderilmez.</p>
<h2>Yöntem 2: Profilin tamamını görüntüleyin</h2>
<p><a href="/tr/">Profil Görüntüleyici</a>’de herhangi bir kullanıcı adını arayın. <em>Hesap ayrıntıları</em> bölümünde kimliği, açılış tarihini, hesap yaşını ve — TikTok sağladığında — <strong>takma adın son değiştirilme tarihini</strong> bulursunuz.</p>
<h2>Tarih “mevcut değil” görünüyorsa?</h2>
<p>Çok eski bazı hesaplar (çoğunlukla musical.ly’den taşınanlar) zaman damgası içermeyen kısa kimlikler kullanır. Bu hesaplar için hiçbir araç güvenilir bir tarih hesaplayamaz.</p>
<h2>Neden önemli?</h2>
<ul>
<li><strong>Sahte hesapları yakalayın:</strong> köklü bir marka olduğunu iddia eden ama geçen hafta açılmış bir hesap tehlike işaretidir.</li>
<li><strong>Daha güvenli alışveriş:</strong> ödeme yapmadan önce satıcının hesap yaşını kontrol edin.</li>
<li><strong>Araştırma:</strong> içerik üreticilerinin büyüme hızını hesap yaşlarıyla karşılaştırın.</li>
</ul>
HTML,
        ],
    ],
];
