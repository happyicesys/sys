<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
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
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|required_without:username|unique:users,email',
            'username' => 'nullable|required_without:email|unique:users,username',
            'password' => 'required',
        ]);

        $user = User::create($request->all());

        return redirect()->route('users');
    }

    public function selfIndex()
    {
        return Inertia::render('User/Self/Form', [
            'user' => UserResource::make(
                auth()->user()
            )
        ]);
    }

    public function selfUpdate(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|required_without:username|unique:users,email,'.$id,
            'username' => 'nullable|required_without:email|unique:users,username,'.$id,
            'password' => 'nullable|confirmed',
        ]);

        if($request->password and $request->password_confirmation) {
            $validated = $request->only('name', 'email', 'username', 'password');
        }else {
            $validated = $request->only('name', 'email', 'username');
        }

        $user = User::findOrFail($id);

        $user->update($validated);

        return redirect()->route('self');
    }

    public function update(Request $request, $userId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|required_without:username|unique:users,email,'.$userId,
            'username' => 'nullable|required_without:email|unique:users,username,'.$userId,
            'password' => 'nullable',
        ]);
        // dd($request->all());
        if($request->password) {
            $validated = $request->only('name', 'email', 'username', 'password');
        }else {
            $validated = $request->only('name', 'email', 'username');
        }

        $user = User::findOrFail($userId);

        $user->update($validated);

        return redirect()->route('users');
    }

    public function delete($userId)
    {
        $user = User::findOrFail($userId);
        $user->delete();

        return redirect()->route('users');
    }
}
