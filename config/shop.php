<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Prices are whole New Taiwan dollars stored as integers, so every total is
    | exact. There is no minor unit and no floating point anywhere.
    |
    */

    'currency' => 'TWD',

    /*
    |--------------------------------------------------------------------------
    | Cart Limits
    |--------------------------------------------------------------------------
    |
    | These caps apply to the cart no matter who is filling it. An AI agent
    | driving the WebMCP tools shares the visitor's session and therefore
    | shares these limits: there is no separate, looser path for automation.
    |
    | They exist because an agent can be talked into a large order by text it
    | reads on the page - a product description, a review, a search result -
    | so the blast radius of a hijacked agent is bounded by the application
    | rather than by the agent's own judgement.
    |
    */

    'cart' => [
        'max_quantity_per_item' => 10,
        'max_items' => 20,
        'max_total' => 100_000,
        'session_key' => 'cart',
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | prepare_checkout writes a draft order and returns a summary. Confirming
    | it is deliberately not exposed as a tool - only a person clicking in the
    | UI can turn a draft into a real order.
    |
    | The lifetime is generous because the usual failure mode is a live demo
    | where someone explains the flow for several minutes between preparing and
    | confirming an order.
    |
    */

    'checkout' => [
        'draft_lifetime_minutes' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tool Rate Limits
    |--------------------------------------------------------------------------
    |
    | Per minute, keyed by session and IP. Writes are throttled harder than
    | reads because they are the ones that change state.
    |
    */

    'rate_limits' => [
        'read' => 60,
        'write' => 20,
    ],

];
