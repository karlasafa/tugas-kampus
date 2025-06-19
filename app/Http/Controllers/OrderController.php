<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Produk;
use App\Models\Order;
use App\Models\OrderItem;
use Midtrans\Snap;
use Midtrans\Config;

class OrderController extends Controller
{
    public function addToCart($id)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $produk = Produk::findOrFail($id);

        $order = Order::firstOrCreate(
            ['customer_id' => $customer->id, 'status' => 'pending'],
            ['total_harga' => 0,
            'user_id' => Auth::id() 
            ]
            
        );

        $orderItem = OrderItem::firstOrCreate(
            ['order_id' => $order->id, 'produk_id' => $produk->id],
            ['quantity' => 1, 'harga' => $produk->harga]
        );

        if (!$orderItem->wasRecentlyCreated) {
            $orderItem->quantity++;
            $orderItem->save();
        }

        $order->total_harga += $produk->harga;
        $order->save();

        return redirect()->route('order.cart')->with('success', 'Produk berhasil ditambahkan ke keranjang');
    }

    public function viewCart()
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        if (!$order) {
            return redirect()->route('order.cart')->with('error', 'Order tidak ditemukan.');
        }

        $order->load('orderItems.produk');

        return view('v_order.cart', compact('order'));
    }

    public function updateCart(Request $request, $id)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        if ($order) {
            $orderItem = $order->orderItems()->where('id', $id)->first();

            if ($orderItem) {
                $quantity = $request->input('quantity');

                if ($quantity > $orderItem->produk->stok) {
                    return redirect()->route('order.cart')->with('error', 'Jumlah produk melebihi stok yang tersedia');
                }

                $order->total_harga -= $orderItem->harga * $orderItem->quantity;
                $orderItem->quantity = $quantity;
                $orderItem->save();
                $order->total_harga += $orderItem->harga * $orderItem->quantity;
                $order->save();
            }
        }

        return redirect()->route('order.cart')->with('success', 'Jumlah produk berhasil diperbarui');
    }

    public function removeFromCart(Request $request, $id)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        if ($order) {
            $orderItem = OrderItem::where('order_id', $order->id)->where('produk_id', $id)->first();

            if ($orderItem) {
                $order->total_harga -= $orderItem->harga * $orderItem->quantity;
                $orderItem->delete();

                if ($order->total_harga <= 0) {
                    $order->delete();
                } else {
                    $order->save();
                }
            }
        }

        return redirect()->route('order.cart')->with('success', 'Produk berhasil dihapus dari keranjang');
    }

    public function selectShipping(Request $request)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        if (!$order) {
            return redirect()->route('order.cart')->with('error', 'Keranjang belanja kosong.');
        }

        $order->load('orderItems.produk');

        return view('v_order.select_shipping', compact('order'));
    }

    public function updateOngkir(Request $request)
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $order = Order::where('customer_id', $customer->id)->where('status', 'pending')->first();

        $origin = $request->input('city_origin');
        $originName = $request->input('city_origin_name');

        if ($order) {
            $order->kurir = $request->input('kurir');
            $order->layanan_ongkir = $request->input('layanan_ongkir');
            $order->biaya_ongkir = $request->input('biaya_ongkir');
            $order->estimasi_ongkir = $request->input('estimasi_ongkir');
            $order->total_berat = $request->input('total_berat');
            $order->alamat = $request->input('alamat') . ', <br>' . $request->input('city_name') . ', <br>' . $request->input('province_name');
            $order->pos = $request->input('pos');
            $order->save();

            return redirect()->route('order.selectpayment')
                ->with('origin', $origin)
                ->with('originName', $originName);
        }

        return back()->with('error', 'Gagal menyimpan data ongkir');
    }

    public function selectPayment()
    {
        $customer = Auth::user();
        $order = Order::where('customer_id', $customer->customer->id)->where('status', 'pending')->first();

        $origin = session('origin');
        $originName = session('originName');

        if (!$order) {
            return redirect()->route('order.cart')->with('error', 'Keranjang belanja kosong.');
        }

        $order->load('orderItems.produk');

        $totalHarga = 0;
        foreach ($order->orderItems as $item) {
            $totalHarga += $item->harga * $item->quantity;
        }

        $grossAmount = $totalHarga + $order->biaya_ongkir;

        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $orderId = $order->id . '-' . time();

        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => (int) $grossAmount,
            ],
            'customer_details' => [
                'first_name' => $customer->nama,
                'email' => $customer->email,
                'phone' => $customer->hp,
            ],
        ];

        $snapToken = Snap::getSnapToken($params);

        return view('v_order.select_payment', compact('order', 'origin', 'originName', 'snapToken'));
    }

    public function callback(Request $request)
    {
        $serverKey = config('midtrans.server_key');
        $hashed = hash("sha512", $request->order_id . $request->status_code . $request->gross_amount . $serverKey);

        if ($hashed == $request->signature_key) {
            $order = Order::find($request->order_id);

            if ($order) {
                $order->update(['status' => 'Paid']);
            }
        }
    }

    public function complete()
    {
        $customer = Auth::user();
        $order = Order::where('customer_id', $customer->customer->id)
            ->where('status', 'pending')
            ->first();

        if ($order) {
            $order->status = 'Paid';
            $order->save();
        }

        return redirect()->route('order.history')->with('success', 'Checkout berhasil');
    }

    public function orderHistory()
    {
        $customer = Customer::where('user_id', Auth::id())->first();
        $statuses = ['Paid', 'Kirim', 'Selesai'];
        $orders = Order::where('customer_id', $customer->id)
            ->whereIn('status', $statuses)
            ->orderBy('id', 'desc')
            ->get();

        return view('v_order.history', compact('orders'));
    }

    public function invoiceFrontend($id)
    {
        $order = Order::findOrFail($id);

        return view('v_order.invoice', [
            'judul' => 'Data Transaksi',
            'subJudul' => 'Pesanan Proses',
            'order' => $order,
        ]);
    }

    public function statusProses()
{
    $order = Order::whereIn('status', ['Paid', 'Kirim'])->orderBy('id', 'desc')->get();

    return view('backend.v_pesanan.proses', [
        'judul' => 'Pesanan',
        'subJudul' => 'Pesanan Proses',
        'index' => $order
    ]);
}

public function statusSelesai()
{
    $order = Order::where('status', 'Selesai')->orderBy('id', 'desc')->get();

    return view('backend.v_pesanan.selesai', [
        'judul' => 'Data Transaksi',
        'subJudul' => 'Pesanan Selesai',
        'index' => $order
    ]);
}

public function statusDetail($id)
{
    $order = Order::findOrFail($id);

    return view('backend.v_pesanan.detail', [
        'judul' => 'Data Transaksi',
        'subJudul' => 'Pesanan Proses',
        'order' => $order,
    ]);
}

public function statusUpdate(Request $request, string $id)
{
    $order = Order::findOrFail($id);
    $rules = [
        'alamat' => 'required',
    ];

    if ($request->status != $order->status) {
        $rules['status'] = 'required';
    }

    if ($request->noresi != $order->noresi) {
        $rules['noresi'] = 'required';
    }

    if ($request->pos != $order->pos) {
        $rules['pos'] = 'required';
    }

    $validatedData = $request->validate($rules);

    Order::where('id', $id)->update($validatedData);

    return redirect()->route('pesanan.proses')->with('success', 'Data berhasil diperbaharui');
}

public function formOrderProses()
{
    return view('backend.v_pesanan.formproses', [
        'judul' => 'Laporan',
        'subJudul' => 'Laporan Pesanan Proses',
    ]);
}

public function cetakOrderProses(Request $request)
{
    $request->validate([
        'tanggal_awal' => 'required|date',
        'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
    ], [
        'tanggal_awal.required' => 'Tanggal Awal harus diisi.',
        'tanggal_akhir.required' => 'Tanggal Akhir harus diisi.',
        'tanggal_akhir.after_or_equal' => 'Tanggal Akhir harus lebih besar atau sama dengan Tanggal Awal.',
    ]);

    $tanggalAwal = $request->input('tanggal_awal');
    $tanggalAkhir = $request->input('tanggal_akhir');

    $order = Order::whereIn('status', ['Paid', 'Kirim'])
        ->whereBetween('created_at', [$tanggalAwal, $tanggalAkhir])
        ->orderBy('id', 'desc')
        ->get();

    return view('backend.v_pesanan.cetakproses', [
        'judul' => 'Laporan',
        'subJudul' => 'Laporan Pesanan Proses',
        'tanggalAwal' => $tanggalAwal,
        'tanggalAkhir' => $tanggalAkhir,
        'cetak' => $order,
    ]);
}

public function formOrderSelesai()
{
    return view('backend.v_pesanan.formselesai', [
        'judul' => 'Laporan',
        'subJudul' => 'Laporan Pesanan Selesai',
    ]);
}

public function cetakOrderSelesai(Request $request)
{
    $request->validate([
        'tanggal_awal' => 'required|date',
        'tanggal_akhir' => 'required|date|after_or_equal:tanggal_awal',
    ], [
        'tanggal_awal.required' => 'Tanggal Awal harus diisi.',
        'tanggal_akhir.required' => 'Tanggal Akhir harus diisi.',
        'tanggal_akhir.after_or_equal' => 'Tanggal Akhir harus lebih besar atau sama dengan Tanggal Awal.',
    ]);

    $tanggalAwal = $request->input('tanggal_awal');
    $tanggalAkhir = $request->input('tanggal_akhir');

    $order = Order::where('status', 'Selesai')
        ->whereBetween('created_at', [$tanggalAwal, $tanggalAkhir])
        ->orderBy('id', 'desc')
        ->get();

    $totalPendapatan = $order->sum(function ($row) {
        return $row->total_harga + $row->biaya_ongkir;
    });

    return view('backend.v_pesanan.cetakselesai', [
        'judul' => 'Laporan',
        'subJudul' => 'Laporan Pesanan Selesai',
        'tanggalAwal' => $tanggalAwal,
        'tanggalAkhir' => $tanggalAkhir,
        'cetak' => $order,
        'totalPendapatan' => $totalPendapatan
    ]);
}

public function invoiceBackend($id)
{
    $order = Order::findOrFail($id);

    return view('backend.v_pesanan.invoice', [
        'judul' => 'Data Transaksi',
        'subJudul' => 'Pesanan Proses',
        'order' => $order,
    ]);
}

}
