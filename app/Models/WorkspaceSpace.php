<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WorkspaceSpace extends Model
{
    protected $guarded = ['id'];
    public function images() { return $this->hasMany(WorkspaceImage::class); }
    public static function kinds(): array
    {
        return ['bedroom'=>'غرفة نوم','master'=>'غرفة رئيسية','bathroom'=>'حمّام','living'=>'صالة','kitchen'=>'مطبخ','balcony'=>'شرفة','shop_floor'=>'مساحة عرض','storage'=>'مستودع','office'=>'مكتب','parking'=>'موقف','other'=>'مساحة أخرى'];
    }
}
