<?php
/**
 * MongoDBAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

namespace InitPHP\Sessions\Adapters;

use \InitPHP\Sessions\Exceptions\{SessionException, SessionNotSupportedAdapter};

use function extension_loaded;
use function class_exists;

class MongoDBAdapter extends \InitPHP\Sessions\AbstractAdapter implements \InitPHP\Sessions\Interfaces\AdapterInterface
{

    /** @var \MongoDB\Driver\Manager $manager */
    private $manager;

    private string $collection;

    public function __construct(string $dsn, string $collection)
    {
        if (
            !extension_loaded("MongoDB")
            || !class_exists("\\MongoDB\\Driver\\Manager")
            || !class_exists("\\MongoDB\\Driver\\BulkWrite")
            || !class_exists("\\MongoDB\\Driver\\Query")
        ) {
            throw new SessionNotSupportedAdapter();
        }
        try {
            $this->manager = new \MongoDB\Driver\Manager($dsn);
        }catch (\Exception $e) {
            throw new SessionException("MongoDB connection failed." . $e->getMessage(), (int)$e->getCode());
        }
        $this->collection = $collection;
    }

    /**
     * @inheritDoc
     */
    public function destroy($id)
    {
        try {
            $bulkWrite = new \MongoDB\Driver\BulkWrite();
            $bulkWrite->delete(['_id' => $id]);

            $res = $this->manager->executeBulkWrite($this->collection, $bulkWrite);
            unset($bulkWrite);

            return $res->isAcknowledged() !== FALSE;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function read($id)
    {
        try {
            $query = new \MongoDB\Driver\Query(['_id' => $id]);
            $res = $this->manager->executeQuery($this->collection, $query)->toArray();
            if (empty($res)) {
                return false;
            }

            $first = current($res);
            return $first->data ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function write($id, $data)
    {
        try {
            $bulkWrite = new \MongoDB\Driver\BulkWrite();

            $bulkWrite->insert(['_id' => $id, 'data' => $data]);

            $res = $this->manager->executeBulkWrite($this->collection, $bulkWrite);

            unset($bulkWrite);

            return $res->isAcknowledged() !== FALSE;
        } catch (\Exception $e) {
            return false;
        }
    }

}
