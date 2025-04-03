<?php

namespace App\Http\Controllers\dashboard;
use App\Models\ContractAttributes;
use Illuminate\Http\Request;

class ContractAttributesController extends Controller
{
 public function store(Request $request) {
        $validated = $request->validate([
            'contract_subtype_id' => 'required|exists:contract_subtypes,id',
            'attributes' => 'required|array|min:1',
            'attributes.*.name' => 'required|string|max:255',
            'attributes.*.type' => 'required|string|in:text,number,date,boolean'
        ]);

        foreach ($validated['attributes'] as $attr) {
            ContractAttributes::create([
                'contract_subtype_id' => $validated['contract_subtype_id'],
                'name' => $attr['name'],
                'type' => $attr['type']
            ]);
        }

        return response()->json(['message' => 'Attributes saved successfully'], 201);
    }

     public function index($contract_subtype_id) {
        $attributes = ContractAttributes::where('contract_subtype_id', $contract_subtype_id)->get();
        return response()->json($attributes);
    }

    public function delete($id) {
        $attribute = ContractAttributes::findOrFail($id);
        $attribute->delete();

        return response()->json(['message' => 'Attribute deleted successfully'], 200);
    }

    public function rename(Request $request, $id) {
        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $attribute = ContractAttributes::findOrFail($id);
        $attribute->update(['name' => $validated['name']]);

        return response()->json(['message' => 'Attribute renamed successfully'], 200);
    }

}
