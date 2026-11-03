<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\PageBuilderComponents\Model\ImageSize;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ImageSizeTest extends TestCase
{
    /**
     * A 3 x 2 GIF: getimagesize() only needs the header.
     */
    private const GIF = "GIF89a\x03\x00\x02\x00\x00\x00\x00;";

    /**
     * @var string
     */
    private string $mediaDir;

    /**
     * @var ReadInterface&MockObject
     */
    private ReadInterface $media;

    /**
     * @var ImageSize
     */
    private ImageSize $imageSize;

    protected function setUp(): void
    {
        $this->mediaDir = sys_get_temp_dir() . '/mageos-pbc-image-size-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->mediaDir . 'wysiwyg/a b', 0o777, true);
        file_put_contents($this->mediaDir . 'wysiwyg/hero.gif', self::GIF);
        file_put_contents($this->mediaDir . 'wysiwyg/a b/c.gif', self::GIF);
        file_put_contents($this->mediaDir . 'wysiwyg/logo.svg', '<svg width="10" height="10"/>');
        file_put_contents($this->mediaDir . 'wysiwyg/broken.jpg', 'not an image');

        $this->media = $this->createMock(ReadInterface::class);
        $this->media->method('isFile')->willReturnCallback(fn (string $path): bool => is_file($this->mediaDir . $path));
        $this->media->method('getAbsolutePath')
            ->willReturnCallback(fn (string $path): string => $this->mediaDir . $path);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->with(DirectoryList::MEDIA)->willReturn($this->media);

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn (string $type, ?bool $secure = null): string => $type === UrlInterface::URL_TYPE_MEDIA
                ? ($secure ? 'https://shop.test/media/' : 'http://shop.test/media/')
                : ''
        );
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $this->imageSize = new ImageSize($filesystem, $storeManager);
    }

    protected function tearDown(): void
    {
        foreach (['wysiwyg/hero.gif', 'wysiwyg/a b/c.gif', 'wysiwyg/logo.svg', 'wysiwyg/broken.jpg'] as $file) {
            unlink($this->mediaDir . $file);
        }
        rmdir($this->mediaDir . 'wysiwyg/a b');
        rmdir($this->mediaDir . 'wysiwyg');
        rmdir($this->mediaDir);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function mediaUrls(): array
    {
        return [
            'secure media url' => ['https://shop.test/media/wysiwyg/hero.gif'],
            'unsecure media url' => ['http://shop.test/media/wysiwyg/hero.gif'],
            'media path on the same site' => ['/media/wysiwyg/hero.gif'],
            'with a query and a fragment' => ['https://shop.test/media/wysiwyg/hero.gif?v=2#x'],
            'a space in the path' => ['https://shop.test/media/wysiwyg/a b/c.gif'],
        ];
    }

    /**
     * @dataProvider mediaUrls
     */
    public function testMeasuresAnImageInTheMediaDirectory(string $url): void
    {
        self::assertSame(['width' => 3, 'height' => 2], $this->imageSize->get($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unmeasurable(): array
    {
        return [
            'empty' => [''],
            'remote' => ['https://cdn.test/media/wysiwyg/hero.gif'],
            'protocol-relative' => ['//shop.test/media/wysiwyg/hero.gif'],
            'media-relative path' => ['wysiwyg/hero.gif'],
            'svg' => ['https://shop.test/media/wysiwyg/logo.svg'],
            'missing file' => ['https://shop.test/media/wysiwyg/missing.gif'],
            'not an image' => ['https://shop.test/media/wysiwyg/broken.jpg'],
            'the media directory itself' => ['https://shop.test/media/'],
            'out of the media directory' => ['https://shop.test/media/../app/etc/env.php'],
            'out of the media directory, encoded' => ['https://shop.test/media/wysiwyg/%2e%2e/%2e%2e/env.php'],
        ];
    }

    /**
     * @dataProvider unmeasurable
     */
    public function testReturnsNullForWhatItCannotMeasure(string $url): void
    {
        self::assertNull($this->imageSize->get($url));
    }

    public function testMeasuresEachUrlOncePerRequest(): void
    {
        $this->media->expects(self::once())->method('getAbsolutePath');

        $this->imageSize->get('https://shop.test/media/wysiwyg/hero.gif');
        self::assertSame(
            ['width' => 3, 'height' => 2],
            $this->imageSize->get('https://shop.test/media/wysiwyg/hero.gif')
        );
    }

    public function testNeverThrows(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willThrowException(new RuntimeException('No media directory'));
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willThrowException(new RuntimeException('No store'));

        self::assertNull((new ImageSize($filesystem, $storeManager))->get('https://shop.test/media/a.gif'));
    }
}
