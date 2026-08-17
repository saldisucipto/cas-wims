<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function employees(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $query = Employee::query()->with(['division', 'department', 'position'])->orderBy('employee_code');

        if ($q = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($q) {
                $builder->where('employee_code', 'like', "%{$q}%")->orWhere('employee_name', 'like', "%{$q}%");
            });
        }

        return view('administration.master.employees', [
            'rows' => $query->paginate(20)->withQueryString(),
            'divisions' => Division::query()->orderBy('name')->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'positions' => Position::query()->orderBy('name')->get(),
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function storeEmployee(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $this->validateEmployee($request);
        $data['created_by'] = Auth::id();

        Employee::query()->create($data);

        return back()->with('success', 'Employee created.');
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $data = $this->validateEmployee($request, $employee->id);
        $data['updated_by'] = Auth::id();

        $employee->update($data);

        return back()->with('success', 'Employee updated.');
    }

    public function destroyEmployee(Request $request, Employee $employee)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $employee->delete();

        return back()->with('success', 'Employee deleted.');
    }

    public function divisions(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.master.divisions', ['rows' => Division::query()->orderBy('code')->get()]);
    }

    public function storeDivision(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        Division::query()->create($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:divisions,code'],
            'name' => ['required', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Division saved.');
    }

    public function updateDivision(Request $request, Division $division)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $division->update($request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:divisions,code,'.$division->id],
            'name' => ['required', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Division updated.');
    }

    public function destroyDivision(Request $request, Division $division)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $division->delete();

        return back()->with('success', 'Division deleted.');
    }

    public function positions(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        return view('administration.master.positions', [
            'rows' => Position::query()->with('division')->orderBy('code')->get(),
            'divisions' => Division::query()->orderBy('name')->get(),
        ]);
    }

    public function storePosition(Request $request)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        Position::query()->create($this->validatePosition($request));

        return back()->with('success', 'Position saved.');
    }

    public function updatePosition(Request $request, Position $position)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $position->update($this->validatePosition($request, $position->id));

        return back()->with('success', 'Position updated.');
    }

    public function destroyPosition(Request $request, Position $position)
    {
        if ($redirect = $this->ensureAdmin()) {
            return $redirect;
        }

        $position->delete();

        return back()->with('success', 'Position deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = $ignoreId ? 'unique:employees,employee_code,'.$ignoreId : 'unique:employees,employee_code';

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:50', $codeRule],
            'employee_name' => ['required', 'string', 'max:255'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'position_id' => ['nullable', 'exists:positions,id'],
            'employment_type' => ['nullable', 'string', 'max:50'],
            'employment_start_date' => ['nullable', 'date'],
            'employment_end_date' => ['nullable', 'date'],
            'status' => ['required', 'in:ACTIVE,INACTIVE'],
            'shift_pattern' => ['required', Rule::in(['ROTATING', 'FIXED_S1', 'FIXED_S2'])],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $data['employment_type'] = $data['employment_type'] ?? 'CORE_EMPLOYEE';

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePosition(Request $request, ?int $ignoreId = null): array
    {
        $codeRule = $ignoreId ? 'unique:positions,code,'.$ignoreId : 'unique:positions,code';

        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', $codeRule],
            'name' => ['required', 'string', 'max:255'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'device_type' => ['nullable', 'string', 'max:50'],
            'allowed_shifts' => ['nullable', Rule::in(['S1,S2', 'S1', 'S2'])],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $data['device_type'] = ($data['device_type'] ?? '') ?: null;
        $data['allowed_shifts'] = $data['allowed_shifts'] ?? 'S1,S2';
        $data['start_time'] = $data['start_time'] ?? '07:00';
        $data['end_time'] = $data['end_time'] ?? '23:00';

        return $data;
    }

    private function ensureAdmin()
    {
        if (! Auth::check() || Auth::user()->role !== 'Administrator') {
            return redirect()->route('administration.login');
        }

        return null;
    }
}
