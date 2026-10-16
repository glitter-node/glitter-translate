import '../css/plugin.css';

const CONTENT_SELECTOR = '.glitter-translate-content';
const PREVIEW_ENDPOINT = '/api/plugins/g7-ckeditor5-superpack/link-preview';
const REQUEST_TIMEOUT_MS = 8000;

interface LinkPreviewResponse {
    status?: string;
    title?: string | null;
    description?: string | null;
    image?: string | null;
    site_name?: string | null;
    favicon?: string | null;
    domain?: string | null;
}

type PreviewResult = LinkPreviewResponse | null;

const previewCache = new Map<string, PreviewResult>();
const previewRequests = new Map<string, Promise<PreviewResult>>();
const pendingTextNodes = new WeakSet<Text>();

function asText(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
}

function safeHttpUrl(value: string): URL | null {
    try {
        const url = new URL(value);
        if (url.protocol !== 'http:' && url.protocol !== 'https:') return null;
        if (url.username !== '' || url.password !== '') return null;
        return url;
    } catch {
        return null;
    }
}

function standaloneUrl(line: string): string | null {
    const candidate = line.trim();
    if (candidate === '') return null;
    return safeHttpUrl(candidate) ? candidate : null;
}

function validMetadataUrl(value: unknown): string | null {
    const candidate = typeof value === 'string' ? value.trim() : '';
    return safeHttpUrl(candidate) ? candidate : null;
}

async function requestPreview(url: string): Promise<PreviewResult> {
    const cached = previewCache.get(url);
    if (cached !== undefined) return cached;

    const existing = previewRequests.get(url);
    if (existing) return existing;

    const request = (async (): Promise<PreviewResult> => {
        const controller = typeof AbortController !== 'undefined' ? new AbortController() : undefined;
        const timeout = controller ? window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS) : undefined;

        try {
            const response = await fetch(`${PREVIEW_ENDPOINT}?url=${encodeURIComponent(url)}`, {
                headers: { Accept: 'application/json' },
                signal: controller?.signal,
            });
            if (!response.ok) return null;

            const payload = await response.json() as LinkPreviewResponse;
            if (payload.status !== 'ok' && payload.status !== 'minimal') return null;
            return payload;
        } catch {
            return null;
        } finally {
            if (timeout !== undefined) window.clearTimeout(timeout);
        }
    })();

    previewRequests.set(url, request);
    const result = await request;
    previewRequests.delete(url);
    previewCache.set(url, result);
    return result;
}

function appendText(parent: DocumentFragment, value: string): void {
    if (value !== '') parent.appendChild(document.createTextNode(value));
}

function createPreviewCard(url: string, metadata: LinkPreviewResponse): HTMLElement {
    const wrapper = document.createElement('div');
    wrapper.className = 'glitter-translate-link-preview-wrap';

    const anchor = document.createElement('a');
    anchor.className = 'glitter-translate-link-preview';
    anchor.href = url;
    anchor.target = '_blank';
    anchor.rel = 'noopener noreferrer nofollow';

    const title = asText(metadata.title) || asText(metadata.site_name) || asText(metadata.domain) || url;
    const description = asText(metadata.description);
    const domain = asText(metadata.domain) || safeHttpUrl(url)?.host || url;
    const image = validMetadataUrl(metadata.image);

    if (image) {
        const thumb = document.createElement('div');
        thumb.className = 'glitter-translate-link-preview__thumb';
        const imageElement = document.createElement('img');
        imageElement.src = image;
        imageElement.alt = title;
        imageElement.loading = 'lazy';
        imageElement.referrerPolicy = 'no-referrer';
        thumb.appendChild(imageElement);
        anchor.appendChild(thumb);
    }

    const body = document.createElement('div');
    body.className = 'glitter-translate-link-preview__body';

    const titleElement = document.createElement('div');
    titleElement.className = 'glitter-translate-link-preview__title';
    titleElement.textContent = title;
    body.appendChild(titleElement);

    if (description !== '') {
        const descriptionElement = document.createElement('div');
        descriptionElement.className = 'glitter-translate-link-preview__description';
        descriptionElement.textContent = description;
        body.appendChild(descriptionElement);
    }

    const domainElement = document.createElement('div');
    domainElement.className = 'glitter-translate-link-preview__domain';
    domainElement.textContent = domain;
    body.appendChild(domainElement);

    anchor.appendChild(body);
    wrapper.appendChild(anchor);
    return wrapper;
}

function translatedContentContainer(node: Element): node is HTMLElement {
    return node instanceof HTMLElement && node.matches(CONTENT_SELECTOR);
}

function hasStructuredContent(container: HTMLElement): boolean {
    return container.querySelector('*') !== null;
}

function enhanceTextNode(node: Text, container: HTMLElement): void {
    if (pendingTextNodes.has(node) || !node.isConnected || hasStructuredContent(container)) return;

    const lines = node.data.split(/\r\n?|\n/);
    const candidates = lines.map(standaloneUrl);
    const urls = candidates.filter((value): value is string => value !== null);
    if (urls.length === 0) return;

    pendingTextNodes.add(node);
    void Promise.all(urls.map((url) => requestPreview(url))).then((previews) => {
        if (!node.isConnected || !container.isConnected || hasStructuredContent(container)) return;
        if (previews.some((preview) => preview === null)) return;

        const previewByUrl = new Map<string, LinkPreviewResponse>();
        urls.forEach((url, index) => {
            const preview = previews[index];
            if (preview) previewByUrl.set(url, preview);
        });

        const fragment = document.createDocumentFragment();
        lines.forEach((line, index) => {
            if (index > 0) appendText(fragment, '\n');
            const url = candidates[index];
            const preview = url ? previewByUrl.get(url) : undefined;
            if (url && preview) {
                fragment.appendChild(createPreviewCard(url, preview));
            } else {
                appendText(fragment, line);
            }
        });

        node.parentNode?.replaceChild(fragment, node);
    });
}

function enhanceContainer(container: HTMLElement): void {
    if (hasStructuredContent(container)) return;

    const walker = document.createTreeWalker(container, NodeFilter.SHOW_TEXT);
    const nodes: Text[] = [];
    let current: Node | null = walker.nextNode();
    while (current) {
        nodes.push(current as Text);
        current = walker.nextNode();
    }
    nodes.forEach((node) => enhanceTextNode(node, container));
}

function scan(): void {
    document.querySelectorAll(CONTENT_SELECTOR).forEach((node) => {
        if (translatedContentContainer(node)) enhanceContainer(node);
    });
}

const queuedContainers = new Set<HTMLElement>();
let enhancementQueued = false;

function queueContainer(container: HTMLElement): void {
    queuedContainers.add(container);
}

function queueMutationContainers(records: MutationRecord[]): void {
    records.forEach((record) => {
        const containingContainer = record.target instanceof Element
            ? record.target.closest(CONTENT_SELECTOR)
            : record.target.parentElement?.closest(CONTENT_SELECTOR);

        if (containingContainer && translatedContentContainer(containingContainer)) {
            queueContainer(containingContainer);
        }

        record.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) return;

            if (translatedContentContainer(node)) {
                queueContainer(node);
            }

            node.querySelectorAll(CONTENT_SELECTOR).forEach((candidate) => {
                if (translatedContentContainer(candidate)) {
                    queueContainer(candidate);
                }
            });
        });
    });
}

function scheduleEnhancement(): void {
    if (enhancementQueued || queuedContainers.size === 0) return;
    enhancementQueued = true;
    window.requestAnimationFrame(() => {
        enhancementQueued = false;
        const containers = Array.from(queuedContainers);
        queuedContainers.clear();
        containers.forEach((container) => {
            if (container.isConnected) enhanceContainer(container);
        });
    });
}

function bootstrap(): void {
    scan();
    if (!document.body || typeof MutationObserver === 'undefined') return;

    const observer = new MutationObserver((records) => {
        queueMutationContainers(records);
        scheduleEnhancement();
    });
    observer.observe(document.body, { childList: true, subtree: true });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
} else {
    bootstrap();
}
