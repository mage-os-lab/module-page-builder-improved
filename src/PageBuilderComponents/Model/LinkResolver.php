<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Resolves a stored {@see \MageOS\PageBuilderComponents\Field\UrlField} value to a link for the current store.
 *
 * The value is the JSON of core's URL input: `{"type": "product", "product": "42", "setting": true}`. Product,
 * category and CMS page links resolve through their canonical URL rewrite (no redirect, no category path), and
 * fall back to the entity's route without one. `setting` is "Open in new tab".
 *
 * A typed URL (a plain string, or type `default`) is only kept with a safe scheme: `http:`, `https:`, `mailto:`,
 * `tel:`, or none (a relative path, a query or a `#fragment`). Anything else, `javascript:` or `data:` among
 * them, gives no link, as does a protocol-relative `//host`.
 */
class LinkResolver
{
    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Link type => [URL rewrite entity type, route, route id parameter].
     */
    private const ENTITY_LINKS = [
        'product' => ['product', 'catalog/product/view', 'id'],
        'category' => ['category', 'catalog/category/view', 'id'],
        'page' => ['cms-page', 'cms/page/view', 'page_id'],
    ];

    /**
     * Entity URLs resolved in this request: "store/type/id" => URL.
     *
     * @var array<string, string>
     */
    private array $entityUrls = [];

    /**
     * @param UrlFinderInterface $urlFinder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly UrlFinderInterface $urlFinder,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * The link, or null when none is set or its target cannot be resolved.
     *
     * @param string $stored
     * @return Link|null
     */
    public function resolve(string $stored): ?Link
    {
        $data = $stored !== '' ? json_decode($stored, true) : null;
        if (!\is_array($data)) {
            // Not JSON: a plain URL, as typed into the widget dialog.
            $url = $this->sanitizeUrl($stored);

            return $url !== null ? new Link($url) : null;
        }

        $type = \is_string($data['type'] ?? null) ? $data['type'] : 'default';
        $value = $data[$type] ?? '';
        $value = \is_scalar($value) ? trim((string) $value) : '';
        if ($value === '') {
            return null;
        }

        $url = $type === 'default' ? $this->sanitizeUrl($value) : $this->resolveEntityUrl($type, $value);
        if ($url === null) {
            return null;
        }

        return new Link($url, \in_array($data['setting'] ?? false, [true, 1, '1', 'true'], true));
    }

    /**
     * URL of a product, category or CMS page in the current store, or null.
     *
     * @param string $type
     * @param string $id
     * @return string|null
     */
    private function resolveEntityUrl(string $type, string $id): ?string
    {
        if (!isset(self::ENTITY_LINKS[$type]) || !ctype_digit($id)) {
            return null;
        }
        [$entityType, $route, $idParam] = self::ENTITY_LINKS[$type];

        try {
            /** @var Store $store */
            $store = $this->storeManager->getStore();
        } catch (NoSuchEntityException) {
            return null;
        }

        $key = $store->getId() . '/' . $type . '/' . $id;
        if (!isset($this->entityUrls[$key])) {
            $requestPath = $this->findRequestPath($entityType, (int) $id, (int) $store->getId());
            $this->entityUrls[$key] = $requestPath !== null
                ? $store->getUrl('', ['_direct' => $requestPath])
                : $store->getUrl($route, [$idParam => (int) $id]);
        }

        return $this->entityUrls[$key];
    }

    /**
     * Request path of the entity's canonical URL rewrite in a store, or null.
     *
     * Not a permanent or temporary redirect left by a URL key change, and not a product's URL under one of its
     * categories. Filtered here rather than in the query: the default URL finder compares every condition with
     * `IN (...)`, so it cannot ask for a null `metadata`.
     *
     * @param string $entityType
     * @param int $id
     * @param int $storeId
     * @return string|null
     */
    private function findRequestPath(string $entityType, int $id, int $storeId): ?string
    {
        $rewrites = $this->urlFinder->findAllByData([
            UrlRewrite::ENTITY_ID => $id,
            UrlRewrite::ENTITY_TYPE => $entityType,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::REDIRECT_TYPE => 0,
        ]);
        foreach ($rewrites as $rewrite) {
            if ($rewrite->getRedirectType() === 0 && $rewrite->getMetadata() === []) {
                return $rewrite->getRequestPath();
            }
        }

        return null;
    }

    /**
     * The URL, trimmed, when its scheme is safe in an `href`; otherwise null.
     *
     * Browsers ignore leading and trailing control characters and spaces, and tabs and line breaks anywhere,
     * so `" java\tscript:"` runs script: those are trimmed, and a URL that still has one is refused. They also
     * read `\` as `/` in a web URL, so `/\host` is protocol-relative too.
     *
     * @param string $url
     * @return string|null
     */
    private function sanitizeUrl(string $url): ?string
    {
        $url = trim($url, "\x00..\x20\x7f");
        if ($url === '' || preg_match('/[\x00-\x1f\x7f]/', $url) || preg_match('#^[/\\\\]{2}#', $url)) {
            return null;
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match)
            && !\in_array(strtolower($match[1]), self::SAFE_SCHEMES, true)
        ) {
            return null;
        }

        return $url;
    }
}
