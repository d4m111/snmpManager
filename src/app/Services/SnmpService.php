<?php

namespace Telecentro\SnmpManager\App\Services;

use Telecentro\SnmpManager\App\Adapters\SnmpAdapter;
use Telecentro\SnmpManager\App\Entities\SnmpEntity;
use Telecentro\SnmpManager\App\Entities\LogEntity;

use \Exception;

class SnmpService {

    private $entity;

    private $log;
    
    public function __construct()
    {
        $adapter = new SnmpAdapter();

        $this->entity = new SnmpEntity($adapter);
        
        $this->log = new LogEntity();
	}

    public function config(
        $comunity = null, 
        $timeoutMsOnGet = null, 
        $retriesOnGet = null, 
        $timeoutMsOnSet = null, 
        $retriesOnSet = null,
        $format = null,
        $oidFilesPath = null, 
        $deviceOidCacheMaxLen = null, 
        $logChannel = null, 
        $logParams = null, 
    ): self
    {
        $this->entity->config(
            $comunity, 
            $timeoutMsOnGet, 
            $retriesOnGet, 
            $timeoutMsOnSet, 
            $retriesOnSet, 
            $format, 
            $oidFilesPath, 
            $deviceOidCacheMaxLen, 
            $logChannel,
        );

        $this->log->config(
            channel: $logChannel,
            defaultParams: $logParams,
        );

        return $this;
	}

    public function iterateByRef(
        $ip, 
        $oidRefsList = [],
        $ignoreOidRefsList = [], 
        $tagsList = [], 
        $vendor = null, 
        $model = null, 
        $hardware = null, 
        $firmware = null, 
        $oidRefIsAlive = null, 
        $unreachableTimesBreak = null,
        $unreachableException = false, 
        $oidAsIndexOnWalk = null,
        $allowSet = false,
        array $customLogParams = [],
        $preProcess = null,
        $postProcess = null,
    ): array
    {
        try {

            $customLogParamsItem = $customLogParams;

            $resp = $this->entity->iterateByRef(
                $ip,
                $oidRefsList,
                $ignoreOidRefsList,
                $tagsList,
                $vendor,
                $model,
                $hardware,
                $firmware,
                $oidRefIsAlive,
                $unreachableTimesBreak,
                $unreachableException,
                $oidAsIndexOnWalk,
                $allowSet,
                function($ix, $oidRef, $fileParams, $lastSnmpResp, $snmpRespList) use ($preProcess, &$customLogParamsItem)
                {
                    $respParams = $preProcess($ix, $oidRef, $fileParams, $lastSnmpResp, $snmpRespList);

                    if( isset($r['customLogParams']) && is_array($r['customLogParams']) ){
                        $customLogParamsItem += $r['customLogParams'];
                    }

                    return $respParams;
                },
                function($ix, $oidRef, $fileParams, $snmpResp, $snmpRespList, $deviceIsAlive, $delay, $error, $errorCode, $errorMessage) use ($postProcess, $ip, $vendor, $model, $hardware, $firmware, $customLogParams, &$customLogParamsItem)
                {

                    if(!$error){

                        $logData = array_merge([
                            'method' => $fileParams['method'], 
                            'ip' => $ip, 
                            'ref' => $oidRef, 
                            'val' => $fileParams['value'], 
                            'vendor' => $vendor, 
                            'model' => $model, 
                            'hw' => $hardware, 
                            'fw' => $firmware, 
                            'resp' => $snmpResp, 
                            'delay' => $delay, 
                            'isok' => true
                        ], $customLogParamsItem);

                        if($fileParams['method'] != 'SET' && array_key_exists('val', $logData)){
                            unset($logData['val']);
                        }
        
                        $this->log->info($logData);

                    }else{

                        $logData = array_merge([
                            'method' => $fileParams['method'], 
                            'ip' => $ip, 
                            'ref' => $oidRef, 
                            'val' => $fileParams['value'], 
                            'vendor' => $vendor, 
                            'model' => $model, 
                            'hw' => $hardware, 
                            'fw' => $firmware, 
                            'isok' => false, 
                            'class' => __METHOD__, 
                            'code' => $errorCode, 
                            'error' => $errorMessage
                        ], $customLogParams);

                        if($fileParams['method'] != 'SET' && array_key_exists('val', $logData)){
                            unset($logData['val']);
                        }
                        
                        $this->log->error($logData);
                    }

                    return $postProcess($ix, $oidRef, $fileParams, $snmpResp, $snmpRespList, $deviceIsAlive, $delay, $error, $errorCode, $errorMessage);
                },
            );

            return $resp;

        }catch(\Exception $e){

            $logData = array_merge([
                'method' => 'ITERATE', 
                'ip' => $ip, 
                'ref' => '', 
                'vendor' => $vendor, 
                'model' => $model, 
                'hw' => $hardware, 
                'fw' => $firmware, 
                'isok' => false, 
                'class' => __METHOD__, 
                'code' => $e->getCode(), 
                'error' => $e->getMessage()
            ], $customLogParams);

            $this->log->error($logData);

            throw $e;
        }
    }
    
    public function getByRef(
        $ip, 
        $oidRef, 
        $vendor = null, 
        $model = null, 
        $hardware = null, 
        $firmware = null, 
        $format = null, 
        $comunity = null, 
        $timeoutMs = null, 
        $retries = null, 
        $oidParams = null, 
        $unreachableException = false,
        array $customLogParams = [],
    ): mixed
    {
        try {

            $startTime = microtime(true);

            $resp = $this->entity->getByRef(
                $ip,
                $oidRef,
                $vendor,
                $model,
                $hardware,
                $firmware,
                $format,
                $comunity,
                $timeoutMs,
                $retries,
                $oidParams,
                $unreachableException,
            );

            $delay = (float) number_format(microtime(true) - $startTime, 2);

            $logData = array_merge([
                    'method' => 'GET', 
                    'ip' => $ip, 
                    'ref' => $oidRef,
                    'vendor' => $vendor, 
                    'model' => $model, 
                    'hw' => $hardware, 
                    'fw' => $firmware, 
                    'resp' => $resp, 
                    'delay' => $delay, 
                    'isok' => true
                ], $customLogParams);

            $this->log->info($logData);

            return $resp;

        }catch(\Exception $e){

            $logData = array_merge([
                    'method' => 'GET', 
                    'ip' => $ip, 
                    'ref' => $oidRef, 
                    'vendor' => $vendor, 
                    'model' => $model, 
                    'hw' => $hardware, 
                    'fw' => $firmware, 
                    'isok' => false, 
                    'class' => __METHOD__, 
                    'code' => $e->getCode(), 
                    'error' => $e->getMessage()
                ], $customLogParams);

            $this->log->error($logData);

            throw $e;
        }
    }

    public function get(
        $ip, 
        $oid, 
        $format = null, 
        $comunity = null, 
        $timeoutMs = null, 
        $retries = null, 
        $oidParams = null, 
        $unreachableException = false,
        array $customLogParams = [],
    ): mixed
    {
        try {

            $startTime = microtime(true);

            $resp = $this->entity->get(
                $ip,
                $oid,
                $format,
                $comunity,
                $timeoutMs,
                $retries,
                $oidParams,
                $unreachableException,
            );

            $delay = (float) number_format(microtime(true) - $startTime, 2);

            $logData = array_merge([
                'method' => 'GET', 
                'ip' => $ip, 
                'oid' => $oid,
                'resp' => $resp, 
                'delay' => $delay, 
                'isok' => true
            ], $customLogParams);

            $this->log->info($logData);

            return $resp;

        }catch(\Exception $e){

            $logData = array_merge([
                'method' => 'GET', 
                'ip' => $ip, 
                'oid' => $oid,  
                'isok' => false, 
                'class' => __METHOD__, 
                'code' => $e->getCode(), 
                'error' => $e->getMessage()
            ], $customLogParams);

            $this->log->error($logData);
            
            throw $e;
        }
    }
    
    public function walkByRef(
        $ip, 
        $oidRef, 
        $vendor = null, 
        $model = null, 
        $hardware = null, 
        $firmware = null, 
        $format = null, 
        $comunity = null, 
        $timeoutMs = null, 
        $retries = null, 
        $oidParams = null, 
        $oidAsIndex = null,
        $unreachableException = false,
        array $customLogParams = [],
    )
    {
        try {

            $startTime = microtime(true);

            $resp = $this->entity->walkByRef(
                $ip,
                $oidRef,
                $vendor,
                $model,
                $hardware,
                $firmware,
                $format,
                $comunity,
                $timeoutMs,
                $retries,
                $oidParams,
                $oidAsIndex,
                $unreachableException,
            );
            
            $delay = (float) number_format(microtime(true) - $startTime, 2);

            $logData = array_merge([
                'method' => 'WALK', 
                'ip' => $ip, 
                'ref' => $oidRef,
                'vendor' => $vendor, 
                'model' => $model, 
                'hw' => $hardware, 
                'fw' => $firmware, 
                'resp' => $resp, 
                'delay' => $delay, 
                'isok' => true
            ], $customLogParams);

            $this->log->info($logData);
            
            return $resp;

        }catch(\Exception $e){

            $logData = array_merge([
                'method' => 'WALK', 
                'ip' => $ip, 
                'ref' => $oidRef, 
                'vendor' => $vendor, 
                'model' => $model, 
                'hw' => $hardware, 
                'fw' => $firmware, 
                'isok' => false, 
                'class' => __METHOD__, 
                'code' => $e->getCode(), 
                'error' => $e->getMessage()
            ], $customLogParams);

            $this->log->error($logData);

            throw $e;
        }
    }

    public function walk(
        $ip, 
        $oid, 
        $format = null, 
        $comunity = null, 
        $timeoutMs = null, 
        $retries = null, 
        $oidParams = null, 
        $oidAsIndex = null, 
        $unreachableException = false, 
        array $customLogParams = [],
    )
    {
        try {

            $startTime = microtime(true);

            $resp = $this->entity->walk(
                $ip,
                $oid,
                $format,
                $comunity,
                $timeoutMs,
                $retries,
                $oidParams,
                $oidAsIndex,
                $unreachableException,
            );

            $delay = (float) number_format(microtime(true) - $startTime, 2);


            $logData = array_merge([
                'method' => 'WALK', 
                'ip' => $ip, 
                'oid' => $oid,
                'resp' => $resp, 
                'delay' => $delay, 
                'isok' => true
            ], $customLogParams);

            $this->log->info($logData);

            return $resp;

        }catch(\Exception $e){

            $logData = array_merge([
                'method' => 'WALK', 
                'ip' => $ip, 
                'oid' => $oid,  
                'isok' => false, 
                'class' => __METHOD__, 
                'code' => $e->getCode(), 
                'error' => $e->getMessage()
            ], $customLogParams);

            $this->log->error($logData);

            throw $e;
        }
    }

    public function setByRef(
        $ip, 
        $oidRef,
        $type = null, 
        $value = null, 
        $vendor = null, 
        $model = null, 
        $hardware = null, 
        $firmware = null,
        $comunity = null, 
        $timeoutMs = null, 
        $retries = null, 
        $oidParams = null, 
        $unreachableException = false, 
        array $customLogParams = [],
    )
    {
        try {

            $startTime = microtime(true);

            $resp = $this->entity->setByRef(
                $ip,
                $oidRef,
                $type,
                $value,
                $vendor,
                $model,
                $hardware,
                $firmware,
                $comunity,
                $timeoutMs,
                $retries,
                $oidParams,
                $unreachableException,
            );
            
            $delay = (float) number_format(microtime(true) - $startTime, 2);

            $logData = array_merge([
                'method' => 'SET', 
                'ip' => $ip, 
                'ref' => $oidRef,
                'val' => $value,
                'vendor' => $vendor, 
                'model' => $model, 
                'hw' => $hardware, 
                'fw' => $firmware, 
                'resp' => $resp, 
                'delay' => $delay, 
                'isok' => true
            ], $customLogParams);

            $this->log->info($logData);

            return $resp;

        }catch(\Exception $e){

            $logData = array_merge([
                'method' => 'SET', 
                'ip' => $ip, 
                'ref' => $oidRef, 
                'val' => $value, 
                'vendor' => $vendor, 
                'model' => $model, 
                'hw' => $hardware, 
                'fw' => $firmware, 
                'isok' => false, 
                'class' => __METHOD__, 
                'code' => $e->getCode(), 
                'error' => $e->getMessage()
            ], $customLogParams);

            $this->log->error($logData);
            
            throw $e;
        }
    }

    public function set(
        $ip, 
        $oid, 
        $type, 
        $value, 
        $comunity = null, 
        $timeoutMs = null, 
        $retries = null, 
        $oidParams = null,
        $unreachableException = false, 
        array $customLogParams = [],
    )
    {
        try {

            $startTime = microtime(true);

            $resp = $this->entity->set(
                $ip,
                $oid,
                $type,
                $value,
                $comunity,
                $timeoutMs,
                $retries,
                $oidParams,
                $unreachableException,
            );

            $delay = (float) number_format(microtime(true) - $startTime, 2);

            $logData = array_merge([
                'method' => 'SET', 
                'ip' => $ip, 
                'oid' => $oid,
                'val' => $value, 
                'resp' => $resp, 
                'delay' => $delay, 
                'isok' => true
            ], $customLogParams);

            $this->log->info($logData);

            return $resp;

        }catch(\Exception $e){

            $logData = array_merge([
                'method' => 'SET', 
                'ip' => $ip, 
                'oid' => $oid, 
                'val' => $value, 
                'isok' => false, 
                'class' => __METHOD__, 
                'code' => $e->getCode(), 
                'error' => $e->getMessage()
            ], $customLogParams);

            $this->log->error($logData);

            throw $e;
        }
    }
    
    public function getOidRefListsByDevice(
        $oidRefsList = [],
        $ignoreOidRefsList = [], 
        $tagsList = [], 
        $vendor = null, 
        $model = null, 
        $hardware = null, 
        $firmware = null, 
    )
    {
        return $this->entity->getOidRefListsByDevice(
            $oidRefsList,
            $ignoreOidRefsList,
            $tagsList,
            $vendor,
            $model,
            $hardware,
            $firmware,
        );
    }

}