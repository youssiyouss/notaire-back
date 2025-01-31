<x-mail::message>
<div class="ar">
<b> السلام عليكم, </b>

 إليك رابط إعادة تعيين كلمة المرور الذي طلبته. يُرجى النقر أدناه لإعادة تعيين كلمة المرور واستعادة الوصول إلى حسابك:
<x-mail::button :url="$verificationUrl">
إعادة تعيين كلمة المرور
</x-mail::button>
سنبقي هذا الرابط صالحًا لمدة 24 ساعة. هل تحتاج إلى رابط جديد؟ لا تقلق. ما عليك سوى طلب إعادة الضبط مرة أخرى على صفحة تسجيل الدخول الخاصة بنا.
<br>
<br>
شكرا,<br>
<b>
    {{ config('app.nameArabe') }}
</b>
</div>
<div class="green-line"></div>

<b>Salam Aleikoum, </b>

Voici le lien de réinitialisation du mot de passe que vous avez demandé. Veuillez cliquer ci-dessous pour réinitialiser votre mot de passe et retrouver l'accès à votre compte:
<x-mail::button :url="$verificationUrl">
Réinitialiser le mot de passe
</x-mail::button>
Nous conserverons ce lien valide pendant 24 heures. Vous en avez besoin d'un nouveau jeton?  Pas de soucis. Il vous suffit de demander une autre réinitialisation sur la page de connexion.
<br><br>
Cordialement,<br>
{{ config('app.name') }}

</x-mail::message>
