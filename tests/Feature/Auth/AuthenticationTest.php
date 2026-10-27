<?php

use App\Helpers\TimezoneHelper;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Livewire\Volt\Volt as LivewireVolt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('admin user with type 1 and status 0 is redirected to admin dashboard', function () {
    $admin = User::factory()->create([
        'type' => 1,
        'status' => 0,
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('simple user with type 0 is redirected to dashboard', function () {
    $user = User::factory()->create([
        'type' => 0,
        'status' => 1,
    ]);

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->withoutMiddleware(ValidateCsrfToken::class)->post('/logout');

    $response->assertRedirect('/');
});

test('user location and timezone are updated upon login', function () {
    $user = User::factory()->create([
        'timezone' => null,
        'countryiso2' => null,
    ]);

    LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->set('timezone', 'Asia/Kolkata')
        ->call('login');

    $user->refresh();
    expect($user->timezone)->toBe('Asia/Kolkata');
    expect($user->countryiso2)->toBe('IN');
});

test('timezone helper resolves country by timezone', function () {
    expect(TimezoneHelper::getCountryCodeByTimezone('Asia/Kolkata'))->toBe('IN');
    expect(TimezoneHelper::getCountryCodeByTimezone('America/New_York'))->toBe('US');
    expect(TimezoneHelper::getCountryByTimezone('Asia/Kolkata')?->name)->toBe('India');
});
