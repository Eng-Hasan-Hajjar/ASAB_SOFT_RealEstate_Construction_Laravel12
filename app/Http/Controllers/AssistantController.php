<?php
namespace App\Http\Controllers;
use App\Models\AssistantMessage;
use App\Services\LocalAssistant;
use Illuminate\Http\Request;
class AssistantController extends CompanyBaseController {
 public function index(){ $messages=AssistantMessage::where('company_id',$this->companyId())->where('user_id',auth()->id())->latest()->limit(12)->get()->reverse();return view('assistant.index',compact('messages')); }
 public function ask(Request $request,LocalAssistant $assistant){
  $data=$request->validate(['question'=>['required','string','min:2','max:500']]);
  $c=$this->companyId();$response=$assistant->reply($data['question'],$c);
  AssistantMessage::create(['company_id'=>$c,'user_id'=>auth()->id(),'question'=>$data['question'],...$response]);
  return response()->json($response);
 }
}
