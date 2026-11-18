<?php
namespace App\Http\Controllers;

use App\Models\{Property,Unit,WorkspaceImage};
use App\Services\WorkspaceGallery;
use App\Support\PropertyAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PropertyExplorerController extends CompanyBaseController
{
    // Arabic-Indic (٠-٩) and Extended Arabic-Indic / Persian (۰-۹) digits normalized to Latin,
    // the same digit map already used by RentalSheetRows/LocalAssistant elsewhere in the app —
    // so a unit code typed or pasted with an Arabic keyboard still matches when searching.
    private function normalizeDigits(?string $value): ?string
    {
        if ($value === null) return null;
        return strtr($value, array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789')
        ));
    }

    public function index(Request $request)
    {
        $data=$request->validate(['q'=>['nullable','string','max:120']]);
        $company=$this->companyId();
        $query=Property::where('company_id',$company);
        PropertyAccess::scopeProperties($query, auth()->user());
        $search=$this->normalizeDigits(trim((string)($data['q']??'')));
        if ($search!=='') {
            $term='%'.$search.'%';
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
            'floor'=>['nullable','string','max:10'],
            'q'=>['nullable','string','max:120'],
        ]);

        $base=$item->units()->where('company_id',$company);

        // Status breakdown + grand total, computed from the SAME scopeOccupancy() used for the
        // card badges and the status filter below — so the summary bar can never disagree with
        // what the grid or the dropdown show. Deliberately unfiltered by the current
        // status/type/floor/q selection: it is always "out of the property's X units", not
        // "out of the current result set".
        $statusCounts=[];
        foreach (array_keys(Unit::statusLabels()) as $key) {
            $statusCounts[$key]=(clone $base)->occupancy($key)->count();
        }
        $totalUnits=(clone $base)->count();

        // Floor options for the filter dropdown reflect the whole property, not just whatever
        // the current filters happen to leave on the page — otherwise picking one filter could
        // make another filter's own options disappear.
        $floorOptions=(clone $base)->select('floor')->distinct()->orderByRaw('floor IS NULL')->orderBy('floor')->pluck('floor');

        $query=clone $base;
        if (!empty($data['status'])) $query->occupancy($data['status']);
        if (!empty($data['type'])) $query->where('type',$data['type']);
        if (array_key_exists('floor',$data) && $data['floor']!==null && $data['floor']!=='') {
            $data['floor']==='none' ? $query->whereNull('floor') : $query->where('floor',(int)$this->normalizeDigits($data['floor']));
        }
        $search=$this->normalizeDigits(trim((string)($data['q']??'')));
        if ($search!=='') $query->where('code','like','%'.$search.'%');

        $units=$query->withOccupancy()->orderByRaw('CASE WHEN floor IS NULL THEN 1 ELSE 0 END')->orderByDesc('floor')->orderBy('code')->paginate(72)->withQueryString();
        $floorGroups=$units->getCollection()->groupBy(fn($u)=>$u->floor===null?'غير محدد':(string)$u->floor);
        $cover=$gallery->images($item)->first();
        return view('explorer.show',compact('item','units','floorGroups','floorOptions','statusCounts','totalUnits','cover'));
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
