<?php
namespace App\Services;
use RuntimeException;
/**
 * Read-only XLSX/CSV staging importer. Never executes spreadsheet formulas and never
 * automatically creates contracts or financial postings. All rows require human review.
 * Handles the cell types used by the supplied operational workbooks.
 */
final class WorkbookReader {
 public static function rows(string $path,string $extension,int $max=12000):\Generator {
  if($extension==='csv'){
   $handle=fopen($path,'rb');if(!$handle)throw new RuntimeException('تعذر قراءة الملف.');
   try{$sample=fgets($handle)?:'';rewind($handle);$sep=substr_count($sample,';')>substr_count($sample,',')?';':',';$i=0;
    while(($values=fgetcsv($handle,0,$sep,'"',''))!==false && ++$i<=$max){
     if($i===1 && isset($values[0]))$values[0]=preg_replace('/^\xEF\xBB\xBF/','',$values[0]);
     if(self::nonempty($values))yield ['sheet'=>'CSV','row'=>$i,'cells'=>array_slice($values,0,32)];
    }
   }finally{fclose($handle);}return;
  }
  if(!class_exists(\ZipArchive::class))throw new RuntimeException('لا تتوفر إضافة PHP Zip. فعّل extension=zip أو صدّر CSV.');
  $z=new \ZipArchive();if($z->open($path)!==true)throw new RuntimeException('تعذر فتح ملف XLSX.');
  try{
   $stringXml=$z->getFromName('xl/sharedStrings.xml');$strings=[];
   if($stringXml!==false){$sxml=self::xml($stringXml);foreach($sxml->si as $si){
    $value=(string)$si->t;foreach($si->r as $run)$value.=(string)$run->t;$strings[]=$value;
   }}
   $book=self::xml((string)$z->getFromName('xl/workbook.xml'));
   $rels=self::xml((string)$z->getFromName('xl/_rels/workbook.xml.rels'));$paths=[];
   foreach($rels->Relationship as $rel){$attr=$rel->attributes();$id=(string)$attr['Id'];$target=(string)$attr['Target'];
    if(str_starts_with($target,'/'))$pathInside=ltrim($target,'/');
    else $pathInside='xl/'.ltrim($target,'/');
    $paths[$id]=$pathInside;
   }
   $count=0;$ns='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
   foreach($book->sheets->sheet as $s){$attrs=$s->attributes();$label=mb_substr((string)$attrs['name'],0,120);
    $ra=$s->attributes($ns);$target=$paths[(string)$ra['id']]??null;
    if(!$target || ($xml=$z->getFromName($target))===false)continue;
    $sheet=self::xml($xml);if(!isset($sheet->sheetData))continue;
    foreach($sheet->sheetData->row as $row){$ri=(int)$row['r'];$values=[];
     foreach($row->c as $cell){$col=self::columnNumber((string)$cell['r']);if($col<1||$col>32)continue;
      $type=(string)$cell['t'];$value=(string)$cell->v;
      if($type==='s')$value=$strings[(int)$value]??'';
      elseif($type==='inlineStr')$value=(string)$cell->is->t;
      if($value!=='')$values[$col-1]=mb_substr($value,0,2000);
     }
     if(self::nonempty($values)){ksort($values);$count++;if($count>$max)break 2;
      yield ['sheet'=>$label,'row'=>$ri,'cells'=>array_replace(array_fill(0,min(32,max(array_keys($values))+1),''),$values)];
     }
    }
   }
  }finally{$z->close();}
 }
 private static function xml(string $raw):\SimpleXMLElement {
  if(!$raw)throw new RuntimeException('ملف Excel ناقص أو تالف.');
  $previous=libxml_use_internal_errors(true);$xml=simplexml_load_string($raw,'SimpleXMLElement',LIBXML_NONET);
  libxml_clear_errors();libxml_use_internal_errors($previous);
  if($xml===false)throw new RuntimeException('فشل تحليل ملف Excel.');return $xml;
 }
 private static function columnNumber(string $ref):int {
  preg_match('/^[A-Z]+/i',$ref,$m);$v=0;foreach(str_split(strtoupper($m[0]??'')) as $ch)$v=$v*26+(ord($ch)-64);return $v;
 }
 private static function nonempty(array $row):bool {foreach($row as $v)if(trim((string)$v)!=='')return true;return false;}
}
