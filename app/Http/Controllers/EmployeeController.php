<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class EmployeeController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * GET /employees
     * Returns a paginated list of employees with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::with(['branch', 'position']);

        // Filters
        if ($request->has('branch_id')) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('division') || $request->has('level')) {
            $query->whereHas('position', function ($q) use ($request) {
                if ($request->has('division')) {
                    $q->where('division', $request->query('division'));
                }
                if ($request->has('level')) {
                    $q->where('level', (int) $request->query('level'));
                }
            });
        }

        // Search (nama_lengkap or nomor_induk_karyawan)
        if ($request->has('search') && !empty($request->query('search'))) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nomor_induk_karyawan', 'like', "%{$search}%");
            });
        }

        // Pagination parameters
        $limit = (int) $request->query('limit', 10);
        if ($limit < 1) $limit = 10;
        if ($limit > 100) $limit = 100; // Safe upper bound

        $paginator = $query->paginate($limit);

        // Fetch user data for each employee in parallel/loop and append
        $data = [];
        foreach ($paginator->items() as $employee) {
            $employeeData = $employee->toArray();
            
            // Get user from auth-service
            $user = $this->userService->getUserById($employee->user_id);
            $employeeData['user'] = $user;
            
            $data[] = $employeeData;
        }

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => $paginator->currentPage(),
                'limit' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /employees/{id}
     */
    public function show(int $id): JsonResponse
    {
        $employee = Employee::with(['branch', 'position'])->find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found.',
            ], 404);
        }

        $employeeData = $employee->toArray();
        $user = $this->userService->getUserById($employee->user_id);
        $employeeData['user'] = $user;

        return response()->json([
            'message' => 'Success',
            'data' => $employeeData,
        ]);
    }

    /**
     * POST /employees
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'nomor_induk_karyawan' => ['required', 'string', 'max:30', 'unique:employees,nomor_induk_karyawan'],
            'alamat' => ['nullable', 'string'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id'],
            'tanggal_gabung' => ['required', 'date'],
            'tanggal_mulai_kontrak' => ['required', 'date'],
            'tanggal_akhir_kontrak' => ['nullable', 'date', 'after_or_equal:tanggal_mulai_kontrak'],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif', 'kontrak_berakhir'])],
        ]);

        // Verify that user_id exists in auth-service
        $user = $this->userService->getUserById((int) $validated['user_id']);
        if (!$user) {
            return response()->json([
                'message' => 'The selected user_id is invalid or does not exist in Auth Service.',
                'errors' => [
                    'user_id' => ['The selected user_id is invalid or does not exist in Auth Service.']
                ]
            ], 422);
        }

        DB::beginTransaction();
        try {
            $employee = Employee::create([
                'user_id' => $validated['user_id'],
                'nama_lengkap' => $validated['nama_lengkap'],
                'nomor_induk_karyawan' => $validated['nomor_induk_karyawan'],
                'alamat' => $validated['alamat'] ?? null,
                'branch_id' => $validated['branch_id'],
                'position_id' => $validated['position_id'],
                'tanggal_gabung' => $validated['tanggal_gabung'],
                'tanggal_mulai_kontrak' => $validated['tanggal_mulai_kontrak'],
                'tanggal_akhir_kontrak' => $validated['tanggal_akhir_kontrak'] ?? null,
                'status' => $validated['status'] ?? 'aktif',
            ]);

            DB::commit();

            $employeeData = $employee->toArray();
            $employeeData['user'] = $user;

            return response()->json([
                'message' => 'Employee created successfully.',
                'data' => $employeeData,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create employee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /employees/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found.',
            ], 404);
        }

        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer'],
            'nama_lengkap' => ['sometimes', 'string', 'max:150'],
            'nomor_induk_karyawan' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('employees', 'nomor_induk_karyawan')->ignore($employee->id),
            ],
            'alamat' => ['nullable', 'string'],
            'branch_id' => ['sometimes', 'integer', 'exists:branches,id'],
            'position_id' => ['sometimes', 'integer', 'exists:positions,id'],
            'tanggal_gabung' => ['sometimes', 'date'],
            'tanggal_mulai_kontrak' => ['sometimes', 'date'],
            'tanggal_akhir_kontrak' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['aktif', 'nonaktif', 'kontrak_berakhir'])],
        ]);

        if (isset($validated['user_id'])) {
            $user = $this->userService->getUserById((int) $validated['user_id']);
            if (!$user) {
                return response()->json([
                    'message' => 'The selected user_id is invalid or does not exist in Auth Service.',
                    'errors' => [
                        'user_id' => ['The selected user_id is invalid or does not exist in Auth Service.']
                    ]
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $employee->update($validated);
            DB::commit();

            $employeeData = $employee->toArray();
            $user = $this->userService->getUserById($employee->user_id);
            $employeeData['user'] = $user;

            return response()->json([
                'message' => 'Employee updated successfully.',
                'data' => $employeeData,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update employee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /employees/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::find($id);

        if (!$employee) {
            return response()->json([
                'message' => 'Employee not found.',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $employee->delete();
            DB::commit();

            return response()->json([
                'message' => 'Employee soft-deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete employee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
