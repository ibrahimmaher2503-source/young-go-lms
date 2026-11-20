# طلب O-003: المطلوب لفتح نقل البيانات (P10)

المصدر: أسئلة برومبت 11 والـ `ASSUMPTION` في [MAPPING_DRAFT.md](MAPPING_DRAFT.md).
الملف ده متقسّم جزئين:
- **الجزء أ**: قرارات تقدر تاخدها إنت النهارده من غير ما حد يفتح السيرفر القديم.
- **الجزء ب**: حقائق محتاجة حد عنده صلاحية على السيرفر أو قاعدة البيانات، يعني المطوّر القديم أو شركة الاستضافة. ابعتله الجزء ب زي ما هو.

---

## الجزء أ: قرارات من المالك (من غير وصول للسيرفر)

علّم على اختيار واحد في كل بند. الاختيار المكتوب عليه **(مقترح)** هو اللي هيتنفّذ لو مردّتش.

### A1. الدروس المدفوعة المستضافة برّه (YouTube / Vimeo / Google Drive / روابط Wasabi = مستوى T3)
القاعدة R-014 بتقول إن الدرس المدفوع لازم يكون محمي (T1 أو T2).
- [ ] **(مقترح)** نعيد رفعها على Bunny Stream (T1) أثناء النقل. ده بيتطلب إن الملفات الأصلية أو روابط التحميل تكون متاحة.
- [ ] نسيبها زي ما هي كاستثناء موثّق لكل كورس، والحماية هتبقى أضعف.
- [ ] نحوّل الكورسات دي لمجانية.

### A2. كلمات سر المستخدمين القديمة
- [ ] **(مقترح)** لو طلع إن التشفير bcrypt (`$2y$`)، ننقله والمستخدم يدخل بنفس كلمة السر. لو أي نوع تاني (md5/sha1)، الحساب يتنقل من غير كلمة سر ويوصله رابط تعيين كلمة سر.
- [ ] الكل يعيّن كلمة سر جديدة أول مرة يدخل، بغض النظر عن نوع التشفير.

### A3. سجل مستحقات المدرسين القديمة (جدول `payout`)
- [ ] **(مقترح)** يتنقل كسجل تاريخي للقراءة بس، وما يدخلش في أرصدة المدرسين الجديدة.
- [ ] يتنقل كقيود مسوّاة في دفتر المدرس الجديد.

### A4. نطاق النقل (تأكيد O-018)
الكورسات والمحتوى إجباري. المستخدمين والتسجيلات والتقدم والشهادات ونتائج الاختبارات وسجل الطلبات = نعم. محادثات الـ AI = لا.
- [ ] موافق
- [ ] عايز أغيّر: ____

### A5. معلومات ممكن تكون عارفها من غير السيرفر
- العملة اللي كانت الأسعار بتتسجّل بيها: جنيه أم دولار؟ وهل السعر كان شامل الضريبة؟ ____
- مدة صلاحية الكورس اللي كانت بتتكتب في لوحة التحكم القديمة: شهور أم أيام؟ ____
- الدومين الحقيقي للمنصة القديمة (O-001). `youngschool.com` موقع أمريكي مالوش علاقة. ____
- الإضافات اللي كانت متفعّلة: البث المباشر، الشهادات، الواجبات، الدفع اليدوي/إنستاباي، الباقات، الاشتراكات؟ ____

---

## الجزء ب: للي عنده صلاحية على السيرفر القديم

> يا ريت تبعت المطلوب ده. **كله قراءة بس، ومفيش أي تعديل على قاعدة البيانات أو الملفات.**

### B1. التسليمات الأساسية (أهم حاجة)
1. **نسخة كاملة من قاعدة البيانات** (`mysqldump --single-transaction --routines --no-tablespaces <db>`)، ومعاها تاريخ أخذ النسخة.
2. **كود الموقع المنشور حاليًا**، أو على الأقل الملفات دي: `application/config/routes.php` و`.htaccess` وفولدر `application/` وقايمة الإضافات اللي في `addons`.
3. **قايمة بكل الملفات المرفوعة** (Storage Manifest)، بالمسار والحجم:
   ```
   find /path/to/uploads -type f -printf '%p\t%s\n' > storage_manifest.tsv
   ```
   ولو الملفات على S3 أو Wasabi: اسم الـ bucket، و`aws s3 ls --recursive s3://<bucket> > storage_manifest.txt`.
4. **إيه اللي ورا `academy_cloud`**: اسم مزوّد الفيديو، وهل فيه طريقة نحمّل بيها الفيديوهات الأصلية.

### B2. لو مقدرتش تبعت نسخة كاملة، شغّل الاستعلامات دي وابعت نتايجها
```sql
-- إصدار المنصة والإعدادات
SELECT `key`, `value` FROM settings WHERE `key` IN ('version','system_currency','currency_position','language');

-- مخطط الجداول اللي مش في المثبّت الأصلي
SHOW TABLES;
SHOW CREATE TABLE live_class;      -- لو موجود
SHOW CREATE TABLE certificates;    -- لو موجود
SHOW CREATE TABLE offline_payment; -- لو موجود
SHOW CREATE TABLE users;
SHOW CREATE TABLE course;
SHOW CREATE TABLE lesson;

-- القيم الفعلية للحقول
SELECT status, course_type, level, language, COUNT(*) FROM course GROUP BY 1,2,3,4;
SELECT expiry_period, COUNT(*) FROM course GROUP BY 1 ORDER BY 2 DESC LIMIT 20;
SELECT faqs, outcomes, requirements FROM course WHERE faqs <> '' LIMIT 3;

-- نوع تشفير كلمات السر (أول 4 حروف بس، مش الهاش كامل)
SELECT LEFT(password,4) AS prefix, LENGTH(password) AS len, COUNT(*) FROM users GROUP BY 1,2;

-- الأدوار والحسابات من غير إيميل والأرقام اللي مش مصرية
SELECT * FROM role;
SELECT COUNT(*) FROM users WHERE email IS NULL OR email = '';
SELECT email, COUNT(*) FROM users GROUP BY email HAVING COUNT(*) > 1 LIMIT 20;
SELECT LEFT(phone,4) AS prefix, COUNT(*) FROM users GROUP BY 1 ORDER BY 2 DESC LIMIT 20;

-- المنح اليدوي مقابل الشراء
SELECT e.gifted_by, (p.id IS NOT NULL) AS has_payment, COUNT(*)
FROM enrol e LEFT JOIN payment p ON p.user_id = e.user_id AND p.course_id = e.course_id
GROUP BY 1,2;
SELECT payment_type, COUNT(*), MIN(amount), MAX(amount), SUM(tax) FROM payment GROUP BY 1;

-- الاختبارات
SELECT options, correct_answers FROM question LIMIT 5;
SELECT total_obtained_marks FROM quiz_results LIMIT 10;

-- الشهادات
SELECT * FROM certificates LIMIT 3;

-- مصادر الفيديو
SELECT video_type, lesson_type, COUNT(*) FROM lesson GROUP BY 1,2;
```

### B3. أسئلة نصية للمطوّر القديم
1. فيه أي حقول أو جداول اتضافت يدوي على قاعدة الإنتاج؟ زي تاريخ الميلاد، أو ربط ولي الأمر بالأبناء، أو `audio_url_for_mobile_application`.
2. العربي والإنجليزي بيتخزنوا في نفس العمود ولا في جداول ترجمة منفصلة؟
3. لو `quiz_results` و`watch_histories.quiz_result` اختلفوا، أنهي واحد فيهم الصح؟
4. فيه سجل لموافقات إنستاباي أو للمنح اليدوي من الإدارة؟
5. الروابط بتاعة `/home/course/{slug}/{id}` و`/category/{slug}` و`/certificate/{token}` شغالة كده فعلًا، ولا فيه rewrite rules مخصصة؟

---

## بعد ما الرد يوصل
- الجزء أ هيتسجّل في `docs/07_DECISION_REGISTER.md`، وهيتشال الـ `ASSUMPTION` المقابل له من `MAPPING_DRAFT.md`.
- الجزء ب هيفتح T-P10-01. الـ framework جاهز فعلًا (`legacy:import --dry-run` و`legacy:verify`)، فأول تشغيل حقيقي هيبقى مجرد تغيير config لاتصال `legacy`.
