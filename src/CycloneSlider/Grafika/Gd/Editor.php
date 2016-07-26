<?php

namespace CycloneSlider\Grafika\Gd;

use CycloneSlider\Grafika\DrawingObjectInterface;
use CycloneSlider\Grafika\EditorInterface;
use CycloneSlider\Grafika\EffectInterface;
use CycloneSlider\Grafika\Gd\DrawingObject\CubicBezier;
use CycloneSlider\Grafika\Gd\DrawingObject\Ellipse;
use CycloneSlider\Grafika\Gd\DrawingObject\Line;
use CycloneSlider\Grafika\Gd\DrawingObject\Polygon;
use CycloneSlider\Grafika\Gd\DrawingObject\QuadraticBezier;
use CycloneSlider\Grafika\Gd\DrawingObject\Rectangle;
use CycloneSlider\Grafika\Gd\Effect\Dither;
use CycloneSlider\Grafika\Grafika;
use CycloneSlider\Grafika\ImageInterface;
use CycloneSlider\Grafika\ImageType;
use CycloneSlider\Grafika\Color;

/**
 * GD Editor class. Uses the PHP GD library.
 * @package Grafika\Gd
 */
final class Editor implements EditorInterface
{

    /**
     * @var Image Holds the image instance.
     */
    private $image;

    /**
     * Constructor.
     */
    function __construct()
    {
        $this->image = null;
    }

    /**
     * @param EffectInterface $effect
     *
     * @return $this
     */
    public function apply($effect)
    {
        $this->image = $effect->apply($this->image);

        return $this;
    }

    /**
     * Creates a cubic bezier. Cubic bezier has 2 control points.
     *
     * @param array $point1 Array of X and Y value for start point.
     * @param array $control1 Array of X and Y value for control point 1.
     * @param array $control2 Array of X and Y value for control point 2.
     * @param array $point2 Array of X and Y value for end point.
     * @param Color|string $color Color of the curve. Accepts hex string or a Color object. Defaults to black.
     *
     * @return Editor
     */
    public function bezierCubic($point1, $control1, $control2, $point2, $color = '#000000')
    {
        if (is_string($color)) {
            $color = new Color($color);
        }
        $obj = new CubicBezier($point1, $control1, $control2, $point2, $color);

        return $this->draw($obj);
    }

    /**
     * Creates a quadratic bezier. Quadratic bezier has 1 control point.
     *
     * @param array $point1 Array of X and Y value for start point.
     * @param array $control Array of X and Y value for control point.
     * @param array $point2 Array of X and Y value for end point.
     * @param Color|string $color Color of the curve. Accepts hex string or a Color object. Defaults to black.
     *
     * @return Editor
     */
    public function bezierQuad($point1, $control, $point2, $color = '#000000')
    {
        if (is_string($color)) {
            $color = new Color($color);
        }
        $obj = new QuadraticBezier($point1, $control, $point2, $color);

        return $this->draw($obj);
    }

    /**
     * Create a blank image given width and height.
     *
     * @param int $width Width of image in pixels.
     * @param int $height Height of image in pixels.
     *
     * @return self
     */
    public function blank($width, $height)
    {
        $this->image = Image::createBlank($width, $height);

        return $this;
    }

    /**
     * Compare two images and returns a hamming distance. A value of 0 indicates a likely similar picture. A value between 1 and 10 is potentially a variation. A value greater than 10 is likely a different image.
     *
     * @param ImageInterface|string $image1
     * @param ImageInterface|string $image2
     *
     * @return int Hamming distance. Note: This breaks the chain if you are doing fluent api calls as it does not return an Editor.
     * @throws \Exception
     */
    public function compare($image1, $image2)
    {

        if (is_string($image1)) { // If string passed, turn it into a Image object
            $image1 = Image::createFromFile($image1);
        }

        if (is_string($image2)) { // If string passed, turn it into a Image object
            $image2 = Image::createFromFile($image2);
        }

        $bin1     = $this->_differenceHash($image1);
        $bin2     = $this->_differenceHash($image2);
        $str1     = str_split($bin1);
        $str2     = str_split($bin2);
        $distance = 0;
        foreach ($str1 as $i => $char) {
            if ($char !== $str2[$i]) {
                $distance++;
            }
        }

        return $distance;

    }

    /**
     * Crop the image to the given dimension and position.
     *
     * @param int $cropWidth Crop width in pixels.
     * @param int $cropHeight Crop Height in pixels.
     * @param int|string $cropX The number of pixels from the left of the image. This parameter can be a number or any of the words "left", "center", "right".
     * @param int|string $cropY The number of pixels from the top of the image. This parameter can be a number or any of the words "top", "center", "bottom".
     *
     * @return self
     */
    public function crop($cropWidth, $cropHeight, $cropX = 'center', $cropY = 'center')
    {

        if (is_string($cropX)) {
            // Compute position from string
            switch ($cropX) {
                case 'left':
                    $x = 0;
                    break;

                case 'right':
                    $x = $this->image->getWidth() - $cropWidth;
                    break;

                case 'center':
                default:
                    $x = (int)round(($this->image->getWidth() / 2) - ($cropWidth / 2));
                    break;
            }
        } else {
            $x = $cropX;
        }

        if (is_string($cropY)) {
            switch ($cropY) {
                case 'top':
                    $y = 0;
                    break;

                case 'bottom':
                    $y = $this->image->getHeight() - $cropHeight;
                    break;

                case 'center':
                default:
                    $y = (int)round(($this->image->getHeight() / 2) - ($cropHeight / 2));
                    break;
            }
        } else {
            $y = $cropY;
        }

        // Create blank image
        $newImageResource = imagecreatetruecolor($cropWidth, $cropHeight);

        // Now crop
        imagecopyresampled(
            $newImageResource, // Target image
            $this->image->getCore(), // Source image
            0, // Target x
            0, // Target y
            $x, // Src x
            $y, // Src y
            $cropWidth, // Target width
            $cropHeight, // Target height
            $cropWidth, // Src width
            $cropHeight // Src height
        );

        // Free memory of old resource
        imagedestroy($this->image->getCore());

        // Cropped image instance
        $this->image = new Image($newImageResource, $this->image->getImageFile(), $cropWidth, $cropHeight, $this->image->getType());

        return $this;
    }

    /**
     * Dither image using Floyd-Steinberg algorithm. Dithering will reduce the color to black and white and add noise.
     * @return EditorInterface An instance of image editor.
     */
    public function dither()
    {
        $e = new Dither();

        return $this->apply($e);
    }

    /**
     * @param DrawingObjectInterface $drawingObject
     *
     * @return $this
     */
    public function draw($drawingObject)
    {
        $this->image = $drawingObject->draw($this->image);

        return $this;
    }

    /**
     * Creates an ellipse.
     *
     * @param int $width Width of ellipse in pixels.
     * @param int $height Height of ellipse in pixels.
     * @param array $pos Array containing int X and int Y position of the ellipse from top left of the canvass.
     * @param int $borderSize Size of the border in pixels. Defaults to 1 pixel. Set to 0 for no border.
     * @param Color|string|null $borderColor Border color. Defaults to black. Set to null for no color.
     * @param Color|string|null $fillColor Fill color. Defaults to white. Set to null for no color.
     *
     * @return EditorInterface An instance of image editor.
     */
    public function ellipse(
        $width,
        $height,
        array $pos,
        $borderSize = 1,
        $borderColor = '#000000',
        $fillColor = '#FFFFFF'
    ) {
        if (is_string($borderColor)) {
            $borderColor = new Color($borderColor);
        }
        if (is_string($fillColor)) {
            $fillColor = new Color($fillColor);
        }
        $obj = new Ellipse($width, $height, $pos, $borderSize, $borderColor, $fillColor);

        return $this->draw($obj);
    }

    /**
     * Compare if two images are equal. It will compare if the two images are of the same width and height. If the dimensions differ, it will return false. If the dimensions are equal, it will loop through each pixels. If one of the pixel don't match, it will return false. The pixels are compared using their RGB (Red, Green, Blue) values.
     *
     * @param string|ImageInterface $image1 Can be an instance of Image or string containing the file system path to image.
     * @param string|ImageInterface $image2 Can be an instance of Image or string containing the file system path to image.
     *
     * @return bool True if equals false if not. Note: This breaks the chain if you are doing fluent api calls as it does not return an Editor.
     * @throws \Exception
     */
    public function equal($image1, $image2)
    {

        if (is_string($image1)) { // If string passed, turn it into a Image object
            $image1 = Image::createFromFile($image1);
        }

        if (is_string($image2)) { // If string passed, turn it into a Image object
            $image2 = Image::createFromFile($image2);
        }

        // Check if image dimensions are equal
        if ($image1->getWidth() !== $image2->getWidth() or $image1->getHeight() !== $image2->getHeight()) {

            return false;

        } else {

            // Loop using image1
            for ($y = 0; $y < $image1->getHeight(); $y++) {
                for ($x = 0; $x < $image1->getWidth(); $x++) {

                    // Get image1 pixel
                    $rgb1 = imagecolorat($image1->getCore(), $x, $y);
                    $r1   = ($rgb1 >> 16) & 0xFF;
                    $g1   = ($rgb1 >> 8) & 0xFF;
                    $b1   = $rgb1 & 0xFF;

                    // Get image2 pixel
                    $rgb2 = imagecolorat($image2->getCore(), $x, $y);
                    $r2   = ($rgb2 >> 16) & 0xFF;
                    $g2   = ($rgb2 >> 8) & 0xFF;
                    $b2   = $rgb2 & 0xFF;

                    // Compare pixel value
                    if (
                        $r1 !== $r2 or
                        $g1 !== $g2 or
                        $b1 !== $b2
                    ) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Fill entire image with color.
     *
     * @param Color $color Color object
     * @param int $x X-coordinate of start point
     * @param int $y Y-coordinate of start point
     *
     * @return self
     */
    public function fill($color, $x = 0, $y = 0)
    {

        $this->_imageCheck();

        list($r, $g, $b, $alpha) = $color->getRgba();

        $colorResource = imagecolorallocatealpha($this->image->getCore(), $r, $g, $b,
            $this->gdAlpha($alpha));
        imagefill($this->image->getCore(), $x, $y, $colorResource);

        return $this;
    }

    /**
     * Free the current image clearing resources associated with it.
     */
    public function free()
    {
        if (null !== $this->image) {
            if (null !== $this->image->getCore()) {
                imagedestroy($this->image->getCore());
            }
        } else {
            $this->image = null;
        }
    }


    /**
     * Converts image to grayscale.
     *
     * @return $this
     */
    public function grayscale()
    {
        $this->_imageCheck();

        imagefilter($this->image->getCore(), IMG_FILTER_GRAYSCALE);

        return $this;
    }

    /**
     * Alias for grayscale. They are the same.
     *
     * @return $this
     */
    public function greyscale()
    {
        return $this->grayscale();
    }

    /**
     * Get image instance.
     *
     * @return Image
     */
    public function getImage()
    {
        return $this->image;
    }

    /**
     * Checks if the editor is available on the current PHP install.
     *
     * @return bool True if available false if not.
     */
    public function isAvailable()
    {
        if (false === extension_loaded('gd') || false === function_exists('gd_info')) {
            return false;
        }

        // On some setups GD library does not provide imagerotate()
        if ( ! function_exists('imagerotate')) {

            return false;
        }

        return true;
    }

    /**
     * Creates a line.
     *
     * @param array $point1 Array containing int X and int Y position of the starting point.
     * @param array $point2 Array containing int X and int Y position of the starting point.
     * @param int $thickness Thickness in pixel. Note: This is currently ignored in GD editor and falls back to 1.
     * @param Color|string $color Color of the line. Defaults to black.
     *
     * @return Editor
     */
    public function line(array $point1, array $point2, $thickness = 1, $color = '#000000')
    {
        if (is_string($color)) {
            $color = new Color($color);
        }
        $obj = new Line($point1, $point2, $thickness, $color);

        return $this->draw($obj);
    }

    /**
     * Sets the image to the specified opacity level where 1.0 is fully opaque and 0.0 is fully transparent.
     * Warning: This function loops thru each pixel manually which can be slow. Use sparingly.
     *
     * @param float $opacity
     *
     * @return self
     * @throws \Exception
     */
    public function opacity($opacity)
    {

        $this->_imageCheck();

        // Bounds checks
        $opacity = ($opacity > 1) ? 1 : $opacity;
        $opacity = ($opacity < 0) ? 0 : $opacity;

        for ($y = 0; $y < $this->image->getHeight(); $y++) {
            for ($x = 0; $x < $this->image->getWidth(); $x++) {
                $rgb   = imagecolorat($this->image->getCore(), $x, $y);
                $alpha = ($rgb >> 24) & 0x7F; // 127 in hex. These are binary operations.
                $r     = ($rgb >> 16) & 0xFF;
                $g     = ($rgb >> 8) & 0xFF;
                $b     = $rgb & 0xFF;

                // Reverse alpha values from 127-0 (transparent to opaque) to 0-127 for easy math
                // Previously: 0 = opaque, 127 = transparent.
                // Now: 0 = transparent, 127 = opaque
                $reverse = 127 - $alpha;
                $reverse = round($reverse * $opacity);

                if ($alpha < 127) { // Process non transparent pixels only
                    imagesetpixel($this->image->getCore(), $x, $y,
                        imagecolorallocatealpha($this->image->getCore(), $r, $g, $b, 127 - $reverse));
                }
            }
        }

        return $this;
    }

    /**
     * Opens an image file for manipulation specified by $target.
     *
     * @param mixed $target Can be an instance of Image or a string containing file system path to the image.
     *
     * @return Editor
     * @throws \Exception
     */
    public function open($target)
    {
        if ($target instanceof ImageInterface) {
            $this->openImage($target);
        } else if (is_string($target)) {
            $this->openFile($target);
        } else {
            throw new \Exception('Could not open image.');
        }

        return $this;
    }

    /**
     * Open an image by passing an instance of Image.
     *
     * @param ImageInterface $image
     *
     * @return $this
     */
    public function openImage($image)
    {
        $this->image = $image;

        return $this;
    }

    /**
     * Open an image by passing a file system path.
     *
     * @param string $file A full path to the image in the file system.
     *
     * @return $this
     * @throws \Exception
     */
    public function openFile($file)
    {
        $this->image = Image::createFromFile($file);

        return $this;
    }

    /**
     * Overlay an image on top of the current image.
     *
     * @param Image|string $overlay Can be a string containing a file path of the image to overlay or an Image object.
     * @param string|int $xPos Horizontal position of image. Can be 'left','center','right' or integer number. Defaults to 'center'.
     * @param string|int $yPos Vertical position of image. Can be 'top', 'center','bottom' or integer number. Defaults to 'center'.
     * @param null $width
     * @param null $height
     *
     * @return Editor
     * @throws \Exception
     */
    public function overlay($overlay, $xPos = 'center', $yPos = 'center', $width = null, $height = null)
    {

        $this->_imageCheck();

        if (is_string($overlay)) { // If string passed, turn it into a Image object
            $overlay = Image::createFromFile($overlay);
        }

        // Resize overlay
        if ($width and $height) {

            $overlayWidth  = $overlay->getWidth();
            $overlayHeight = $overlay->getHeight();

            if (is_numeric($width)) {
                $overlayWidth = (int)$width;
            } else {
                $percent = strpos($width, '%');
                if (false !== $percent) {
                    $overlayWidth = intval($width) / 100 * $this->image->getWidth();
                }
            }

            if (is_numeric($height)) {
                $overlayHeight = (int)$height;
            } else {
                $percent = strpos($height, '%');
                if (false !== $percent) {
                    $overlayHeight = intval($height) / 100 * $this->image->getHeight();
                }
            }

            $editor = new Editor();
            $editor->setImage($overlay);
            $editor->resizeFit($overlayWidth, $overlayHeight);
            $overlay = $editor->getImage();
        }

        //$x = $y = 0;

        if (is_string($xPos)) {
            // Compute position from string
            switch ($xPos) {
                case 'left':
                    $x = 0;
                    break;

                case 'right':
                    $x = $this->image->getWidth() - $overlay->getWidth();
                    break;

                case 'center':
                default:
                    $x = (int)round(($this->image->getWidth() / 2) - ($overlay->getWidth() / 2));
                    break;
            }
        } else {
            $x = $xPos;
        }

        if (is_string($yPos)) {
            switch ($yPos) {
                case 'top':
                    $y = 0;
                    break;

                case 'bottom':
                    $y = $this->image->getHeight() - $overlay->getHeight();
                    break;

                case 'center':
                default:
                    $y = (int)round(($this->image->getHeight() / 2) - ($overlay->getHeight() / 2));
                    break;
            }
        } else {
            $y = $yPos;
        }

        imagecopyresampled(
            $this->image->getCore(), // Base image
            $overlay->getCore(), // Overlay
            (int)$x, // Overlay x position
            (int)$y, // Overlay y position
            0,
            0,
            $overlay->getWidth(), // Overlay final width
            $overlay->getHeight(), // Overlay final height
            $overlay->getWidth(), // Overlay source width
            $overlay->getHeight() // Overlay source height
        );

        return $this;

    }

    /**
     * Creates a polygon.
     *
     * @param array $points Array of all X and Y positions. Must have at least three positions.
     * @param int $borderSize Size of the border in pixels. Defaults to 1 pixel. Set to 0 for no border.
     * @param Color|string|null $borderColor Border color. Defaults to black. Set to null for no color.
     * @param Color|string|null $fillColor Fill color. Defaults to white. Set to null for no color.
     *
     * @return EditorInterface An instance of image editor.
     */
    public function polygon($points, $borderSize = 1, $borderColor = '#000000', $fillColor = '#FFFFFF')
    {
        if (is_string($borderColor)) {
            $borderColor = new Color($borderColor);
        }
        if (is_string($fillColor)) {
            $fillColor = new Color($fillColor);
        }
        $obj = new Polygon($points, $borderSize, $borderColor, $fillColor);

        return $this->draw($obj);
    }

    /**
     * Creates a rectangle.
     *
     * @param int $width Width of rectangle in pixels.
     * @param int $height Height in pixels.
     * @param array $pos Array of X and Y position. X is the distance in pixels from the left of the canvass to the left of the rectangle. Y is the distance from the top of the canvass to the top of the rectangle. Defaults to array(0,0).
     * @param int $borderSize Size of the border in pixels. Defaults to 1 pixel. Set to 0 for no border.
     * @param Color|string|null $borderColor Border color. Defaults to black. Set to null for no color.
     * @param Color|string|null $fillColor Fill color. Defaults to white. Set to null for no color.
     *
     * @return Editor
     */
    public function rectangle(
        $width,
        $height,
        $pos = array(0, 0),
        $borderSize = 1,
        $borderColor = '#000000',
        $fillColor = '#FFFFFF'
    ) {
        if (is_string($borderColor)) {
            $borderColor = new Color($borderColor);
        }
        if (is_string($fillColor)) {
            $fillColor = new Color($fillColor);
        }
        $obj = new Rectangle($width, $height, $pos, $borderSize, $borderColor, $fillColor);

        return $this->draw($obj);
    }


    /**
     * Wrapper function for the resizeXXX family of functions. Resize image given width, height and mode.
     *
     * @param int $newWidth Width in pixels.
     * @param int $newHeight Height in pixels.
     * @param string $mode Resize mode. Possible values: "exact", "exactHeight", "exactWidth", "fill", "fit".
     *
     * @return Editor
     * @throws \Exception
     */
    public function resize($newWidth, $newHeight, $mode = 'fit')
    {
        /*
         * Resize formula:
         * ratio = w / h
         * h = w / ratio
         * w = h * ratio
         */
        switch ($mode) {
            case 'exact':
                $this->resizeExact($newWidth, $newHeight);
                break;
            case 'fill':
                $this->resizeFill($newWidth, $newHeight);
                break;
            case 'exactWidth':
                $this->resizeExactWidth($newWidth);
                break;
            case 'exactHeight':
                $this->resizeExactHeight($newHeight);
                break;
            case 'fit':
                $this->resizeFit($newWidth, $newHeight);
                break;
            default:
                throw new \Exception(sprintf('Invalid resize mode "%s".', $mode));
        }

        return $this;
    }

    /**
     * Resize image to exact dimensions ignoring aspect ratio. Useful if you want to force exact width and height.
     *
     * @param int $newWidth Width in pixels.
     * @param int $newHeight Height in pixels.
     *
     * @return self
     */
    public function resizeExact($newWidth, $newHeight)
    {

        $this->_resize($newWidth, $newHeight);

        return $this;
    }

    /**
     * Resize image to exact height. Width is auto calculated. Useful for creating row of images with the same height.
     *
     * @param int $newHeight Height in pixels.
     *
     * @return self
     */
    public function resizeExactHeight($newHeight)
    {

        $width  = $this->image->getWidth();
        $height = $this->image->getHeight();
        $ratio  = $width / $height;

        $resizeHeight = $newHeight;
        $resizeWidth  = $newHeight * $ratio;

        $this->_resize($resizeWidth, $resizeHeight);

        return $this;
    }

    /**
     * Resize image to exact width. Height is auto calculated. Useful for creating column of images with the same width.
     *
     * @param int $newWidth Width in pixels.
     *
     * @return self
     */
    public function resizeExactWidth($newWidth)
    {

        $width  = $this->image->getWidth();
        $height = $this->image->getHeight();
        $ratio  = $width / $height;

        $resizeWidth  = $newWidth;
        $resizeHeight = round($newWidth / $ratio);

        $this->_resize($resizeWidth, $resizeHeight);

        return $this;
    }

    /**
     * Resize image to fill all the space in the given dimension. Excess parts are cropped.
     *
     * @param int $newWidth Width in pixels.
     * @param int $newHeight Height in pixels.
     *
     * @return self
     */
    public function resizeFill($newWidth, $newHeight)
    {
        $width  = $this->image->getWidth();
        $height = $this->image->getHeight();
        $ratio  = $width / $height;

        // Base optimum size on new width
        $optimumWidth  = $newWidth;
        $optimumHeight = round($newWidth / $ratio);

        if (($optimumWidth < $newWidth) or ($optimumHeight < $newHeight)) { // Oops, where trying to fill and there are blank areas
            // So base optimum size on height instead
            $optimumWidth  = $newHeight * $ratio;
            $optimumHeight = $newHeight;
        }

        $this->_resize($optimumWidth, $optimumHeight);
        $this->crop($newWidth, $newHeight); // Trim excess parts

        return $this;
    }

    /**
     * Resize image to fit inside the given dimension. No part of the image is lost.
     *
     * @param int $newWidth Width in pixels.
     * @param int $newHeight Height in pixels.
     *
     * @return self
     */
    public function resizeFit($newWidth, $newHeight)
    {

        $width  = $this->image->getWidth();
        $height = $this->image->getHeight();
        $ratio  = $width / $height;

        // Try basing it on width first
        $resizeWidth  = $newWidth;
        $resizeHeight = round($newWidth / $ratio);

        if (($resizeWidth > $newWidth) or ($resizeHeight > $newHeight)) { // Oops, either with or height does not fit
            // So base on height instead
            $resizeHeight = $newHeight;
            $resizeWidth  = $newHeight * $ratio;
        }

        $this->_resize($resizeWidth, $resizeHeight);

        return $this;
    }

    /**
     * Rotate an image counter-clockwise.
     *
     * @param int $angle The angle in degrees.
     * @param Color|null $color The Color object containing the background color.
     *
     * @return EditorInterface An instance of image editor.
     */
    public function rotate($angle, $color = null)
    {

        $this->_imageCheck();

        $color = ($color !== null) ? $color : new Color('#000000');
        list($r, $g, $b, $alpha) = $color->getRgba();

        imagerotate($this->image->getCore(), $angle,
            imagecolorallocatealpha($this->image->getCore(), $r, $g, $b, $alpha));

        return $this;
    }

    /**
     * Save the image to an image format.
     *
     * @param string $file File path where to save the image.
     * @param null|string $type Type of image. Can be null, "GIF", "PNG", or "JPEG".
     * @param null|string $quality Quality of image. Applies to JPEG only. Accepts number 0 - 100 where 0 is lowest and 100 is the highest quality. Or null for default.
     * @param bool|false $interlace Set to true for progressive JPEG. Applies to JPEG only.
     * @param int $permission Default permission when creating non-existing target directory.
     *
     * @return Editor
     * @throws \Exception
     */
    public function save($file, $type = null, $quality = null, $interlace = false, $permission = 0755)
    {

        $this->_imageCheck();

        if (null === $type) {

            $type = $this->_getImageTypeFromFileName($file); // Null given, guess type from file extension
            if (ImageType::UNKNOWN === $type) {
                $type = $this->image->getType(); // 0 result, use original image type
            }
        }

        $targetDir = dirname($file); // $file's directory
        if (false === is_dir($targetDir)) { // Check if $file's directory exist
            // Create and set default perms to 0755
            if ( ! mkdir($targetDir, $permission, true)) {
                throw new \Exception(sprintf('Cannot create %s', $targetDir));
            }
        }

        switch (strtoupper($type)) {
            case ImageType::GIF :
                imagegif($this->image->getCore(), $file);
                break;

            case ImageType::PNG :
                // PNG is lossless and does not need compression. Although GD allow values 0-9 (0 = no compression), we leave it alone.
                imagepng($this->image->getCore(), $file);
                break;

            default: // Defaults to jpeg
                $quality = ($quality === null) ? 75 : $quality; // Default to 75 (GDs default) if null.
                $quality = ($quality > 100) ? 100 : $quality;
                $quality = ($quality < 0) ? 0 : $quality;
                imageinterlace($this->image->getCore(), $interlace);
                imagejpeg($this->image->getCore(), $file, $quality);
        }

        return $this;
    }

    /**
     * Set image instance.
     *
     * @param Image $image
     */
    public function setImage($image)
    {
        $this->image = $image;
    }

    /**
     * Write text to image.
     *
     * @param string $text The text to be written.
     * @param int $size The font size. Defaults to 12.
     * @param int $x The distance from the left edge of the image to the left of the text. Defaults to 0.
     * @param int $y The distance from the top edge of the image to the top of the text. Defaults to 12 (equal to font size) so that the text is placed within the image.
     * @param Color $color The Color object. Default text color is black.
     * @param string $font Full path to font file. If blank, will default to Liberation Sans font.
     * @param int $angle Angle of text from 0 - 359. Defaults to 0.
     *
     * @return EditorInterface
     * @throws \Exception
     */
    public function text($text, $size = 12, $x = 0, $y = 0, $color = null, $font = '', $angle = 0)
    {

        $this->_imageCheck();

        $y += $size;

        $color = ($color !== null) ? $color : new Color('#000000');
        $font  = ($font !== '') ? $font : Grafika::fontsDir() . DIRECTORY_SEPARATOR . 'LiberationSans-Regular.ttf';

        list($r, $g, $b, $alpha) = $color->getRgba();

        $colorResource = imagecolorallocatealpha(
            $this->image->getCore(),
            $r, $g, $b,
            $this->gdAlpha($alpha)
        );

        imagettftext(
            $this->image->getCore(),
            $size,
            $angle,
            $x,
            $y,
            $colorResource,
            $font,
            $text
        );

        return $this;
    }

    /**
     * Get difference hash of image.
     * Algorithm:
     * Reduce size. The fastest way to remove high frequencies and detail is to shrink the image. In this case, shrink it to 9x8 so that there are 72 total pixels.
     * Reduce color. Convert the image to a grayscale picture. This changes the hash from 72 pixels to a total of 72 colors.
     * Compute the difference. The algorithm works on the difference between adjacent pixels. This identifies the relative gradient direction. In this case, the 9 pixels per row yields 8 differences between adjacent pixels. Eight rows of eight differences becomes 64 bits.
     * Assign bits. Each bit is simply set based on whether the left pixel is brighter than the right pixel.
     *
     * http://www.hackerfactor.com/blog/index.php?/archives/529-Kind-of-Like-That.html
     * @param Image $image
     *
     * @return string
     */
    private function _differenceHash($image)
    {

        $width  = 9;
        $height = 8;

        $editor = new Editor();
        $editor->setImage($image);
        $editor->resizeExact($width, $height); // Resize to exactly 9x8
        $gd = $editor->getImage()->getCore();

        // Build hash
        $hash = '';
        for ($y = 0; $y < $height; $y++) {
            // Get the pixel value for the leftmost pixel.
            $rgba = imagecolorat($gd, 0, $y);
            $r    = ($rgba >> 16) & 0xFF;
            $g    = ($rgba >> 8) & 0xFF;
            $b    = $rgba & 0xFF;

            $left = floor(($r + $g + $b) / 3);
            for ($x = 1; $x < $width; $x++) {
                // Get the pixel value for each pixel starting from position 1.
                $rgba  = imagecolorat($gd, $x, $y);
                $r     = ($rgba >> 16) & 0xFF;
                $g     = ($rgba >> 8) & 0xFF;
                $b     = $rgba & 0xFF;
                $right = floor(($r + $g + $b) / 3);
                // Each hash bit is set based on whether the left pixel is brighter than the right pixel.
                if ($left > $right) {
                    $hash .= '1';
                } else {
                    $hash .= '0';
                }
                // Prepare the next loop.
                $left = $right;
            }
        }

        return $hash;
    }

    // TODO: Maybe detach hashes from editor and move to their own classes
//    private function _averageHash($image)
//    {
//        // Resize the image.
//        $width = 8;
//        $height = 8;
//
//        $editor = new Editor();
//        $editor->setImage($image);
//        $editor->resizeExact( $width, $height); // Resize to exactly 9x8
//        $gd = $editor->getImage()->getCore();
//
//        // Create an array of greyscale pixel values.
//        $pixels = array();
//        for ($y = 0; $y < $height; $y++) {
//            for ($x = 0; $x < $width; $x++) {
//                $rgba = imagecolorat($gd, $x, $y);
//                $r = ($rgba >> 16) & 0xFF;
//                $g = ($rgba >> 8) & 0xFF;
//                $b = $rgba & 0xFF;
//
//                $pixels[] = floor(($r + $g + $b) / 3); // Gray
//            }
//        }
//
//        // Get the average pixel value.
//        $average = floor(array_sum($pixels) / count($pixels));
//        // Each hash bit is set based on whether the current pixels value is above or below the average.
//        $hash = '';
//        foreach ($pixels as $pixel) {
//            if ($pixel > $average) {
//                $hash .= '1';
//            } else {
//                $hash .= '0';
//            }
//        }
//        return $hash;
//    }

    /**
     * Resize helper function.
     *
     * @param int $newWidth
     * @param int $newHeight
     * @param int $targetX
     * @param int $targetY
     * @param int $srcX
     * @param int $srcY
     *
     * @throws \Exception
     */
    private function _resize($newWidth, $newHeight, $targetX = 0, $targetY = 0, $srcX = 0, $srcY = 0)
    {

        $this->_imageCheck();

        // Create blank image
        $newImage = Image::createBlank($newWidth, $newHeight);

        if (ImageType::PNG === $this->image->getType()) {
            // Preserve PNG transparency
            $newImage->fullAlphaMode(true);
        }

        imagecopyresampled(
            $newImage->getCore(),
            $this->image->getCore(),
            $targetX,
            $targetY,
            $srcX,
            $srcY,
            $newWidth,
            $newHeight,
            $this->image->getWidth(),
            $this->image->getHeight()
        );

        // Free memory of old resource
        imagedestroy($this->image->getCore());

        // Resize image instance
        $this->image = new Image(
            $newImage->getCore(),
            $this->image->getImageFile(),
            $newWidth,
            $newHeight,
            $this->image->getType()
        );

    }

    /**
     * Convert alpha value of 0 - 1 to GD compatible alpha value of 0 - 127 where 0 is opaque and 127 is transparent
     *
     * @param float $alpha Alpha value of 0 - 1. Example: 0, 0.60, 0.9, 1
     *
     * @return int
     */
    public static function gdAlpha($alpha)
    {

        $scale = round(127 * $alpha);

        return $invert = 127 - $scale;
    }

    /**
     * Get image type base on file extension.
     *
     * @param int $imageFile File path to image.
     *
     * @return ImageType string Type of image.
     */
    private function _getImageTypeFromFileName($imageFile)
    {
        $ext = strtolower((string)pathinfo($imageFile, PATHINFO_EXTENSION));

        if ('jpg' == $ext or 'jpeg' == $ext) {
            return ImageType::JPEG;
        } else if ('gif' == $ext) {
            return ImageType::GIF;
        } else if ('png' == $ext) {
            return ImageType::PNG;
        } else {
            return ImageType::UNKNOWN;
        }
    }

    /**
     * Check if editor has already been assigned an image.
     *
     * @throws \Exception
     */
    private function _imageCheck()
    {
        if (null === $this->image) {
            throw new \Exception('No image to edit.');
        }
    }

}