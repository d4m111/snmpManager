<?php

namespace Telecentro\SnmpManager\App\Console\Commands;

use Illuminate\Console\Command;

use Telecentro\SnmpManager\App\Services\SnmpService;

use \Exception;

class SnmpSetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'snmp:set
        {--i|ip= : IP del dispositivo a consultar}
        {--o|oid= : OID}
        {--r|ref= : Nombre de referencia para la OID}
        {--y|type= : tipo de valor enviado - permitidos: i,u,s,x,d,n,o,t,a,b,U,I,F,D}
        {--a|value= : Valor enviado}
        {--x|vendor= : Fabricante del dispositivo a consultar}
        {--m|model= : Modelo del dispositivo a consultar}
        {--d|hardware= : Versión de hardware del dispositivo a consultar}
        {--w|firmware= : Versión de firmware del dispositivo a consultar}
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

        $resp = null;
        $oid = null;
        
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

        $resp = $snmp->set(
            ip: $this->option('ip'),
            oid: $oid,
            type: $this->option('type'),
            value: $this->option('value'),
            comunity: $this->option('comunity'),
            timeoutMs: $this->option('timeout'),
            retries: $this->option('retries'),
            oidParams: ($this->option('params')) ? json_decode($this->option('params')) : $this->option('params'),
        );

        $delay = number_format(microtime(true) - $startTime, 2);

        $this->table(['Reference','OID','Response','Delay (sec)'], [[$ref, $oid, $resp, $delay]]); 

    }
}
