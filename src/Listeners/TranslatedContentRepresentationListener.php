<?php

namespace Plugins\Glitter\Translate\Listeners;

use App\Contracts\Extension\HookListenerInterface;

class TranslatedContentRepresentationListener implements HookListenerInterface
{
    private const LAYOUT_NAME = 'board/show';

    private const TRANSLATED_CONTENT_PATH = 'postTranslation?.data?.fields?.content?.value';

    private const ORIGINAL_IS_HTML = "{{(post?.data?.content_mode ?? 'text') === 'html'}}";

    private const TRANSLATED_IS_HTML = "{{(postTranslation?.data?.fields?.content?.content_mode ?? post?.data?.content_mode ?? 'text') === 'html'}}";

    private const TRANSLATED_CONTENT_MARKER = 'glitter-translate-content';

    public static function getSubscribedHooks(): array
    {
        return [
            'core.layout_extension.after_apply' => [
                'method' => 'rewriteTranslatedContentRepresentation',
                'priority' => 20,
                'type' => 'filter',
            ],
        ];
    }

    public function handle(...$args): void {}

    public function rewriteTranslatedContentRepresentation(array $layout, int $templateId = 0): array
    {
        if (($layout['layout_name'] ?? null) !== self::LAYOUT_NAME) {
            return $layout;
        }

        $invalid = false;
        $targets = 0;
        $rewritten = $this->rewriteArray($layout, $invalid, $targets);

        if ($invalid || $targets === 0) {
            return $layout;
        }

        return $rewritten;
    }

    /**
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function rewriteArray(array $value, bool &$invalid, int &$targets): array
    {
        $isTarget = $this->isTranslatedContentExtensionPoint($value);

        if ($isTarget) {
            $targets++;

            if (! $this->hasOriginalOrTranslatedIsHtml($value['props']['isHtml'] ?? null)) {
                $invalid = true;
                return $value;
            }

            $defaultMatches = 0;
            $target = $value;
            $target['props']['isHtml'] = self::TRANSLATED_IS_HTML;
            if (! $this->addContentMarker($target['props'])) {
                $invalid = true;
                return $value;
            }

            if (isset($target['default']) && is_array($target['default'])) {
                $target['default'] = $this->rewriteDefault($target['default'], $defaultMatches);
            }

            if ($defaultMatches === 0) {
                $invalid = true;
                return $value;
            }

            $injectedMatches = 0;
            if (array_key_exists('children', $target)) {
                if (! is_array($target['children'])) {
                    $invalid = true;
                    return $value;
                }

                $target['children'] = $this->rewriteInjectedRenderers(
                    $target['children'],
                    $injectedMatches,
                    $invalid,
                );

                if ($injectedMatches === 0 || $invalid) {
                    return $value;
                }
            }

            $value = $target;
        }

        foreach ($value as $key => $child) {
            if ($isTarget && $key === 'default') {
                continue;
            }

            if (is_array($child)) {
                $value[$key] = $this->rewriteArray($child, $invalid, $targets);
            }
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     * @return array<int|string, mixed>
     */
    private function rewriteDefault(array $nodes, int &$matches): array
    {
        foreach ($nodes as $key => $node) {
            if (! is_array($node)) {
                continue;
            }

            if ($this->isTranslatedHtmlContentDefault($node)) {
                $node['props']['isHtml'] = self::TRANSLATED_IS_HTML;
                if (! $this->addContentMarker($node['props'])) {
                    $matches = 0;
                    return $nodes;
                }
                $matches++;
            }

            foreach ($node as $childKey => $child) {
                if (is_array($child)) {
                    $node[$childKey] = $this->rewriteDefault($child, $matches);
                }
            }

            $nodes[$key] = $node;
        }

        return $nodes;
    }

    /**
     * @param  array<int|string, mixed>  $nodes
     * @return array<int|string, mixed>
     */
    private function rewriteInjectedRenderers(array $nodes, int &$matches, bool &$invalid): array
    {
        foreach ($nodes as $key => $node) {
            if (! is_array($node)) {
                continue;
            }

            if (($node['type'] ?? null) === 'composite'
                && ($node['name'] ?? null) === 'HtmlContent'
                && is_array($node['extensionPointProps'] ?? null)
                && $this->hasTranslatedContentPath($node['extensionPointProps']['content'] ?? null)
            ) {
                if (! $this->isInjectedTranslatedContentRenderer($node)) {
                    $invalid = true;
                    return $nodes;
                }

                $node['extensionPointProps']['isHtml'] = self::TRANSLATED_IS_HTML;
                if (! $this->addContentMarker($node['extensionPointProps'])) {
                    $invalid = true;
                    return $nodes;
                }
                $matches++;
            }

            foreach ($node as $childKey => $child) {
                if (is_array($child)) {
                    $node[$childKey] = $this->rewriteInjectedRenderers($child, $matches, $invalid);
                    if ($invalid) {
                        return $nodes;
                    }
                }
            }

            $nodes[$key] = $node;
        }

        return $nodes;
    }

    private function isTranslatedContentExtensionPoint(array $node): bool
    {
        return ($node['type'] ?? null) === 'extension_point'
            && ($node['name'] ?? null) === 'html_content'
            && is_array($node['props'] ?? null)
            && $this->hasTranslatedContentPath($node['props']['content'] ?? null);
    }

    private function isTranslatedHtmlContentDefault(array $node): bool
    {
        return ($node['type'] ?? null) === 'composite'
            && ($node['name'] ?? null) === 'HtmlContent'
            && is_array($node['props'] ?? null)
            && $this->hasTranslatedContentPath($node['props']['content'] ?? null)
            && $this->hasOriginalOrTranslatedIsHtml($node['props']['isHtml'] ?? null);
    }

    private function hasTranslatedContentPath(mixed $expression): bool
    {
        return is_string($expression)
            && str_contains($expression, self::TRANSLATED_CONTENT_PATH);
    }

    private function hasOriginalOrTranslatedIsHtml(mixed $expression): bool
    {
        return $expression === self::ORIGINAL_IS_HTML || $expression === self::TRANSLATED_IS_HTML;
    }

    private function isInjectedTranslatedContentRenderer(array $node): bool
    {
        return is_array($node['props'] ?? null)
            && ($node['props']['content'] ?? null) === '{{extensionPointProps.content ?? \'\'}}'
            && ($node['props']['isHtml'] ?? null) === '{{extensionPointProps.isHtml ?? true}}'
            && $this->hasOriginalOrTranslatedIsHtml($node['extensionPointProps']['isHtml'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function addContentMarker(array &$props): bool
    {
        $className = $props['className'] ?? '';
        if (! is_string($className)) {
            return false;
        }

        $classes = preg_split('/\s+/', trim($className)) ?: [];
        if (! in_array(self::TRANSLATED_CONTENT_MARKER, $classes, true)) {
            $classes[] = self::TRANSLATED_CONTENT_MARKER;
        }

        $props['className'] = implode(' ', array_values(array_filter($classes, static fn ($class) => $class !== '')));

        return true;
    }
}
