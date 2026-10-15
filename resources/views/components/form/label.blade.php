@props(['spaced' => true])

<label {{ $attributes->class(['block text-xs font-semibold uppercase tracking-wide text-slate-500', 'mb-1' => $spaced]) }}>{{ $slot }}</label>
