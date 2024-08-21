<?php

namespace D4m111\SnmpManager\App\Entities;

use \Exception;

class RefFileEntity {

    private $oidFilesPath           = ""; // sin slash delante ni detras
    private $oidFilesExtension      = "yaml";
    private $deviceOidCacheMaxLen   = 10;
    private $oidDeviceFileCounter   = [];
    private $oidFilesCache          = [];
    private $DeviceOidCache         = [];

    const NOT_ALLOWED_CHARS_IN_REF  = [',',';']; // caracteres no permitidos como nombres de referencias a OIDS
    const ALLOW_OIDS_FILES_CACHE    = false;

    
    public function config(
        ?string $oidFilesPath = null, 
        ?int $deviceOidCacheMaxLen = null, 
    ): void
    { 
        $this->oidFilesPath             = $oidFilesPath ?? $this->oidFilesPath;
        $this->deviceOidCacheMaxLen     = $deviceOidCacheMaxLen ?? $this->deviceOidCacheMaxLen;
    }

    public function getOidsFileByDevice(
        ?string $vendor = null, 
        ?string $model = null, 
        ?string $hardware = null, 
        ?string $firmware = null,
    ): array
    {

        $deviceKey = $this->generateDeviceKey($vendor,$model,$hardware,$firmware);

        // Si esta cacheada, devuelvo esa
        if( isset($this->DeviceOidCache[$deviceKey]) ){

            $this->oidDeviceFileCounter[$deviceKey]++;

            return $this->DeviceOidCache[$deviceKey];
        }
        
        // levanto todos los archivos de config
        $oidFiles = $this->getOidsFiles();

        $filesScore = [];
        
        foreach($oidFiles as $filename => $item){

            $fileVendors = (array) ( $item['meta']['vendors'] ?? [] );
            $fileModels = (array) ( $item['meta']['models'] ?? [] );
            $fileHardwares = (array) ( $item['meta']['hardwares'] ?? [] );
            $fileFirmwares = (array) ( $item['meta']['firmwares'] ?? [] );
            $fileNotVendors = (array) ( $item['meta']['not-vendors'] ?? [] );
            $fileNotModels = (array) ( $item['meta']['not-models'] ?? [] );
            $fileNotHardwares = (array) ( $item['meta']['not-hardwares'] ?? [] );
            $fileNotFirmwares = (array) ( $item['meta']['not-firmwares'] ?? [] );
            
            if( in_array($vendor, $fileNotVendors) 
                || in_array($model, $fileNotModels) 
                || in_array($hardware, $fileNotHardwares) 
                || in_array($firmware, $fileNotFirmwares)
            ){
                continue;
            }

            $score = 0;

            if( !$fileVendors || in_array($vendor, $fileVendors) ){

                $score += $fileVendors ? 2 : 1;

                if( !$fileModels || in_array($model, $fileModels) ){

                    $score += $fileModels ? 2 : 1;

                    if( !$fileHardwares || in_array($hardware, $fileHardwares) ){

                        $score += $fileHardwares ? 2 : 1;

                        if( !$fileFirmwares || in_array($firmware, $fileFirmwares) ){

                            $score += $fileFirmwares ? 2 : 1;

                        }
                    }
                }
            }

            $filesScore[$filename] = $score;
        }

        if(!$oidFiles || !$filesScore){
            throw new Exception("OIDs file not found for device - vendor: $vendor model: $model hardware: $hardware firmware: $firmware");
        }
        
        // ordeno descendente el score
        arsort($filesScore);
        // obtengo la primera key de este, que es el nombre del archivo de oids con mayor coincidencias
        $oidsFileByDevice = $oidFiles[ array_key_first( $filesScore ) ];  
        
        if( count($this->DeviceOidCache) >= $this->deviceOidCacheMaxLen ){
            // ordeno el array de llamadas ascendentemente
            asort($this->oidDeviceFileCounter);
            // obtengo la clave del dispositivo menos llamado
            $lessCalledDevice = array_key_first($this->oidDeviceFileCounter);
            // Borro el dispositivo menos llamado del cache
            unset( $this->DeviceOidCache[ $lessCalledDevice ] );
            unset( $this->oidDeviceFileCounter[ $lessCalledDevice ] ); 
        }
        
        $this->oidDeviceFileCounter[$deviceKey] = 1;

        $this->DeviceOidCache[$deviceKey] = $oidsFileByDevice;
        
        return $this->DeviceOidCache[$deviceKey];
    }

    private function generateDeviceKey(
        ?string $vendor = null, 
        ?string $model = null, 
        ?string $hardware = null, 
        ?string $firmware = null
    ): string
    {
        return strtolower('d-'.trim($vendor).'-'.trim($model).'-'.trim($hardware).'-'.trim($firmware));
    }

    private function getOidsFiles(): array
    {
        if($this->oidFilesCache){

            return $this->oidFilesCache;
        }

        $oidFilesCache = [];
            
        $path = ($this->oidFilesPath) ? base_path()."/".$this->oidFilesPath : base_path();

        if(!is_readable($path)){
            throw new Exception("Path '$path' is not readable");
        }

        $files = scandir($path, SCANDIR_SORT_DESCENDING);
        
        foreach ($files as $item){
            
            if( is_file($path."/".$item) && strtolower(pathinfo($path."/".$item, PATHINFO_EXTENSION)) == strtolower($this->oidFilesExtension) ) {
                
                if(!is_readable($path."/".$item)){
                    throw new Exception("Path '$path/$item' is not readable");
                }

                $content = yaml_parse_file($path."/".$item);

                if(!$content) {
                    throw new Exception("OIDs file '$path/$item' is empty");
                }

                if(!isset($content['meta']) || !isset($content['data'])) {
                    throw new Exception("OIDs file bad format. 'META' & 'DATA' tags are required");
                }

                // Verifico si las referencias tienen caracteres no aceptados
                if(is_array($content['data'])){
                    foreach($content['data'] as $ref => $params){
                        $foundCount = 0;
                        
                        // cuanto cuantas veces lo remplacé, por ende cuantas veces lo encontré
                        str_replace(self::NOT_ALLOWED_CHARS_IN_REF, '', $ref, $foundCount);
                        
                        if($foundCount > 0){
                            throw new Exception("OIDs Referencie '$ref' has invalid characters"); 
                        }
                    }
                }

                $oidFilesCache[ pathinfo($path."/".$item, PATHINFO_FILENAME) ] = $content;
            }
        }

        if(!$oidFilesCache) {
            throw new Exception("OIDs file not found");
        }

        $this->oidFilesCache = (self::ALLOW_OIDS_FILES_CACHE === true) ? $oidFilesCache : [];

        return $oidFilesCache;
    }

}