<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;

class EmployeesController extends Controller
{
    public function __invoke()
    {
        $cacheTime = 60; // Время кеширования в минутах
        $employees = Cache::remember('employees_all', $cacheTime, function () {
            return Employee::all();
        });
        return view('company.employees', compact('employees'));
    }
}
