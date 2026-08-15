<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bare domain redirects to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
