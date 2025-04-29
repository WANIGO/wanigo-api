<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\BankSampah;
use App\Models\KatalogSampah;
use App\Models\SubKategoriSampah;
use App\Models\KategoriSampah;
use App\Models\JamOperasionalBankSampah;
use App\Models\MemberBankSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProfilBankSampahController extends Controller
{
    /**
     * Mendapatkan data detail bank sampah.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getBankSampah(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $bankSampah = BankSampah::with(['jamOperasional'])->find($id);

        if (!$bankSampah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bank sampah tidak ditemukan',
            ], 404);
        }

        // Cek status operasional hari ini
        $statusOperasional = $bankSampah->isBukaHariIni();

        // Hitung jumlah nasabah
        $jumlahNasabah = $bankSampah->hitungJumlahNasabah();

        // Identifikasi kategori sampah yang diterima
        $kategoriSampah = $this->getKategoriSampah($bankSampah->id);

        // Cek status keanggotaan pengguna di bank sampah ini
        $member = MemberBankSampah::where('user_id', $user->id)
            ->where('bank_sampah_id', $id)
            ->first();

        $isRegistered = !is_null($member);

        // Format jam operasional untuk tampilan UI
        $formattedJamOperasional = $bankSampah->jamOperasional->map(function($item) {
            $namaHari = $this->getNamaHari($item->day_of_week);
            return [
                'id' => $item->id,
                'hari' => $namaHari,
                'day_of_week' => $item->day_of_week,
                'jam_buka' => $item->open_time,
                'jam_tutup' => $item->close_time,
                'format_tampilan' => "$namaHari: {$item->open_time} - {$item->close_time}"
            ];
        });

        // Get contact information
        $contactInfo = [
            'phone' => $bankSampah->no_telp_publik,
            'email' => $bankSampah->email
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Detail bank sampah berhasil diambil',
            'data' => [
                'bank_sampah' => [
                    'id' => $bankSampah->id,
                    'nama_bank_sampah' => $bankSampah->nama_bank_sampah,
                    'alamat_bank_sampah' => $bankSampah->alamat_bank_sampah,
                    'deskripsi' => $bankSampah->deskripsi,
                    'latitude' => $bankSampah->latitude,
                    'longitude' => $bankSampah->longitude,
                    'foto_usaha_url' => $bankSampah->foto_usaha_url,
                ],
                'status_operasional' => $statusOperasional ? 'Aktif' : 'Tutup',
                'jumlah_nasabah' => $jumlahNasabah,
                'kategori_sampah' => $kategoriSampah,
                'is_registered' => $isRegistered,
                'kode_nasabah' => $isRegistered ? $member->kode_nasabah : null,
                'tanggal_bergabung' => $isRegistered ? $member->created_at->format('Y-m-d') : null,
                'jam_operasional' => $formattedJamOperasional,
                'contact_info' => $contactInfo,
                'jadwal_setoran' => [
                    'tipe' => 'Setiap bulan', // Contoh, sesuaikan dengan data riil
                    'waktu' => 'Setiap tanggal 26', // Contoh, sesuaikan dengan data riil
                    'jam' => '10:00 WIB' // Contoh, sesuaikan dengan data riil
                ]
            ]
        ]);
    }

    /**
     * Mendapatkan data katalog sampah berdasarkan kategori.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $bankSampahId
     * @return \Illuminate\Http\Response
     */
    public function getKatalogSampah(Request $request, $bankSampahId)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'kode_kategori' => 'nullable|string|in:kering,basah',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bankSampah = BankSampah::find($bankSampahId);

        if (!$bankSampah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bank sampah tidak ditemukan',
            ], 404);
        }

        // Query katalog sampah dengan sistem baru (sub kategori)
        $query = KatalogSampah::with('subKategori.kategoriSampah')
                              ->where('bank_sampah_id', $bankSampahId)
                              ->where('status_aktif', true);

        // Filter berdasarkan kategori jika ada
        if ($request->has('kode_kategori')) {
            $kodeKategori = $request->kode_kategori;

            // Jika katalog belum migrasi ke sistem baru (masih menggunakan kategori_sampah lama)
            if ($this->isUsingOldKategoriSystem($bankSampahId)) {
                $kategoriValue = $kodeKategori === 'kering' ? 0 : 1;
                $query->where('kategori_sampah', $kategoriValue);
            } else {
                $query->byKategoriUtama($kodeKategori);
            }
        }

        $katalogSampah = $query->get();

        // Format untuk tampilan UI
        $formattedKatalog = $katalogSampah->map(function($item) {
            return [
                'id' => $item->id,
                'nama_item_sampah' => $item->nama_item_sampah,
                'kategori_sampah' => $item->kategoriSampahText,
                'sub_kategori' => $item->subKategoriText,
                'harga_per_kg' => $item->harga_per_kg,
                'harga_format' => 'Rp ' . number_format($item->harga_per_kg, 0, ',', '.'),
                'deskripsi_item_sampah' => $item->deskripsi_item_sampah,
                'gambar_item_sampah' => $item->gambar_item_sampah ? url('storage/' . $item->gambar_item_sampah) : null,
                'status_aktif' => $item->status_aktif,
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $item->updated_at->format('Y-m-d H:i:s')
            ];
        });

        // Dapatkan juga daftar sub kategori untuk filter
        $subKategoriList = [];
        if ($request->has('kode_kategori')) {
            $kategori = KategoriSampah::where('kode_kategori', $request->kode_kategori)->first();
            if ($kategori) {
                $subKategoriList = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
                    ->where('kategori_sampah_id', $kategori->id)
                    ->where('status_aktif', true)
                    ->ordered()
                    ->get()
                    ->map(function($item) use ($katalogSampah) {
                        // Hitung jumlah item di setiap sub kategori
                        $jumlahItem = $katalogSampah->where('sub_kategori_sampah_id', $item->id)->count();
                        return [
                            'id' => $item->id,
                            'nama_sub_kategori' => $item->nama_sub_kategori,
                            'kode_sub_kategori' => $item->kode_sub_kategori,
                            'warna' => $item->warna,
                            'jumlah_item' => $jumlahItem
                        ];
                    });
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Katalog sampah berhasil diambil',
            'data' => [
                'katalog_sampah' => $formattedKatalog,
                'sub_kategori' => $subKategoriList
            ]
        ]);
    }

    /**
     * Mendapatkan data sub kategori sampah berdasarkan kategori utama.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $bankSampahId
     * @return \Illuminate\Http\Response
     */
    public function getSubKategoriSampah(Request $request, $bankSampahId)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'kode_kategori' => 'required|string|in:kering,basah',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bankSampah = BankSampah::find($bankSampahId);

        if (!$bankSampah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bank sampah tidak ditemukan',
            ], 404);
        }

        // Dapatkan kategori berdasarkan kode
        $kategori = KategoriSampah::where('kode_kategori', $request->kode_kategori)->first();
        if (!$kategori) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kategori sampah tidak valid'
            ], 422);
        }

        // Query sub kategori
        $subKategoriList = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
            ->where('kategori_sampah_id', $kategori->id)
            ->where('status_aktif', true)
            ->ordered()
            ->get();

        // Jika tidak ada sub kategori, buat sub kategori default "Umum"
        if ($subKategoriList->isEmpty()) {
            $subKategori = SubKategoriSampah::create([
                'bank_sampah_id' => $bankSampahId,
                'kategori_sampah_id' => $kategori->id,
                'nama_sub_kategori' => 'Umum',
                'kode_sub_kategori' => 'umum',
                'deskripsi' => 'Semua jenis sampah ' . $kategori->nama_kategori,
                'warna' => $kategori->kode_kategori === 'kering' ? '#2196F3' : '#4CAF50',
                'urutan' => 1,
                'status_aktif' => true,
            ]);

            $subKategoriList = collect([$subKategori]);
        }

        // Hitung jumlah item di setiap sub kategori
        foreach ($subKategoriList as $subKategori) {
            $subKategori->jumlah_item = KatalogSampah::where('sub_kategori_sampah_id', $subKategori->id)
                ->where('status_aktif', true)
                ->count();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Sub kategori sampah berhasil diambil',
            'data' => [
                'kategori' => [
                    'id' => $kategori->id,
                    'nama_kategori' => $kategori->nama_kategori,
                    'kode_kategori' => $kategori->kode_kategori,
                ],
                'sub_kategori' => $subKategoriList
            ]
        ]);
    }

    /**
     * Mendapatkan jam operasional bank sampah.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $bankSampahId
     * @return \Illuminate\Http\Response
     */
    public function getJamOperasional(Request $request, $bankSampahId)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $bankSampah = BankSampah::find($bankSampahId);

        if (!$bankSampah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bank sampah tidak ditemukan',
            ], 404);
        }

        $jamOperasional = JamOperasionalBankSampah::where('bank_sampah_id', $bankSampahId)
            ->orderBy('day_of_week')
            ->get();

        // Format jam operasional untuk ditampilkan di UI
        $formattedJamOperasional = $jamOperasional->map(function($item) {
            $namaHari = $this->getNamaHari($item->day_of_week);
            return [
                'id' => $item->id,
                'hari' => $namaHari,
                'day_of_week' => $item->day_of_week,
                'jam_buka' => $item->open_time,
                'jam_tutup' => $item->close_time,
                'format_tampilan' => "$namaHari: {$item->open_time} - {$item->close_time}"
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Jam operasional berhasil diambil',
            'data' => $formattedJamOperasional
        ]);
    }

    /**
     * Mendapatkan lokasi bank sampah untuk tampilan peta.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $bankSampahId
     * @return \Illuminate\Http\Response
     */
    public function getLokasiBank(Request $request, $bankSampahId)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $bankSampah = BankSampah::find($bankSampahId);

        if (!$bankSampah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bank sampah tidak ditemukan',
            ], 404);
        }

        // Cek apakah koordinat tersedia
        if (!$bankSampah->latitude || !$bankSampah->longitude) {
            return response()->json([
                'status' => 'error',
                'message' => 'Koordinat lokasi bank sampah tidak tersedia',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Lokasi bank sampah berhasil diambil',
            'data' => [
                'nama_bank_sampah' => $bankSampah->nama_bank_sampah,
                'alamat_bank_sampah' => $bankSampah->alamat_bank_sampah,
                'latitude' => $bankSampah->latitude,
                'longitude' => $bankSampah->longitude
            ]
        ]);
    }

    /**
     * Mendapatkan data kontak bank sampah.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $bankSampahId
     * @return \Illuminate\Http\Response
     */
    public function getKontakBank(Request $request, $bankSampahId)
    {
        $user = $request->user();

        if (!$user->isNasabah()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Akses ditolak. Anda bukan nasabah.',
            ], 403);
        }

        $bankSampah = BankSampah::find($bankSampahId);

        if (!$bankSampah) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bank sampah tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Data kontak bank sampah berhasil diambil',
            'data' => [
                'nama_bank_sampah' => $bankSampah->nama_bank_sampah,
                'no_telp_publik' => $bankSampah->no_telp_publik,
                'email' => $bankSampah->email,
                'website' => $bankSampah->website,
                'social_media' => [
                    'instagram' => $bankSampah->instagram,
                    'facebook' => $bankSampah->facebook,
                    'twitter' => $bankSampah->twitter
                ]
            ]
        ]);
    }

    /**
     * Mendapatkan kategori sampah yang diterima oleh bank sampah.
     *
     * @param  int  $bankSampahId
     * @return string
     */
    private function getKategoriSampah($bankSampahId)
    {
        // Cek kategori menggunakan sistem baru - sub kategori
        $subKategoriKering = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
            ->whereHas('kategoriSampah', function($q) {
                $q->where('kode_kategori', 'kering');
            })
            ->exists();

        $subKategoriBasah = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
            ->whereHas('kategoriSampah', function($q) {
                $q->where('kode_kategori', 'basah');
            })
            ->exists();

        // Jika sub kategori belum ada, gunakan cara lama (kompatibilitas)
        if (!$subKategoriKering && !$subKategoriBasah) {
            $kategoriKering = KatalogSampah::where('bank_sampah_id', $bankSampahId)
                ->where('kategori_sampah', 0)
                ->exists();

            $kategoriBasah = KatalogSampah::where('bank_sampah_id', $bankSampahId)
                ->where('kategori_sampah', 1)
                ->exists();

            $subKategoriKering = $kategoriKering;
            $subKategoriBasah = $kategoriBasah;
        }

        if ($subKategoriKering && $subKategoriBasah) {
            return 'Kering & Basah';
        } elseif ($subKategoriKering) {
            return 'Kering';
        } elseif ($subKategoriBasah) {
            return 'Basah';
        } else {
            return 'Tidak ada data';
        }
    }

    /**
     * Memeriksa apakah bank sampah masih menggunakan sistem kategori lama
     *
     * @param int $bankSampahId
     * @return bool
     */
    private function isUsingOldKategoriSystem($bankSampahId)
    {
        // Periksa apakah ada katalog yang sudah menggunakan sub_kategori_sampah_id
        $hasMigratedItems = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->whereNotNull('sub_kategori_sampah_id')
            ->exists();

        // Jika tidak ada yang migrasi, berarti masih menggunakan sistem lama
        return !$hasMigratedItems;
    }

    /**
     * Helper function untuk mendapatkan nama hari dari day_of_week.
     *
     * @param  int  $dayOfWeek
     * @return string
     */
    private function getNamaHari($dayOfWeek)
    {
        $namaHari = [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ];

        return $namaHari[$dayOfWeek] ?? 'Tidak diketahui';
    }
}