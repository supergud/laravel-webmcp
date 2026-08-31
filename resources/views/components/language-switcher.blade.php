@php
    use App\Support\Locales;

    $route = request()->route();
    $routeName = $route?->getName();
    $parameters = $route?->parameters() ?? [];

    // Storefront routes carry {locale} in the URL, so switching language means
    // rewriting the URL. Pages without that segment (auth, account) fall back
    // to the session-based switch route.
    $canRewriteUrl = $routeName !== null && array_key_exists('locale', $parameters);

    $current = Locales::current();
@endphp

<flux:dropdown position="bottom" align="end">
    <flux:button variant="subtle" size="sm" icon:trailing="chevron-down" data-test="language-switcher">
        {{ Locales::nativeName($current) }}
    </flux:button>

    <flux:menu>
        @foreach (Locales::all() as $code => $locale)
            <flux:menu.item
                :href="$canRewriteUrl
                    ? route($routeName, array_merge($parameters, ['locale' => $code]))
                    : route('locale.switch', ['locale' => $code])"
                :checked="$code === $current"
                wire:navigate
            >
                {{ $locale['native'] }}
            </flux:menu.item>
        @endforeach
    </flux:menu>
</flux:dropdown>
