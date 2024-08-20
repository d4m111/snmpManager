<?php

namespace Telecentro\SnmpManager\App\Console\Commands;

use Illuminate\Console\Command;

use Telecentro\SnmpManager\App\Services\SnmpService;

use \Exception;

class SnmpIterateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snmp:iterate
        {--i|ip= : IP del dispositivo a consultar}
        {--r|refs= : Listado de referencias de OIDs separadas por comas}
        {--g|ignorerefs= : Listado de referencias de OIDs a ignorar separadas por comas}
        {--a|tags= : Listado de tags separadas por comas}
        {--x|vendor= : Fabricante del dispositivo a consultar}
        {--m|model= : Modelo del dispositivo a consultar}
        {--d|hardware= : Versión de hardware del dispositivo a consultar}
        {--w|firmware= : Versión de firmware del dispositivo a consultar}
        {--f|format= : Formato de la respuesta PLAIN/LIBRARY}
        {--c|comunity= : Comunidad SNMP}
        {--t|timeout= : Timeout de la consulta en milisegundos}
        {--e|retries= : Cantidad de reintentos para la consulta}
        {--u|unreachabletimes= : Cantidad de veces consecutivas que el dispositivo sea inaccesible se eperarán para dejar de consultarlo}
        {--s|allowset : Habilita la posibilidad de procesar las referencias definidas con el método SET}
    ';

    const PARAM_SEPARATOR = ",";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $snmp = new SnmpService();

        $resp = [];
        $respTable = [];

        $startTime = 0;
        $delays = [];

        $info = $snmp->getOidRefListsByDevice(
            vendor: $this->option('vendor'),
            model: $this->option('model'),
            hardware: $this->option('hardware'),
            firmware: $this->option('firmware'),
        );

        $resp = $snmp->iterateByRef(
            ip: $this->option('ip'),
            oidRefsList: ($this->option('refs')) ? explode(self::PARAM_SEPARATOR, $this->option('refs')) : [],
            ignoreOidRefsList: ($this->option('ignorerefs')) ? explode(self::PARAM_SEPARATOR, $this->option('ignorerefs')) : [],
            tagsList: ($this->option('tags')) ? explode(self::PARAM_SEPARATOR, $this->option('tags')) : [],
            vendor: $this->option('vendor'),
            model: $this->option('model'),
            hardware: $this->option('hardware'),
            firmware: $this->option('firmware'),
            unreachableTimesBreak: $this->option('unreachabletimes') ?? 1,
            allowSet: $this->option('allowset'),
            preProcess: function($ix, $oidRef, $lastResp, $respList) use(&$startTime){

                $startTime = microtime(true);

                return [
                    'format' => $this->option('format'),
                    'comunity' => $this->option('comunity'),
                    'timeoutMs' => $this->option('timeout'),
                    'retries' => $this->option('retries'),
                ];
            },
            postProcess: function($ix, $oidRef, $snmpResp, $respList, $deviceIsAlive, $error, $errorMessage) use(&$startTime, &$delays){
                
                $delays[$oidRef] = number_format(microtime(true) - $startTime, 2);

                echo "$oidRef - {$delays[$oidRef]} \n";
                                
            }
        );

        foreach($resp as $oidRef => $snmpResp){

            $r = !is_array($snmpResp) ? $snmpResp : '';

            $delay = $delays[$oidRef] ?? '';

            $respTable[] = [$oidRef, $info[$oidRef]['oid'], $r, $delay];

            // transformo la resp del GET a array para poder prosesarla igual que la del WALK
            if(is_array($snmpResp)){
                foreach($snmpResp as $k => $v){
    
                    $respTable[] = ['', " |_ $k", $v, ''];
    
                }
            }

        }
        
        $this->table(['Reference','OID','Response','Delay (sec)'], $respTable); 

    }
}
