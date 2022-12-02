<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $numberPerPage = $request->numberPerPage ? $request->numberPerPage : 100;
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return Inertia::render('User/Index', [
            'users' => UserResource::collection(
                User::query()
                    ->when($request->name, function($query, $search) {
                        $query->where('name', 'LIKE', "%{$search}%");
                    })
                    ->when($request->email, function($query, $search) {
                        $query->where('email', 'LIKE', "%{$search}%");
                    })
                    ->when($sortKey, function($query, $search) use ($sortBy) {
                        $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
                    })
                    ->paginate($numberPerPage === 'All' ? 10000 : $numberPerPage)
                    ->withQueryString()
            )
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
        ]);

        $user = User::create($request->all());

        return redirect()->route('users');
    }

    public function selfIndex()
    {
        return Inertia::render('User/Self/Index', [
            'user' => UserResource::make(
                auth()->user()
            )
        ]);
    }

    public function update(Request $request, $userId)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
        ]);

        $user = User::findOrFail($userId);

        $user->update($request->all());

        return redirect()->route('users');
    }

    public function delete($userId)
    {
        $user = User::findOrFail($userId);
        $user->delete();

        return redirect()->route('users');
    }
}
