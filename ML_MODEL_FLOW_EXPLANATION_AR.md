# شرح الموديل من أول ما الطفل يبدأ اللعب

الملف ده يشرح رحلة البيانات من أول ما الطفل يبدأ اللعبة لحد ما النتيجة تظهر في الـ dashboard، مع توضيح مهم جدًا:

- الموديل لا يحسب `errors` بنفسه.
- اللعبة نفسها هي التي ترسل `errors`.
- الـ backend يجمع هذه البيانات.
- ثم الـ ML model يستخدمها في التنبؤ.

---

## 1. بداية اللعب

عندما يبدأ الطفل لعبة جديدة، التطبيق يرسل طلب `start session` إلى:

- `backend/app/Http/Controllers/Api/ChildApi/SessionController.php`

الدالة المسؤولة:

- `start()`

في هذه الخطوة يتم إنشاء `GameSession` جديد في قاعدة البيانات، ويتم تسجيل:

- `child_id`
- `game_id`
- `level`
- `difficulty_level`
- `started_at`
- `status = in_progress`

يعني هنا فقط نعلن أن الطفل بدأ جلسة لعب جديدة.

---

## 2. أثناء اللعب: كل محاولة Trial

كل مرة الطفل يعمل محاولة داخل اللعبة، التطبيق يجب أن يرسل `submitTrial`.

الدالة المسؤولة:

- `submitTrial()`

الحقول التي يرسلها التطبيق لكل محاولة:

- `trial_number`
- `task_type`
- `difficulty_level`
- `target_type`
- `stimulus_count`
- `reaction_time_ms`
- `correct`
- `errors`
- `missed_targets`
- `duration_sec`
- `metadata` اختياري

---

## 3. ما معنى errors؟

`errors` لا يتم حسابها في الـ backend ولا في الـ ML service.

الذي يحسبها هو منطق اللعبة نفسه داخل Flutter.

يعني مثلًا:

- إذا الطفل ضغط خطأ مرة واحدة قبل الإجابة الصحيحة: `errors = 1`
- إذا أخطأ مرتين: `errors = 2`
- إذا جاوب صح من أول مرة: `errors = 0`

إذن:

- `errors` = عدد الأخطاء داخل نفس المحاولة
- وليست عدد الليفلات
- وليست عدد الجلسات

---

## 4. كيف يخزن الـ backend هذه المحاولة؟

داخل `submitTrial()` يقوم Laravel بحفظ المحاولة في جدول `session_trials`.

يتم حفظ القيم كما وصلته من اللعبة، خصوصًا:

- `reaction_time_ms`
- `correct`
- `errors`
- `missed_targets`
- `duration_sec`

يعني الـ backend هنا لا يغير الرقم ولا يعيد حسابه، فقط يخزنه.

---

## 5. نهاية اللعبة End Session

عندما ينتهي الطفل من اللعبة، التطبيق يرسل `end session`.

الدالة المسؤولة:

- `end()`

هنا الـ backend يبدأ في تجميع كل المحاولات الخاصة بهذه الجلسة.

ويحسب:

- `totalTrials` = عدد المحاولات
- `correctCount` = عدد المحاولات الصحيحة
- `errorsCount` = مجموع `errors` في كل المحاولات
- `missedCount` = مجموع `missed_targets`
- `accuracy = correctCount / totalTrials * 100`
- `avgRT` = متوسط `reaction_time_ms`
- `duration` = مجموع `duration_sec`

مهم جدًا:

- `errorsCount` هو مجموع كل الأخطاء في الجلسة كلها
- وليس متوسط الأخطاء

مثال:

إذا عندنا 3 محاولات:

- المحاولة 1: `errors = 0`
- المحاولة 2: `errors = 2`
- المحاولة 3: `errors = 1`

إذن:

- `errorsCount = 3`

---

## 6. النجوم Stars تتحسب إزاي؟

إذا التطبيق لم يرسل `stars` بنفسه، الـ backend يحسبها من `accuracy` داخل:

- `calculateStars()`

القواعد الحالية:

- إذا `accuracy >= 90` => `3 stars`
- إذا `accuracy >= 70` => `2 stars`
- إذا `accuracy >= 50` => `1 star`
- إذا أقل من `50` => `0 star`

لكن لو Flutter أرسل `stars` في `end session`، فالـ backend سيأخذها كما هي.

---

## 7. تجهيز البيانات للـ ML

بعد حساب ملخص الجلسة، الـ backend يدخل إلى:

- `runPrediction()`

وهنا يحول كل محاولة إلى شكل يفهمه الموديل:

- `User_ID`
- `Trial_ID`
- `Task_Type`
- `Stimulus_Count`
- `Difficulty_Level`
- `Target_Type`
- `Reaction_Time_ms`
- `Correct`
- `Errors`
- `Missed_Targets`
- `Session_Duration_sec`

ثم يرسل هذه القائمة إلى `MlService`.

---

## 8. دور MlService

الملف:

- `backend/app/Services/MlService.php`

الدالة الرئيسية:

- `plan()`

الترتيب الحالي في التنفيذ:

1. إذا النظام مضبوط على `local fallback` يستخدم القواعد المحلية مباشرة
2. إذا يوجد `n8n webhook` يحاول استخدامه
3. إذا فشل، يرسل إلى `ml_service /plan`
4. إذا فشل كل ذلك، يعود إلى `fallbackPlan()`

يعني عندكم أكثر من مسار للتنبؤ، وليس مسارًا واحدًا فقط.

---

## 9. الـ ML service الحقيقي

الملف:

- `ml_service/api.py`

شكل الـ input معرف في:

- `class Trial`

وهو يحتوي على:

- `Errors`
- `Missed_Targets`
- `Reaction_Time_ms`
- `Correct`
- وباقي الخصائص

إذن الموديل يتوقع أن هذه القيم تأتي من الـ backend جاهزة.

---

## 10. ماذا يحدث داخل ml_service؟

عند استدعاء:

- `POST /plan`

يحصل الآتي:

1. تحويل البيانات إلى `DataFrame`
2. التحقق من القيم النصية المسموح بها
3. عمل `one-hot encoding` للحقول النصية
4. إعادة ترتيب الأعمدة بنفس ترتيب التدريب
5. تطبيق `StandardScaler`
6. إدخال البيانات إلى الموديل

الدوال المهمة:

- `encode()`
- `aggregate_predictions()`

---

## 11. نوع الموديل المستخدم

في التدريب داخل:

- `ml_service/train.py`

الموديل هو:

- `LogisticRegression`

يعني هو ليس Deep Learning، وليس Neural Network، وليس LSTM.

هو موديل تصنيف تقليدي يعتمد على Features رقمية ومشفرة.

في التدريب يتم استخدام:

- `StandardScaler`
- `train_test_split`
- `predict_proba`

---

## 12. هل errors تدخل فعلاً في الموديل؟

نعم.

من `metadata.json` الحالية:

- `Errors` موجودة فعلًا داخل `selected_features`

لكن في النسخة الحالية من الموديل:

- `Reaction_Time_ms` ليست ضمن الـ selected features المحفوظة
- `Missed_Targets` ليست ضمن الـ selected features المحفوظة

هذا يعني:

- `Errors` تؤثر مباشرة في التنبؤ الحالي
- بينما `Reaction_Time_ms` و `Missed_Targets` قد تكون موجودة في الـ input لكن غير مستخدمة مباشرة في الموديل المحفوظ الحالي

وده مهم جدًا في التفسير.

---

## 13. الموديل يطلع النتيجة إزاي؟

داخل:

- `aggregate_predictions()`

الموديل لا يحكم على الجلسة من محاولة واحدة.

بل يعمل:

1. `predict_proba` لكل محاولة منفصلة
2. يحسب متوسط الاحتمالات لكل المحاولات
3. أعلى متوسط هو الذي يحدد النتيجة النهائية

النتيجة النهائية تكون:

- `normal`
أو
- `visual_disorder`

إذن القرار النهائي مبني على متوسط كل المحاولات في الجلسة.

---

## 14. كيف تؤثر errors على التنبؤ؟

تؤثر بطريقتين:

### أولًا: داخل الموديل نفسه

لأن `Errors` موجودة في الـ selected features، فالموديل يراها مباشرة عند التنبؤ.

بمعنى:

- زيادة الأخطاء غالبًا تدفع النتيجة ناحية `visual_disorder`

### ثانيًا: في تحليل weak skills

داخل `api.py` توجد دالة:

- `compute_trial_score()`

وقاعدتها:

- إذا كانت المحاولة صحيحة بدون أخطاء وبدون missed targets => `100`
- غير ذلك:
  - `penalty = 20 * Errors + 25 * Missed_Targets`
  - `base = 100` إذا كانت صحيحة
  - `base = 40` إذا كانت خاطئة
  - `score = base - penalty`

يعني كل خطأ ينقص `20` درجة من درجة المحاولة.

وكل `missed target` ينقص `25`.

بعدها:

- إذا `score < 60`

فإن هذه المحاولة تعتبر ضعيفة، ويتم إضافتها إلى `weak_skills` حسب نوع المهمة.

---

## 15. كيف يتم تحديد weak skills؟

الدالة:

- `get_weak_skills()`

تعمل كالتالي:

1. تحدد نوع المهارة بناءً على `Task_Type`
2. تحسب score لكل trial
3. أي trial أقل من `score_threshold = 60` تعتبر weak
4. تجمع عدد التكرارات لكل skill

مثال:

إذا الطفل أخطأ كثيرًا في مهام `Matching`

فقد تظهر:

- `Visual Matching: 5`

يعني عنده 5 محاولات ضعيفة في هذه المهارة.

---

## 16. ماذا يحدث إذا كانت النتيجة Visual Disorder؟

إذا رجع التنبؤ:

- `visual_disorder`

فالـ backend يحفظ `VisualPrediction` في قاعدة البيانات.

ثم ينشئ notification لولي الأمر:

- `visual_disorder_alert`

ومعها رسالة تطلب مراجعة مختص.

---

## 17. ماذا يحدث إذا تعطل الـ ML service؟

هنا يدخل `fallbackPlan()` في `MlService.php`.

وفيه قواعد محلية بديلة.

القواعد الحالية تعتبر الحالة `visual_disorder` إذا تحقق واحد من الآتي:

- `accuracy < 60`
- أو `totalErrors >= نصف عدد المحاولات تقريبًا`
- أو `totalMissed` مرتفع
- أو `avgReaction > 2500`

إذن `errors` أيضًا مهمة جدًا في الـ fallback، وليس فقط في الموديل.

---

## 18. مثال عملي بسيط

نفترض أن الطفل لعب 4 محاولات:

### trial 1

- `correct = 1`
- `errors = 0`
- `missed_targets = 0`

النتيجة:

- score = 100

### trial 2

- `correct = 1`
- `errors = 1`
- `missed_targets = 0`

النتيجة:

- base = 100
- penalty = 20
- score = 80

### trial 3

- `correct = 0`
- `errors = 1`
- `missed_targets = 0`

النتيجة:

- base = 40
- penalty = 20
- score = 20

### trial 4

- `correct = 0`
- `errors = 2`
- `missed_targets = 1`

النتيجة:

- base = 40
- penalty = 40 + 25 = 65
- score = 0

ملخص الجلسة:

- `totalTrials = 4`
- `correctCount = 2`
- `accuracy = 50%`
- `errorsCount = 4`
- `missedCount = 1`

وهذا قد يدفع الجلسة ناحية الضعف أو الاضطراب حسب بقية الإشارات.

---

## 19. الخلاصة النهائية

الموديل يعمل كالتالي:

1. الطفل يبدأ جلسة لعب
2. كل محاولة تُرسل إلى الـ backend
3. اللعبة نفسها تحسب `errors`
4. الـ backend يخزن كل محاولة
5. في نهاية الجلسة يحسب:
   - accuracy
   - total errors
   - total missed
   - average reaction time
6. ثم يرسل كل المحاولات إلى الـ ML service
7. الموديل يستخدم Features من بينها `Errors`
8. يحسب احتمالات لكل trial
9. يأخذ متوسط الاحتمالات لكل الجلسة
10. يخرج النتيجة النهائية:
    - `normal`
    - أو `visual_disorder`
11. وإذا ظهرت مؤشرات ضعف، يتم استخراج `weak_skills` و `training_plan`

---

## 20. أهم نقطة لازم تكون واضحة

إذا كانت قيمة `errors` المرسلة من اللعبة غير صحيحة، فالتنبؤ كله قد يصبح غير دقيق.

لذلك دقة الـ ML هنا تعتمد بقوة على:

- دقة تسجيل الأخطاء من اللعبة
- دقة `correct`
- دقة `trial_number`
- دقة `stimulus_count`
- دقة `reaction_time_ms`

بمعنى أوضح:

إذا الـ game logic غلط في إرسال `errors`، فالموديل نفسه حتى لو ممتاز سيعطي نتيجة مضللة.

---

## 21. الملفات الأساسية في هذا المسار

- `backend/app/Http/Controllers/Api/ChildApi/SessionController.php`
- `backend/app/Services/MlService.php`
- `ml_service/api.py`
- `ml_service/train.py`
- `ml_service/artifacts/metadata.json`

