<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Material;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class MaterialController extends Controller
{
    protected function getUser()
    {
        try {
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();
            $userId = $payload->get('sub');
            $user = \App\Models\Usuario::find($userId);
            return $user;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function index()
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $materiales = Material::activos()->get();
        return response()->json(['success' => true, 'data' => $materiales]);
    }

    public function store(Request $request)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $validated = $request->validate([
            'codigo' => 'required|unique:materiales,codigo',
            'nombre' => 'required|string',
            'categoria' => 'required|string',
            'unidad_medida' => 'required|string',
            'precio_base' => 'required|numeric',
            'cantidad_disponible' => 'required|numeric',
            'nivel_stock_minimo' => 'required|numeric',
            'calificacion_sostenibilidad' => 'required|string',
        ]);

        $material = Material::create($validated);
        return response()->json(['success' => true, 'data' => $material], 201);
    }

    public function show(Material $material)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        return response()->json(['success' => true, 'data' => $material]);
    }

    public function update(Request $request, Material $material)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $validated = $request->validate([
            'codigo' => 'sometimes|unique:materiales,codigo,' . $material->id,
            'nombre' => 'sometimes|string',
            'categoria' => 'sometimes|string',
            'unidad_medida' => 'sometimes|string',
            'precio_base' => 'sometimes|numeric',
            'cantidad_disponible' => 'sometimes|numeric',
            'nivel_stock_minimo' => 'sometimes|numeric',
            'calificacion_sostenibilidad' => 'sometimes|string',
        ]);

        $material->update($validated);
        return response()->json(['success' => true, 'data' => $material]);
    }

    public function destroy(Material $material)
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $material->update(['activo' => false]);
        return response()->json(['success' => true, 'message' => 'Material desactivado']);
    }

    public function stockBajo()
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $materiales = Material::stockBajo()->get();
        return response()->json(['success' => true, 'data' => $materiales]);
    }

    public function categorias()
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $categorias = Material::activos()->distinct()->pluck('categoria')->sort()->values();
        return response()->json(['success' => true, 'data' => $categorias]);
    }

    public function valorInventario()
    {
        $user = $this->getUser();
        if (!$user) return response()->json(['error' => 'No autorizado'], 401);

        $valor = Material::activos()->get()->sum(fn($m) => $m->cantidad_disponible * $m->precio_base);
        return response()->json(['success' => true, 'valor_total' => round($valor, 2)]);
    }
}
