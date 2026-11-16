<?php
namespace App\Services;

use App\Models\{Property,Unit,WorkspaceImage,WorkspaceSpace};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\{Rule,ValidationException};
use RuntimeException;

final class WorkspaceGallery
{
    public function validate(Request $request): array
    {
        return $request->validate([
            'spaces_present'=>['sometimes','accepted'],
            'spaces'=>['nullable','array','max:30'],
            'spaces.*.id'=>['nullable','integer','distinct','min:1'],
            'spaces.*.kind'=>['required',Rule::in(array_keys(WorkspaceSpace::kinds()))],
            'spaces.*.name'=>['required','string','max:120'],
            'spaces.*.description'=>['nullable','string','max:2000'],
            'spaces.*.photos'=>['nullable','array','max:4'],
            'spaces.*.photos.*'=>['required','file','image','mimes:jpeg,png,webp','max:4096','dimensions:max_width=6000,max_height=6000'],
            'photos'=>['nullable','array','max:6'],
            'photos.*'=>['required','file','image','mimes:jpeg,png,webp','max:4096','dimensions:max_width=6000,max_height=6000'],
            'delete_images'=>['nullable','array','max:120'],
            'delete_images.*'=>['required','integer','distinct','min:1'],
        ]);
    }

    public function key(Property|Unit $item): array
    {
        return ['company_id'=>$item->company_id,'property_id'=>$item instanceof Property ? $item->id : null,'unit_id'=>$item instanceof Unit ? $item->id : null];
    }

    public function spaces(Property|Unit $item)
    {
        return WorkspaceSpace::where($this->key($item))->with('images')->orderBy('id')->get();
    }

    public function images(Property|Unit $item)
    {
        return WorkspaceImage::where($this->key($item))->whereNull('workspace_space_id')->orderBy('id')->get();
    }

    public static function disk()
    {
        return Storage::build(['driver'=>'local','root'=>storage_path('app/private/asab-gallery'),'throw'=>true]);
    }

    // Called inside the same transaction that saves the asset. Keep file operations reversible until commit.
    public function save(Property|Unit $asset, array $data, array &$created, array &$removed): void
    {
        if(!empty($data['spaces']) && empty($data['spaces_present'])) throw ValidationException::withMessages(['spaces'=>'أعد تحميل النموذج قبل حفظ المساحات.']);
        $where = $this->key($asset);
        $spaces = $this->spaces($asset)->keyBy('id');
        $rows = $data['spaces'] ?? [];
        $kept = collect($rows)->pluck('id')->filter()->map(fn($id)=>(int)$id);
        if ($kept->diff($spaces->keys())->isNotEmpty()) throw ValidationException::withMessages(['spaces'=>'إحدى المساحات لا تنتمي إلى هذا السجل.']);
        $drop = !empty($data['spaces_present']) ? $spaces->keys()->diff($kept) : collect();
        $images = WorkspaceImage::where($where)->orderBy('id')->get();
        $deletes = collect($data['delete_images']??[])->map(fn($id)=>(int)$id);
        if ($deletes->diff($images->pluck('id'))->isNotEmpty()) throw ValidationException::withMessages(['photos'=>'إحدى الصور لا تنتمي إلى هذا السجل.']);
        $discard = $images->filter(fn($image)=>$drop->contains($image->workspace_space_id)||$deletes->contains($image->id));
        $incoming = count($data['photos']??[]) + array_sum(array_map(fn($row)=>count($row['photos']??[]), $rows));
        if ($incoming > 12 || $images->count() - $discard->count() + $incoming > 80) throw ValidationException::withMessages(['photos'=>'الحد 12 صورة لكل عملية حفظ و80 صورة للعقار أو الوحدة.']);
        foreach ($discard as $image) {$removed[]=$image->path; $image->delete();}
        if ($drop->isNotEmpty()) WorkspaceSpace::where($where)->whereIn('id',$drop)->delete();
        foreach ($rows as $row) {
            $fields = ['kind'=>$row['kind'],'name'=>$row['name'],'description'=>$row['description']??null];
            $space = !empty($row['id']) ? $spaces->get((int)$row['id']) : WorkspaceSpace::create($where+$fields);
            if (!empty($row['id'])) $space->update($fields);
            $this->upload($where,$space->id,$row['photos']??[],$created);
        }
        $this->upload($where,null,$data['photos']??[],$created);
        if ($asset instanceof Unit && !empty($data['spaces_present']) && ($rows || $spaces->isNotEmpty())) {
            $asset->update([
                'rooms'=>count(array_filter($rows,fn($row)=>in_array($row['kind'],['bedroom','master'],true))),
                'bathrooms'=>count(array_filter($rows,fn($row)=>$row['kind']==='bathroom')),
            ]);
        }
    }

    private function upload(array $where, ?int $space, array $files, array &$created): void
    {
        foreach ($files as $file) {
            $path = self::disk()->putFile((string)$where['company_id'], $file);
            if (!$path) throw new RuntimeException('تعذر حفظ الصورة.');
            $created[] = $path;
            WorkspaceImage::create($where+['workspace_space_id'=>$space,'path'=>$path,'mime'=>$file->getMimeType()]);
        }
    }

    public function cleanup(array $paths): void
    {
        if (!$paths) return;
        try { self::disk()->delete($paths); } catch (\Throwable $e) { report($e); }
    }
}
