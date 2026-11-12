<?php
namespace App\Http\Controllers;
use App\Models\{ImportBatch,ImportRow};
use App\Services\{WorkbookReader,Audit};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;
class ImportController extends CompanyBaseController {
 private function writer():void{abort_unless(in_array($this->role(),['company_admin','manager','accountant'],true),403);}
 public function index(){
  $c=$this->companyId();$batches=ImportBatch::where('company_id',$c)->latest('id')->paginate(12);
  return view('imports.index',compact('batches'));
 }
 public function upload(Request $request){
  $this->writer();$c=$this->companyId();$data=$request->validate([
   'file'=>['required','file','max:10240','mimes:xlsx,csv,txt'],
   'profile'=>['required',Rule::in(['unit_history','cash_income','bank_income'])],
   'property_label'=>['nullable','string','max:255'],'unit_label'=>['nullable','string','max:255']]);
  $file=$data['file'];$extension=strtolower($file->getClientOriginalExtension());
  abort_unless(in_array($extension,['csv','xlsx','txt'],true),422);
  $batch=ImportBatch::create(['company_id'=>$c,'created_by'=>auth()->id(),'source_filename'=>mb_substr(basename($file->getClientOriginalName()),0,255),'profile'=>$data['profile'],'property_label'=>$data['property_label']??null,'unit_label'=>$data['unit_label']??null,'status'=>'preview']);
  try{
   $total=0;$flagged=0;$chunk=[];
   foreach(WorkbookReader::rows($file->getRealPath(),$extension==='txt'?'csv':$extension) as $row){
    $cells=$row['cells'];$suspicious=false;
    foreach($cells as $v){if(str_contains((string)$v,'#VALUE!')||str_contains((string)$v,'#REF!')){$suspicious=true;break;}}
    if($suspicious)$flagged++;
    // Payload is encrypted at rest through the Eloquent encrypted cast.
    ImportRow::create(['import_batch_id'=>$batch->id,'source_sheet'=>$row['sheet'],'row_number'=>$row['row'],'payload'=>['cells'=>$cells],'state'=>$suspicious?'flagged':'pending_review','review_note'=>$suspicious?'يحتوي على خطأ صيغة من Excel.':null]);
    $total++;
   }
   $batch->update(['rows_total'=>$total,'rows_flagged'=>$flagged]);
  }catch(Throwable $e){$batch->rows()->delete();$batch->delete();report($e);return back()->withErrors(['file'=>'تعذّر تحليل الملف. تأكد أنه XLSX/CSV سليم، وأن امتداد PHP Zip مفعّل لملفات Excel.']);}
  Audit::record('created','import_batches',$batch->id,'إنشاء دفعة مراجعة Excel');
  return redirect()->route('imports.show',$batch->id)->with('success','حُفظت البيانات في منطقة المراجعة فقط، ولم تُنشأ عقود أو حركات مالية تلقائياً.');
 }
 public function show(int $batch){
  $record=ImportBatch::where('company_id',$this->companyId())->findOrFail($batch);
  $rows=$record->rows()->orderBy('id')->paginate(20);return view('imports.show',compact('record','rows'));
 }
 public function delete(int $batch){$this->writer();$b=ImportBatch::where('company_id',$this->companyId())->findOrFail($batch);$b->delete();return redirect()->route('imports.index')->with('success','حُذفت دفعة المراجعة.');}
}
