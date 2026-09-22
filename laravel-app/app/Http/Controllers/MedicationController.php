<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Medication;
use App\Models\Prescription;
use App\Services\ImageOptimizer;
use Illuminate\Support\Facades\Storage;

/**
 * CRUD del catálogo de medicamentos. Deliberadamente NO acepta
 * `stock_quantity` ni `expiration_date` en store()/update() — esos solo
 * cambian a través de MedicationStockMovementController, para que quede
 * rastro auditable de cada cambio de stock (ver Medication). destroy() bloquea
 * el borrado si el medicamento está en uso en alguna prescripción (409), para
 * no romper el nombre que muestran las prescripciones existentes.
 */
class MedicationController extends Controller
{
    private function imageDisk(): string
    {
        return config('filesystems.default') === 'r2' ? 'r2' : 'public';
    }

    public function index()
    {
        $items = Medication::orderBy('name')->get();
        return response()->json($items, 200);
    }

    public function show($id)
    {
        $item = Medication::findOrFail($id);
        return response()->json($item, 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:medications,name',
            'description' => 'nullable|string',
            'dosage_form' => 'nullable|string|max:100',
            'concentration' => 'nullable|string|max:100',
            // stock_quantity y expiration_date NO se aceptan aquí: solo cambian a través de
            // un movimiento en /medication-stock-movements, para que quede su rastro en el
            // kardex. minimum_stock sí es config editable junto con el resto del catálogo.
            'minimum_stock' => 'nullable|integer|min:0',
            // Foto de la caja o del blíster. Mismo límite y formatos que las demás
            // imágenes del sistema (ver UserController).
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:12288',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = ImageOptimizer::store($request->file('image'), 'medication-images', $this->imageDisk(), 800, 82);
        }

        $item = Medication::create($validated);

        return response()->json([
            'message' => 'Medicamento creado exitosamente',
            'data' => $item
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $item = Medication::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:medications,name,' . $item->id,
            'description' => 'nullable|string',
            'dosage_form' => 'nullable|string|max:100',
            'concentration' => 'nullable|string|max:100',
            'minimum_stock' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:12288',
            // Permite quitar la foto sin reemplazarla por otra.
            'remove_image' => 'sometimes|boolean',
        ]);

        $removeImage = (bool) ($validated['remove_image'] ?? false);
        unset($validated['remove_image']);

        if ($request->hasFile('image')) {
            if ($item->image) {
                Storage::disk($this->imageDisk())->delete($item->image);
            }
            $validated['image'] = ImageOptimizer::store($request->file('image'), 'medication-images', $this->imageDisk(), 800, 82);
        } elseif ($removeImage && $item->image) {
            Storage::disk($this->imageDisk())->delete($item->image);
            $validated['image'] = null;
        } else {
            // Sin archivo nuevo no se toca la foto: un PUT que solo cambia el nombre
            // no debe borrarla por venir sin el campo.
            unset($validated['image']);
        }

        $item->update($validated);

        return response()->json([
            'message' => 'Medicamento actualizado exitosamente',
            'data' => $item
        ], 200);
    }

    public function destroy($id)
    {
        $item = Medication::findOrFail($id);

        // La FK de prescriptions.medication_id tiene onDelete('cascade'). El soft-delete de
        // Eloquent no dispara esa cascada (la fila sigue existiendo físicamente), pero si el
        // medicamento queda "eliminado" el scope global de SoftDeletes lo esconde de futuras
        // consultas (incluida la que usa el frontend para mostrar el nombre en prescripciones
        // existentes). Por eso bloqueamos el borrado mientras esté en uso, igual que con las
        // condiciones médicas.
        $inUse = Prescription::where('medication_id', $item->id)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'No se puede eliminar: este medicamento está en una o más prescripciones. Descontinúalas primero.'
            ], 409);
        }

        if ($item->image) {
            Storage::disk($this->imageDisk())->delete($item->image);
            $item->image = null;
            $item->save();
        }

        $item->delete();

        return response()->json([
            'message' => 'Medicamento eliminado exitosamente'
        ], 200);
    }
}
