<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('categories as c')
            ->leftJoin('categories as p', 'c.parent_id', '=', 'p.id')
            ->select('c.*', 'p.name as parent_name')
            ->orderBy('c.id')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        $parents = DB::table('categories')->whereNull('parent_id')->get();
        return view('admin.categories.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        DB::table('categories')->insert([
            'name'      => $request->name,
            'slug'      => Str::slug($request->name) . '-' . time(),
            'parent_id' => $request->parent_id ?: null,
            'created_at' => now(),
        ]);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created!');
    }

    public function edit($id)
    {
        $category = DB::table('categories')->where('id', $id)->first();
        $parents  = DB::table('categories')->whereNull('parent_id')->where('id', '!=', $id)->get();
        return view('admin.categories.edit', compact('category', 'parents'));
    }

    public function update(Request $request, $id)
    {
        $request->validate(['name' => 'required|string|max:255']);

        DB::table('categories')->where('id', $id)->update([
            'name'      => $request->name,
            'parent_id' => $request->parent_id ?: null,
        ]);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated!');
    }

    public function destroy($id)
    {
        DB::table('categories')->where('id', $id)->delete();
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted!');
    }
}
