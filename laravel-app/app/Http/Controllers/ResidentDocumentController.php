<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\ResidentDocument;

class ResidentDocumentController extends Controller
{
    private function fileDisk(): string
    {
        return config('filesystems.default') === 'r2' ? 'r2' : 'public';
    }

    public function index(Request $request)
    {
        $query = ResidentDocument::query();

        if ($request->has('resident_id')) {
            $query->where('resident_id', $request->query('resident_id'));
        }

        return response()->json($query->latest()->get(), 200);
    }

    public function show($id)
    {
        $item = ResidentDocument::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'resident_id' => 'required|integer|exists:residents,id',
            'title' => 'required|string|max:255',
            'document_type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('resident-documents', $this->fileDisk());

        $item = ResidentDocument::create([
            'resident_id' => $validated['resident_id'],
            'title' => $validated['title'],
            'document_type' => $validated['document_type'] ?? null,
            'description' => $validated['description'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Documento subido exitosamente',
            'data' => $item,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = ResidentDocument::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'document_type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        if ($request->hasFile('file')) {
            Storage::disk($this->fileDisk())->delete($item->file_path);
            $file = $request->file('file');
            $validated['file_path'] = $file->store('resident-documents', $this->fileDisk());
            $validated['file_name'] = $file->getClientOriginalName();
            $validated['mime_type'] = $file->getClientMimeType();
            $validated['file_size'] = $file->getSize();
        }

        $item->update($validated);

        return response()->json([
            'message' => 'Actualizado exitosamente',
            'data' => $item,
        ], 200);
    }

    public function destroy($id)
    {
        $item = ResidentDocument::findOrFail($id);
        Storage::disk($this->fileDisk())->delete($item->file_path);
        $item->delete();

        return response()->json([
            'message' => 'Eliminado exitosamente'
        ], 200);
    }
}
