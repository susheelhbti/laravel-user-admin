<?php

use Susheelhbti\LaravelUserAdmin\Models\Otp;
use Illuminate\Support\Facades\Mail;

uses(\Susheelhbti\LaravelUserAdmin\Tests\TestCase::class)->in('Feature');

it('can send an otp', function () {
    Mail::fake();

    $response = $this->postJson('/api/auth/otp/send', [
        'email' => 'test@example.com',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['message', 'otp_id']);

    expect(Otp::where('email', 'test@example.com')->exists())->toBeTrue();
});

it('rejects an invalid email', function () {
    $response = $this->postJson('/api/auth/otp/send', [
        'email' => 'not-an-email',
    ]);

    $response->assertUnprocessable();
});

it('rejects verify with wrong code', function () {
    Mail::fake();

    $this->postJson('/api/auth/otp/send', ['email' => 'a@b.com']);

    $otp = Otp::where('email', 'a@b.com')->first();

    $response = $this->postJson('/api/auth/otp/verify', [
        'otp_id' => $otp->id,
        'code'   => '000000',
    ]);

    $response->assertUnauthorized();
});
