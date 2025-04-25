<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\BankSampah;
use App\Models\KatalogSampah;
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
            'kategori_sampah' => 'nullable|string|in:kering,basah',
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

        $query = KatalogSampah::where('bank_sampah_id', $bankSampahId)
                              ->where('status', 'aktif'); // Hanya ambil katalog aktif

        // Filter berdasarkan kategori jika ada
        if ($request->has('kategori_sampah')) {
            $kategori = $request->kategori_sampah === 'kering' ? 0 : 1;
            $query->where('kategori_sampah', $kategori);
        }

        $katalogSampah = $query->get();

        // Format untuk tampilan UI
        $formattedKatalog = $katalogSampah->map(function($item) {
            return [
                'id' => $item->id,
                'nama_sampah' => $item->nama_sampah,
                'kategori_sampah' => $item->kategori_sampah == 0 ? 'Kering' : 'Basah',
                'harga_per_kg' => $item->harga_per_kg,
                'keterangan' => $item->keterangan,
                'status' => $item->status,
                'created_at' => $item->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $item->updated_at->format('Y-m-d H:i:s')
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Katalog sampah berhasil diambil',
            'data' => $formattedKatalog
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
        $kategoriKering = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('kategori_sampah', 0)
            ->exists();

        $kategoriBasah = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('kategori_sampah', 1)
            ->exists();

        if ($kategoriKering && $kategoriBasah) {
            return 'Kering & Basah';
        } elseif ($kategoriKering) {
            return 'Kering';
        } elseif ($kategoriBasah) {
            return 'Basah';
        } else {
            return 'Tidak ada data';
        }
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