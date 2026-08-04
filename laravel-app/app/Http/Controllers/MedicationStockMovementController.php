<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Models\MedicationStockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedicationStockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = MedicationStockMovement::orderByDesc('created_at');

        if ($request->has('medication_id')) {
            $query->where('medication_id', $request->query('medication_id'));
        }

        return response()->json($query->get(), 200);
    }

    public function show($id)
    {
        $item = MedicationStockMovement::findOrFail($id);
        return response()->json($item, 200);
    }

    // El contrato es: `quantity` siempre es el delta YA firmado que se aplica al stock
    // (positivo = entrada, negativo = salida) — así la suma de todos los movimientos de
    // un medicamento reproduce su stock actual, como en una tarjeta kardex real. El
    // frontend es responsable de mandar el signo correcto para entrada/salida; para
    // "ajuste" el usuario elige el signo directamente (puede corregir al alza o a la baja).
    public function store(Request $request)
    {
        $data = $request->validate([
            'medication_id' => ['required', 'exists:medications,id'],
            'type' => ['required', 'in:entrada,salida,ajuste'],
            'quantity' => ['required', 'integer'],
            'reason' => ['nullable', 'string'],
            'expiration_date' => ['nullable', 'date'],
        ]);

        if ($data['quantity'] === 0) {
            return response()->json(['message' => 'La cantidad no puede ser cero.'], 422);
        }
        if ($data['type'] === 'entrada' && $data['quantity'] < 0) {
            return response()->json(['message' => 'Una entrada debe tener una cantidad positiva.'], 422);
        }
        if ($data['type'] === 'salida' && $data['quantity'] > 0) {
            return response()->json(['message' => 'Una salida debe tener una cantidad negativa.'], 422);
        }

        return DB::transaction(function () use ($data, $request) {
            $medication = Medication::lockForUpdate()->findOrFail($data['medication_id']);
            $resultingStock = $medication->stock_quantity + $data['quantity'];

            if ($resultingStock < 0) {
                return response()->json([
                    'message' => "Stock insuficiente: esta salida dejaría el stock en {$resultingStock}.",
                ], 422);
            }

            $medication->stock_quantity = $resultingStock;
            if ($data['type'] === 'entrada' && !empty($data['expiration_date'])) {
                $medication->expiration_date = $data['expiration_date'];
            }
            $medication->save();

            $movement = MedicationStockMovement::create([
                'medication_id' => $medication->id,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'resulting_stock' => $resultingStock,
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Movimiento registrado exitosamente',
                'data' => $movement,
                'medication' => $medication,
            ], 201);
        });
    }
}
