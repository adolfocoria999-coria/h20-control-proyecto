<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $teniaClaveTemporal = $request->user()->debe_cambiar_clave;

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'debe_cambiar_clave' => false,
        ]);

        // Con la contraseña temporal reemplazada, ya puede usar el sistema
        if ($teniaClaveTemporal) {
            return redirect($request->user()->rutaInicio())->with('success', 'Contraseña actualizada ✓ Ya puedes usar el sistema.');
        }

        return back()->with('status', 'password-updated');
    }
}
