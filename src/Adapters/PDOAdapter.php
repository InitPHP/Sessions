<?php
/**
 * PDOAdapter.php
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

use \InitPHP\Sessions\AbstractAdapter;
use \InitPHP\Sessions\Interfaces\AdapterInterface;

/**
 * Example MySQL Create Table Query
 *
 * CREATE TABLE `sessions` (
 *      `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
 *      `sess_timestamp` timestamp NULL DEFAULT NULL,
 *      `sess_ip_address` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT NULL
 *      `sess_data` text COLLATE utf8mb4_unicode_ci NOT NULL,
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 *   ALTER TABLE `sessions` ADD PRIMARY KEY (`id`);
 *
 */
class PDOAdapter extends AbstractAdapter implements AdapterInterface
{

    private \PDO $pdo;

    private string $table;

    protected bool $withIPAddress;

    public function __construct(\PDO $pdo, string $table, bool $withIPAddress = false)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->withIPAddress = $withIPAddress;
    }

    /**
     * @inheritDoc
     */
    public function destroy($id)
    {
        try {
            $arguments = [
                ':sess_id'  => $id
            ];
            $sql = "DELETE FROM " . $this->table . " WHERE id = :sess_id";
            if($this->withIPAddress){
                $sql .= " AND sess_ip_address = :ip_address";
                $arguments[':ip_address'] = $this->getIP();
            }

            $stmt = $this->query($sql, $arguments);
            return $stmt !== FALSE && $stmt->rowCount() > 0;
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
            $arguments = [
                ':sess_id'  => $id
            ];
            $sql = "SELECT sess_data FROM " . $this->table . " id = :sess_id";
            if ($this->withIPAddress) {
                $sql .= " AND sess_ip_address = :ip_address";
                $arguments[':ip_address'] = $this->getIP();
            }
            $sql .= " LIMIT 0, 1";
            $stmt = $this->query($sql, $arguments);
            if ($stmt === FALSE) {
                return false;
            }
            if ($stmt->rowCount() < 1) {
                return serialize([]);
            }
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row['sess_data'];
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
            $stmt = $this->query("REPLACE INTO " . $this->table . " (id, sess_timestamp, sess_ip_address, sess_data) VALUES (:sess_id, :sess_data, :sess_timestamp, :ip_address);", [
                ':sess_id'              => $id,
                ':sess_data'            => $data,
                ':sess_timestamp'       => time(),
                ':ip_address'           => $this->getIP(),
            ]);

            return $stmt !== FALSE && $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function query(string $query, ?array $arguments = null)
    {
        $stmt = $this->pdo->prepare($query);
        if ($stmt === FALSE) {
            return false;
        }
        if ($stmt->execute($arguments) === FALSE) {
            return false;
        }

        return $stmt;
    }

    private function getIP(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

}
