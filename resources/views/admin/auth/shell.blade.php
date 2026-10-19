<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>@yield('title') · Doogsan Newsroom</title>
    <link rel="icon" href="{{ asset('images/brand/favicon-32.png') }}" type="image/png">
    @vite(['resources/css/admin.css'])
</head>
<body class="min-h-screen">
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-[#04322a] lg:block">
        <div class="absolute inset-0 opacity-[.07]" style="background-image: repeating-linear-gradient(0deg, #a5ea4f 0 1px, transparent 1px 28px), repeating-linear-gradient(90deg, #a5ea4f 0 1px, transparent 1px 28px)"></div>
        <div class="relative flex h-full flex-col justify-between p-12">
            <img src="{{ asset('images/brand/doogsan-logo-dark.png') }}" alt="Doogsan Media" class="h-12 w-auto self-start">
            <div>
                <p class="eyebrow !text-[var(--color-signal)]">Doogsan Newsroom</p>
                <p class="mt-3 max-w-md text-4xl font-extrabold leading-tight text-white">Publish faster.<br>Report accurately.<br>In two languages.</p>
                <p class="mt-4 max-w-md text-sm text-white/60">Editorial workflow, live coverage, breaking alerts and Live TV for doogsanmedia.com.</p>
            </div>
            <p class="text-xs text-white/40">Designed &amp; Developed by <a href="https://somalidigital.so" class="font-bold text-white/60 hover:text-[var(--color-signal)]" target="_blank" rel="noopener">Somali Digital</a></p>
        </div>
    </div>
    <div class="flex items-center justify-center p-6">
        <div class="w-full max-w-sm">
            <img src="{{ asset('images/brand/doogsan-logo-dark.png') }}" alt="Doogsan Media" class="mb-10 h-10 w-auto lg:hidden">
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
