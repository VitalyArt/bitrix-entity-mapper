<?php

/** @noinspection PhpUndefinedClassInspection */

namespace Sheerockoff\BitrixEntityMapper\Test\FunctionalTest;

use CDBResult;
use CIBlock;
use CIBlockProperty;
use CIBlockPropertyResult;
use Doctrine\Common\Annotations\AnnotationException;
use Entity\Book;
use ReflectionException;
use Sheerockoff\BitrixEntityMapper\Map\EntityMap;
use Sheerockoff\BitrixEntityMapper\SchemaBuilder;
use Sheerockoff\BitrixEntityMapper\Test\TestCase;

final class SchemaBuilderTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        self::deleteInfoBlocks();
        self::deleteInfoBlockType('entity');
        self::clearBitrixCache();
        self::addInfoBlockType('entity', 'Библиотека');
    }

    /**
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public function testCanBuildSchema()
    {
        $entityMap = EntityMap::fromClass(Book::class);
        $schemaBuilder = new SchemaBuilder($entityMap);
        $this->assertTrue($schemaBuilder->build());
    }

    /**
     * @depends testCanBuildSchema
     */
    public function testIsSchemaCorrect()
    {
        $infoBlock = CIBlock::GetList(null, [
            '=TYPE' => 'entity',
            '=CODE' => 'books',
            'CHECK_PERMISSIONS' => 'N'
        ])->Fetch();

        $this->assertTrue(is_array($infoBlock));
        $this->assertArrayHasKey('ID', $infoBlock);
        $this->assertNotEmpty($infoBlock['ID']);

        $rs = CIBlockProperty::GetList(null, [
            'IBLOCK_ID' => $infoBlock['ID']
        ]);

        $this->assertInstanceOf(CIBlockPropertyResult::class, $rs);

        $properties = [];
        while ($prop = $rs->Fetch()) {
            $properties[$prop['CODE']] = $prop;
        }

        $this->assertArrayHasKey('author', $properties);
        $this->assertEquals('Автор', $properties['author']['NAME']);
        $this->assertEquals('S', $properties['author']['PROPERTY_TYPE']);
        $this->assertEmpty($properties['author']['USER_TYPE']);
        $this->assertEquals('N', $properties['author']['MULTIPLE']);

        $this->assertArrayHasKey('published_at', $properties);
        $this->assertEquals('Опубликована', $properties['published_at']['NAME']);
        $this->assertEquals('S', $properties['published_at']['PROPERTY_TYPE']);
        $this->assertEquals('DateTime', $properties['published_at']['USER_TYPE']);
        $this->assertEquals('N', $properties['published_at']['MULTIPLE']);

        $this->assertArrayHasKey('is_bestseller', $properties);
        $this->assertEquals('Бестселлер', $properties['is_bestseller']['NAME']);
        $this->assertEquals('L', $properties['is_bestseller']['PROPERTY_TYPE']);
        $this->assertEmpty($properties['is_bestseller']['USER_TYPE']);
        $this->assertEquals('N', $properties['is_bestseller']['MULTIPLE']);
        $this->assertEquals('C', $properties['is_bestseller']['LIST_TYPE']);

        $enumRs = CIBlockProperty::GetPropertyEnum($properties['is_bestseller']['ID']);
        $this->assertInstanceOf(CDBResult::class, $enumRs);

        $propEnum = [];
        while ($entry = $enumRs->Fetch()) {
            $propEnum[] = $entry;
        }

        $this->assertCount(1, $propEnum);
        $enumYesOption = reset($propEnum);
        $this->assertTrue(is_array($enumYesOption));
        $this->assertArrayHasKey('XML_ID', $enumYesOption);
        $this->assertEquals('Y', $enumYesOption['XML_ID']);
        $this->assertArrayHasKey('VALUE', $enumYesOption);
        $this->assertEquals('Y', $enumYesOption['VALUE']);

        $this->assertArrayHasKey('pages_num', $properties);
        $this->assertEquals('Кол-во страниц', $properties['pages_num']['NAME']);
        $this->assertEquals('N', $properties['pages_num']['PROPERTY_TYPE']);
        $this->assertEmpty($properties['pages_num']['USER_TYPE']);
        $this->assertEquals('N', $properties['pages_num']['MULTIPLE']);
    }

    /**
     * @depends testIsSchemaCorrect
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public function testCanRebuildSchemaCorrect()
    {
        $this->testCanBuildSchema();
        $this->testIsSchemaCorrect();
    }
}