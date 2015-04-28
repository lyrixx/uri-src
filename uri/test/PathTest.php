<?php

namespace League\Url\Test;

use ArrayIterator;
use League\Url\Path;
use PHPUnit_Framework_TestCase;
use StdClass;

/**
 * @group path
 */
class PathTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param string $raw
     * @param string $parsed
     * @dataProvider validPathProvider
     */
    public function testValidPath($raw, $parsed)
    {
        $path = new Path($raw);
        $this->assertSame($parsed, $path->__toString());
    }

    public function validPathProvider()
    {
        return [
            [null, ''],
            ['/path/to/my/file.csv', '/path/to/my/file.csv'],
            ['you', 'you'],
            ['foo/bar/', 'foo/bar/'],
            ['', ''],
            ['/', '/'],
            ['/shop/rev iew/', '/shop/rev%20iew/'],
            ['/master/toto/a%c2%b1b', '/master/toto/a%C2%B1b'],
            ['/master/toto/%7Eetc', '/master/toto/~etc'],
        ];
    }

    /**
     * @param string $raw
     * @param int    $key
     * @param string $value
     * @param mixed  $default
     * @dataProvider getSegmentProvider
     */
    public function testGetSegment($raw, $key, $value, $default)
    {
        $path = new Path($raw);
        $this->assertSame($value, $path->getSegment($key, $default));
    }

    public function getSegmentProvider()
    {
        return [
            ['/shop/rev iew/', 1, 'rev iew', null],
            ['/shop/rev%20iew/', 1, 'rev iew', null],
            ['/shop/rev%20iew/', 28, 'foo', 'foo'],
        ];
    }

    /**
     * @param $input
     * @param $expected
     * @dataProvider createFromArrayValid
     */
    public function testCreateFromArray($input, $has_front_delimiter, $expected)
    {
        $this->assertSame($expected, Path::createFromArray($input, $has_front_delimiter)->__toString());
    }

    public function createFromArrayValid()
    {
        return [
            'array' => [['www', 'example', 'com'], false, 'www/example/com'],
            'array' => [['www', 'example', 'com'], true, '/www/example/com'],
            'iterator' => [new ArrayIterator(['www', 'example', 'com']), true, '/www/example/com'],
            'host object' => [(new Path('::1'))->toArray(), false, '::1',],
            'arbitrary cut 1' => [['foo', 'bar', 'baz'], true, '/foo/bar/baz'],
            'arbitrary cut 2' => [['foo/bar', 'baz'], true, '/foo/bar/baz'],
            'arbitrary cut 3' => [['foo/bar/baz'], true, '/foo/bar/baz'],
            'ending delimiter' => [['foo/bar/baz', ''], false, 'foo/bar/baz/'],
        ];
    }

    /**
     * @param $input
     * @dataProvider createFromArrayInvalid
     * @expectedException \InvalidArgumentException
     */
    public function testCreateFromArrayFailed($input)
    {
        Path::createFromArray($input);
    }

    public function createFromArrayInvalid()
    {
        return [
            'string' => ['www.example.com'],
            'bool' => [true],
            'integer' => [1],
            'object' => [new \StdClass()],
            'host object' => [new Path('::1')],
        ];
    }

    public function testPrepend()
    {
        $path    = new Path('/test/query.php');
        $newPath = $path->prependWith('/master');
        $this->assertSame('/master/test/query.php', $newPath->__toString());
    }

    public function testAppendEmptyPath()
    {
        $expected = '/shop/checkout/';
        $this->assertSame($expected, (string) (new Path())->appendWith($expected));
    }

    /**
     * Test AbstractSegment::without
     *
     * @param string $origin
     * @param string $without
     * @param string $result
     *
     * @dataProvider withoutProvider
     */
    public function testWithout($origin, $without, $result)
    {
        $this->assertSame($result, (string) (new Path($origin))->without($without));
    }

    public function withoutProvider()
    {
        return [
            ['/test/query.php', 'toto', '/test/query.php'],
            ['/test/query.php', '  ', '/test/query.php'],
            ['/master/test/query.php', 'query.php', '/master/test/'],
            ['/toto/le/heros/masson', 'toto', '/le/heros/masson'],
            ['/toto/le/heros/masson', 'ros/masson', '/toto/le/heros/masson'],
            ['/toto/le/heros/masson', 'asson', '/toto/le/heros/masson'],
            ['/toto/le/heros/masson', '/heros/masson', '/toto/le'],
            ['/toto/le/heros/masson', '/le/heros', '/toto/masson'],
        ];
    }

    public function testKeys()
    {
        $path = new Path('/bar/3/troll/3');
        $this->assertCount(4, $path->getKeys());
        $this->assertCount(0, $path->getKeys('foo'));
        $this->assertSame([0], $path->getKeys('bar'));
        $this->assertCount(2, $path->getKeys('3'));
        $this->assertSame([1, 3], $path->getKeys('3'));
    }

    /**
     * @param $input
     * @param $toArray
     * @param $nbSegment
     * @dataProvider arrayProvider
     */
    public function testCountable($input, $toArray, $nbSegment)
    {
        $path = new Path($input);
        $this->assertCount($nbSegment, $path);
        $this->assertSame($toArray, $path->toArray());
    }

    public function arrayProvider()
    {
        return [
            ['/toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['toto/le/heros/masson', ['toto', 'le', 'heros', 'masson'], 4],
            ['/toto/le/heros/masson/', ['toto', 'le', 'heros', 'masson', ''], 5],
        ];
    }

    /**
     * Test Removing Dot Segment
     *
     * @param  string $expected
     * @param  string $path
     * @dataProvider normalizeProvider
     */
    public function testNormalize($path, $expected)
    {
        $this->assertSame($expected, (new Path($path))->normalize()->__toString());
    }

    /**
     * Provides different segment to be normalized
     *
     * @return array
     */
    public function normalizeProvider()
    {
        return [
            ['/a/b/c/./../../g', '/a/g'],
            ['mid/content=5/../6', 'mid/6'],
            ['a/b/c', 'a/b/c'],
            ['a/b/c/.', 'a/b/c/'],
        ];
    }

    public function testGetBasemane()
    {
        $path = new Path('/path/to/my/file.txt');
        $this->assertSame('file.txt', $path->getBasename());
    }

    public function testGetBasemaneWithEmptyBasename()
    {
        $path = new Path('/path/to/my/');
        $this->assertEmpty($path->getBasename());
    }

    /**
     * @param  string $raw
     * @param  string $parsed
     * @dataProvider extensionProvider
     */
    public function testGetExtension($raw, $parsed)
    {
        $this->assertSame($parsed, (new Path($raw))->getExtension());
    }

    public function extensionProvider()
    {
        return [
            ['/path/to/my/', ''],
            ['/path/to/my/file', ''],
            ['/path/to/my/file.txt', 'txt'],
            ['/path/to/my/file.csv.txt', 'txt'],
        ];
    }

    /**
     * @param  string $raw
     * @param  string $raw_ext
     * @param  string $parsed_ext
     * @dataProvider withExtensionProvider
     */
    public function testWithExtension($raw, $raw_ext, $parsed_ext)
    {
        $newPath = (new Path($raw))->withExtension($raw_ext);
        $this->assertSame($parsed_ext, $newPath->getExtension());
    }

    public function withExtensionProvider()
    {
        return [
            ['/path/to/my/file.txt', '.csv', 'csv'],
            ['/path/to/my/file.txt', 'csv', 'csv'],
            ['/path/to/my/file', '.csv', 'csv'],
        ];
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWithExtensionWithInvalidExtension()
    {
        (new Path())->withExtension('t/xt');
    }

    /**
     * @expectedException \LogicException
     */
    public function testWithExtensionWithoutBasename()
    {
        (new Path())->withExtension('txt');
    }

    /**
     * @param $path
     * @expectedException \InvalidArgumentException
     * @dataProvider invalidPath
     */
    public function testInvalidPath($path)
    {
        new Path($path);
    }

    public function invalidPath()
    {
        return [
            [new \StdClass()],
        ];
    }
}
