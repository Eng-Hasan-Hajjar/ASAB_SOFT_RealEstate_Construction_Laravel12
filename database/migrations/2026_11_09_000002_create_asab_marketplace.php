<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('properties',function(Blueprint $t){
   $t->boolean('is_published')->default(false)->index();$t->boolean('is_featured')->default(false);
   $t->string('public_title',180)->nullable();$t->text('public_summary')->nullable();
   $t->decimal('latitude',10,7)->nullable();$t->decimal('longitude',10,7)->nullable();$t->timestamp('published_at')->nullable();
  });
  Schema::table('units',function(Blueprint $t){
   $t->boolean('is_listed')->default(false)->index();$t->string('listing_type',12)->default('rent');
   $t->decimal('sale_price',15,3)->nullable();$t->boolean('furnished')->default(false);
  });
  Schema::create('property_media',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('property_id')->constrained()->cascadeOnDelete();
   $t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();$t->string('path');$t->string('alt',180)->nullable();
   $t->boolean('is_cover')->default(false);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();$t->index(['property_id','sort_order']);
  });
  Schema::create('property_inquiries',function(Blueprint $t){
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('property_id')->constrained()->cascadeOnDelete();
   $t->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();$t->string('name',160);$t->string('phone',50);
   $t->string('email')->nullable();$t->text('message')->nullable();$t->string('status',30)->default('new');
   $t->boolean('privacy_accepted')->default(false);$t->timestamps();$t->index(['company_id','status']);
  });
 }
 public function down():void {
  Schema::dropIfExists('property_inquiries');Schema::dropIfExists('property_media');
  Schema::table('units',fn(Blueprint $t)=>$t->dropColumn(['is_listed','listing_type','sale_price','furnished']));
  Schema::table('properties',fn(Blueprint $t)=>$t->dropColumn(['is_published','is_featured','public_title','public_summary','latitude','longitude','published_at']));
 }
};
