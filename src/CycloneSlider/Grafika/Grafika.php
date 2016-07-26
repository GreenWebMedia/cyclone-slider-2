<?php

namespace CycloneSlider\Grafika;

use CycloneSlider\Grafika\Gd\DrawingObject\CubicBezier as GdCubicBezier;
use CycloneSlider\Grafika\Gd\DrawingObject\Ellipse as GdEllipse;
use CycloneSlider\Grafika\Gd\DrawingObject\Line as GdLine;
use CycloneSlider\Grafika\Gd\DrawingObject\Polygon as GdPolygon;
use CycloneSlider\Grafika\Gd\DrawingObject\QuadraticBezier as GdQuadraticBezier;
use CycloneSlider\Grafika\Gd\DrawingObject\Rectangle as GdRectangle;
use CycloneSlider\Grafika\Gd\Editor as GdEditor;
use CycloneSlider\Grafika\Gd\Filter\Dither as GdDither;
use CycloneSlider\Grafika\Gd\Filter\Grayscale as GdGrayscale;
use CycloneSlider\Grafika\Gd\Filter\Sobel as GdSobel;
use CycloneSlider\Grafika\Gd\Image as GdImage;
use CycloneSlider\Grafika\Imagick\DrawingObject\CubicBezier as ImagickCubicBezier;
use CycloneSlider\Grafika\Imagick\DrawingObject\Ellipse as ImagickEllipse;
use CycloneSlider\Grafika\Imagick\DrawingObject\Line as ImagickLine;
use CycloneSlider\Grafika\Imagick\DrawingObject\Polygon as ImagickPolygon;
use CycloneSlider\Grafika\Imagick\DrawingObject\QuadraticBezier as ImagickQuadraticBezier;
use CycloneSlider\Grafika\Imagick\DrawingObject\Rectangle as ImagickRectangle;
use CycloneSlider\Grafika\Imagick\Editor as ImagickEditor;
use CycloneSlider\Grafika\Imagick\Filter\Dither as ImagickDither;
use CycloneSlider\Grafika\Imagick\Filter\Grayscale as ImagickGrayscale;
use CycloneSlider\Grafika\Imagick\Filter\Sobel as ImagickSobel;
use CycloneSlider\Grafika\Imagick\Image as ImagickImage;

/**
 * Contains factory methods for detecting editors, creating editors and images.
 * @package Grafika
 */
class Grafika
{

    /**
     * Grafika root directory
     */
    const DIR = __DIR__;

    /**
     * @var array $editorList List of editors to evaluate.
     */
    private static $editorList = array('Imagick', 'Gd');

    /**
     * Return path to directory containing fonts used in text operations.
     *
     * @return string
     */
    public static function fontsDir()
    {
        $ds = DIRECTORY_SEPARATOR;
        return realpath(self::DIR . $ds . '..' . $ds . '..') . $ds . 'fonts';
    }


    /**
     * Change the editor list order of evaluation globally.
     *
     * @param array $editorList
     *
     * @throws \Exception
     */
    public static function setEditorList($editorList){
        if(!is_array($editorList)){
            throw new \Exception('$editorList must be an array.');
        }
        self::$editorList = $editorList;
    }

    /**
     * Detects and return the name of the first supported editor which can either be "Imagick" or "Gd".
     *
     * @param array $editorList Array of editor list names. Use this to change the order of evaluation for editors for this function call only. Default order of evaluation is Imagick then GD.
     *
     * @return string Name of available editor.
     * @throws \Exception Throws exception if there are no supported editors.
     */
    public static function detectAvailableEditor($editorList = null)
    {

        if(null === $editorList){
            $editorList = self::$editorList;
        }

        /* Get first supported editor instance. Order of editorList matter. */
        foreach ($editorList as $editorName) {
            if ('Imagick' === $editorName) {
                $editorInstance = new ImagickEditor();
            } else {
                $editorInstance = new GdEditor();
            }
            /** @var EditorInterface $editorInstance */
            if (true === $editorInstance->isAvailable()) {
                return $editorName;
            }
        }

        throw new \Exception('No supported editor.');
    }

    /**
     * Creates the first available editor.
     *
     * @param array $editorList Array of editor list names. Use this to change the order of evaluation for editors. Default order of evaluation is Imagick then GD.
     *
     * @return EditorInterface
     * @throws \Exception
     */
    public static function createEditor($editorList = array('Imagick', 'Gd'))
    {
        $editorName = self::detectAvailableEditor($editorList);
        if ('Imagick' === $editorName) {
            return new ImagickEditor();
        } else {
            return new GdEditor();
        }
    }

    /**
     * Create an image.
     * @param string $imageFile Path to image file.
     *
     * @return ImageInterface
     * @throws \Exception
     */
    public static function createImage($imageFile)
    {
        $editorName = self::detectAvailableEditor();
        if ('Imagick' === $editorName) {
            return ImagickImage::createFromFile($imageFile);
        } else {
            return GdImage::createFromFile($imageFile);
        }
    }


    /**
     * Create a blank image.
     *
     * @param int $width Width of image in pixels.
     * @param int $height Height of image in pixels.
     *
     * @return ImageInterface
     * @throws \Exception
     */
    public static function createBlankImage($width = 1, $height = 1)
    {
        $editorName = self::detectAvailableEditor();
        if ('Imagick' === $editorName) {
            return ImagickImage::createBlank($width, $height);
        } else {
            return GdImage::createBlank($width, $height);
        }
    }


    /**
     * Create a filter. Detects available editor to use.
     *
     * @param string $filterName The name of the filter.
     *
     * @return FilterInterface
     * @throws \Exception
     */
    public static function createFilter($filterName)
    {
        $editorName = self::detectAvailableEditor();
        if ('Imagick' === $editorName) {
            switch ($filterName){
                case 'Dither':
                    return new ImagickDither();
                case 'Grayscale':
                    return new ImagickGrayscale();
                case 'Sobel':
                    return new ImagickSobel();
            }
            throw new \Exception('Invalid filter name.');
        } else {
            switch ($filterName){
                case 'Dither':
                    return new GdDither();
                case 'Grayscale':
                    return new GdGrayscale();
                case 'Sobel':
                    return new GdSobel();
            }
            throw new \Exception('Invalid filter name.');
        }
    }

    /**
     * Draws an object. Detects available editor to use.
     *
     * @param string $drawingObjectName The name of the DrawingObject.
     *
     * @return DrawingObjectInterface
     * @throws \Exception
     *
     * We use array_key_exist() instead of isset() to be able to detect a parameter with a NULL value.
     */
    public static function createDrawingObject($drawingObjectName)
    {
        $editorName = self::detectAvailableEditor();
        $p = func_get_args();
        if ('Imagick' === $editorName) {
            switch ($drawingObjectName){
                case 'CubicBezier':
                    return new ImagickCubicBezier(
                        $p[1],
                        $p[2],
                        $p[3],
                        $p[4],
                        (array_key_exists(5,$p) ? $p[5] : '#000000')
                    );
                case 'Ellipse':
                    return new ImagickEllipse(
                        $p[1],
                        $p[2],
                        (array_key_exists(3,$p) ? $p[3] : array(0,0)),
                        (array_key_exists(4,$p) ? $p[4] : 1),
                        (array_key_exists(5,$p) ? $p[5] : '#000000'),
                        (array_key_exists(6,$p) ? $p[6] : '#FFFFFF')
                    );
                case 'Line':
                    return new ImagickLine(
                        $p[1],
                        $p[2],
                        (array_key_exists(3,$p) ? $p[3] : 1),
                        (array_key_exists(4,$p) ? $p[4] : '#000000')
                    );
                case 'Polygon':
                    return new ImagickPolygon(
                        $p[1],
                        (array_key_exists(2,$p) ? $p[2] : 1),
                        (array_key_exists(3,$p) ? $p[3] : '#000000'),
                        (array_key_exists(4,$p) ? $p[4] : '#FFFFFF')
                    );
                case 'Rectangle':
                    return new ImagickRectangle(
                        $p[1],
                        $p[2],
                        (array_key_exists(3,$p) ? $p[3] : array(0,0)),
                        (array_key_exists(4,$p) ? $p[4] : 1),
                        (array_key_exists(5,$p) ? $p[5] : '#000000'),
                        (array_key_exists(6,$p) ? $p[6] : '#FFFFFF')
                    );
                case 'QuadraticBezier':
                    return new ImagickQuadraticBezier(
                        $p[1],
                        $p[2],
                        $p[3],
                        (array_key_exists(4,$p) ? $p[4] : '#000000')
                    );

            }
            throw new \Exception('Invalid drawing object name.');
        } else {
            switch ($drawingObjectName) {
                case 'CubicBezier':
                    return new GdCubicBezier(
                        $p[1],
                        $p[2],
                        $p[3],
                        $p[4],
                        (array_key_exists(5,$p) ? $p[5] : '#000000')
                    );
                case 'Ellipse':
                    return new GdEllipse(
                        $p[1],
                        $p[2],
                        (array_key_exists(3,$p) ? $p[3] : array(0,0)),
                        (array_key_exists(4,$p) ? $p[4] : 1),
                        (array_key_exists(5,$p) ? $p[5] : '#000000'),
                        (array_key_exists(6,$p) ? $p[6] : '#FFFFFF')
                    );
                case 'Line':
                    return new GdLine(
                        $p[1],
                        $p[2],
                        (array_key_exists(3,$p) ? $p[3] : 1),
                        (array_key_exists(4,$p) ? $p[4] : '#000000')
                    );
                case 'Polygon':
                    return new GdPolygon(
                        $p[1],
                        (array_key_exists(2,$p) ? $p[2] : 1),
                        (array_key_exists(3,$p) ? $p[3] : '#000000'),
                        (array_key_exists(4,$p) ? $p[4] : '#FFFFFF')
                    );
                case 'Rectangle':
                    return new GdRectangle(
                        $p[1],
                        $p[2],
                        (array_key_exists(3,$p) ? $p[3] : array(0,0)),
                        (array_key_exists(4,$p) ? $p[4] : 1),
                        (array_key_exists(5,$p) ? $p[5] : '#000000'),
                        (array_key_exists(6,$p) ? $p[6] : '#FFFFFF')
                    );
                case 'QuadraticBezier':
                    return new GdQuadraticBezier(
                        $p[1],
                        $p[2],
                        $p[3],
                        (array_key_exists(4,$p) ? $p[4] : '#000000')
                    );
            }
            throw new \Exception('Invalid drawing object name.');
        }
    }


}