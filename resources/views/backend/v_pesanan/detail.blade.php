@extends('backend.v_layouts.app') 

@section('content')
<!-- Template -->
<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12 col-xl-12"> 
    <div class="card mb-3"> 
        <div class="card-header"> 
            <h3>{{ $subJudul }}</h3> 
        </div> 

        <div class="card-body"> 
            <div class="invoice-title text-center mb-3"> 
                <h2>Detail Pesanan #{{ $order->id }}</h2> 
                <strong>Tanggal:</strong> {{ $order->created_at->format('d M Y H:i') }} 
            </div> 

            <form action="{{ route('pesanan.update', $order->id) }}" method="POST"> 
                @method('put') 
                @csrf 

                <hr> 
                <div class="row"> 
                    <div class="col-md-6"> 
                        <h5>Pelanggan</h5> 
                        <address>
                            Nama: {{ $order->customer->nama }}<br>
                            Email: {{ $order->customer->email }}<br>
                            Hp: {{ $order->customer->hp }}<br>
                        </address>
                    </div>

                    <div class="col-md-6 text-right"> 
                        <h5>Ongkos Kirim</h5> 
                        <address>
                            Kurir: {{ $order->kurir }}<br>
                            Layanan: {{ $order->layanan_ongkir }}<br>
                            Estimasi: {{ $order->estimasi_ongkir }} Hari<br>
                            Berat: {{ $order->total_berat }} Gram<br>
                        </address>
                    </div> 
                </div>

                <div class="row mt-4"> 
                    <div class="col-md-12"> 
                        <h5>Produk</h5> 
                        <table class="table table-bordered table-hover display"> 
                            <thead> 
                                <tr> 
                                    <th colspan="2">Produk</th> 
                                    <th class="text-center">Harga</th> 
                                    <th class="text-center">Quantity</th> 
                                    <th class="text-center">Total</th> 
                                </tr> 
                            </thead> 
                            <tbody> 
                                @php 
                                    $totalHarga = 0; 
                                    $totalBerat = 0; 
                                @endphp 
                                @foreach ($order->orderItems as $item)
                                    @php
                                        $totalHarga += $item->harga * $item->quantity;
                                        $totalBerat += $item->produk->berat * $item->quantity;
                                    @endphp
                                    <tr>
                                        <td align="center">
                                            <img src="{{ asset('storage/img-produk/thumb_sm_' . $item->produk->foto) }}" alt="" width="60%">
                                        </td>
                                        <td>
                                            {{ $item->produk->nama_produk }} #{{ $item->produk->kategori->nama_kategori }}
                                            <ul class="mb-0">
                                                <li>Berat: {{ $item->produk->berat }}</li>
                                                <li>Stok: {{ $item->produk->stok }}</li>
                                            </ul>
                                        </td>
                                        <td class="text-center">Rp. {{ number_format($item->harga, 0, ',', '.') }}</td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-center">Rp. {{ number_format($item->harga * $item->quantity, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach 
                            </tbody> 
                            <tfoot> 
                                <tr> 
                                    <th colspan="3"></th> 
                                    <td>Subtotal</td> 
                                    <td class="text-right">Rp. {{ number_format($totalHarga, 0, ',', '.') }}</td> 
                                </tr> 
                                <tr> 
                                    <th colspan="3"></th> 
                                    <td>Ongkos Kirim</td> 
                                    <td class="text-right">Rp. {{ number_format($order->biaya_ongkir, 0, ',', '.') }}</td> 
                                </tr> 
                                <tr> 
                                    <th colspan="3"></th> 
                                    <th>TOTAL BAYAR</th> 
                                    <th class="text-right">Rp. {{ number_format($totalHarga + $order->biaya_ongkir, 0, ',', '.') }}</th> 
                                </tr> 
                            </tfoot> 
                        </table> 
                    </div> 
                </div>

                <div class="row"> 
                    <div class="col-md-6"> 
                        <div class="form-group"> 
                            <label>No. Resi</label> 
                            <input type="text" name="noresi" value="{{ old('noresi', $order->noresi) }}" class="form-control @error('noresi') is-invalid @enderror" placeholder="Masukkan Nomor Resi"> 
                            @error('noresi') 
                                <span class="invalid-feedback alert-danger" role="alert">{{ $message }}</span> 
                            @enderror 
                        </div> 

                        <div class="form-group"> 
                            <label>Status</label> 
                            <select name="status" class="form-control @error('status') is-invalid @enderror"> 
                                <option value="" {{ old('status', $order->status) == '' ? 'selected' : '' }}>- Pilih Status Pesanan -</option> 
                                <option value="Paid" {{ old('status', $order->status) == 'Paid' ? 'selected' : '' }}>Proses</option> 
                                <option value="Kirim" {{ old('status', $order->status) == 'Kirim' ? 'selected' : '' }}>Kirim</option> 
                                <option value="Selesai" {{ old('status', $order->status) == 'Selesai' ? 'selected' : '' }}>Selesai</option> 
                            </select> 
                            @error('status') 
                                <span class="invalid-feedback alert-danger" role="alert">{{ $message }}</span> 
                            @enderror 
                        </div> 
                    </div> 

                    <div class="col-md-6"> 
                        <div class="form-group"> 
                            <label>Alamat</label> 
                            <textarea name="alamat" class="form-control @error('alamat') is-invalid @enderror" id="ckeditor">{{ old('alamat', $order->alamat) }}</textarea> 
                            @error('alamat') 
                                <span class="invalid-feedback alert-danger" role="alert">{{ $message }}</span> 
                            @enderror 
                        </div> 

                        <div class="form-group"> 
                            <label>Kode Pos</label> 
                            <input type="text" name="pos" value="{{ old('pos', $order->pos) }}" class="form-control @error('pos') is-invalid @enderror" placeholder="Masukkan Kode Pos"> 
                            @error('pos') 
                                <span class="invalid-feedback alert-danger" role="alert">{{ $message }}</span> 
                            @enderror 
                        </div> 
                    </div> 
                </div>

                <br> 
                <button type="submit" class="btn btn-primary">Simpan</button> 
                <a href="{{ route('pesanan.proses') }}" class="btn btn-secondary">Kembali</a>
            </form>
        </div>
    </div>
</div>
<!-- End Template -->
@endsection
