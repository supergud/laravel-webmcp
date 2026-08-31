<?php

return [

    'nav' => [
        'home' => 'Home',
        'products' => 'Products',
        'categories' => 'Categories',
        'cart' => 'Cart',
        'orders' => 'Orders',
        'login' => 'Log in',
        'register' => 'Register',
        'dashboard' => 'Dashboard',
        'language' => 'Language',
    ],

    'products' => [
        'title' => 'Products',
        'search_placeholder' => 'Search products…',
        'all_categories' => 'All categories',
        'price_from' => 'Min price',
        'price_to' => 'Max price',
        'sort' => 'Sort',
        'sort_newest' => 'Newest',
        'sort_price_asc' => 'Price: low to high',
        'sort_price_desc' => 'Price: high to low',
        'sort_name' => 'Name',
        'empty' => 'No products match your search.',
        'in_stock' => 'In stock',
        'out_of_stock' => 'Out of stock',
        'sku' => 'SKU',
        'results' => ':count product|:count products',
    ],

    'cart' => [
        'title' => 'Shopping cart',
        'empty' => 'Your cart is empty.',
        'add' => 'Add to cart',
        'added' => ':name added to your cart.',
        'remove' => 'Remove',
        'removed' => 'Item removed from your cart.',
        'clear' => 'Clear cart',
        'cleared' => 'Your cart has been cleared.',
        'quantity' => 'Quantity',
        'updated' => 'Cart updated.',
        'subtotal' => 'Subtotal',
        'total' => 'Total',
        'checkout' => 'Checkout',
        'continue_shopping' => 'Continue shopping',
    ],

    'checkout' => [
        'title' => 'Checkout',
        'review' => 'Review your order',
        'confirm' => 'Confirm order',
        'confirm_hint' => 'An AI agent can prepare this order, but only you can confirm it.',
        'cancel' => 'Cancel',
        'placed' => 'Order :number has been placed.',
        'expired' => 'This checkout draft has expired. Please start again.',
        'shipping_name' => 'Recipient name',
        'shipping_email' => 'Email',
        'shipping_address' => 'Shipping address',
        'pending_draft' => 'You have an order awaiting your confirmation.',
    ],

    'orders' => [
        'title' => 'Orders',
        'empty' => 'You have not placed any orders yet.',
        'number' => 'Order number',
        'placed_at' => 'Placed at',
        'status' => 'Status',
        'total' => 'Total',
        'items' => 'Items',
        'view' => 'View',
    ],

    'errors' => [
        'quantity_max' => 'You can order at most :max of any single product.',
        'items_max' => 'A cart can hold at most :max different products.',
        'total_max' => 'A cart can total at most NT$:max.',
        'product_unavailable' => 'That product is not available.',
        'out_of_stock' => 'That product is out of stock.',
        'insufficient_stock' => 'Only :stock left in stock.',
    ],

    'status' => [
        'draft' => 'Awaiting confirmation',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],

];
