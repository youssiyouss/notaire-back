<x-mail::message>

<div class="ar">
<b>مرحبًا بكم في منصتنا !</b>
يرجى تأكيد بريدك الإلكتروني أدناه لتفعيل حسابك
<x-mail::button :url="$verificationUrl">
تأكيد البريد الإلكتروني
</x-mail::button>
سنبقي هذا الرابط صالحًا لمدة 24 ساعة.<br>
شكرا,<br>

<b>
{{ config('app.nameArabe') }}
</b>
</div>

<div class="green-line"></div>

<b>Bonjour et bienvenue à bord!</b>

Veuillez confirmer votre adresse e-mail ci-dessous afin d'activer votre compte
<x-mail::button :url="$verificationUrl">
Confirmer l'adresse e-mail
</x-mail::button>
Nous conserverons ce lien valide pendant 24 heures.

Merci,<br>

{{ config('app.name') }}


</x-mail::message>
