<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use App\Helpers\ImageHelper;

class CustomerController extends Controller
{
    // Tampilkan daftar customer
    public function index()
    {
        $customer = Customer::orderBy('id', 'desc')->get();
        return view('backend.v_customer.index', [
            'judul' => 'Halaman Customer',
            'index' => $customer
        ]);
    }

    // Tampilkan detail customer
    public function show(string $id)
    {
        $customer = Customer::findOrFail($id);
        return view('backend.v_customer.detail', [
            'judul' => 'Detail Customer',
            'edit' => $customer
        ]);
    }

    // Tampilkan form edit
    public function edit(string $id)
    {
        $customer = Customer::findOrFail($id);
        return view('backend.v_customer.edit', [
            'judul' => 'Edit Customer',
            'edit' => $customer
        ]);
    }

    // Update data customer & user
    public function update(Request $request, string $id)
    {
        $request->validate([
            'alamat' => 'required|max:255',
            'pos' => 'required|max:10',
        ]);

        $customer = Customer::where('user_id', $id)->first();
        if ($customer) {
            $customer->update($request->only(['alamat', 'pos']));
        }

        $validatedUser = $request->validate([
            'nama' => 'required|max:255',
            'hp' => 'required|min:10|max:20',
            'foto' => 'image|mimes:jpeg,jpg,png,gif|file|max:1024',
        ], [
            'foto.image' => 'Format gambar gunakan file dengan ekstensi jpeg, jpg, png, atau gif.',
            'foto.max' => 'Ukuran file gambar Maksimal adalah 1 mb.'
        ]);

        $user = $customer->user;
        if ($user) {
            if ($request->file('foto')) {
                // Hapus gambar lama
                if ($user->foto) {
                    $oldImagePath = public_path('storage/img-customer/') . $user->foto;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $file = $request->file('foto');
                $extension = $file->getClientOriginalExtension();
                $fileName = date('YmdHis') . '_' . uniqid() . '.' . $extension;
                $directory = 'storage/img-customer/';
                ImageHelper::uploadAndResize($file, $directory, $fileName);
                $validatedUser['foto'] = $fileName;
            }

            $user->update($validatedUser);
        }

        return redirect()->route('backend.customer.index')->with('success', 'Data berhasil diperbaharui');
    }

    // Hapus customer
    public function destroy(string $id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();
        return redirect()->route('backend.customer.index')->with('success', 'Data berhasil dihapus');
    }

    // Redirect ke Google
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    // Callback dari Google
    public function callback()
    {
        try {
            $socialUser = Socialite::driver('google')->user();
            $registeredUser = User::where('email', $socialUser->email)->first();

            if (!$registeredUser) {
                $user = User::create([
                    'nama' => $socialUser->name,
                    'email' => $socialUser->email,
                    'role' => '2',
                    'status' => 1,
                    'password' => Hash::make('default_password'),
                ]);

                Customer::create([
                    'user_id' => $user->id,
                    'google_id' => $socialUser->id,
                    'google_token' => $socialUser->token
                ]);

                Auth::login($user);
            } else {
                Auth::login($registeredUser);
            }

            return redirect()->intended('beranda');
        } catch (\Exception $e) {
            return redirect('/')->with('error', 'Terjadi kesalahan saat login dengan Google.');
        }
    }

    // Logout pengguna
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Anda telah berhasil logout.');
    }

    // Halaman edit akun customer
    public function akun($id)
    {
        $loggedInCustomerId = Auth::user()->id;

        if ($id != $loggedInCustomerId) {
            return redirect()->route('customer.akun', ['id' => $loggedInCustomerId])
                             ->with('msgError', 'Anda tidak berhak mengakses akun ini.');
        }

        $customer = Customer::where('user_id', $id)->firstOrFail();

        return view('v_customer.edit', [
            'judul' => 'Customer',
            'subJudul' => 'Akun Customer',
            'edit' => $customer
        ]);
    }

    // Update akun customer sendiri
    public function updateAkun(Request $request, $id)
    {
        $customer = Customer::where('user_id', $id)->firstOrFail();

        $rules = [
            'nama' => 'required|max:255',
            'hp' => 'required|min:10|max:13',
            'foto' => 'image|mimes:jpeg,jpg,png,gif|file|max:1024',
        ];

        $messages = [
            'foto.image' => 'Format gambar gunakan file dengan ekstensi jpeg, jpg, png, atau gif.',
            'foto.max' => 'Ukuran file gambar Maksimal adalah 1024 KB.'
        ];

        if ($request->email != $customer->user->email) {
            $rules['email'] = 'required|max:255|email|unique:customer';
        }
        if ($request->alamat != $customer->alamat) {
            $rules['alamat'] = 'required';
        }
        if ($request->pos != $customer->pos) {
            $rules['pos'] = 'required';
        }

        $validatedData = $request->validate($rules, $messages);

        if ($request->file('foto')) {
            if ($customer->user->foto) {
                $oldImagePath = public_path('storage/img-customer/') . $customer->user->foto;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            $file = $request->file('foto');
            $extension = $file->getClientOriginalExtension();
            $fileName = date('YmdHis') . '_' . uniqid() . '.' . $extension;
            $directory = 'storage/img-customer/';
            ImageHelper::uploadAndResize($file, $directory, $fileName, 385, 400);
            $validatedData['foto'] = $fileName;
        }

        $customer->user->update($validatedData);
        $customer->update([
            'alamat' => $request->input('alamat'),
            'pos' => $request->input('pos'),
        ]);

        return redirect()->route('customer.akun', $id)->with('success', 'Data berhasil diperbarui');
    }
}
