<?php
namespace App\Services;
use App\Models\ActivityLog;
use App\Support\Workspace;
use Illuminate\Support\Facades\Auth;
class Audit {
 public static function record(string $action,string $entity,int $id,string $summary,?int $companyId=null):void {
  ActivityLog::create(['company_id'=>$companyId??Workspace::companyId(),'user_id'=>Auth::id(),'action'=>$action,'entity_type'=>$entity,'entity_id'=>$id,'summary'=>mb_substr($summary,0,240)]);
 }
}
