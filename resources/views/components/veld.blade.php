@props(['naam','label','type' => 'text','waarde' => '', 'foutsleutel' => null])
@php($sleutel = $foutsleutel ?? $naam)
<div class="form-veld"><label for="{{ $naam }}">{{ $label }}</label><input id="{{ $naam }}" name="{{ $naam }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($sleutel, $waarde) }}" {{ $attributes }} @if($errors->has($sleutel)) aria-invalid="true" aria-describedby="{{ $naam }}-fout" @endif>
@error($sleutel)<x-melding type="fout" id="{{ $naam }}-fout">{{ $message }}</x-melding>@enderror</div>
