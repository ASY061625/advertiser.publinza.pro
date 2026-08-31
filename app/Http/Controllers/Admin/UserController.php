<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        return inertia('Users/Index', [
            'users' => User::query()
                ->when($request->input('q'), fn ($q, $term) => $q->where('email', 'like', "%{$term}%"))
                ->latest()
                ->paginate(50),
        ]);
    }

    public function show(User $user): Response
    {
        return inertia('Users/Show', ['user' => $user->load('wallet')]);
    }
}
