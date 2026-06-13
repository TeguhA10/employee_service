<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;

class BranchController extends Controller
{
    /**
     * GET /branches
     * Returns a list of branches. Supports hierarchical tree view if tree=1 is passed.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Branch::orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $branches = $query->get();

        if ($request->query('tree') == '1' || $request->query('tree') === 'true') {
            return response()->json([
                'message' => 'Success',
                'data' => $this->buildTree($branches, null),
            ]);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $branches,
        ]);
    }

    /**
     * GET /branches/{id}
     */
    public function show(int $id): JsonResponse
    {
        $branch = Branch::with(['parent'])->find($id);

        if (!$branch) {
            return response()->json([
                'message' => 'Branch not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $branch,
        ]);
    }

    /**
     * POST /branches
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:branches,code'],
            'parent_id' => ['nullable', 'integer', 'exists:branches,id'],
            'address' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch = Branch::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'parent_id' => $validated['parent_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Branch created successfully.',
            'data' => $branch,
        ], 201);
    }

    /**
     * PUT /branches/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'message' => 'Branch not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('branches', 'code')->ignore($branch->id),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:branches,id'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            if ($branch->createsCircularReference((int) $validated['parent_id'])) {
                return response()->json([
                    'message' => 'Cannot set parent branch: circular reference detected.',
                ], 422);
            }
        }

        $branch->fill($validated);

        if (isset($validated['code'])) {
            $branch->code = strtoupper($validated['code']);
        }

        $branch->save();

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => $branch,
        ]);
    }

    /**
     * DELETE /branches/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'message' => 'Branch not found.',
            ], 404);
        }

        try {
            $branch->delete();
            return response()->json([
                'message' => 'Branch deleted successfully.',
            ]);
        } catch (QueryException $e) {
            // Check if foreign key constraint fails
            return response()->json([
                'message' => 'Cannot delete branch because it is being referenced by other records (e.g. employees or positions).',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Recursively builds parent-child tree from a flat collection of branches.
     */
    protected function buildTree($branches, $parentId = null): array
    {
        $branchTree = [];

        foreach ($branches as $branch) {
            if ($branch->parent_id == $parentId) {
                $children = $this->buildTree($branches, $branch->id);
                $node = $branch->toArray();
                $node['children'] = $children;
                $branchTree[] = $node;
            }
        }

        return $branchTree;
    }
}
