<?php
namespace WebPConvert\Tests\Convert\Converters;


use WebPConvert\Tests\Convert\Exposers\GdExposer;
use WebPConvert\Convert\Converters\Gd;
use WebPConvert\Convert\Exceptions\ConversionFailed\ConverterNotOperational\SystemRequirementsNotMetException;
use WebPConvert\Convert\Exceptions\ConversionFailedException;

use PHPUnit\Framework\TestCase;

class GdTest extends TestCase
{


      public static function getImageFolder()
      {
          return realpath(__DIR__ . '/../../images');
      }

      public static function getImagePath($image)
      {
          return self::getImageFolder() . '/' . $image;
      }

      public function testConvert()
      {
          ConverterTestHelper::runAllConvertTests($this, 'Gd');
      }

      private function createGd($src)
      {
          $source = self::getImagePath($src);
          $this->assertTrue(file_exists($source), 'source does not exist:' . $source);

          return new Gd($source, $source . '.webp');
      }

      private function createGdExposer($src)
      {
          $gd = $this->createGd($src);
          return new GdExposer($gd);
      }

      private static function resetPretending()
      {
          reset_pretending();
      }

      // pretend imagewebp is missing
      public function testNotOperational1()
      {
          global $pretend;

          $gd = $this->createGd('test.png');
          self::resetPretending();

          $pretend['functionsNotExisting'] = ['imagewebp'];
          $this->expectException(SystemRequirementsNotMetException::class);
          $gd->checkOperationality();
      }


      // pretend gd is not loaded
      public function testNotOperational2()
      {
          global $pretend;

          $gd = $this->createGd('test.png');
          self::resetPretending();

          $pretend['extensionsNotExisting'] = ['gd'];
          $this->expectException(SystemRequirementsNotMetException::class);
          $gd->checkOperationality();
          $pretend['extensionsNotExisting'] = [];
      }

      // pretend imagecreatefrompng is missing
      public function testCheckConvertability1()
      {
          global $pretend;

          $gd = $this->createGd('test.png');
          self::resetPretending();

          $pretend['functionsNotExisting'] = ['imagecreatefrompng'];
          $this->expectException(SystemRequirementsNotMetException::class);
          $gd->checkConvertability();
          $pretend['functionsNotExisting'] = [];
      }

      // pretend imagecreatefrompng is working
      public function testCheckConvertability2()
      {
          global $pretend;

          $gd = $this->createGd('test.png');
          self::resetPretending();

          $pretend['functionsExisting'] = ['imagecreatefrompng'];
          $gd->checkConvertability();
          $pretend['functionsExisting'] = [];
      }

      // pretend imagecreatefromjpeg is missing
      public function testCheckConvertability3()
      {
          global $pretend;

          $gd = $this->createGd('test.jpg');
          self::resetPretending();

          $pretend['functionsNotExisting'] = ['imagecreatefromjpeg'];
          $this->expectException(SystemRequirementsNotMetException::class);
          $gd->checkConvertability();
          $pretend['functionsNotExisting'] = [];
      }

      public function testSource()
      {

          $source = self::getImagePath('test.png');
          $gd = new Gd($source, $source . '.webp');

          self::resetPretending();

          $gdExposer = new GdExposer($gd);

          $this->assertEquals($source, $gdExposer->getSource());
          $this->assertTrue(file_exists($source), 'source does not exist');
      }

      public function testCreateImageResource()
      {
          $gd = $this->createGd('test.png');
          self::resetPretending();

          $gdExposer = new GdExposer($gd);

          if (!$gdExposer->isOperating()) {
              //$this->assertTrue(false);
              return;
          }

          // It is operating and image should be ok.
          // - so it should be able to create image resource (or, for PHP 8, an \GdImage object)
          $image = $gdExposer->createImageResource();
          $isResourceOrObject = ((gettype($image) == 'resource') || (gettype($image) == 'object'));
          $this->assertTrue($isResourceOrObject, 'Expected createImageResource to return a resource or an object but got:' . gettype($image));

  /*
          // Try the workaround method.
          $result = $gdExposer->makeTrueColorUsingWorkaround($image);

          // As the workaround is pretty sturdy, let us assert that it simply works.
          // It would be good to find out if it doesn't, anyway!
          $this->assertTrue($result);            */

          //$gdExposer->tryToMakeTrueColorIfNot($image);
          $this->assertTrue(imageistruecolor($image), 'image is not true color');

          $result = $gdExposer->trySettingAlphaBlending($image);
          $this->assertTrue($result, 'failed setting alpha blending');
      }

      public function testStuffOnNotTrueColor()
      {
          $gd = $this->createGd('not-true-color.png');
          self::resetPretending();

          $gdExposer = new GdExposer($gd);

          if (!$gdExposer->isOperating()) {
              return;
          }

          // It is operating and image should be ok.
          // - so it should be able to create image resource
          $image = $gdExposer->createImageResource();
          $isResourceOrObject = ((gettype($image) == 'resource') || (gettype($image) == 'object'));
          $this->assertTrue($isResourceOrObject, 'Expected createImageResource to return a resource or an object but got:' . gettype($image));

          $this->assertFalse(imageistruecolor($image), 'image is already true color');
          $gdExposer->tryToMakeTrueColorIfNot($image);
          $this->assertTrue(imageistruecolor($image), 'image is not true color after trying to make it');
          $result = $gdExposer->trySettingAlphaBlending($image);
          $this->assertTrue($result, 'failed setting alpha blending');

          // Test the workaround method.
          $gd = $this->createGd('not-true-color.png');
          $gdExposer = new GdExposer($gd);
          $image = $gdExposer->createImageResource();
          $this->assertFalse(imageistruecolor($image), 'image is already true color');

          //$image = imagecreatetruecolor(imagesx($image), imagesy($image));
          $result = $gdExposer->makeTrueColorUsingWorkaround($image);
          //$result = $gd->makeTrueColorUsingWorkaround($image);
          $this->assertTrue($result);
          $this->assertTrue(imageistruecolor($image), 'image is not true color after trying to make it (with workaround method)');
          $result = $gdExposer->trySettingAlphaBlending($image);
          $this->assertTrue($result, 'failed setting alpha blending');
      }
}

require_once('pretend.inc');
