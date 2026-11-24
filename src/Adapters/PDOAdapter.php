<?php

/**
 * PDOAdapter.php
 *
 * This file is part of InitPHP Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Sessions\Adapters;

use InitPHP\Sessions\AbstractAdapter;
use InitPHP\Sessions\Exceptions\SessionAdapterException;
use InitPHP\Sessions\Exceptions\SessionInvalidArgumentException;
use InitPHP\Sessions\Internal\ResolvesSessionLifetime;
use PDO;
use PDOException;
use PDOStatement;
use SessionUpdateTimestampHandlerInterface;
use Throwable;

use function date;
use function time;

/**
 * Stores sessions in a relational database through PDO.
 *
 * Works with any PDO driver (MySQL, PostgreSQL, SQLite, ...). The write uses a
 * portable "select then insert/update" upsert rather than a vendor-specific
 * `REPLACE`/`ON CONFLICT` statement.
 *
 * Supported options:
 *  - `pdo`           (PDO)    An existing connection. When given, the
 *                             connection options below are ignored.
 *  - `dsn`           (string) DSN used to build a connection when `pdo` is
 *                             absent.
 *  - `username`      (string) Connection username (with `dsn`).
 *  - `password`      (string) Connection password (with `dsn`).
 *  - `table`         (string, required) Table that holds the sessions.
 *  - `withIPAddress` (bool)   When true, the client IP is also used as a match
 *                             condition on read/destroy and when validating or
 *                             renewing a session. Defaults to false.
 *
 * With `session.use_strict_mode` on, a row counts as an existing session only
 * while its `sess_timestamp` is younger than `session.gc_maxlifetime`;
 * {@see updateTimestamp()} renews that timestamp and never inserts a row. A
 * database error is not taken as proof that the session does not exist.
 *
 * Expected table layout:
 *
 *  CREATE TABLE `sessions` (
 *      `id`              VARCHAR(255) NOT NULL,
 *      `sess_timestamp`  TIMESTAMP NULL DEFAULT NULL,
 *      `sess_ip_address` VARCHAR(48) DEFAULT NULL,
 *      `sess_data`       TEXT NOT NULL,
 *      PRIMARY KEY (`id`)
 *  );
 */
class PDOAdapter extends AbstractAdapter implements SessionUpdateTimestampHandlerInterface
{
    use ResolvesSessionLifetime;

    private PDO $pdo;

    private string $table;

    protected bool $withIPAddress;

    /**
     * @param array<string, mixed> $options
     * @throws SessionInvalidArgumentException When required options are missing.
     * @throws SessionAdapterException When the connection cannot be opened.
     */
    public function __construct(array $options)
    {
        if (!isset($options['table'])) {
            throw new SessionInvalidArgumentException('The "table" option is required.');
        }

        if (isset($options['pdo'])) {
            if (!$options['pdo'] instanceof PDO) {
                throw new SessionInvalidArgumentException('The "pdo" option must be a PDO instance.');
            }
            $this->pdo = $options['pdo'];
        } elseif (isset($options['dsn'])) {
            try {
                $this->pdo = new PDO(
                    (string) $options['dsn'],
                    isset($options['username']) ? (string) $options['username'] : null,
                    isset($options['password']) ? (string) $options['password'] : null,
                );
            } catch (PDOException $e) {
                throw new SessionAdapterException('PDO connection failed. ' . $e->getMessage(), (int) $e->getCode(), $e);
            }
        } else {
            throw new SessionInvalidArgumentException('Either a "pdo" instance or a "dsn" option is required.');
        }

        $this->table = (string) $options['table'];
        $this->withIPAddress = (bool) ($options['withIPAddress'] ?? false);
    }

    public function validateId(string $id): bool
    {
        try {
            $arguments = [
                ':sess_id'   => $id,
                ':threshold' => date('Y-m-d H:i:s', time() - $this->resolveSessionLifetime()),
            ];
            $sql = 'SELECT 1 FROM ' . $this->table . ' WHERE id = :sess_id AND sess_timestamp >= :threshold';
            if ($this->withIPAddress) {
                $sql .= ' AND sess_ip_address = :ip_address';
                $arguments[':ip_address'] = $this->getIP();
            }
            $sql .= ' LIMIT 1';

            $stmt = $this->query($sql, $arguments);
            if ($stmt === false) {
                // The store failed: do not reject the id (that would replace
                // the user's session cookie over a transient outage). read()
                // will fail the same way and the session simply does not start.
                return true;
            }

            return $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return true;
        }
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        try {
            $arguments = [
                ':sess_timestamp' => date('Y-m-d H:i:s', time()),
                ':sess_id'        => $id,
            ];
            $sql = 'UPDATE ' . $this->table . ' SET sess_timestamp = :sess_timestamp WHERE id = :sess_id';
            if ($this->withIPAddress) {
                $sql .= ' AND sess_ip_address = :ip_address';
                $arguments[':ip_address'] = $this->getIP();
            }

            return $this->query($sql, $arguments) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function read(string $id): string|false
    {
        try {
            $arguments = [':sess_id' => $id];
            $sql = 'SELECT sess_data FROM ' . $this->table . ' WHERE id = :sess_id';
            if ($this->withIPAddress) {
                $sql .= ' AND sess_ip_address = :ip_address';
                $arguments[':ip_address'] = $this->getIP();
            }
            $sql .= ' LIMIT 1';

            $stmt = $this->query($sql, $arguments);
            if ($stmt === false) {
                return false;
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row === false ? '' : (string) $row['sess_data'];
        } catch (Throwable) {
            return false;
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $arguments = [
                ':sess_data'      => $data,
                ':sess_timestamp' => date('Y-m-d H:i:s', time()),
                ':ip_address'     => $this->getIP(),
                ':sess_id'        => $id,
            ];

            if ($this->exists($id)) {
                $stmt = $this->query(
                    'UPDATE ' . $this->table
                    . ' SET sess_timestamp = :sess_timestamp, sess_ip_address = :ip_address, sess_data = :sess_data'
                    . ' WHERE id = :sess_id',
                    $arguments,
                );
            } else {
                $stmt = $this->query(
                    'INSERT INTO ' . $this->table
                    . ' (id, sess_timestamp, sess_ip_address, sess_data)'
                    . ' VALUES (:sess_id, :sess_timestamp, :ip_address, :sess_data)',
                    $arguments,
                );
            }

            return $stmt !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $arguments = [':sess_id' => $id];
            $sql = 'DELETE FROM ' . $this->table . ' WHERE id = :sess_id';
            if ($this->withIPAddress) {
                $sql .= ' AND sess_ip_address = :ip_address';
                $arguments[':ip_address'] = $this->getIP();
            }

            return $this->query($sql, $arguments) !== false;
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->query(
                'DELETE FROM ' . $this->table . ' WHERE sess_timestamp < :threshold',
                [':threshold' => date('Y-m-d H:i:s', time() - $max_lifetime)],
            );

            return $stmt === false ? false : $stmt->rowCount();
        } catch (Throwable) {
            return false;
        }
    }

    private function exists(string $id): bool
    {
        $stmt = $this->query('SELECT 1 FROM ' . $this->table . ' WHERE id = :sess_id LIMIT 1', [':sess_id' => $id]);

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function query(string $query, array $arguments = []): PDOStatement|false
    {
        $stmt = $this->pdo->prepare($query);
        if ($stmt === false) {
            return false;
        }

        return $stmt->execute($arguments) ? $stmt : false;
    }

    private function getIP(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
