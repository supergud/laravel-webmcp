<?php

declare(strict_types=1);

use App\Models\User;

it('renders the login page in Traditional Chinese', function (): void {
    $this->withSession(['locale' => 'zh-TW'])
        ->get('/login')
        ->assertOk()
        ->assertSee('登入你的帳號')
        ->assertSee('電子郵件');
});

it('renders the registration page in Traditional Chinese', function (): void {
    $this->withSession(['locale' => 'zh-TW'])
        ->get('/register')
        ->assertOk()
        ->assertSee('建立一個帳號');
});

it('keeps the auth pages in English by default', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Log in to your account');
});

it('translates authentication failures', function (): void {
    User::factory()->create(['email' => 'someone@example.com']);

    $this->withSession(['locale' => 'zh-TW'])
        ->post(route('login.store'), [
            'email' => 'someone@example.com',
            'password' => 'not-the-password',
        ])
        ->assertSessionHasErrors(['email' => '帳號或密碼錯誤。']);
});

it('translates validation failures', function (): void {
    $this->withSession(['locale' => 'zh-TW'])
        ->post(route('login.store'), ['email' => '', 'password' => ''])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('必填');
});

it('offers a language switcher on the auth pages', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSee('繁體中文')
        ->assertSee(route('locale.switch', ['locale' => 'zh-TW']), escape: false);
});

it('switches the auth page language through the session route', function (): void {
    $this->get('/en');

    $this->get(route('locale.switch', ['locale' => 'zh-TW']));

    $this->get('/login')->assertSee('登入你的帳號');
});

it('shows the settings pages in the chosen language', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['locale' => 'zh-TW'])
        ->get('/settings/profile')
        ->assertOk()
        ->assertSee('個人資料設定');
});
