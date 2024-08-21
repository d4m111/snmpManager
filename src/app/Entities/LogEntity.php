<?php

namespace D4m111\SnmpManager\App\Entities;

use Illuminate\Support\Facades\Log;

use \Exception;

class LogEntity {
    
    private $channel = '';
    private $messageFormat = 'JSON';
    private $defaultParams = null;
    private $paramMaxLength = 140;

    public function __construct(){}

    public function config(
        ?string $channel = null,
        ?string $messageFormat = null,
        ?array $defaultParams = null,
        ?int $paramMaxLength = null,
    )
    {
        $this->channel = $channel ?? $this->channel;
        $this->messageFormat = $messageFormat ?? $this->messageFormat;
        $this->defaultParams = $defaultParams ?? $this->defaultParams;
        $this->paramMaxLength = $paramMaxLength ?? $this->paramMaxLength;

        return $this;
	}

    public function info(array $params):void
    {
        $this->log('INFO', $params);
    }

    public function error(array $params):void
    {
        $this->log('ERROR', $params);
    }

    public function debug(array $params):void
    {
        $this->log('DEBUG', $params);
    }

    public function log(string $type, array $params): void
    {

        $message = '';
        $context = [];

        if(!$this->channel){
            return;
        }

        $params = $this->porcess($params); 

        if(strtoupper($this->messageFormat) == 'LOKI'){
            
            $message = $this->lokiFormat($params);

        }else if(strtoupper($this->messageFormat) == 'JSON'){

            $context = $this->jsonFormat($params);

        }else{

            throw new Exception("Log format not found");
        }
                
        match(strtoupper($type)) {
            // mando el mensaje en el context para que el log menje automaticamente el formato json
            'INFO'      => optional(Log::channel($this->channel))->info($message,$context),
            'ERROR'     => optional(Log::channel($this->channel))->error($message,$context),
            'DEBUG'     => optional(Log::channel($this->channel))->debug($message,$context),
        };
    }

    private function porcess(array $params): array
    {

        $respParams = [];

        $processParams = ($this->defaultParams) ? $this->defaultParams : array_keys($params);

        foreach($processParams as $key){

            // con esto mantendria todos los defaultParams aunque no me lo hayan manadado 
            // $value = isset($params[$key]) ? $params[$key] : '';
            // $respParams[$key] = $value;
            
            // con esto no mantengo los defaultParams si no me lo mandaron en el param
            if(!array_key_exists($key, $params)){
                continue;
            }
                        
            $respParams[$key] = $params[$key];
        }

        foreach($respParams as $k => $v){
            
            if(is_array($v)){
                $v = implode("|", $v);
            }

            if(is_bool($v)){
                $v = ($v) ? 1 : 0;
            }

            if(is_string($v) && strlen($v) > $this->paramMaxLength){
                $v = substr($v, 0, $this->paramMaxLength); 
                $v .= "..";
            }

            $respParams[$k] = $v;
        }
        
        return $respParams;
    }
    
    private function lokiFormat(array $params): string
    {
        $resp = '';

        foreach($params as $k => $v){

            $message[] = "$k=$v";
        }

        $resp = implode(', ',$message);
        
        return $resp;
    }

    private function jsonFormat(array $params)
    {       
        // no hace falta un json_encode porque el mensaje lo mando al log por el context
        return $params;
    }

}