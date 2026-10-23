<?php
namespace App\Modules\Erp\Services;
use Illuminate\Support\Facades\DB;
class Audit
{
    public static function record($unitId, $actorId, string $action, string $entityType, $entityId, array $payload): void
    {
        DB::table('erp_audit')->insert(['unit_id'=>$unitId,'actor_id'=>$actorId,'action'=>$action,'entity_type'=>$entityType,'entity_id'=>$entityId,'payload'=>json_encode($payload, JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
    }
}
