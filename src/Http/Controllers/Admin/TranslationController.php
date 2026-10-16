<?php

namespace Plugins\Glitter\Translate\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Helpers\PermissionHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sirsoft\Board\Http\Requests\Admin\PostIndexRequest;
use Modules\Sirsoft\Board\Http\Resources\BoardResource;
use Modules\Sirsoft\Board\Http\Resources\PostCollection;
use Modules\Sirsoft\Board\Services\BoardService;
use Modules\Sirsoft\Board\Http\Resources\PostResource;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\PostService;
use Plugins\Glitter\Translate\Models\Translation;
use Plugins\Glitter\Translate\Services\DependencyStatus;
use Plugins\Glitter\Translate\Services\TranslationResolver;

class TranslationController
{
    public function __construct(
        private readonly DependencyStatus $dependencyStatus,
        private readonly TranslationResolver $resolver,
        private readonly PostService $postService,
        private readonly BoardService $boardService,
    ) {}

    public function sourcePosts(PostIndexRequest $request, string $slug): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }

        $board = $this->boardService->getBoardBySlug($slug);
        if (! $board) {
            return $this->error('messages.post_not_found', 404);
        }

        $listParams = $this->postService->buildListParams($request->all(), [
            'context' => 'admin',
            'board' => $board,
        ]);
        $posts = $this->postService->getPosts(
            $slug,
            $listParams['filters'],
            $listParams['perPage'],
            withTrashed: false,
            board: $board,
        );
        $totalNormalPosts = $this->postService->getCachedNormalPostCount(
            $slug,
            $board->id,
            $listParams['filters'],
            withTrashed: false,
            context: 'admin',
        );

        $collection = new PostCollection($posts);
        $collection->setTotalNormalPosts($totalNormalPosts);
        $collection->setOrderDirection($listParams['filters']['order_direction']);

        $payload = $collection->withBoardInfo(
            (new BoardResource($board))->toBoardInfoForAdmin(),
        );
        $selectedPostId = (int) $request->input('post_id', 0);
        $payload['selection'] = [
            'post_id' => $selectedPostId > 0 ? $selectedPostId : null,
            'exists' => $selectedPostId > 0 && Post::query()
                ->where('board_id', $board->id)
                ->whereKey($selectedPostId)
                ->exists(),
        ];

        return $this->success('messages.translations_fetched', $payload);
    }

    public function index(Request $request): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }

        $query = Translation::query()->orderByDesc('updated_at');
        if ($request->filled('post_id')) {
            $query->where('resource_id', (int) $request->input('post_id'));
        }
        if ($request->filled('locale')) {
            $locale = (string) $request->input('locale');
            if (! $this->resolver->isSupportedLocale($locale)) {
                return $this->error('messages.invalid_locale', 422);
            }
            $query->where('locale', $locale);
        }

        $rows = $query->paginate(min(max((int) $request->input('per_page', 20), 1), 100));
        $data = $rows->getCollection()->map(function (Translation $translation): array {
            $post = Post::query()->find($translation->resource_id);
            $status = $post === null
                ? 'orphaned'
                : ($translation->source_hash === $this->resolver->sourceHash((string) $post->{$translation->field})
                    ? 'current'
                    : 'stale');

            $row = [
                'id' => $translation->id,
                'resource_type' => $translation->resource_type,
                'post_id' => $translation->resource_id,
                'locale' => $translation->locale,
                'field' => $translation->field,
                'value' => $translation->value,
                'status' => $status,
                'source_hash' => $translation->source_hash,
                'updated_at' => $translation->updated_at?->toISOString(),
                'post_title' => $post?->title,
            ];

            if ($translation->field === 'content') {
                $row['content_mode'] = $translation->content_mode ?: 'text';
            }

            return $row;
        })->values();

        return $this->success('messages.translations.fetched', [
            'data' => $data,
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function show(Request $request, int $postId): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }

        $post = Post::query()->find($postId);
        if ($post === null) {
            return $this->error('messages.post_not_found', 404);
        }

        $locale = (string) $request->input('locale', app()->getLocale());
        if (! $this->resolver->isSupportedLocale($locale)) {
            return $this->error('messages.invalid_locale', 422);
        }

        return $this->success('messages.translation_fetched', $this->resolver->serialize($post, $locale));
    }

    public function publicShow(Request $request, string $slug, int $postId): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }

        $locale = (string) $request->input('locale', app()->getLocale());
        if (! $this->resolver->isSupportedLocale($locale)) {
            return $this->error('messages.invalid_locale', 422);
        }

        try {
            $post = $this->postService->getPostWithCounts($slug, $postId, context: 'user');
            $postData = (new PostResource($post))->toArray($request);
        } catch (\Throwable) {
            return $this->error('messages.post_not_found', 404);
        }

        $resolved = $this->resolver->serialize($post, $locale);
        foreach (TranslationResolver::FIELDS as $field) {
            if ($resolved['fields'][$field]['status'] !== 'current') {
                $resolved['fields'][$field]['value'] = $postData[$field] ?? '';
            }
        }

        $resolved['original']['title'] = $postData['title'] ?? '';
        $resolved['original']['content'] = $postData['content'] ?? '';
        $resolved['post'] = [
            'id' => $postId,
            'slug' => $slug,
            'content_mode' => $postData['content_mode'] ?? $post->content_mode,
        ];

        return $this->success('messages.translation_fetched', $resolved);
    }

    public function publicBatch(Request $request): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }

        $validated = $request->validate([
            'board' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/', 'max:100'],
            'post_ids' => ['required', 'array', 'min:1', 'max:100'],
            'post_ids.*' => ['integer', 'min:1'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', 'in:title,content'],
        ]);

        $fields = array_values(array_unique($validated['fields']));
        $postIds = array_values(array_unique(array_map('intval', $validated['post_ids'])));
        $board = $this->boardService->getBoardBySlug($validated['board'], checkScope: false);

        if (! $board || ! $board->is_active) {
            return $this->error('messages.post_not_found', 404);
        }

        $permission = "sirsoft-board.{$validated['board']}.posts.read";
        $postsQuery = Post::query()
            ->where('board_id', $board->getKey())
            ->whereIn('id', $postIds)
            ->whereNull('parent_id')
            ->whereNull('deleted_at')
            ->when(in_array('content', $fields, true), static fn ($query) => $query->with('board'));
        PermissionHelper::applyPermissionScope($postsQuery, $permission);
        $posts = $postsQuery->get(['id', 'board_id', 'title', 'content', 'content_mode']);
        $visibleFields = [];

        if (in_array('content', $fields, true)) {
            foreach ($posts as $post) {
                $postData = (new PostResource($post))->toArray($request);
                $post->setAttribute('content', (string) ($postData['content'] ?? ''));
                $visibleFields[(string) $post->getKey()]['content'] = $postData['content'] !== null;
            }
        }

        return $this->success(
            'messages.translations_fetched',
            $this->resolver->serializeBatch($posts, app()->getLocale(), $fields, $visibleFields),
        );
    }

    public function upsert(Request $request, int $postId, string $locale, string $field): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }
        if (! $this->resolver->isSupportedLocale($locale) || ! in_array($field, TranslationResolver::FIELDS, true)) {
            return $this->error('messages.invalid_translation_target', 422);
        }

        $validated = $request->validate([
            'value' => ['required', 'string', 'max:1000000'],
        ]);
        $post = Post::query()->find($postId);
        if ($post === null) {
            return $this->error('messages.post_not_found', 404);
        }

        $translation = Translation::query()->updateOrCreate(
            [
                'resource_type' => TranslationResolver::RESOURCE_TYPE,
                'resource_id' => $postId,
                'locale' => $locale,
                'field' => $field,
            ],
            [
                'value' => $validated['value'],
                'content_mode' => $field === 'content' ? 'text' : null,
                'source_hash' => $this->resolver->sourceHash((string) $post->{$field}),
            ],
        );

        return $this->success('messages.translation_saved', [
            'id' => $translation->id,
            'post_id' => $postId,
            'locale' => $locale,
            'field' => $field,
            'status' => 'current',
            'value' => $translation->value,
            'source_hash' => $translation->source_hash,
            ...($field === 'content' ? ['content_mode' => $translation->content_mode ?: 'text'] : []),
        ], 200);
    }

    public function destroy(int $postId, string $locale, string $field): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }
        if (! $this->resolver->isSupportedLocale($locale) || ! in_array($field, TranslationResolver::FIELDS, true)) {
            return $this->error('messages.invalid_translation_target', 422);
        }

        $deleted = Translation::query()
            ->where('resource_type', TranslationResolver::RESOURCE_TYPE)
            ->where('resource_id', $postId)
            ->where('locale', $locale)
            ->where('field', $field)
            ->delete();

        return $this->success('messages.translation_deleted', ['deleted' => $deleted > 0]);
    }

    public function destroyRecord(int $translationId): JsonResponse
    {
        if ($response = $this->dependencyError()) {
            return $response;
        }

        $translation = Translation::query()
            ->whereKey($translationId)
            ->where('resource_type', TranslationResolver::RESOURCE_TYPE)
            ->first();

        if ($translation === null) {
            return $this->error('messages.translation_not_found', 404);
        }

        try {
            if (! $translation->delete()) {
                return $this->error('messages.translation_delete_failed', 500);
            }
        } catch (\Throwable) {
            return $this->error('messages.translation_delete_failed', 500);
        }

        return $this->success('messages.translation_deleted', [
            'deleted' => true,
            'id' => $translationId,
        ]);
    }

    private function dependencyError(): ?JsonResponse
    {
        $status = $this->dependencyStatus->resolve();
        if ($status['installed'] && $status['active'] && $status['compatible']) {
            return null;
        }

        return $this->error('messages.dependency_unavailable', 503, [
            'dependency' => DependencyStatus::MODULE,
            'installed' => $status['installed'],
            'active' => $status['active'],
            'compatible' => $status['compatible'],
            'version' => $status['version'],
        ]);
    }

    private function success(string $message, mixed $data = null, int $status = 200): JsonResponse
    {
        return ResponseHelper::success($message, $data, $status, [], 'glitter-translate');
    }

    private function error(string $message, int $status, mixed $errors = null): JsonResponse
    {
        return ResponseHelper::error($message, $status, $errors, [], 'glitter-translate');
    }
}
