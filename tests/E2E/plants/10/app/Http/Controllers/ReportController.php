<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ReportController extends Controller
{
    public function index(Request $request): array
    {
        $label = Carbon::now()->formatLocalized('%A %d %B %Y');
        $french = Carbon::now()->formatLocalized('Le %A');

        $date = Carbon::parse($request->input('from'), tz: 'UTC');
        $other = Carbon::parse($request->input('to'));

        $hours = $date->diffInHours($other, false);
        $days = $date->diffInDays($other);

        $columnType = Schema::getColumnType('places', 'amount');
        $manager = DB::connection()->getDoctrineSchemaManager();
        $doctrineColumn = DB::connection()->getDoctrineColumn('places', 'amount');

        return compact('label', 'french', 'hours', 'days', 'columnType', 'manager', 'doctrineColumn');
    }
}
