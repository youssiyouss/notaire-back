<?php

namespace App\Http\Controllers\dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Log;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use Illuminate\Support\Facades\Auth;


class ContractTemplateController extends Controller
{


     public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:contract_types,id',
            'subcategory_name' => 'required|string|max:255',
            'attributes' => 'required',
            'transformations' => 'required',
            'content' => 'required|string',
        ]);

        // Store the new contract template
        $template = ContractTemplate::create([
            'contract_type_id' => $request->category_id, //$request->category_name,
            'contract_subtype' => $request->subcategory_name,
            'attributes' => json_encode((array) $request->attributes),
            'pronoun_transformations' => json_encode($request->transformations),
            'content' => $request->content,
            'created_by' =>  auth()->id()
        ]);

        return response()->json(['message' => 'Contract Template created successfully', 'data' => $template], 201);
    }
}
