<?php

namespace Sheerwater\RestfulController\Tests;

use Config, DataList, Director, JSONDataFormatter;
use DataObject;
use Sheerwater\RestfulController\RestfulController;

class RestfulControllerTest extends \SapphireTest
{
    protected $extraDataObjects = ['Sheerwater\RestfulController\Tests\TestDataObject'];
    protected static $fixture_file = 'RestfulControllerFixture.yml';

    public function setUpOnce()
    {
        parent::setUpOnce();

        Config::inst()->update('Director', 'rules', [
            'GetOnly'    => 'Sheerwater\RestfulController\Tests\GetOnlyController',
            'DeleteOnly' => 'Sheerwater\RestfulController\Tests\DeleteOnlyController',
            'PostOnly'   => 'Sheerwater\RestfulController\Tests\PostOnlyController',
            'PutOnly'    => 'Sheerwater\RestfulController\Tests\PutOnlyController'
        ]);
    }

    public function testGet()
    {
        /** @var DataList $objs */
        $objs   = TestDataObject::get();
        $single = $objs->last();
        /** @var JSONDataFormatter $formatter */
        $formatter = JSONDataFormatter::create();

        $singleReq = Director::test("GetOnly/$single->ID", null, null, 'GET');
        $this->assertEquals(json_decode($formatter->convertDataObject($single)), json_decode($singleReq->getBody()),
            'RestfulController is not processing a GET request with an ID correctly'
        );

        $allReq = Director::test('GetOnly', null, null, 'GET');
        $formatter->setTotalSize(5);
        $this->assertEquals(json_decode($formatter->convertDataObjectSet($objs)), json_decode($allReq->getBody()),
            'RestfulController is not processing a GET request with an ID correctly');

        $invalidReq = Director::test('GetOnly', null, null, 'DELETE');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
        $invalidReq = Director::test('GetOnly', null, null, 'POST');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
        $invalidReq = Director::test('GetOnly', null, null, 'PUT');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
    }

    public function testDelete()
    {
        $ids = TestDataObject::get()->column();
        Director::test('DeleteOnly/' . $ids[1], null, null, 'DELETE');
        Director::test('DeleteOnly/' . $ids[3], null, null, 'DELETE');
        unset($ids[1], $ids[3]);

        $idsAfter = TestDataObject::get()->column();
        $this->assertEmpty(array_diff($ids, $idsAfter));

        $invalidReq = Director::test('DeleteOnly', null, null, 'GET');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
        $invalidReq = Director::test('DeleteOnly', null, null, 'POST');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
        $invalidReq = Director::test('DeleteOnly', null, null, 'PUT');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
    }

    public function testPost()
    {
        $newObj = [
            'Title'    => 'New Object',
            'Subtitle' => 'New Subtitle'
        ];

        $postResponse = Director::test('PostOnly', null, null, 'POST', json_encode($newObj),
            ['Content-Type' => 'application/json']);
        $responseObj  = json_decode($postResponse->getBody());
        $this->assertTrue(isset($responseObj->ID), 'Response object from POST request has no ID');
        $this->assertEmpty(array_diff_assoc($newObj, (array)$responseObj),
            'Response object from POST request does not contain all the data sent in the request');

        $objFromDb = TestDataObject::get()->byID($responseObj->ID);
        $this->assertNotNull($objFromDb, 'POST request didn\'t save the object in the database');
        $this->assertEmpty(array_diff_assoc($newObj, $objFromDb->toMap()),
            'POST request saved object to the database incorrectly');

        $invalidReq = Director::test('PostOnly', null, null, 'GET');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
        $invalidReq = Director::test('PostOnly', null, null, 'DELETE');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
        $invalidReq = Director::test('PostOnly', null, null, 'PUT');
        $this->assertEquals(403, $invalidReq->getStatusCode(),
            'RestfulController should return 403 Forbidden when trying to access it using the wrong HTTP method');
    }

    public function testPut()
    {
        $className      = 'Sheerwater\RestfulController\Tests\TestDataObject';
        $obj1           = $this->objFromFixture($className, 'item2');
        $obj2           = $this->objFromFixture($className, 'item3');
        $obj1->Title    = 'Change 1';
        $obj2->Subtitle = 'Change 2';

        $response = Director::test('PutOnly/' . $obj1->ID, null, null, 'PUT', json_encode($obj1->toMap()));
        $this->assertEmpty(array_diff_assoc([
                'ID'    => $obj1->ID,
                'Title' => 'Change 1'
            ], (array)json_decode($response->getBody())),
            'Response object from PUT is invalid'
        );
        $this->assertEmpty(array_diff_assoc([
                'ID'    => $obj1->ID,
                'Title' => 'Change 1'
            ], TestDataObject::get()->byID($obj1->ID)->toMap()),
            'PUT request is not saving the requested changes'
        );

        $response = Director::test('PutOnly/' . $obj2->ID, null, null, 'PUT', json_encode($obj2->toMap()));
        $this->assertEmpty(array_diff_assoc([
                'ID'       => $obj2->ID,
                'Subtitle' => 'Change 2'
            ], (array)json_decode($response->getBody())),
            'Response object from PUT is invalid'
        );
        $this->assertEmpty(array_diff_assoc([
                'ID'       => $obj2->ID,
                'Subtitle' => 'Change 2'
            ], TestDataObject::get()->byID($obj2->ID)->toMap()),
            'PUT request is not saving the requested changes'
        );
    }
}

class GetOnlyController extends RestfulController
{
    public function get($id = null)
    {
        if ($id) {
            return TestDataObject::get()->byID($id);
        } else {
            return TestDataObject::get();
        }
    }
}

class DeleteOnlyController extends RestfulController
{
    public function delete($id)
    {
        DataObject::delete_by_id('Sheerwater\RestfulController\Tests\TestDataObject', $id);
    }
}

class PostOnlyController extends RestfulController
{
    public function post($body)
    {
        $formatter = static::getRequestDataFormatter($this->request);
        /** @noinspection PhpVoidFunctionResultUsedInspection */
        /** @var string[] $data */
        $data = $formatter->convertStringToArray($body);
        /** @var TestDataObject $newObj */
        $newObj = TestDataObject::create();
        $newObj->update($data);
        $newObj->write();

        return $newObj;
    }
}

class PutOnlyController extends RestfulController
{
    public function put($id, $body)
    {
        /** @var TestDataObject $existing */
        $existing = TestDataObject::get()->byID($id);
        if ($existing and $existing->exists()) {
            $formatter = static::getRequestDataFormatter($this->request);
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            /** @var string[] $data */
            $data = $formatter->convertStringToArray($body);
            $existing->update($data);
            $existing->write();
        }

        return $existing;
    }
}

/**
 * Class TestDataObject
 * @package Sheerwater\RestfulController
 * @property string Title
 */
class TestDataObject extends \DataObject implements \TestOnly
{
    private static $db = [
        'Title'    => 'Varchar',
        'Subtitle' => 'Varchar'
    ];

    public function canView($member = null)
    {
        return true;
    }
}
