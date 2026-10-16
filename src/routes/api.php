<?php

use Illuminate\Support\Facades\Route;
use Plugins\Glitter\Translate\Http\Controllers\Admin\TranslationController;

Route::post('posts/translations/batch', [TranslationController::class, 'publicBatch'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('posts.translations.batch');

Route::get('posts/{slug}/{postId}/translations', [TranslationController::class, 'publicShow'])
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->where(['slug' => '[A-Za-z0-9_-]+', 'postId' => '[0-9]+'])
    ->name('posts.translations.show');

Route::prefix('admin')->middleware(['auth:sanctum', 'admin', 'throttle:600,1'])->group(function (): void {
    Route::get('translations', [TranslationController::class, 'index'])
        ->middleware('permission:admin,glitter-translate.translations.read')
        ->name('admin.translations.index');

    Route::get('source-posts/{slug}', [TranslationController::class, 'sourcePosts'])
        ->middleware('permission:admin,glitter-translate.translations.read')
        ->where('slug', '[A-Za-z0-9_-]+')
        ->name('admin.source-posts.index');

    Route::delete('translations/{translation}', [TranslationController::class, 'destroyRecord'])
        ->middleware('permission:admin,glitter-translate.translations.write')
        ->whereNumber('translation')
        ->name('admin.translations.destroy');

    Route::get('posts/{postId}/translations', [TranslationController::class, 'show'])
        ->middleware('permission:admin,glitter-translate.translations.read')
        ->whereNumber('postId')
        ->name('admin.posts.translations.show');

    Route::put('posts/{postId}/translations/{locale}/{field}', [TranslationController::class, 'upsert'])
        ->middleware('permission:admin,glitter-translate.translations.write')
        ->where(['postId' => '[0-9]+', 'locale' => '[a-zA-Z-]+', 'field' => 'title|content'])
        ->name('admin.posts.translations.upsert');

    Route::delete('posts/{postId}/translations/{locale}/{field}', [TranslationController::class, 'destroy'])
        ->middleware('permission:admin,glitter-translate.translations.write')
        ->where(['postId' => '[0-9]+', 'locale' => '[a-zA-Z-]+', 'field' => 'title|content'])
        ->name('admin.posts.translations.destroy');
});
