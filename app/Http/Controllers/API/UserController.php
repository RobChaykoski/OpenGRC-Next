<?php

namespace App\Http\Controllers\API;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends BaseApiController
{
    protected string $modelClass = User::class;

    protected string $resourceName = 'Users';

    protected array $indexRelations = [];

    protected array $showRelations = ['managedPrograms'];

    protected array $searchableFields = ['name', 'email'];

    protected array $sortableFields = ['id', 'name', 'email', 'role', 'created_at', 'updated_at', 'last_activity'];

    protected function validateStore(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::default()->mixedCase()->uncompromised(3)->min(12)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ]);
    }

    protected function validateUpdate(Request $request, Model $resource): array
    {
        return $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($resource->id)],
            'password' => ['nullable', 'confirmed', Password::default()->mixedCase()->uncompromised(3)->min(12)],
            'role' => ['nullable', Rule::enum(UserRole::class)],
        ]);
    }

    /**
     * Create a user with a hashed password and an explicit role.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validatedData = $this->validateStore($request);
        $validatedData['role'] ??= UserRole::Viewer->value;

        $resource = $this->modelClass::create($validatedData);

        if (! empty($this->showRelations)) {
            $resource->load($this->showRelations);
        }

        return response()->json([
            'message' => $this->resourceName.' created successfully',
            'data' => $resource,
        ], 201);
    }

    /**
     * Update a user, keeping the password only when one was supplied.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $resource = $this->modelClass::findOrFail($id);

        $this->authorize('update', $resource);

        $validatedData = $this->validateUpdate($request, $resource);

        if (empty($validatedData['password'])) {
            unset($validatedData['password']);
        }

        $resource->update($validatedData);
        $resource->refresh();

        if (! empty($this->showRelations)) {
            $resource->load($this->showRelations);
        }

        return response()->json([
            'message' => $this->resourceName.' updated successfully',
            'data' => $resource,
        ], 200);
    }
}
