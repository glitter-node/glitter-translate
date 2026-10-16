<?php

namespace Plugins\Glitter\Translate\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Plugins\Glitter\Translate\Models\Translation;

class TranslationResolver
{
    public const RESOURCE_TYPE = 'sirsoft-board.post';

    /** @var array<int, string> */
    public const FIELDS = ['title', 'content'];

    public function sourceHash(string $value): string
    {
        return hash('sha256', $value);
    }

    public function locales(): array
    {
        $locales = config('app.supported_locales', []);
        if (! is_array($locales) || $locales === []) {
            $locales = [config('app.locale', 'ko'), config('app.fallback_locale', 'ko')];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $locale): ?string => is_string($locale) && preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $locale) === 1
                ? $locale
                : null,
            $locales,
        ))));
    }

    public function isSupportedLocale(string $locale): bool
    {
        return in_array($locale, $this->locales(), true);
    }

    /**
     * @return array{
     *     value: string,
     *     content_mode: ?string,
     *     stored_content_mode: ?string,
     *     status: string,
     *     source_hash: string,
     *     translation: ?Translation
     * }
     */
    public function resolve(Model $post, string $field, string $locale): array
    {
        $original = (string) ($post->getAttribute($field) ?? '');
        $hash = $this->sourceHash($original);
        $translation = Translation::query()
            ->where('resource_type', self::RESOURCE_TYPE)
            ->where('resource_id', (int) $post->getKey())
            ->where('locale', $locale)
            ->where('field', $field)
            ->first();

        $status = $translation === null
            ? 'missing'
            : ($translation->source_hash === $hash ? 'current' : 'stale');
        $originalContentMode = $this->originalContentMode($post, $field);
        $storedContentMode = $this->storedContentMode($translation, $field);

        return [
            'value' => $status === 'current' ? (string) $translation->value : $original,
            'content_mode' => $status === 'current' ? $storedContentMode : $originalContentMode,
            'stored_content_mode' => $storedContentMode,
            'status' => $status,
            'source_hash' => $hash,
            'translation' => $translation,
        ];
    }

    /** @return array<string, mixed> */
    public function serialize(Model $post, string $locale): array
    {
        $fields = [];
        foreach (self::FIELDS as $field) {
            $resolved = $this->resolve($post, $field, $locale);
            $fieldData = [
                'value' => $resolved['value'],
                'status' => $resolved['status'],
                'source_hash' => $resolved['source_hash'],
                'stored_value' => $resolved['translation']?->value,
                'updated_at' => $resolved['translation']?->updated_at?->toISOString(),
            ];

            if ($field === 'content') {
                $fieldData['content_mode'] = $resolved['content_mode'];
                $fieldData['stored_content_mode'] = $resolved['stored_content_mode'];
            }

            $fields[$field] = $fieldData;
        }

        return [
            'resource_type' => self::RESOURCE_TYPE,
            'resource_id' => (int) $post->getKey(),
            'locale' => $locale,
            'original' => [
                'title' => (string) $post->title,
                'content' => (string) $post->content,
                'content_mode' => (string) ($post->content_mode ?? 'text'),
            ],
            'fields' => $fields,
        ];
    }

    /**
     * Resolve an already permission-filtered Post collection with one
     * translation query.
     *
     * @param Collection<int, Model> $posts
     * @param array<int, string> $fields
     * @param array<string, array<string, bool>> $visibleFields
     * @return array<string, array{id: int, fields: array<string, array<string, mixed>>}>
     */
    public function serializeBatch(Collection $posts, string $locale, array $fields, array $visibleFields = []): array
    {
        $postIds = $posts->map(static fn (Model $post): int => (int) $post->getKey())->values();
        if ($postIds->isEmpty() || $fields === []) {
            return [];
        }

        $translations = Translation::query()
            ->where('resource_type', self::RESOURCE_TYPE)
            ->whereIn('resource_id', $postIds->all())
            ->where('locale', $locale)
            ->whereIn('field', $fields)
            ->get()
            ->keyBy(static fn (Translation $translation): string => $translation->resource_id . ':' . $translation->field);

        $result = [];
        foreach ($posts as $post) {
            $postId = (int) $post->getKey();
            $resolvedFields = [];

            foreach ($fields as $field) {
                if (($visibleFields[(string) $postId][$field] ?? true) === false) {
                    continue;
                }

                $original = (string) ($post->getAttribute($field) ?? '');
                $sourceHash = $this->sourceHash($original);
                $translation = $translations->get($postId . ':' . $field);
                $status = $translation === null
                    ? 'missing'
                    : ($translation->source_hash === $sourceHash ? 'current' : 'stale');

                $fieldData = [
                    'value' => $status === 'current' ? (string) $translation->value : $original,
                    'status' => $status,
                    'source_hash' => $sourceHash,
                ];

                if ($field === 'content') {
                    $originalContentMode = $this->originalContentMode($post, $field);
                    $storedContentMode = $this->storedContentMode($translation, $field);
                    $fieldData['content_mode'] = $status === 'current'
                        ? $storedContentMode
                        : $originalContentMode;
                }

                $resolvedFields[$field] = $fieldData;
            }

            $result[(string) $postId] = [
                'id' => $postId,
                'fields' => $resolvedFields,
            ];
        }

        return $result;
    }

    private function originalContentMode(Model $post, string $field): ?string
    {
        if ($field !== 'content') {
            return null;
        }

        return (string) ($post->getAttribute('content_mode') ?: 'text');
    }

    private function storedContentMode(?Translation $translation, string $field): ?string
    {
        if ($field !== 'content' || $translation === null) {
            return null;
        }

        return (string) ($translation->content_mode ?: 'text');
    }
}
