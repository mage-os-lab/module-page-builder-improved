<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Width and height of an image in the media directory, from its URL, for `width` and `height` attributes.
 *
 * Only an image under the current store's media URL (or that URL's path, on the same site) is measured: a remote
 * URL, an SVG, a missing file or one that is not an image gives null. The URL is taken as ImageField hydrates it,
 * the stored path appended to the media URL, so it is not URL-decoded. The result is kept for the request.
 *
 * @api
 */
class ImageSize
{
    /**
     * URL => size, or null.
     *
     * @var array<string, array{width: int, height: int}|null>
     */
    private array $sizes = [];

    /**
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * The image's size in pixels, or null when it cannot be measured. Never throws.
     *
     * @param string $url An image URL, as an ImageField hydrates it.
     * @return array{width: int, height: int}|null
     */
    public function get(string $url): ?array
    {
        if (!\array_key_exists($url, $this->sizes)) {
            try {
                $this->sizes[$url] = $this->measure($url);
            } catch (Throwable) {
                $this->sizes[$url] = null;
            }
        }

        return $this->sizes[$url];
    }

    /**
     * Measure the file the URL points to.
     *
     * @param string $url
     * @return array{width: int, height: int}|null
     */
    private function measure(string $url): ?array
    {
        $path = $this->toMediaPath($url);
        if ($path === null || preg_match('/\.svgz?$/i', $path)) {
            return null;
        }
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        if (!$media->isFile($path)) {
            return null;
        }

        $absolutePath = $media->getAbsolutePath($path);
        // A local file is read as far as its header; remote media storage only through the directory.
        $size = str_contains($absolutePath, '://')
            ? getimagesizefromstring($media->readFile($path))
            // phpcs:ignore Magento2.Functions.DiscouragedFunction -- reads the header, not the whole file.
            : getimagesize($absolutePath);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return ['width' => $size[0], 'height' => $size[1]];
    }

    /**
     * The path under the media directory a URL points to, or null when it is not a media URL.
     *
     * @param string $url
     * @return string|null
     */
    private function toMediaPath(string $url): ?string
    {
        $url = trim(explode('#', explode('?', $url, 2)[0], 2)[0]);
        if ($url === '') {
            return null;
        }

        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $prefixes = [];
        foreach ([false, true] as $secure) {
            $mediaUrl = (string) $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA, $secure);
            $prefixes[] = $mediaUrl;
            // Its path, for a URL on the same site.
            if (preg_match('#^[a-z][a-z0-9+.-]*://[^/]*(/.*)$#i', $mediaUrl, $match)) {
                $prefixes[] = $match[1];
            }
        }

        foreach (array_unique($prefixes) as $prefix) {
            if ($prefix !== '' && str_starts_with($url, $prefix) && !str_starts_with($url, '//')) {
                $path = substr($url, \strlen($prefix));

                return $path !== '' && !preg_match('#(^|/)\.\.(/|$)|[\x00-\x1f\\\\]#', $path) ? $path : null;
            }
        }

        return null;
    }
}
