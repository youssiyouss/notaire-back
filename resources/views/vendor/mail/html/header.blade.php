@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" class="logo-header">
            <img src="{{ url('imgs/Logo horizentale AinSbaa.png') }}" class="logo" alt="Logo Notaire">
            <p>{{ $slot }}</p>
        </a>
    </td>
</tr>
