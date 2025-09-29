<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Institute;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InstituteController extends Controller
{
    /**
     * Display all institutes
     */
    public function index()
    {
        $institutes = Institute::withCount('professors')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $institutes
        ]);
    }

    /**
     * Store a newly created institute
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'       => 'required|string|max:255|unique:institutes,name',
                'full_name'  => 'required|string|max:255',
                'description'=> 'nullable|string',
            ]);

            $institute = Institute::create($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Institute created successfully.',
                'data'    => $institute
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Institute Store Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to create institute',
            ], 500);
        }
    }

    /**
     * Show a specific institute
     */
    public function show($id)
    {
        $institute = Institute::find($id);

        if (!$institute) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Institute not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $institute
        ]);
    }

    /**
     * Update an institute
     */
    public function update(Request $request, $id)
    {
        try {
            $institute = Institute::find($id);

            if (!$institute) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Institute not found',
                ], 404);
            }

            $validated = $request->validate([
                'name'       => 'sometimes|string|max:255|unique:institutes,name,' . $institute->id,
                'full_name'  => 'sometimes|string|max:255',
                'description'=> 'nullable|string',
            ]);

            $institute->update($validated);

            return response()->json([
                'status'  => 'success',
                'message' => 'Institute updated successfully.',
                'data'    => $institute
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Institute Update Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Failed to update institute',
            ], 500);
        }
    }

    /**
     * Delete an institute
     */
    public function destroy($id)
    {
        $institute = Institute::find($id);

        if (!$institute) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Institute not found',
            ], 404);
        }

        $institute->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Institute deleted successfully.'
        ]);
    }
}
