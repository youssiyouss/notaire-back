<x-mail::message>
<div class="ar">
<b>مرحبًا, </b>

تلقيت رسالة جديدة من نموذج الاتصال على موقعك:

<div style="background: #f8f9fa; padding: 20px; border-right: 4px solid #8d50fb; margin: 20px 0; border-radius: 5px;">

**الاسم الكامل:** {{ $contactData['name'] }}

**البريد الإلكتروني:** [{{ $contactData['email'] }}](mailto:{{ $contactData['email'] }})

**رقم الهاتف:** [{{ $contactData['phone'] }}](tel:{{ $contactData['phone'] }})

**الموضوع:** {{ $contactData['subject'] }}

**الرسالة:**

{{ $contactData['message'] }}

</div>

يمكنك الرد مباشرة على هذا البريد الإلكتروني للتواصل مع العميل.

<br>
<br>
مع أطيب التحيات,<br>
<b>
    {{ config('app.nameArabe') }}
</b>
</div>
<div class="green-line"></div>

<b>Bonjour, </b>

Vous avez reçu un nouveau message depuis le formulaire de contact de votre site web:

<div style="background: #f8f9fa; padding: 20px; border-left: 4px solid #8d50fb; margin: 20px 0; border-radius: 5px;">

**Nom complet:** {{ $contactData['name'] }}

**Email:** [{{ $contactData['email'] }}](mailto:{{ $contactData['email'] }})

**Téléphone:** [{{ $contactData['phone'] }}](tel:{{ $contactData['phone'] }})

**Sujet:** {{ $contactData['subject'] }}

**Message:**

{{ $contactData['message'] }}

</div>

Vous pouvez répondre directement à cet email pour contacter le client.

<br><br>
Cordialement,<br>
{{ config('app.name') }}

</x-mail::message>
