<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title.' · veteFlow' : 'veteFlow' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @php($posLock = request()->routeIs('pos.lock'))
    @vite($posLock ? ['resources/css/app.css', 'resources/js/app.js', 'resources/js/pos-offline.js'] : ['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
{{-- On the mostrador lock screen pos-offline.js uploads pending offline sales and keeps the PC ready to sell without internet. --}}
<body @if ($posLock) data-pos-page="lock" @endif class="flex min-h-full items-center justify-center bg-[#F4F6F5] p-4 font-sans text-slate-800 antialiased">
    {{ $slot }}

    @livewireScripts
</body>
</html>
