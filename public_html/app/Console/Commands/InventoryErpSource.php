<?php
namespace App\Console\Commands;
use App\Modules\Erp\Services\SourceCsvInventory;
use Illuminate\Console\Command;
class InventoryErpSource extends Command
{
    protected $signature='erp:source-inventory {manifest : Local versioned mapping JSON}';
    protected $description='Read-only physical-column CSV inventory; never authorizes or performs import';
    public function handle(): int
    {
        try {
            $file=$this->argument('manifest');
            if(strpos($file,'://')!==false||!is_file($file)||!is_readable($file)||filesize($file)>1024*1024)throw new \InvalidArgumentException();
            $manifest=json_decode(file_get_contents($file),true,64,JSON_THROW_ON_ERROR);
            if(!is_array($manifest))throw new \InvalidArgumentException();
            $summary=(new SourceCsvInventory())->inspect($manifest,dirname(realpath($file)));
            $this->line(json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
            return count($summary['issues'])?1:0;
        }catch(\Throwable $e){$this->line(json_encode(['can_import'=>false,'writes'=>0,'issues'=>[['code'=>'invalid_local_manifest_or_source']]]));return 1;}
    }
}
