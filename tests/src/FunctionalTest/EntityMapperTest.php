<?php

namespace Sheerockoff\BitrixEntityMapper\Test\FunctionalTest;

use _CIBElement;
use CIBlockElement;
use DateTime;
use Doctrine\Common\Annotations\AnnotationException;
use Entity\Book;
use Exception;
use ReflectionException;
use ReflectionObject;
use Sheerockoff\BitrixEntityMapper\EntityMapper;
use Sheerockoff\BitrixEntityMapper\Map\EntityMap;
use Sheerockoff\BitrixEntityMapper\SchemaBuilder;
use Sheerockoff\BitrixEntityMapper\Test\TestCase;

final class EntityMapperTest extends TestCase
{
    /**
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public static function setUpBeforeClass()
    {
        self::deleteInfoBlocks();
        self::deleteInfoBlockType();
        self::clearBitrixCache();
        self::addInfoBlockType();
        $schemaBuilder = new SchemaBuilder(EntityMap::fromClass(Book::class));
        $schemaBuilder->build();
    }

    public static function tearDownAfterClass()
    {
        self::deleteInfoBlocks();
        self::deleteInfoBlockType();
        self::clearBitrixCache();
    }

    /**
     * @return array
     * @throws ReflectionException
     * @throws AnnotationException
     */
    public function testCanSaveNewObject()
    {
        $book = new Book();
        $book->title = 'Остров сокровищ';
        $book->isShow = true;
        $book->author = 'Р. Л. Стивенсон';
        $book->isBestseller = true;
        $book->pagesNum = 350;
        $book->publishedAt = DateTime::createFromFormat('d.m.Y H:i:s', '14.06.1883 00:00:00');

        $book->setCover(__DIR__ . '/../../resources/cover.jpg');
        $bookRef = new ReflectionObject($book);
        $coverRef = $bookRef->getProperty('cover');
        $coverRef->setAccessible(true);
        $coverFileId = $coverRef->getValue($book);
        $coverRef->setAccessible(false);
        $this->assertNotEmpty($coverFileId);

        $id = EntityMapper::save($book);
        $this->assertNotEmpty($id);

        return [
            'id' => $id,
            'coverFileId' => $coverFileId
        ];
    }

    /**
     * @depends testCanSaveNewObject
     * @param array $stack
     * @return array
     * @throws Exception
     */
    public function testIsSavedCorrect(array $stack)
    {
        $id = $stack['id'];
        $coverFileId = $stack['coverFileId'];

        $element = CIBlockElement::GetList(null, ['ID' => $id])->GetNextElement();
        $this->assertInstanceOf(_CIBElement::class, $element);

        $fields = $element->GetFields();
        $this->assertEquals($id, $fields['ID']);
        $this->assertEquals('Остров сокровищ', $fields['NAME']);
        $this->assertEquals('Y', $fields['ACTIVE']);

        $properties = $element->GetProperties();
        $this->assertEquals('Р. Л. Стивенсон', $properties['author']['VALUE']);
        $this->assertEquals('Y', $properties['is_bestseller']['VALUE']);
        $this->assertEquals(350, $properties['pages_num']['VALUE']);
        $this->assertEquals($coverFileId, $properties['cover']['VALUE']);
        $this->assertEquals(
            DateTime::createFromFormat('d.m.Y H:i:s', '14.06.1883 00:00:00')->getTimestamp(),
            (new DateTime($properties['published_at']['VALUE']))->getTimestamp()
        );

        return $stack;
    }

    /**
     * @depends testIsSavedCorrect
     * @param array $stack
     * @return array
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public function testCanUpdateObject(array $stack)
    {
        $id = $stack['id'];

        /** @var Book $book */
        $book = EntityMapper::select(Book::class)->where('id', $id)->fetch();
        $this->assertInstanceOf(Book::class, $book);

        $book->title = 'Цвет волшебства';
        $book->isShow = false;
        $book->author = 'Т. Пратчетт';
        $book->isBestseller = false;
        $book->pagesNum = 300;
        $book->publishedAt = DateTime::createFromFormat('d.m.Y H:i:s', '01.09.1983 00:00:00');

        $updatedId = EntityMapper::save($book);
        $this->assertNotEmpty($updatedId);
        $this->assertEquals($id, $updatedId);

        return $stack;
    }

    /**
     * @depends testCanUpdateObject
     * @param array $stack
     * @return array
     * @throws Exception
     */
    public function testIsUpdatedCorrect(array $stack)
    {
        $id = $stack['id'];
        $coverFileId = $stack['coverFileId'];

        $element = CIBlockElement::GetList(null, ['ID' => $id])->GetNextElement();
        $this->assertInstanceOf(_CIBElement::class, $element);

        $fields = $element->GetFields();
        $this->assertEquals($id, $fields['ID']);
        $this->assertEquals('Цвет волшебства', $fields['NAME']);
        $this->assertEquals('N', $fields['ACTIVE']);

        $properties = $element->GetProperties();
        $this->assertEquals('Т. Пратчетт', $properties['author']['VALUE']);
        $this->assertEquals(false, $properties['is_bestseller']['VALUE']);
        $this->assertEquals(300, $properties['pages_num']['VALUE']);
        $this->assertEquals($coverFileId, $properties['cover']['VALUE']);
        $this->assertEquals(
            DateTime::createFromFormat('d.m.Y H:i:s', '01.09.1983 00:00:00')->getTimestamp(),
            (new DateTime($properties['published_at']['VALUE']))->getTimestamp()
        );

        return $stack;
    }

    /**
     * @depends testIsUpdatedCorrect
     * @param array $stack
     * @return array
     * @throws AnnotationException
     * @throws ReflectionException
     */
    public function testCanSkipUnmodifiedObjectSave(array $stack)
    {
        $id = $stack['id'];

        /** @var Book $book */
        $book = EntityMapper::select(Book::class)->where('id', $id)->fetch();
        $this->assertInstanceOf(Book::class, $book);

        $element = CIBlockElement::GetList(null, ['ID' => $id])->Fetch();
        $this->assertNotEmpty($element['TIMESTAMP_X']);
        $oldTimestamp = $element['TIMESTAMP_X'];
        sleep(2);

        $updatedId = EntityMapper::save($book);
        $this->assertNotEmpty($updatedId);
        $this->assertEquals($id, $updatedId);

        $element = CIBlockElement::GetByID($id)->Fetch();
        $this->assertNotEmpty($element['TIMESTAMP_X']);
        $this->assertEquals($oldTimestamp, $element['TIMESTAMP_X']);

        return $stack;
    }

    /**
     * @depends testCanSkipUnmodifiedObjectSave
     * @param array $stack
     * @return array
     * @throws AnnotationException
     * @throws ReflectionException
     * @throws Exception
     */
    public function testDateTimeFormats(array $stack)
    {
        $id = $stack['id'];

        /** @var Book $book */
        $book = EntityMapper::select(Book::class)->where('id', $id)->fetch();
        $this->assertInstanceOf(Book::class, $book);

        $book->publishedAt = '1883-09-12 15:30:59';
        $updatedId = EntityMapper::save($book);
        $this->assertEquals($id, $updatedId);

        $element = CIBlockElement::GetList(null, ['ID' => $id])->GetNextElement();
        $this->assertInstanceOf(_CIBElement::class, $element);
        $properties = $element->GetProperties();

        $this->assertEquals(
            DateTime::createFromFormat('d.m.Y H:i:s', '12.09.1883 15:30:59')->getTimestamp(),
            (new DateTime($properties['published_at']['VALUE']))->getTimestamp()
        );

        $book->publishedAt = DateTime::createFromFormat('d.m.Y H:i:s', '10.08.1883 00:00:00')->getTimestamp();
        $updatedId = EntityMapper::save($book);
        $this->assertEquals($id, $updatedId);

        $element = CIBlockElement::GetList(null, ['ID' => $id])->GetNextElement();
        $this->assertInstanceOf(_CIBElement::class, $element);
        $properties = $element->GetProperties();

        $this->assertEquals(
            DateTime::createFromFormat('d.m.Y H:i:s', '10.08.1883 00:00:00')->getTimestamp(),
            (new DateTime($properties['published_at']['VALUE']))->getTimestamp()
        );

        $book->publishedAt = null;
        $updatedId = EntityMapper::save($book);
        $this->assertEquals($id, $updatedId);

        $element = CIBlockElement::GetList(null, ['ID' => $id])->GetNextElement();
        $this->assertInstanceOf(_CIBElement::class, $element);
        $properties = $element->GetProperties();

        $this->assertEmpty($properties['published_at']['VALUE']);

        return $stack;
    }
}