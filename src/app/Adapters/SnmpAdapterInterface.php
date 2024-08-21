<?php

namespace D4m111\SnmpManager\App\Adapters;

interface SnmpAdapterInterface
{

    public function get(
        string $ip, 
        string $oid, 
        ?string $format, 
        ?string $comunity, 
        ?int $timeoutMs, 
        ?int $retries,
    ): string|float|int;

    public function walk(
        string $ip, 
        string $oid, 
        ?string $format, 
        ?string $comunity, 
        ?int $timeoutMs, 
        ?int $retries,
        ?bool $oidAsIndex,
    ): array;


    public function set(
        string $ip, 
        string $oid, 
        string $type, 
        int|float|string $value, 
        ?string $comunity, 
        ?int $timeoutMs, 
        ?int $retries,
    ): bool;
}
