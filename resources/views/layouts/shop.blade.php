<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-neutral-900">
            <flux:brand
                :href="route('home')"
                :name="config('app.name')"
                class="max-lg:hidden dark:hidden"
                wire:navigate
            >
                <x-slot name="logo">
                    <x-app-logo-icon class="size-6 fill-current text-black" />
                </x-slot>
            </flux:brand>

            <flux:brand
                :href="route('home')"
                :name="config('app.name')"
                class="max-lg:hidden! hidden dark:flex"
                wire:navigate
            >
                <x-slot name="logo">
                    <x-app-logo-icon class="size-6 fill-current text-white" />
                </x-slot>
            </flux:brand>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('shop.nav.products') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            <flux:navbar class="me-1.5 items-center">
                <x-language-switcher />
            </flux:navbar>

            @auth
                <flux:navbar class="items-center">
                    <flux:navbar.item :href="route('dashboard')" wire:navigate>
                        {{ __('shop.nav.dashboard') }}
                    </flux:navbar.item>
                </flux:navbar>
            @else
                <flux:navbar class="items-center gap-1">
                    <flux:button :href="route('login')" variant="subtle" size="sm" wire:navigate>
                        {{ __('shop.nav.login') }}
                    </flux:button>
                    <flux:button :href="route('register')" variant="primary" size="sm" wire:navigate>
                        {{ __('shop.nav.register') }}
                    </flux:button>
                </flux:navbar>
            @endauth
        </flux:header>

        <flux:main container>
            {{ $slot }}
        </flux:main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
