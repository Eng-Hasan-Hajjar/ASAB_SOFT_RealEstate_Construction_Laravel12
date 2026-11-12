<?php
namespace Tests\Unit;
use App\Services\LocalAssistant;
use PHPUnit\Framework\TestCase;
class LocalAssistantNormalizationTest extends TestCase {
 public function test_it_normalizes_arabic_diacritics_and_variants():void {
  $this->assertSame('ايجار',LocalAssistant::normalize('إِيــجَار'));
  $this->assertSame('وحده متاحه',LocalAssistant::normalize('وحدةٌ مُتاحة'));
 }
}
