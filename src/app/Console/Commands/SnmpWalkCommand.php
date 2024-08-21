<?php

namespace D4m111\SnmpManager\App\Console\Commands;

use Illuminate\Console\Command;

use Telecentro\SnmpManager\App\Services\SnmpService;

use \Exception;

class SnmpWalkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snmp:walk
        {--i|ip= : IP del dispositivo a consultar}
        {--o|oid= : OID}
        {--r|ref= : Nombre de referencia para la OID}
        {--x|vendor= : Fabricante del dispositivo a consultar}
        {--m|model= : Modelo del dispositivo a consultar}
        {--d|hardware= : Versión de hardware del dispositivo a consultar}
        {--w|firmware= : Versión de firmware del dispositivo a consultar}
        {--f|format= : Formato de la respuesta PLAIN/LIBRARY}
        {--c|comunity= : Comunidad SNMP}
        {--t|timeout= : Timeout de la consulta en milisegundos}
        {--e|retries= : Cantidad de reintentos para la consulta}
        {--p|params= : Parametros en formato JSON para reemplazar en la OID}
    ';

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

        $ref = $this->option('ref');


        $resp = [];
        $respTable = [];

        if($ref){ // si el option no esta definido, es nulo

            $info = $snmp->getOidRefListsByDevice(
                oidRefsList: [$ref],
                vendor: $this->option('vendor'),
                model: $this->option('model'),
                hardware: $this->option('hardware'),
                firmware: $this->option('firmware'),
            );

            $oid = $info[$ref]['oid'];

        }else{

            $oid = $this->option('oid');            

        }

        $startTime = microtime(true);

        $resp = $snmp->walk(
            ip: $this->option('ip'),
            oid: $oid,
            format: $this->option('format'),
            comunity: $this->option('comunity'),
            timeoutMs: $this->option('timeout'),
            retries: $this->option('retries'),
            oidParams: ($this->option('params')) ? json_decode($this->option('params')) : $this->option('params'),
        );

        $delay = number_format(microtime(true) - $startTime, 2);
        
        $respTable[] = [$ref,  $oid , '', $delay];        

        foreach($resp as $k => $v){
            $respTable[] = ['', " |_ $k", $v, ''];
        }
        
        $this->table(['Reference','OID','Response','Delay (sec)'], $respTable); 

    }
}
