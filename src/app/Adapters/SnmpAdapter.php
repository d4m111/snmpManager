<?php

namespace Telecentro\SnmpManager\App\Adapters;

class SnmpAdapter implements SnmpAdapterInterface {

	private $comunity               = "public";
	private $timeoutMsOnGet         = 200000; //milliseconds
	private $retriesOnGet           = 1;
    private $timeoutMsOnSet         = 1000000; //milliseconds
	private $retriesOnSet           = 2; // -1 = deshabilitado
    private $format                 = 'PLAIN';
    private $oidAsIndexOnWalk       = true;

    public function __construct()
    {
	}

    public function get(
        string $ip, 
        string $oid, 
        ?string $format = null, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null,
    ): string|float|int
    {

        $format = $format ?? $this->format;
        $comunity = $comunity ?? $this->comunity;
        $timeoutMs = $timeoutMs ?? $this->timeoutMsOnGet;
        $retries = $retries ?? $this->retriesOnGet;

        // snmp_set_quick_print(1); 
		// snmp_set_enum_print(0);

        match (strtoupper($format)) {
            'LIBRARY'   => snmp_set_valueretrieval(SNMP_VALUE_LIBRARY),
            'PLAIN'     => snmp_set_valueretrieval(SNMP_VALUE_PLAIN),
            default     => snmp_set_valueretrieval(SNMP_VALUE_LIBRARY),
        };

        return snmpget($ip, $comunity, $oid, $timeoutMs, $retries);
    }

    public function walk(
        string $ip, 
        string $oid, 
        ?string $format = null, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null,
        ?bool $oidAsIndex = null,
    ): array
    {        
        $format = $format ?? $this->format;
        $comunity = $comunity ?? $this->comunity;
        $timeoutMs = $timeoutMs ?? $this->timeoutMsOnGet;
        $retries = $retries ?? $this->retriesOnGet;
        $oidAsIndex = $oidAsIndex ?? $this->oidAsIndexOnWalk;

        // snmp_set_quick_print(1); 
		// snmp_set_enum_print(0);

        match (strtoupper($format)) {
            'LIBRARY'   => snmp_set_valueretrieval(SNMP_VALUE_LIBRARY),
            'PLAIN'     => snmp_set_valueretrieval(SNMP_VALUE_PLAIN),
            default     => snmp_set_valueretrieval(SNMP_VALUE_LIBRARY),
        };

        if($oidAsIndex === true){
            return snmprealwalk($ip, $comunity, $oid, $timeoutMs, $retries);
        }else{
            return snmpwalk($ip, $comunity, $oid, $timeoutMs, $retries);
        }
    }

    public function set(
        string $ip, 
        string $oid, 
        string $type, 
        int|float|string $value, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null,
    ): bool
    {
        $comunity = $comunity ?? $this->comunity;
        $timeoutMs = $timeoutMs ?? $this->timeoutMsOnSet;
        $retries = $retries ?? $this->retriesOnSet;

        $resp = snmpset($ip, $comunity, $oid, $type, $value, $timeoutMs, $retries);

        return ($resp === true) ? true : false; 
    }

}