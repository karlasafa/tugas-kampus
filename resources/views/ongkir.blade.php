<!DOCTYPE html>
<html>

<head>
    <title>Cek Ongkir</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        select, input, button {
            display: block;
            margin-bottom: 10px;
            padding: 6px;
            width: 300px;
        }
        #result div {
            margin-top: 8px;
            padding: 6px;
            border: 1px solid #ccc;
        }
    </style>
</head>

<body>
    <h2>Cek Ongkir</h2>

    <form id="ongkirForm">
        <select name="province" id="province">
            <option value="">Pilih Provinsi</option>
        </select>

        <select name="city" id="city">
            <option value="">Pilih Kota</option>
        </select>

        <input type="number" name="weight" id="weight" placeholder="Berat (gram)" required>

        <select name="courier" id="courier">
            <option value="">Pilih Kurir</option>
            <option value="jne">JNE</option>
            <option value="tiki">TIKI</option>
            <option value="pos">POS Indonesia</option>
        </select>

        <button type="submit">Cek Ongkir</button>
    </form>

    <div id="result"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Ambil daftar provinsi saat halaman dimuat
            fetch('/provinces')
                .then(response => response.json())
                .then(data => {
                    if (data.rajaongkir.status.code === 200) {
                        const provinces = data.rajaongkir.results;
                        const provinceSelect = document.getElementById('province');
                        provinces.forEach(province => {
                            const option = document.createElement('option');
                            option.value = province.province_id;
                            option.textContent = province.province;
                            provinceSelect.appendChild(option);
                        });
                    } else {
                        console.error('Gagal mengambil data provinsi:', data.rajaongkir.status.description);
                    }
                })
                .catch(error => {
                    console.error('Error fetching provinces:', error);
                });

            // Saat provinsi dipilih, ambil kota
            document.getElementById('province').addEventListener('change', function () {
                const provinceId = this.value;
                const citySelect = document.getElementById('city');
                citySelect.innerHTML = '<option value="">Loading...</option>';

                fetch(`/cities?province_id=${provinceId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.rajaongkir.status.code === 200) {
                            citySelect.innerHTML = '<option value="">Pilih Kota</option>';
                            const cities = data.rajaongkir.results;
                            cities.forEach(city => {
                                const option = document.createElement('option');
                                option.value = city.city_id;
                                option.textContent = city.city_name;
                                citySelect.appendChild(option);
                            });
                        } else {
                            console.error('Gagal mengambil data kota:', data.rajaongkir.status.description);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching cities:', error);
                    });
            });

            // Saat form dikirim, kirim data ke endpoint /cost
            document.getElementById('ongkirForm').addEventListener('submit', function (event) {
                event.preventDefault();

                const origin = 501; // ID kota asal
                const destination = document.getElementById('city').value;
                const weight = document.getElementById('weight').value;
                const courier = document.getElementById('courier').value;

                fetch('/cost', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        origin: origin,
                        destination: destination,
                        weight: weight,
                        courier: courier
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        const resultDiv = document.getElementById('result');
                        resultDiv.innerHTML = '';

                        if (data.rajaongkir.status.code === 200) {
                            const services = data.rajaongkir.results[0].costs;
                            services.forEach(service => {
                                const div = document.createElement('div');
                                div.textContent = `${service.service} : ${service.cost[0].value} Rupiah (${service.cost[0].etd} hari)`;
                                resultDiv.appendChild(div);
                            });
                        } else {
                            resultDiv.textContent = 'Gagal mengambil data ongkir.';
                            console.error('Gagal mengambil ongkir:', data.rajaongkir.status.description);
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching cost:', error);
                    });
            });
        });
    </script>
</body>

</html>
