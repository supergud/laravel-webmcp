<?php

/*
|--------------------------------------------------------------------------
| Validation Language Lines (zh-TW)
|--------------------------------------------------------------------------
|
| This file is deliberately partial. Laravel falls back to the fallback
| locale for any key it cannot find here, so only the rules this application
| actually applies - account forms, the cart and the WebMCP tool endpoints -
| are translated. Anything else degrades to English rather than to a missing
| translation key.
|
*/

return [

    'array' => ':attribute 必須是陣列。',
    'between' => [
        'array' => ':attribute 的項目數必須介於 :min 至 :max 之間。',
        'file' => ':attribute 必須介於 :min 至 :max KB 之間。',
        'numeric' => ':attribute 必須介於 :min 至 :max 之間。',
        'string' => ':attribute 必須介於 :min 至 :max 個字元之間。',
    ],
    'boolean' => ':attribute 必須是 true 或 false。',
    'confirmed' => ':attribute 兩次輸入不一致。',
    'current_password' => '密碼不正確。',
    'digits' => ':attribute 必須是 :digits 位數字。',
    'email' => ':attribute 必須是有效的電子郵件地址。',
    'exists' => '所選的 :attribute 無效。',
    'in' => '所選的 :attribute 無效。',
    'integer' => ':attribute 必須是整數。',
    'max' => [
        'array' => ':attribute 最多只能有 :max 個項目。',
        'file' => ':attribute 不得大於 :max KB。',
        'numeric' => ':attribute 不得大於 :max。',
        'string' => ':attribute 不得超過 :max 個字元。',
    ],
    'min' => [
        'array' => ':attribute 至少需要 :min 個項目。',
        'file' => ':attribute 不得小於 :min KB。',
        'numeric' => ':attribute 不得小於 :min。',
        'string' => ':attribute 至少需要 :min 個字元。',
    ],
    'numeric' => ':attribute 必須是數字。',
    'prohibited' => ':attribute 為禁止填寫的欄位。',
    'regex' => ':attribute 的格式不正確。',
    'required' => ':attribute 為必填欄位。',
    'size' => [
        'array' => ':attribute 必須包含 :size 個項目。',
        'file' => ':attribute 必須是 :size KB。',
        'numeric' => ':attribute 必須是 :size。',
        'string' => ':attribute 必須是 :size 個字元。',
    ],
    'string' => ':attribute 必須是字串。',
    'unique' => ':attribute 已經被使用。',
    'url' => ':attribute 的格式不正確。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'code' => '驗證碼',
        'current_password' => '目前密碼',
        'email' => '電子郵件',
        'name' => '姓名',
        'password' => '密碼',
        'password_confirmation' => '確認密碼',
        'quantity' => '數量',
        'recovery_code' => '備用碼',
        'shipping_address' => '收件地址',
        'shipping_email' => '電子郵件',
        'shipping_name' => '收件人姓名',
        'sku' => '商品編號',
        'term' => '搜尋關鍵字',
    ],

];
