<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function showForm()
    {
        return inertia('auth/ChangePassword');
    }

    public function update(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ], [
            'current_password.current_password' => 'The provided current password was incorrect.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The new password and confirmation password do not match.',

        ], [
            'current_password' => 'Current Password',
            'password' => 'New Password',
            
        ]);

        // $user = $request->user();
        $user = auth()->user();

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Password changed successfully.');
    }
}