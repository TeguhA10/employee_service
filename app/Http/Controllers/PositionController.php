<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class PositionController extends Controller
{
    /**
     * GET /positions
     * Returns a list of positions. Supports tree view if tree=1 is passed.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Position::with(['branch']);

        if ($request->has('division')) {
            $query->where('division', $request->query('division'));
        }

        if ($request->has('level')) {
            $query->where('level', (int) $request->query('level'));
        }

        if ($request->has('branch_id')) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        $positions = $query->orderBy('level', 'desc')->orderBy('name')->get();

        if ($request->query('tree') == '1' || $request->query('tree') === 'true') {
            return response()->json([
                'message' => 'Success',
                'data' => $this->buildTree($positions, null),
            ]);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $positions,
        ]);
    }

    /**
     * GET /positions/{id}
     */
    public function show(int $id): JsonResponse
    {
        $position = Position::with(['parent', 'branch'])->find($id);

        if (!$position) {
            return response()->json([
                'message' => 'Position not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Success',
            'data' => $position,
        ]);
    }

    /**
     * POST /positions
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'level' => ['required', 'integer', 'min:1', 'max:4'],
            'division' => ['required', 'string', 'max:100'],
            'parent_position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $position = Position::create($validated);

        return response()->json([
            'message' => 'Position created successfully.',
            'data' => $position,
        ], 201);
    }

    /**
     * PUT /positions/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $position = Position::find($id);

        if (!$position) {
            return response()->json([
                'message' => 'Position not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'level' => ['sometimes', 'integer', 'min:1', 'max:4'],
            'division' => ['sometimes', 'string', 'max:100'],
            'parent_position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        if (array_key_exists('parent_position_id', $validated) && $validated['parent_position_id'] !== null) {
            if ($position->createsCircularReference((int) $validated['parent_position_id'])) {
                return response()->json([
                    'message' => 'Cannot set parent position: circular reference detected.',
                ], 422);
            }
        }

        $position->update($validated);

        return response()->json([
            'message' => 'Position updated successfully.',
            'data' => $position,
        ]);
    }

    /**
     * DELETE /positions/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $position = Position::find($id);

        if (!$position) {
            return response()->json([
                'message' => 'Position not found.',
            ], 404);
        }

        try {
            $position->delete();
            return response()->json([
                'message' => 'Position deleted successfully.',
            ]);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Cannot delete position because it is referenced by other records (e.g. employees).',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Recursively builds parent-child tree from a flat collection of positions.
     */
    protected function buildTree($positions, $parentId = null): array
    {
        $positionTree = [];

        foreach ($positions as $pos) {
            if ($pos->parent_position_id == $parentId) {
                $children = $this->buildTree($positions, $pos->id);
                $node = $pos->toArray();
                $node['children'] = $children;
                $positionTree[] = $node;
            }
        }

        return $positionTree;
    }
}
