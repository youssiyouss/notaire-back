<x-mail::message>
# 🗓️ {{ __('Date de Signature Confirmée') }}

{{ __('Bonjour') }} **{{ $clientName }}**,

{{ __('Nous avons le plaisir de vous informer que la date de signature de votre contrat a été fixée.') }}

<x-mail::panel>
**{{ __('Numéro de contrat') }}:** {{ $contractNumber }}  
**{{ __('Notaire responsable') }}:** {{ $notary }}
</x-mail::panel>

<x-mail::panel>
## 📅 {{ $signatureDate }}
</x-mail::panel>

{{ __('Veuillez vous présenter à notre bureau à la date et l\'heure indiquées avec tous les documents nécessaires.') }}


{{ __('Si vous avez des questions ou si vous avez besoin de reporter ce rendez-vous, n\'hésitez pas à nous contacter.') }}

<x-mail::button :url="config('app.frontend_url', 'http://localhost:4200')">
{{ __('Se Connecter') }}
</x-mail::button>

{{ __('Cordialement') }},  
**{{ __('L\'équipe du bureau notarial') }}**

<small>{{ __('Cet email a été envoyé automatiquement, merci de ne pas y répondre directement.') }}</small>
</x-mail::message>
