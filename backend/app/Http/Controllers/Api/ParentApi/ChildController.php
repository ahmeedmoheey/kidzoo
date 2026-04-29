<?php

namespace App\Http\Controllers\Api\ParentApi;

use App\Http\Controllers\Controller;
use App\Models\Child;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ChildController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $children = $request->user()->children()->latest()->get();
        return response()->json(['children' => $children]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:children,username'],
            'password' => ['required', 'confirmed', Password::min(4)],
            'age' => ['required', 'integer', 'min:3', 'max:17'],
            'gender' => ['required', 'in:boy,girl'],
            'avatar_url' => ['nullable', 'url'],
        ]);

        $child = $request->user()->children()->create($data);

        return response()->json(['message' => 'Child created.', 'child' => $child], 201);
    }

    public function show(Request $request, Child $child): JsonResponse
    {
        $this->authorize($request, $child);
        return response()->json(['child' => $child]);
    }

    public function update(Request $request, Child $child): JsonResponse
    {
        $this->authorize($request, $child);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('children', 'username')->ignore($child->id)],
            'password' => ['sometimes', 'confirmed', Password::min(4)],
            'age' => ['sometimes', 'integer', 'min:3', 'max:17'],
            'gender' => ['sometimes', 'in:boy,girl'],
            'avatar_url' => ['sometimes', 'nullable', 'url'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $child->update($data);

        return response()->json(['message' => 'Child updated.', 'child' => $child->fresh()]);
    }

    public function destroy(Request $request, Child $child): JsonResponse
    {
        $this->authorize($request, $child);
        $child->delete();
        return response()->json(['message' => 'Child deleted.']);
    }

    private function authorize(Request $request, Child $child): void
    {
        abort_unless($child->parent_id === $request->user()->id, 403, 'You do not own this child.');
    }
}
