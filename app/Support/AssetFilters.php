<?php
namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class AssetFilters
{
    public static function fields(string $module): array
    {
        return match($module) {
            'properties'=>['asset_city'=>'المدينة','asset_district'=>'الحي','asset_unit_type'=>'نوع الوحدة داخل العقار','asset_units_min'=>'عدد الوحدات من','asset_area_min'=>'مساحة العقار من (م²)','asset_area_max'=>'مساحة العقار إلى (م²)','asset_photo'=>'صور داخلية'],
            'units'=>['asset_floor'=>'الطابق (صفر للأرضي)','asset_rooms_min'=>'غرف النوم من','asset_rooms_max'=>'غرف النوم إلى','asset_baths_min'=>'الحمّامات من','asset_area_min'=>'المساحة من (م²)','asset_area_max'=>'المساحة إلى (م²)','asset_rent_min'=>'الإيجار الشهري من','asset_rent_max'=>'الإيجار الشهري إلى','asset_photo'=>'صور داخلية','asset_furnished'=>'التأثيث'],
            'contacts'=>['asset_name'=>'اسم المستأجر','asset_nat'=>'الجنسية','asset_phone'=>'الجوال','asset_city'=>'المدينة','asset_email'=>'البريد الإلكتروني','asset_unit'=>'رمز الوحدة المرتبطة بعقد'],
            default=>[],
        };
    }

    public static function options(string $module): array
    {
        return match($module){
            'properties'=>['asset_unit_type'=>config('modules.units.fields.type.options'),'asset_photo'=>['yes'=>'لديه صور','no'=>'دون صور']],
            'units'=>['asset_photo'=>['yes'=>'لديها صور','no'=>'دون صور'],'asset_furnished'=>['yes'=>'مفروشة','no'=>'غير مفروشة']],
            default=>[],
        };
    }

    public static function ruleSet(string $module): array
    {
        $rules=[];
        foreach (self::fields($module) as $key=>$label) {
            $opts=self::options($module)[$key]??null;
            $rules[$key]=['nullable',...match(true) {
                $opts!==null=>[Rule::in(array_keys($opts))],
                $key==='asset_floor'=>['integer','between:-50,300'],
                preg_match('/_(min|max)$/',$key)===1=>['numeric','between:0,1000000000'],
                default=>['string','max:120'],
            }];
        }
        if (self::fields($module)) $rules['asset_sort']=['nullable',Rule::in(array_keys(self::sorts($module)))];
        return $rules;
    }

    public static function sorts(string $module): array
    {
        $main=['recent'=>'الأحدث أولاً','name'=>'ترتيب بالاسم أو الرمز'];
        return match($module){
            'units'=>$main+['rent_low'=>'الأقل إيجارًا','rent_high'=>'الأعلى إيجارًا','floor'=>'ترتيب بالطابق'],
            'properties'=>$main+['units_count'=>'الأكثر وحدات','area'=>'الأكبر مساحة'],
            default=>$main,
        };
    }

    public static function apply($query,string $module,array $data): void
    {
        $exact=['asset_floor'=>'floor'];
        $partial=$module==='contacts'
            ? ['asset_name'=>'name','asset_nat'=>'nationality','asset_phone'=>'phone','asset_city'=>'city','asset_email'=>'email']
            : ['asset_city'=>'city','asset_district'=>'district'];
        foreach($exact as $key=>$column)if(isset($data[$key]))$query->where($column,$data[$key]);
        foreach($partial as $key=>$column)if(isset($data[$key]))$query->where($column,'like','%'.trim($data[$key]).'%');
        foreach(['area'=>'area','rooms'=>'rooms','baths'=>'bathrooms','rent'=>'monthly_rent'] as $field=>$column){
            $min=$data['asset_'.$field.'_min']??null;$max=$data['asset_'.$field.'_max']??null;
            if ($min!==null && $max!==null && $max<$min) throw ValidationException::withMessages(['asset_'.$field.'_max'=>'الحد الأعلى يجب ألا يقل عن الحد الأدنى.']);
            if($min!==null)$query->where($column,'>=',$min);
            if($max!==null)$query->where($column,'<=',$max);
        }
        if(isset($data['asset_unit_type']))$query->whereHas('units',fn($q)=>$q->where('type',$data['asset_unit_type']));
        if(isset($data['asset_units_min']))$query->has('units','>=',(int)$data['asset_units_min']);
        if(isset($data['asset_furnished']))$query->where('furnished',$data['asset_furnished']==='yes');
        if(isset($data['asset_photo'])) {
            $table=$module==='properties'?'properties':'units';$key=$module==='properties'?'property_id':'unit_id';
            $method=$data['asset_photo']==='yes'?'whereExists':'whereNotExists';
            $query->{$method}(fn($q)=>$q->selectRaw('1')->from('workspace_images')->whereColumn("workspace_images.$key","$table.id")->whereColumn('workspace_images.company_id',"$table.company_id"));
        }
        match($data['asset_sort']??null) {
            'name'=>$query->orderBy($module==='units'?'code':'name'),
            'rent_low'=>$query->orderBy('monthly_rent'),
            'rent_high'=>$query->orderByDesc('monthly_rent'),
            'floor'=>$query->orderBy('floor'),
            'units_count'=>$query->orderByDesc('units_count'),
            'area'=>$query->orderByDesc('area'),
            default=>null,
        };
    }
}
