<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ResidentImage;

class ResidentImageController extends Controller
{
    private function imageDisk(): string
    {
        return config('filesystems.default') === 'r2' ? 'r2' : 'public';
    }

    public function index(Request $request)
    {
        $query = ResidentImage::query();

        if ($request->has('resident_id')) {
            $query->where('resident_id', $request->query('resident_id'));
        }

        return response()->json($query->get(), 200);
    }

    public function show($id)
    {
        $item = ResidentImage::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'resident_id' => 'required|integer|exists:residents,id',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_type' => 'nullable|string|max:255',
        ]);

        $path = $request->file('image')->store('resident-images', $this->imageDisk());

        $item = ResidentImage::create([
            'resident_id' => $validated['resident_id'],
            'image_path' => $path,
            'image_type' => $validated['image_type'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Imagen subida exitosamente',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = ResidentImage::findOrFail($id);

        $validated = $request->validate([
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'image_type' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('image')) {
            Storage::disk($this->imageDisk())->delete($item->image_path);
            $validated['image_path'] = $request->file('image')->store('resident-images', $this->imageDisk());
        }

        $item->update($validated);

        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item,
        ], 200);
    }

    public function destroy($id)
    {
        $item = ResidentImage::findOrFail($id);
        Storage::disk($this->imageDisk())->delete($item->image_path);
        $item->delete(); // Hard delete porque la tabla no tiene softDeletes

        return response()->json([
            'message' => 'Eliminado exitosamente'
        ], 200);
    }
}
