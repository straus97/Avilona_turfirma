<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Support\Facades\Cache;

class EmployeesController extends Controller
{
    public function __invoke()
    {
        $employees = Cache::remember('employees_all', 3600, function () {
            return Employee::select(
                'id',
                'name',
                'position',
                'tel',
                'email',
                'whatsapp',
                'vk',
                'image'
            )
                ->orderBy('id')
                ->get();
        });

        return view('company.employees', compact('employees'));
    }
}