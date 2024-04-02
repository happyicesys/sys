<!DOCTYPE html>
<html class="scroll-smooth" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta http-equiv="refresh" content="{{ config('session.lifetime') * 60 }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css">

        <!-- Scripts -->
        @routes
        @vite('resources/js/app.js')
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
