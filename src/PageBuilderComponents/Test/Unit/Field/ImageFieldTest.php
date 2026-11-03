<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Field;

use MageOS\PageBuilderComponents\Field\ImageField;
use PHPUnit\Framework\TestCase;

class ImageFieldTest extends TestCase
{
    /**
     * The uploader must offer what core's upload controller accepts, and never SVG, which can carry scripts.
     */
    public function testAllowsTheExtensionsCoresUploadControllerAccepts(): void
    {
        $xml = (new ImageField(label: 'Image'))->getFormXml('image', '', 'form');

        self::assertSame(1, preg_match('#<allowedExtensions>([^<]*)</allowedExtensions>#', $xml, $match));
        self::assertSame(['jpg', 'jpeg', 'png', 'gif'], explode(' ', $match[1]));
    }
}
