<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Position;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationTreeController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    /**
     * GET /employees/{id}/org-tree
     * Returns the organization structure for the given employee.
     */
    public function show(int $id): JsonResponse
    {
        /** @var Employee|null $employee */
        $employee = Employee::with(['branch', 'position'])->find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found.',
            ], 404);
        }

        // 1. Target employee with user info
        $employeeData = $employee->toArray();
        $employeeData['user'] = $this->userService->getUserById($employee->user_id);

        // 2. Supervisor chain (rantai atasan) based on parent_position_id
        $supervisorChain = [];
        $currentPosition = $employee->position;

        while ($currentPosition && $currentPosition->parent_position_id !== null) {
            /** @var Position|null $parentPosition */
            $parentPosition = Position::with(['branch'])->find($currentPosition->parent_position_id);
            if (!$parentPosition) {
                break;
            }

            // Get employees holding this parent position
            $supervisors = Employee::with(['branch', 'position'])
                ->where('position_id', $parentPosition->id)
                ->get();

            $supervisorsData = [];
            /** @var Employee $sup */
            foreach ($supervisors as $sup) {
                $supArr = $sup->toArray();
                $supArr['user'] = $this->userService->getUserById($sup->user_id);
                $supervisorsData[] = $supArr;
            }

            $supervisorChain[] = [
                'position' => $parentPosition->toArray(),
                'employees' => $supervisorsData,
            ];

            $currentPosition = $parentPosition;
        }

        // 3. Direct reports (bawahan langsung)
        // Employees whose position has parent_position_id pointing to current employee's position
        $directReportEmployees = Employee::with(['branch', 'position'])
            ->whereHas('position', function ($q) use ($employee) {
                $q->where('parent_position_id', $employee->position_id);
            })
            ->get();

        $directReports = [];
        /** @var Employee $dr */
        foreach ($directReportEmployees as $dr) {
            $drArr = $dr->toArray();
            $drArr['user'] = $this->userService->getUserById($dr->user_id);
            $directReports[] = $drArr;
        }

        return response()->json([
            'message' => 'Success',
            'data' => [
                'employee' => $employeeData,
                'supervisor_chain' => $supervisorChain,
                'direct_reports' => $directReports,
            ],
        ]);
    }
}
