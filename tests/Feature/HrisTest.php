<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Position;
use App\Models\Employee;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Mockery;

class HrisTest extends TestCase
{
    use DatabaseTransactions;

    protected $mockUserService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the UserService to avoid HTTP requests to auth-service
        $this->mockUserService = Mockery::mock(UserService::class);
        $this->app->instance(UserService::class, $this->mockUserService);

        // Default auth-service mock response for jwt.auth and getUserById
        $this->mockUserService->shouldReceive('getMe')->andReturn((object)[
            'id' => 1,
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'role' => 'superadmin',
            'is_active' => true,
        ])->byDefault();

        $this->mockUserService->shouldReceive('getUserById')->andReturnUsing(function ($id) {
            if ($id === 999) {
                return null;
            }
            return (object)[
                'id' => $id,
                'name' => 'User ' . $id,
                'email' => "user{$id}@example.com",
                'role' => 'karyawan',
                'is_active' => true,
            ];
        })->byDefault();
    }

    /**
     * 1. Test public branches endpoint
     */
    public function test_public_branches_list(): void
    {
        // GET /branches should be accessible without credentials cookie
        $response = $this->getJson('/api/branches');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => ['id', 'name', 'code', 'parent_id', 'address', 'is_active']
                ]
            ]);
    }

    /**
     * 2. Test branches tree structure
     */
    public function test_branches_tree_view(): void
    {
        $response = $this->getJson('/api/branches?tree=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id', 'name', 'code', 'parent_id', 'address', 'is_active', 'children'
                    ]
                ]
            ]);
    }

    /**
     * 3. Test branch circular reference rejection
     */
    public function test_branch_circular_reference_rejection(): void
    {
        // HQ (id=1)
        // Area Jawa Barat (id=2, parent=1)
        // Cabang Bandung (id=3, parent=2)
        // Trying to set HQ parent to Cabang Bandung (id=3) should fail.
        
        $response = $this->putJson('/api/branches/1', [
            'parent_id' => 3
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot set parent branch: circular reference detected.'
            ]);
    }

    /**
     * 4. Test position circular reference rejection
     */
    public function test_position_circular_reference_rejection(): void
    {
        // IT Manager (id=2, parent=1)
        // IT Supervisor (id=3, parent=2)
        // IT Staff (id=4, parent=3)
        // Trying to set IT Manager (id=2) parent to IT Staff (id=4) should fail.
        
        $response = $this->putJson('/api/positions/2', [
            'parent_position_id' => 4
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Cannot set parent position: circular reference detected.'
            ]);
    }

    /**
     * 5. Test employee CRUD operations
     */
    public function test_employee_crud(): void
    {
        // 5a. Store Employee
        $response = $this->postJson('/api/employees', [
            'user_id' => 5,
            'nama_lengkap' => 'Budi Santoso',
            'nomor_induk_karyawan' => '2026.06.00001',
            'alamat' => 'Jl. Merdeka Bandung',
            'branch_id' => 3, // Cabang Bandung
            'position_id' => 4, // Staff IT
            'tanggal_gabung' => '2026-01-01',
            'tanggal_mulai_kontrak' => '2026-01-01',
            'status' => 'aktif',
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(201)
            ->assertJsonPath('data.nama_lengkap', 'Budi Santoso')
            ->assertJsonPath('data.user.id', 5);

        $employeeId = $response->json('data.id');

        // 5b. Show Employee Detail
        $response = $this->getJson("/api/employees/{$employeeId}", ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama_lengkap', 'Budi Santoso');

        // 5c. Update Employee
        $response = $this->putJson("/api/employees/{$employeeId}", [
            'nama_lengkap' => 'Budi Santoso Updated',
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(200)
            ->assertJsonPath('data.nama_lengkap', 'Budi Santoso Updated');

        // 5d. Delete Employee (Soft Delete)
        $response = $this->deleteJson("/api/employees/{$employeeId}", [], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'message' => 'Employee soft-deleted successfully.'
            ]);

        // 5e. Detail should return 404 after soft delete
        $response = $this->getJson("/api/employees/{$employeeId}", ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(404);
    }

    /**
     * 6. Test employee store invalid user validation
     */
    public function test_employee_store_invalid_user(): void
    {
        $response = $this->postJson('/api/employees', [
            'user_id' => 999, // mocked to return null in UserService
            'nama_lengkap' => 'Invalid User Employee',
            'nomor_induk_karyawan' => '2026.06.99999',
            'branch_id' => 3,
            'position_id' => 4,
            'tanggal_gabung' => '2026-01-01',
            'tanggal_mulai_kontrak' => '2026-01-01',
        ], ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('user_id');
    }

    /**
     * 7. Test employee list with filters and pagination metadata
     */
    public function test_employee_list_filters_and_pagination(): void
    {
        // Insert a few employees
        Employee::create([
            'user_id' => 10,
            'nama_lengkap' => 'John Doe IT',
            'nomor_induk_karyawan' => '2026.06.10001',
            'branch_id' => 3, // Bandung
            'position_id' => 4, // Staff IT
            'tanggal_gabung' => '2026-01-01',
            'tanggal_mulai_kontrak' => '2026-01-01',
            'status' => 'aktif',
        ]);

        Employee::create([
            'user_id' => 11,
            'nama_lengkap' => 'Jane Smith Finance',
            'nomor_induk_karyawan' => '2026.06.10002',
            'branch_id' => 3, // Bandung
            'position_id' => 7, // Staff Finance
            'tanggal_gabung' => '2026-01-01',
            'tanggal_mulai_kontrak' => '2026-01-01',
            'status' => 'aktif',
        ]);

        // Query with search
        $response = $this->getJson('/api/employees?search=Doe', ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta' => ['page', 'limit', 'total', 'total_pages']
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_lengkap', 'John Doe IT');

        // Query with division filter (Finance has division='Finance')
        $response = $this->getJson('/api/employees?division=Finance', ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_lengkap', 'Jane Smith Finance');
    }

    /**
     * 8. Test organization tree
     */
    public function test_employee_organization_tree(): void
    {
        // Insert chain:
        // Position 1: Direktur Operasional (level 4)
        // Position 2: Manager IT (level 3, parent = 1)
        // Position 3: Supervisor IT (level 2, parent = 2)
        // Position 4: Staff IT (level 1, parent = 3)

        // Let's create an IT Supervisor employee and Staff employee
        $supervisor = Employee::create([
            'user_id' => 20,
            'nama_lengkap' => 'Supervisor IT Eko',
            'nomor_induk_karyawan' => '2026.06.20001',
            'branch_id' => 3,
            'position_id' => 3, // Supervisor IT
            'tanggal_gabung' => '2025-01-01',
            'tanggal_mulai_kontrak' => '2025-01-01',
            'status' => 'aktif',
        ]);

        $staff = Employee::create([
            'user_id' => 21,
            'nama_lengkap' => 'Staff IT Dwi',
            'nomor_induk_karyawan' => '2026.06.20002',
            'branch_id' => 3,
            'position_id' => 4, // Staff IT (reports to Supervisor IT)
            'tanggal_gabung' => '2026-01-01',
            'tanggal_mulai_kontrak' => '2026-01-01',
            'status' => 'aktif',
        ]);

        $response = $this->getJson("/api/employees/{$staff->id}/org-tree", ['Cookie' => 'access_token=valid-token']);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'employee' => ['id', 'nama_lengkap', 'user'],
                    'supervisor_chain',
                    'direct_reports'
                ]
            ]);

        // The supervisor chain should have Supervisor IT, Manager IT, and Direktur Operasional
        $chain = $response->json('data.supervisor_chain');
        $this->assertGreaterThanOrEqual(1, count($chain));
        $this->assertEquals('Supervisor IT', $chain[0]['position']['name']);
        $this->assertEquals('Supervisor IT Eko', $chain[0]['employees'][0]['nama_lengkap']);
    }
}
