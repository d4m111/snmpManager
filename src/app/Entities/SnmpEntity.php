<?php

namespace Telecentro\SnmpManager\App\Entities;

use Telecentro\SnmpManager\App\Adapters\SnmpAdapterInterface;
use Telecentro\SnmpManager\App\Entities\LogEntity;
use Telecentro\SnmpManager\App\Entities\RefFileEntity;

use \Exception;

class SnmpEntity {

	private $comunity               = null;
	private $timeoutMsOnGet         = null; //milliseconds
	private $retriesOnGet           = null;
    private $timeoutMsOnSet         = null; //milliseconds
	private $retriesOnSet           = null;
    private $format                 = null;

    const NOT_ALLOWED_CHARS_IN_REF  = [',',';']; // caracteres no permitidos como nombres de referencias a OIDS
    const IOD_PARAM_MARKERS         = ['{{','}}'];
    const ALLOW_OIDS_FILES_CACHE    = false;
    const UNRECHABLE_TIMES_BREAK    = 1;

    private $snmp;
    private $log;
    private $refFile;

    public function __construct(SnmpAdapterInterface $adapter)
    {
        $this->snmp = $adapter;

        $this->log = new LogEntity();

        $this->refFile = new RefFileEntity();
	}

    public function config(
        ?string $comunity = null, 
        ?int $timeoutMsOnGet = null, 
        ?int $retriesOnGet = null, 
        ?int $timeoutMsOnSet = null, 
        ?int $retriesOnSet = null,
        ?string $format = null,
        ?string $oidFilesPath = null, 
        ?int $deviceOidCacheMaxLen = null, 
        ?string $logChannel = null, 
        ?array $logParams = null, 
    ): void
    {        
        $this->comunity             = $comunity ?? $this->comunity;
        $this->timeoutMsOnGet       = $timeoutMsOnGet ?? $this->timeoutMsOnGet;
        $this->retriesOnGet         = $retriesOnGet ?? $this->retriesOnGet;
        $this->timeoutMsOnSet       = $timeoutMsOnSet ?? $this->timeoutMsOnSet;
        $this->retriesOnSet         = $retriesOnSet ?? $this->retriesOnSet;
        $this->format               = $format ?? $this->format;

        $this->log->config(
            channel: $logChannel,
            defaultParams: $logParams,
        );

        $this->refFile->config(
            oidFilesPath: $oidFilesPath,
            deviceOidCacheMaxLen: $deviceOidCacheMaxLen,
        );
	}

    public function iterateByRef(
        string $ip, 
        array $oidRefsList = [],
        array $ignoreOidRefsList = [], 
        array $tagsList = [], 
        ?string $vendor = null, 
        ?string $model = null, 
        ?string $hardware = null, 
        ?string $firmware = null, 
        ?string $oidRefIsAlive = null, 
        ?int $unreachableTimesBreak = null,
        bool $unreachableException = false, 
        bool $oidAsIndexOnWalk = null,
        bool $allowSet = false,
        ?callable $preProcess = null,
        ?callable $postProcess = null
    ): array
    {
        $resp = [];
        $snmpResp = null;
        $unreachableTimes = 0;
        $deviceIsAlive = null;
        $oidRefIsAliveAppended = null;
        $unreachableTimesBreak = $unreachableTimesBreak ?? self::UNRECHABLE_TIMES_BREAK;

        $oidsByDevice = $this->refFile->getOidsFileByDevice($vendor, $model, $hardware, $firmware);

        $oidRefsList = $this->defineOidRefList(
            deviceOidsData: $oidsByDevice['data'],
            oidRefsList: $oidRefsList,
            ignoreOidRefsList: $ignoreOidRefsList, 
            tagsList: $tagsList,
        );
        
        if($oidRefIsAlive){

            $oidsByDevice['data'][$oidRefIsAlive] ?? throw new Exception("'isAlive' key ref not found");

            if(in_array($oidRefIsAlive, $oidRefsList) ){
                // la saco para luego inserttarla al principo del array
                $oidRefsList = array_diff($oidRefsList, [$oidRefIsAlive]);

                $oidRefIsAliveAppended = false;
            }else{
                $oidRefIsAliveAppended = true;
            }

            // agrago oid al principio del array
            array_unshift($oidRefsList, $oidRefIsAlive);
        }

        foreach($oidRefsList as $ix => $oidRef){

            $params = [];
            $delay = null;

            try {

                if($unreachableTimes >= $unreachableTimesBreak){              
                    $resp[$oidRef] = null;
                    continue;
                }
            
                $lastResp = ($resp) ? $resp[array_key_last($resp)] : null;

                $params = $this->definetHierarchicalParamsFromOidFile(
                    deviceOidsFile:     $oidsByDevice,
                    oidRef:             $oidRef, 
                );

                $respPreCallback = (is_callable($preProcess)) ? $preProcess($ix, $oidRef, $params, $lastResp, $resp) : null;

                if($respPreCallback){
                    $params = $this->definetHierarchicalParamsFromOidFile(
                        deviceOidsFile:     $oidsByDevice,
                        oidRef:             $oidRef, 
                        method:             $respPreCallback['method'] ?? null, // el method ya sale de aca en mayusculas
                        format:             $respPreCallback['format'] ?? null, 
                        comunity:           $respPreCallback['comunity'] ?? null, 
                        timeoutMs:          $respPreCallback['timeoutMs'] ?? null, 
                        retries:            $respPreCallback['retries'] ?? null, 
                        type:               $respPreCallback['type'] ?? null, 
                        value:              $respPreCallback['value'] ?? null
                    );
                }

                $params['oidParams'] = $respPreCallback['oidParams'] ?? null;

                $deviceIsAlive = true;
                
                $errorExposed = false;
                $errorCodeExposed = null;
                $errorMessageExposed = '';
                
                $startTime = microtime(true);

                $snmpResp = match($params['method']) {
                    'GET'   => $this->get(
                                    $ip, 
                                    $params['oid'], 
                                    $params['format'], 
                                    $params['comunity'], 
                                    $params['timeoutMs'], 
                                    $params['retries'], 
                                    $params['oidParams'],
                                    true,
                                ), 
                    'WALK'  => $this->walk(
                                    $ip, 
                                    $params['oid'], 
                                    $params['format'], 
                                    $params['comunity'], 
                                    $params['timeoutMs'], 
                                    $params['retries'], 
                                    $params['oidParams'],
                                    $oidAsIndexOnWalk,
                                    true,
                                ),
                    'SET'   => ($allowSet !== true) ? 'SET_NOT_ALLOWED' : $this->set(
                                    $ip, 
                                    $params['oid'], 
                                    $params['type'], 
                                    $params['value'], 
                                    $params['comunity'], 
                                    $params['timeoutMs'], 
                                    $params['retries'], 
                                    $params['oidParams'],
                                    true,
                                ),
                };

                $delay = (float) number_format(microtime(true) - $startTime, 2);

            }catch(Exception $e){
                $deviceIsAlive = str_contains($e->getMessage(), 'No response from') ? false : true;

                $snmpResp = null;
                $errorExposed = ($unreachableException === false && str_contains($e->getMessage(), 'No response from')) ? false : true;
                $errorCodeExposed = ($unreachableException === false && str_contains($e->getMessage(), 'No response from')) ? null : $e->getCode();
                $errorMessageExposed = ($unreachableException === false && str_contains($e->getMessage(), 'No response from')) ? '' : $e->getMessage();                
            }

            $resp[$oidRef] = $snmpResp;

            if($postProcess){
                $respPostCallback = $postProcess($ix, $oidRef, $params, $snmpResp, $resp, $deviceIsAlive, $delay, $errorExposed, $errorCodeExposed, $errorMessageExposed);
                    
                // si me devuelven un FALSE en la función postProcess quiere decir que quieren frenar el loop
                if($respPostCallback === false){
                        break;
                }
            }

            if(!$deviceIsAlive){
                $unreachableTimes++;
            }else{
                // tiene que no responder de forma seguida, de otro modo reseteo el contador
                $unreachableTimes = 0;
            }
        }

        if(isset($resp[$oidRefIsAlive]) && $oidRefIsAliveAppended === true){
            unset($resp[$oidRefIsAlive]);
        }
    
            
        return $resp;
    }
    
    public function getByRef(
        string $ip, 
        string $oidRef, 
        ?string $vendor = null, 
        ?string $model = null, 
        ?string $hardware = null, 
        ?string $firmware = null, 
        ?string $format = null, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null, 
        ?array $oidParams = null, 
        bool $unreachableException = false,
    ): mixed
    {
        $oidsByDevice = $this->refFile->getOidsFileByDevice($vendor, $model, $hardware, $firmware);

        $params = $this->definetHierarchicalParamsFromOidFile(
            deviceOidsFile:     $oidsByDevice,
            oidRef:             $oidRef, 
            format:             $format, 
            comunity:           $comunity, 
            timeoutMs:          $timeoutMs, 
            retries:            $retries, 
        );

        return $this->get(
            $ip, 
            $params['oid'], 
            $params['format'], 
            $params['comunity'], 
            $params['timeoutMs'], 
            $params['retries'], 
            $oidParams, 
            $unreachableException, 
        );
    }

    public function get(
        string $ip, 
        string $oid, 
        ?string $format = null, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null, 
        ?array $oidParams = null, 
        bool $unreachableException = false, 
    ): mixed
    {        
        try {
            
            $oid = $this->replaceOidParms($oid, (array)$oidParams);

            $resp = $this->snmp->get($ip, $oid, $format, $comunity, $timeoutMs, $retries);

        }catch(Exception $e){
            
            // $resp = ($unreachableException === false && str_contains($e->getMessage(), 'No response from')) ? null : throw $e;

            if($unreachableException === false && str_contains($e->getMessage(), 'No response from')){

                $resp = null;
            
            }else{

                throw $e;
            }
        }

        return $resp;
    }

    public function walkByRef(
        string $ip, 
        string $oidRef, 
        ?string $vendor = null, 
        ?string $model = null, 
        ?string $hardware = null, 
        ?string $firmware = null, 
        ?string $format = null, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null, 
        ?array $oidParams = null, 
        ?bool $oidAsIndex = null,
        bool $unreachableException = false,
    ): array
    {
        $oidsByDevice = $this->refFile->getOidsFileByDevice($vendor, $model, $hardware, $firmware);

        $params = $this->definetHierarchicalParamsFromOidFile(
            deviceOidsFile:     $oidsByDevice,
            oidRef:             $oidRef, 
            format:             $format, 
            comunity:           $comunity, 
            timeoutMs:          $timeoutMs, 
            retries:            $retries, 
        );

        return $this->walk(
            $ip, 
            $params['oid'], 
            $params['format'], 
            $params['comunity'], 
            $params['timeoutMs'], 
            $params['retries'], 
            $oidParams, 
            $oidAsIndex, 
            $unreachableException, 
        );
    }

    public function walk(
        string $ip, 
        string $oid, 
        ?string $format = null, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null, 
        ?array $oidParams = null, 
        ?bool $oidAsIndex = null, 
        bool $unreachableException = false, 
    ): array
    {
        try {
            
            $oid = $this->replaceOidParms($oid, (array)$oidParams);

            $resp = $this->snmp->walk($ip, $oid, $format, $comunity, $timeoutMs, $retries, $oidAsIndex);

        }catch(Exception $e){

            if($unreachableException === false && str_contains($e->getMessage(), 'No response from')){
                
                $resp = [];

            }else{

                throw $e;
            }
        }

        return $resp;
    }

    public function setByRef(
        string $ip, 
        string $oidRef,
        ?string $type = null, 
        mixed $value = null, 
        ?string $vendor = null, 
        ?string $model = null, 
        ?string $hardware = null, 
        ?string $firmware = null,
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null, 
        ?array $oidParams = null, 
        bool $unreachableException = false, 
    ): ?bool
    {
        $oidsByDevice = $this->refFile->getOidsFileByDevice($vendor, $model, $hardware, $firmware);

        $params = $this->definetHierarchicalParamsFromOidFile(
            deviceOidsFile:     $oidsByDevice,
            oidRef:             $oidRef, 
            comunity:           $comunity, 
            timeoutMs:          $timeoutMs, 
            retries:            $retries, 
            type:               $type, 
            value:              $value
        );

        return $this->set(
            $ip, 
            $params['oid'], 
            $params['type'], 
            $params['value'], 
            $params['comunity'], 
            $params['timeoutMs'], 
            $params['retries'], 
            $oidParams, 
            $unreachableException, 
        );
    }

    public function set(
        string $ip, 
        string $oid, 
        string $type, 
        mixed $value, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null, 
        ?array $oidParams = null,
        bool $unreachableException = false,
    ): ?bool
    {
        try {

            $oid = $this->replaceOidParms($oid, (array)$oidParams);

            $resp = $this->snmp->set($ip, $oid, $type, $value, $comunity, $timeoutMs, $retries);

            if($resp !== true){
                throw new Exception("Unimplemented changes");
            }

        }catch(\Exception $e){

            if($unreachableException === false && str_contains($e->getMessage(), 'No response from')){
                
                $resp = null;
            
            }else{

                throw $e;
            }
        }

        return $resp;
    }
    
    public function getOidRefListsByDevice(
        array $oidRefsList = [],
        array $ignoreOidRefsList = [], 
        array $tagsList = [], 
        ?string $vendor = null, 
        ?string $model = null, 
        ?string $hardware = null, 
        ?string $firmware = null, 
    ): array
    {
        $resp = [];

        $oidsByDevice = $this->refFile->getOidsFileByDevice($vendor, $model, $hardware, $firmware);

        $oidRefsList = $this->defineOidRefList(
            deviceOidsData: $oidsByDevice['data'],
            oidRefsList: $oidRefsList,
            ignoreOidRefsList: $ignoreOidRefsList, 
            tagsList: $tagsList,
        );

        foreach($oidRefsList as $oidRef){

            $resp[$oidRef] = $this->definetHierarchicalParamsFromOidFile(
                deviceOidsFile:     $oidsByDevice,
                oidRef:             $oidRef,
            );
        }

        return $resp;
    }

    private function defineOidRefList(
        array $deviceOidsData,
        array $oidRefsList,
        array $ignoreOidRefsList, 
        array $tagsList,      
    ): array
    {
        $resp = [];

        // ACEPTED
        if(!$oidRefsList){
            $oidRefsList = array_keys($deviceOidsData);
        }

        foreach($oidRefsList as $oidRef){

            // IGNORED
            if(in_array($oidRef, $ignoreOidRefsList)){
                continue;
            }

            // TAGS
            // solo entra si hay por lo menos un tag
            if($tagsList){
                // existe, no es vacio, no es null
                $tagsFromFile = !empty($deviceOidsData[$oidRef]['tags']) ? (array)$deviceOidsData[$oidRef]['tags'] : [];

                $tagExist = false;

                foreach($tagsFromFile as $tag){
                    if(in_array($tag, $tagsList)){
                        $tagExist = true;
                        break;
                    }
                }

                if($tagExist === false){
                    continue;
                }
            }
            
            $resp[] = $oidRef;
        }

        return $resp;
    }

    private function definetHierarchicalParamsFromOidFile(
        array $deviceOidsFile,
        string $oidRef,
        ?string $method = null, 
        ?string $format = null, 
        ?string $comunity = null, 
        ?int $timeoutMs = null, 
        ?int $retries = null,
        ?string $type = null, 
        mixed $value = null, 
    ): array
    {
        $respByRef = $deviceOidsFile['data'][$oidRef] ?? throw new Exception("OID reference '$oidRef' not found");

        $oid = $respByRef['oid'] ?? $respByRef;

        $method     = $method ?? $respByRef['method'] ?? $deviceOidsFile['meta']['method'] ?? 'GET'; // Si no se defini el método para la referencia asumo que es un GET
        $format     = $format ?? $respByRef['format'] ?? $deviceOidsFile['meta']['format'] ?? $this->format;
        $comunity   = $comunity ?? $respByRef['comunity'] ?? $deviceOidsFile['meta']['comunity'] ?? $this->comunity;
        $timeoutMs  = $timeoutMs ?? $respByRef['timeoutMs'] ?? $deviceOidsFile['meta']['timeoutMs'] ?? ($method == 'SET' ? $this->timeoutMsOnSet : $this->timeoutMsOnGet);
        $retries    = $retries ?? $respByRef['retries'] ?? $deviceOidsFile['meta']['retries'] ?? ($method == 'SET' ? $this->retriesOnSet :  $this->retriesOnGet);
        $type       = $type ?? $respByRef['type'] ?? $deviceOidsFile['meta']['type'] ?? null;
        $value      = $value ?? $respByRef['value'] ?? $deviceOidsFile['meta']['forvaluemat'] ?? null;

        strtoupper($method);

        return [
            'oid'       => $oid,
            'method'    => $method,
            'format'    => $format,
            'comunity'  => $comunity,
            'timeoutMs' => $timeoutMs,
            'retries'   => $retries,
            'type'      => $type,
            'value'     => $value,
        ];
    }

    private function replaceOidParms(string $oid, array $params): string
    {
        $startMarker = self::IOD_PARAM_MARKERS[0];
        $endMarker = self::IOD_PARAM_MARKERS[1];

        if($params){
            preg_match_all("/$startMarker(.*?)$endMarker/", $oid, $matches);
        
            $keysFromOid = $matches[1];

            foreach ($keysFromOid as $key) {
            
                strval($params[$key]) ?? throw new Exception("Key '$key' not found in oid");
    
                $oid = str_replace($startMarker.$key.$endMarker,$params[$key],$oid);
            }
        }

        return $oid;
    }
}