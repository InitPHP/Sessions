<?php
/**
 * SessionInterface.php
 *
 * This file is part of Sessions.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 Muhammet ŞAFAK
 * @license    ./LICENSE  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Sessions;

use InitPHP\Sessions\Exception\SessionException;
use InitPHP\Sessions\Exception\SessionInvalidArgumentException;

interface SessionInterface
{

    /**
     * Session adını döndürür.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Session adını tanımlar.
     *
     * @param string $name
     * @return $this
     */
    public function setName(string $name): self;

    /**
     * Session başlatılmış mı diye kontrol eder.
     *
     * @return bool
     */
    public function isStarted(): bool;

    /**
     * Session başlatır.
     *
     * @see https://php.net/session.configuration
     * @param array $options
     * @return bool
     * @throws SessionException <p>Session zaten başlatılmış ise ya da $options seçenekleri başarısız olursa.</p>
     */
    public function start(array $options = []): bool;

    /**
     * Geçerli session id yeni oluşturulacak bir session id ile değiştirir.
     *
     * @param bool $deleteOldSession
     * @return bool
     * @throws SessionException <p>Session başlatılmadıysa.</p>
     */
    public function regenerateId(bool $deleteOldSession = false): bool;

    /**
     * Session ID bilgisini verir.
     *
     * @return string
     */
    public function getID(): string;

    /**
     * Session ID bilgisini tanımlar.
     *
     * @param string $sessionId
     * @return bool
     * @throws SessionException <p>Eğer session zaten başlatılmış ise.</p>
     */
    public function setID(string $sessionId): bool;


    /**
     * Session içeriğini ilişkisel bir dizi olarak verir.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Session'ı yok eder.
     *
     * @return bool
     */
    public function destroy(): bool;

    /**
     * Session içeriğini boşaltır, temizler.
     *
     * @return bool
     * @throws SessionException <p>Session başlatılmadıysa.</p>
     */
    public function flush(): bool;

    /**
     * Bir session anahtarının varlığını kontrol eder.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Belirtilen session verisini/değerini döndürür.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Belirtilen session verisini/değerini döndürür ve oturum verisini kaldırır.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function pull(string $key, $default = null);

    /**
     * Bir oturum verisinin değerini tanımlar.
     *
     * @param string $key
     * @param mixed $value
     * @param null|int $ttl
     * @return $this
     * @throws SessionException <p>Session başlatılmadıysa.</p>
     * @throws SessionInvalidArgumentException <p>$ttl 0 verilirse.</p>
     */
    public function set(string $key, $value, ?int $ttl = null): self;

    /**
     * Bir veriyi tanımlar ve tanımladığı veriyi geriye döndürür.
     *
     * @param string $key
     * @param mixed $value
     * @param null|int $ttl
     * @return mixed
     * @throws SessionException <p>Session başlatılmadıysa.</p>
     * @throws SessionInvalidArgumentException <p>$ttl 0 verilirse.</p>
     */
    public function push(string $key, $value, ?int $ttl = null);

    /**
     * İlişkisel bir dizi ile toplu olarak session tanımlamayı sağlar.
     *
     * @param array $assoc
     * @param int|null $ttl
     * @return $this
     * @throws SessionException <p>Session başlatılmadıysa.</p>
     * @throws SessionInvalidArgumentException <p>$ttl 0 verilirse ya da $assoc ilişkisel bir dizi değilse.</p>
     */
    public function setAssoc(array $assoc, ?int $ttl = null): self;

    /**
     * Belirtilen anahtarlara ait verileri siler/kaldırır.
     *
     * @param string ...$key
     * @return $this
     * @throws SessionException <p>Session başlatılmadıysa.</p>
     */
    public function remove(string ...$key): self;

}
