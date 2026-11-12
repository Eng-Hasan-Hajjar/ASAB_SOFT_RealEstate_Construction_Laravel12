<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
class AppServiceProvider extends ServiceProvider {
 public function register():void {}
 public function boot():void { Paginator::defaultView('components.pagination'); }
}
