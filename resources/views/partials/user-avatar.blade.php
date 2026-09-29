{{-- The inside of an account's avatar: their picture (chosen, or Google's),
     else the given letters. Goes inside whatever circle the page draws. A
     picture that fails to load gives way to the letters. $user, $text. --}}
@php $url = $user?->avatarUrl(); @endphp
@if($url)
    <img class="u-av-img" src="{{ $url }}" alt="" referrerpolicy="no-referrer" loading="lazy"
         onerror="this.nextElementSibling.hidden = false; this.remove();"><span hidden>{{ $text }}</span>
@else
    {{ $text }}
@endif
