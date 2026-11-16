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
        $kinds=['named'=>'سجل مستأجر','payment_candidate'=>'سند ومبلغ للمراجعة','recorded_amount'=>'مبلغ تاريخي','period'=>'فترة إيجار سابقة'];
        $options = Property::where('company_id', $companyId)->orderBy('name')->pluck('name','id');
        $data = $request->validate([
            'property' => ['nullable','integer',Rule::exists('properties','id')->where('company_id',$companyId)],
            'q' => ['nullable','string','max:100'],
            'kind' => ['nullable',Rule::in(array_keys($kinds))],
            'min_amount' => ['nullable','numeric','min:0'],
            'max_amount' => ['nullable','numeric','min:0'],
        ]);
        $query = AsabRentalHistory::where('company_id', $companyId)->with(['property:id,name','unit:id,code','contact:id,name']);
        $query->visibleHistory();
        if (!empty($data['kind'])) $query->where('entry_kind',$data['kind']);
        if (! empty($data['property'])) $query->where('property_id', (int)$data['property']);
        if (! empty($data['q'])) {
            $term = trim($data['q']);
            $query->where(fn ($q) => $q->where('tenant_label','like','%'.$term.'%')
                ->orWhereHas('contact',fn($c)=>$c->where('company_id',$companyId)->where('name','like','%'.$term.'%'))
                ->orWhere('reference','like','%'.$term.'%')
                ->orWhere('source_sheet','like','%'.$term.'%'));
        }
        if (isset($data['min_amount'])) $query->where('source_amount','>=',$data['min_amount']);
        if (isset($data['max_amount'])) $query->where('source_amount','<=',$data['max_amount']);
        $total = (clone $query)->count();
        $items = $query->orderByDesc('id')->paginate(35)->withQueryString();
        return view('asab-imported.rentals', compact('items','options','total','kinds'));
    }

    public function finance(Request $request)
    {
        $companyId = $this->companyId();
        $data = $request->validate([
            'channel' => ['nullable',Rule::in(['cash','bank','bank_auxiliary'])],
            'flow' => ['nullable',Rule::in(['in','out','review'])],
            'property' => ['nullable','integer',Rule::exists('properties','id')->where('company_id',$companyId)],
            'status' => ['nullable',Rule::in(['unverified','verified_posted'])],
            'q' => ['nullable','string','max:120'],
            'from' => ['nullable','date_format:Y-m-d'],
            'to' => ['nullable','date_format:Y-m-d','after_or_equal:from'],
            'min_amount' => ['nullable','numeric','min:0'],
            'max_amount' => ['nullable','numeric','min:0'],
        ]);
        $query = AsabFinancialHistory::where('company_id',$companyId)->with('property:id,name');
        if (! empty($data['channel'])) $query->where('channel',$data['channel']);
        if (! empty($data['flow'])) $query->where('flow',$data['flow']);
        if (! empty($data['property'])) $query->where('property_id',(int)$data['property']);
        if (! empty($data['status'])) $query->where('review_status',$data['status']);
        if (! empty($data['from'])) $query->whereDate('event_on','>=',$data['from']);
        if (! empty($data['to'])) $query->whereDate('event_on','<=',$data['to']);
        if (isset($data['min_amount'])) $query->where('source_amount','>=',$data['min_amount']);
        if (isset($data['max_amount'])) $query->where('source_amount','<=',$data['max_amount']);
        if (! empty($data['q'])) {
            $q=trim($data['q']);
            $query->where(fn($builder)=>$builder->where('party_name','like','%'.$q.'%')
                ->orWhere('voucher_number','like','%'.$q.'%')
                ->orWhere('unit_label','like','%'.$q.'%')
                ->orWhere('property_label','like','%'.$q.'%')
                ->orWhere('source_sheet','like','%'.$q.'%'));
        }
        $total = (clone $query)->count();
        $summary = (clone $query)->select('flow','channel',DB::raw('COUNT(*) AS count_rows'),DB::raw('SUM(source_amount) AS total_amount'))
            ->groupBy('flow','channel')->get();
        $items = $query->orderByDesc('id')->paginate(35)->withQueryString();
        $options = Property::where('company_id',$companyId)->orderBy('name')->pluck('name','id');
        $matchStates=DB::table('asab_receipt_matches')->where('company_id',$companyId)->whereIn('history_id',$items->pluck('id'))->pluck('state','history_id');
        return view('asab-imported.finance',compact('items','summary','total','options','matchStates'));
    }
}
