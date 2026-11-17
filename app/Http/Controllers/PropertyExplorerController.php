<?php
namespace App\Http\Controllers;

use App\Models\{Property,Unit,WorkspaceImage};
use App\Services\WorkspaceGallery;
use App\Support\PropertyAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PropertyExplorerController extends CompanyBaseController
{
    public function index(Request $request)
    {
        $data=$request->validate(['q'=>['nullable','string','max:120']]);
        $company=$this->companyId();
        $query=Property::where('company_id',$company);
        PropertyAccess::scopeProperties($query, auth()->user());
        if (!empty($data['q'])) {
            $term='%'.trim($data['q']).'%';
            $query->where(fn($q)=>$q->where('name','like',$term)->orWhere('city','like',$term)->orWhere('district','like',$term));
        }
        $properties=$query->withCount(['units','units as occupied_count'=>fn($q)=>$q->occupancy('occupied'),'units as available_count'=>fn($q)=>$q->occupancy('available')])->orderBy('name')->paginate(18)->withQueryString();
        $covers=WorkspaceImage::where('company_id',$company)->whereNull('workspace_space_id')->whereIn('property_id',$properties->pluck('id'))->orderBy('id')->get()->groupBy('property_id')->map(fn($group)=>$group->first());
        return view('explorer.index',compact('properties','covers'));
    }

    public function show(Request $request,int $property, WorkspaceGallery $gallery)
    {
        $company=$this->companyId();
        $itemQuery=Property::where('company_id',$company);
        PropertyAccess::scopeProperties($itemQuery, auth()->user());
        $item=$itemQuery->findOrFail($property);
        $data=$request->validate([
            'status'=>['nullable',Rule::in(array_keys(Unit::statusLabels()))],
            'type'=>['nullable',Rule::in(array_keys(config('modules.units.fields.type.options')))],
            'q'=>['nullable','string','max:120'],
        ]);
        $query=$item->units()->where('company_id',$company);
        if (!empty($data['status'])) $query->occupancy($data['status']);
        if (!empty($data['type'])) $query->where('type',$data['type']);
        if (!empty($data['q'])) $query->where('code','like','%'.$data['q'].'%');
        $units=$query->withOccupancy()->orderByRaw('CASE WHEN floor IS NULL THEN 1 ELSE 0 END')->orderByDesc('floor')->orderBy('code')->paginate(72)->withQueryString();
        $floors=$units->getCollection()->groupBy(fn($u)=>$u->floor===null?'غير محدد':(string)$u->floor);
        $cover=$gallery->images($item)->first();
        return view('explorer.show',compact('item','units','floors','cover'));
    }

    public function image(int $image)
    {
        $record=WorkspaceImage::where('company_id',$this->companyId())->findOrFail($image);
        $disk=WorkspaceGallery::disk();
        abort_unless($disk->exists($record->path),404);
        return $disk->response($record->path,null,[
            'Content-Type'=>$record->mime,'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, no-store',
        ]);
    }
}
