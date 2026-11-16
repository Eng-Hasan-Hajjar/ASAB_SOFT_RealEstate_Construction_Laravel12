<?php
namespace Tests\Unit;

use App\Support\RentalSheetRows;
use PHPUnit\Framework\TestCase;

final class RentalSheetRowsTest extends TestCase
{
    public function test_carries_only_identity_within_same_unit_and_stops_at_new_sections():void
    {
        $sheet=['rows'=>[
            ['row'=>6,'cells'=>['B'=>'المستاجر','E'=>'رقم السند','F'=>'بداية العقد','H'=>'المبلغ']],
            ['row'=>7,'cells'=>['B'=>'مستأجر أول','F'=>'2026-01-01','H'=>'1500']],
            ['row'=>8,'cells'=>['A'=>'2','F'=>'1-2-1447','H'=>'1500','E'=>'803']],
            ['row'=>9,'cells'=>['A'=>'3']],
            ['row'=>10,'cells'=>['G'=>'11-1-1447']],
            ['row'=>11,'cells'=>['H'=>'900','E'=>'805']],
            ['row'=>12,'cells'=>['B'=>'مستأجر ثانٍ','F'=>'2026-04-01','H'=>'1200']],
            ['row'=>13,'cells'=>['H'=>'800','E'=>'900']],
            ['row'=>14,'cells'=>['B'=>'الإجمالي','H'=>'4700'],'formulas'=>['H'=>'SUM(H7:H13)']],
            ['row'=>15,'cells'=>['F'=>'10-5-1447','H'=>'1200','E'=>'901']],
            ['row'=>16,'cells'=>['B'=>'المستاجر','E'=>'رقم السند','F'=>'بداية العقد','H'=>'المبلغ']],
            ['row'=>17,'cells'=>['G'=>'1-8-1447']],
            ['row'=>18,'cells'=>['B'=>'شاغرة','H'=>'100']],
        ]];
        $rows=iterator_to_array(RentalSheetRows::explain($sheet,[7=>true,12=>true,14=>true,18=>true]));
        self::assertSame(7,$rows[8]['anchor']);
        self::assertSame('payment_candidate',$rows[8]['kind']);
        self::assertSame('archive_only',$rows[9]['kind']);
        self::assertSame(7,$rows[10]['anchor']);
        self::assertSame('period',$rows[10]['kind']);
        self::assertSame(7,$rows[11]['anchor']);
        self::assertArrayNotHasKey('F',$rows[11]['row']['cells']);
        self::assertSame(12,$rows[13]['anchor']);
        self::assertSame('archive_only',$rows[14]['kind']);
        self::assertNull($rows[15]['anchor']);
        self::assertNull($rows[17]['anchor']);
        self::assertSame('archive_only',$rows[18]['kind']);
        $other=iterator_to_array(RentalSheetRows::explain(['rows'=>[$sheet['rows'][0],$sheet['rows'][2]]],[]));
        self::assertNull($other[8]['anchor']);
    }

    public function test_date_and_amount_are_not_invented():void
    {
        $sheet=['rows'=>[
            ['row'=>7,'cells'=>['B'=>'A','F'=>'2026-01-01','H'=>'600']],
            ['row'=>9,'cells'=>['F'=>'١-٢-١٤٤٧','H'=>'240']],
            ['row'=>10,'cells'=>['F'=>'1-3-1447']],
        ]];
        $rows=iterator_to_array(RentalSheetRows::explain($sheet,[7=>true]));
        self::assertSame('recorded_amount',$rows[9]['kind']);
        self::assertSame('period',$rows[10]['kind']);
        self::assertArrayNotHasKey('H',$rows[10]['row']['cells']);
    }
}
