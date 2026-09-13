<?php

use App\Models\Category;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    File::ensureDirectoryExists(public_path('tmp-test-images'));
    File::put(public_path('tmp-test-images/sample.png'), 'fake-image-bytes');
});

afterEach(function () {
    File::deleteDirectory(public_path('tmp-test-images'));
});

it('lists image files found under the public folder, excluding compiled build assets', function () {
    $admin = User::factory()->create();

    $component = Livewire::actingAs($admin)->test('pages::admin.categories');

    $paths = collect($component->get('publicImages'))->pluck('path');

    expect($paths)->toContain('tmp-test-images/sample.png');
    expect($paths->contains(fn ($path) => str_starts_with($path, 'build/')))->toBeFalse();
});

it('fills the image url field when a public folder image is chosen', function () {
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.categories')
        ->call('openCreate')
        ->set('selectedPublicImage', asset('tmp-test-images/sample.png'))
        ->assertSet('imageExternalUrl', asset('tmp-test-images/sample.png'));
});

it('saves a category using an image chosen from the public folder', function () {
    $admin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.categories')
        ->call('openCreate')
        ->set('name', 'Netflix Premium')
        ->set('price', '199.00')
        ->set('selectedPublicImage', asset('tmp-test-images/sample.png'))
        ->call('save');

    $category = Category::where('name', 'Netflix Premium')->first();

    expect($category)->not->toBeNull();
    expect($category->image)->toBe(asset('tmp-test-images/sample.png'));
});

it('also offers the public folder image picker on the admin store-categories page', function () {
    $admin = User::factory()->create();
    $store = Store::factory()->create();

    Livewire::actingAs($admin)
        ->test('pages::admin.store-categories', ['store' => $store])
        ->call('openCreate')
        ->set('name', 'Netflix Premium')
        ->set('price', '199.00')
        ->set('selectedPublicImage', asset('tmp-test-images/sample.png'))
        ->assertSet('imageExternalUrl', asset('tmp-test-images/sample.png'))
        ->call('save');

    $category = Category::where('name', 'Netflix Premium')->first();

    expect($category)->not->toBeNull();
    expect($category->image)->toBe(asset('tmp-test-images/sample.png'));
});
