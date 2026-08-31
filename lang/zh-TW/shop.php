<?php

return [

    'nav' => [
        'home' => '首頁',
        'products' => '商品',
        'categories' => '分類',
        'cart' => '購物車',
        'orders' => '訂單',
        'login' => '登入',
        'register' => '註冊',
        'dashboard' => '會員中心',
        'language' => '語言',
    ],

    'products' => [
        'title' => '商品',
        'search_placeholder' => '搜尋商品…',
        'all_categories' => '所有分類',
        'price_from' => '最低價',
        'price_to' => '最高價',
        'sort' => '排序',
        'sort_newest' => '最新上架',
        'sort_price_asc' => '價格由低到高',
        'sort_price_desc' => '價格由高到低',
        'sort_name' => '名稱',
        'empty' => '沒有符合條件的商品。',
        'in_stock' => '有現貨',
        'out_of_stock' => '已售完',
        'sku' => '商品編號',
        'results' => ':count 件商品',
    ],

    'cart' => [
        'title' => '購物車',
        'empty' => '購物車是空的。',
        'add' => '加入購物車',
        'added' => '已將 :name 加入購物車。',
        'remove' => '移除',
        'removed' => '已從購物車移除。',
        'clear' => '清空購物車',
        'cleared' => '購物車已清空。',
        'quantity' => '數量',
        'updated' => '購物車已更新。',
        'subtotal' => '小計',
        'total' => '總計',
        'checkout' => '前往結帳',
        'continue_shopping' => '繼續購物',
    ],

    'checkout' => [
        'title' => '結帳',
        'review' => '確認訂單內容',
        'confirm' => '確認送出訂單',
        'confirm_hint' => 'AI 助理可以幫你準備訂單，但只有你本人能按下確認。',
        'cancel' => '取消',
        'placed' => '訂單 :number 已成立。',
        'expired' => '這筆結帳草稿已過期，請重新操作。',
        'shipping_name' => '收件人姓名',
        'shipping_email' => '電子郵件',
        'shipping_address' => '收件地址',
        'pending_draft' => '你有一筆訂單正在等待確認。',
    ],

    'orders' => [
        'title' => '訂單',
        'empty' => '你還沒有任何訂單。',
        'number' => '訂單編號',
        'placed_at' => '成立時間',
        'status' => '狀態',
        'total' => '總計',
        'items' => '商品',
        'view' => '查看',
    ],

    'errors' => [
        'quantity_max' => '同一件商品最多只能購買 :max 件。',
        'items_max' => '購物車最多只能放 :max 種商品。',
        'total_max' => '購物車金額上限為 NT$:max。',
        'product_unavailable' => '這件商品目前無法購買。',
        'out_of_stock' => '這件商品已售完。',
        'insufficient_stock' => '庫存只剩 :stock 件。',
    ],

    'status' => [
        'draft' => '等待確認',
        'pending' => '處理中',
        'paid' => '已付款',
        'cancelled' => '已取消',
    ],

];
