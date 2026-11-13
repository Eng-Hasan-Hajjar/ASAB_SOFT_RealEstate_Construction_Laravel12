<?php

namespace App\Http\Controllers;

use App\Models\{AsabFinancialHistory, AsabRentalHistory, Property};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AsabImportedHistoryController extends CompanyBaseController
{
    public function rentals(Request $request)
    {
        $companyId = $this->companyId();
        $options = Property::where('company_id', $companyId)->orderBy('name')->pluck('name','id');
        $data = $request->validate([
            'property' => ['nullable','integer',Rule::exists('properties','id')->where('company_id',$companyId)],
            'q' => ['nullable','string','max:100'],
        ]);
        $query = AsabRentalHistory::where('company_id', $companyId)->with(['property:id,name','unit:id,code','contact:id,name']);
        if (! empty($data['property'])) $query->where('property_id', (int) $data['property']);
        if (! empty($data['q'])) {
            $term = trim($data['q']);
            $query->where(fn ($q) => $q->where('tenant_label','like','%'.$term.'%')
                ->orWhere('reference','like','%'.$term.'%')
                ->orWhere('source_sheet','like','%'.$term.'%'));
        }
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->paginate(35)->withQueryString();
        return view('asab-imported.rentals', compact('items','options','total'));
    }

    public function finance(Request $request)
    {
        $companyId = $this->companyId();
        $data = $request->validate([
            'channel' => ['nullable',Rule::in(['cash','bank','bank_auxiliary'])],
            'flow' => ['nullable',Rule::in(['in','out','review'])],
            'property' => ['nullable','integer',Rule::exists('properties','id')->where('company_id',$companyId)],
        ]);
        $query = AsabFinancialHistory::where('company_id',$companyId)->with('property:id,name');
        if (! empty($data['channel'])) $query->where('channel',$data['channel']);
        if (! empty($data['flow'])) $query->where('flow',$data['flow']);
        if (! empty($data['property'])) $query->where('property_id',(int)$data['property']);
        $total = (clone $query)->count();
        $summary = (clone $query)->select('flow','channel',DB::raw('COUNT(*) AS count_rows'),DB::raw('SUM(source_amount) AS total_amount'))
            ->groupBy('flow','channel')->get();
        $items = $query->orderByDesc('id')->paginate(35)->withQueryString();
        $options = Property::where('company_id',$companyId)->orderBy('name')->pluck('name','id');
        return view('asab-imported.finance',compact('items','summary','total','options'));
    }
}
