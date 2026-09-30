@props(['type' => 'info'])
<div {{ $attributes->class(['melding', 'melding-'.$type]) }} role="{{ $type === 'fout' ? 'alert' : 'status' }}">{{ $slot }}</div>
