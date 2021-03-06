<?php

namespace Doctrine\MongoDB\Tests;

use Doctrine\MongoDB\GridFSFile;

class GridFSTest extends DatabaseTestCase
{
    public function testInsertFindOneAndWrite()
    {
        $document = [
            'foo' => 'bar',
            'file' => new GridFSFile(__FILE__),
        ];

        $gridFS = $this->getGridFS();
        $gridFS->insert($document);

        $document = $gridFS->findOne(['_id' => $document['_id']]);

        $this->assertArrayHasKey('_id', $document);
        $this->assertEquals('bar', $document['foo']);

        $file = $document['file'];
        $this->assertInstanceOf('Doctrine\MongoDB\GridFSFile', $file);
        $this->assertFalse($file->isDirty());
        $this->assertEquals(__FILE__, $file->getFilename());
        $this->assertStringEqualsFile(__FILE__, $file->getBytes());
        $this->assertEquals(filesize(__FILE__), $file->getSize());

        $path = tempnam(sys_get_temp_dir(), 'doctrine_write_test');
        $this->assertNotEquals(false, $path);

        $file->write($path);
        $this->assertFileEquals(__FILE__, $path);
        unlink($path);
    }

    public function testStoreFile()
    {
        $document = ['foo' => 'bar'];

        $gridFS = $this->getGridFS();
        $file = $gridFS->storeFile(__FILE__, $document);

        $this->assertArrayHasKey('_id', $document);
        $this->assertEquals('bar', $document['foo']);

        $this->assertInstanceOf('Doctrine\MongoDB\GridFSFile', $file);
        $this->assertFalse($file->isDirty());
        $this->assertEquals(__FILE__, $file->getFilename());
        $this->assertStringEqualsFile(__FILE__, $file->getBytes());
        $this->assertEquals(filesize(__FILE__), $file->getSize());
    }

    public function testUpdate()
    {
        $gridFS = $this->getGridFS();

        $path = __DIR__.'/file.txt';
        $file = new GridFSFile($path);
        $document = [
            'title' => 'Test Title',
            'file' => $file
        ];
        $gridFS->insert($document);
        $id = $document['_id'];

        $document = $gridFS->findOne(['_id' => $id]);
        $file = $document['file'];

        $gridFS->update(['_id' => $id], ['$pushAll' => ['test' => [1, 2, 3]]]);
        $check = $gridFS->findOne(['_id' => $id]);
        $this->assertArrayHasKey('test', $check);
        $this->assertCount(3, $check['test']);
        $this->assertEquals([1, 2, 3], $check['test']);

        $gridFS->update(['_id' => $id], ['_id' => $id]);
        $gridFS->update(['_id' => $id], ['_id' => $id, 'boom' => true]);
        $check = $gridFS->findOne(['_id' => $id]);
        $this->assertArrayNotHasKey('test', $check);
        $this->assertTrue($check['boom']);
    }

    public function testUpsertDocumentWithoutFile()
    {
        $gridFS = $this->getGridFS();

        $gridFS->update(
            ['id' => 123],
            ['x' => 1],
            ['upsert' => true, 'multiple' => false]
        );

        $document = $gridFS->findOne();

        $this->assertNotNull($document);
        $this->assertNotEquals(123, $document['_id']);
        $this->assertEquals(1, $document['x']);
    }

    public function testUpsertDocumentWithoutFileWithId()
    {
        $gridFS = $this->getGridFS();

        $gridFS->update(
            ['x' => 1],
            ['_id' => 123],
            ['upsert' => true, 'multiple' => false]
        );

        $document = $gridFS->findOne(['_id' => 123]);

        $this->assertNotNull($document);
        $this->assertArrayNotHasKey('x', $document);
    }

    public function testUpsertModifierWithoutFile()
    {
        $gridFS = $this->getGridFS();

        $gridFS->update(
            ['_id' => 123],
            ['$set' => ['x' => 1]],
            ['upsert' => true, 'multiple' => false]
        );

        $document = $gridFS->findOne(['_id' => 123]);

        $this->assertNotNull($document);
        $this->assertEquals(1, $document['x']);
    }

    public function testUpsert()
    {
        $gridFS = $this->getGridFS();
        $id = new \MongoId();

        $path = __DIR__.'/file.txt';
        $file = new GridFSFile($path);

        $newObj = [
            '$set' => [
                'title' => 'Test Title',
                'file' => $file,
            ],
        ];
        $gridFS->update(['_id' => $id], $newObj, ['upsert' => true, 'multiple' => false]);

        $document = $gridFS->findOne(['_id' => $id]);

        $file = $document['file'];

        $this->assertFalse($file->isDirty());
        $this->assertEquals($path, $file->getFilename());
        $this->assertEquals(file_get_contents($path), $file->getBytes());
        $this->assertEquals(22, $file->getSize());
    }

    private function getGridFS()
    {
        return $this->conn->selectDatabase(self::$dbName)->getGridFS();
    }
}
