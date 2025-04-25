<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\KatalogSampah;
use App\Models\BankSampah;
use App\Models\MemberBankSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class KatalogSampahController extends Controller
{
    /**
     * Mendapatkan daftar katalog sampah berdasarkan bank sampah dan kategori.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getByBankSampah(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kategori_sampah' => 'sometimes|integer|in:0,1', // 0: Kering, 1: Basah
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Dapatkan informasi bank sampah
        $bankSampah = BankSampah::find($bankSampahId);
        if (!$bankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Bank sampah tidak ditemukan'
            ], 404);
        }

        // Query katalog sampah
        $query = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true);

        // Filter berdasarkan kategori jika ada
        if ($request->has('kategori_sampah')) {
            $query->where('kategori_sampah', $request->kategori_sampah);
        }

        $katalogSampah = $query->get();

        // Hitung jumlah item per kategori
        $jumlahKering = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->where('kategori_sampah', 0)
            ->count();

        $jumlahBasah = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->where('kategori_sampah', 1)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'bank_sampah' => [
                    'id' => $bankSampah->id,
                    'nama' => $bankSampah->nama_bank_sampah,
                    'alamat' => $bankSampah->alamat_bank_sampah,
                    'tanggal_setoran' => $bankSampah->tanggal_setoran,
                    'status_operasional' => $bankSampah->status_operasional ? 'Aktif' : 'Tutup'
                ],
                'katalog_sampah' => $katalogSampah,
                'info_kategori' => [
                    'jumlah_kering' => $jumlahKering,
                    'jumlah_basah' => $jumlahBasah
                ]
            ]
        ]);
    }

    /**
     * Mendapatkan detail item sampah.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $userId = Auth::id();
        $katalogSampah = KatalogSampah::find($id);

        if (!$katalogSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Item sampah tidak ditemukan'
            ], 404);
        }

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $katalogSampah->bank_sampah_id)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Tambahkan format harga
        $katalogSampah->harga_format = 'Rp ' . number_format($katalogSampah->harga_per_kg, 0, ',', '.');

        return response()->json([
            'success' => true,
            'data' => $katalogSampah
        ]);
    }

    /**
     * Mencari item sampah berdasarkan nama.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'keyword' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;
        $keyword = $request->keyword;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Cari item sampah berdasarkan keyword
        $katalogSampah = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true)
            ->where(function($query) use ($keyword) {
                $query->where('nama_item_sampah', 'like', "%{$keyword}%")
                      ->orWhere('deskripsi_item_sampah', 'like', "%{$keyword}%");
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $katalogSampah
        ]);
    }

    /**
     * Mendapatkan item sampah untuk memilih item setoran.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getForSetoran(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kategori_sampah' => 'sometimes|integer|in:0,1', // 0: Kering, 1: Basah
            'selected_items' => 'sometimes|array', // ID item yang sudah dipilih
            'setoran_id' => 'sometimes|exists:setoran_sampah,id' // Untuk edit setoran
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;

        // Cek apakah nasabah terdaftar di bank sampah ini
        $memberBankSampah = MemberBankSampah::where('user_id', $userId)
            ->where('bank_sampah_id', $bankSampahId)
            ->first();

        if (!$memberBankSampah) {
            return response()->json([
                'success' => false,
                'message' => 'Anda belum terdaftar sebagai nasabah bank sampah ini'
            ], 422);
        }

        // Query katalog sampah
        $query = KatalogSampah::where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true);

        // Filter berdasarkan kategori jika ada
        if ($request->has('kategori_sampah')) {
            $query->where('kategori_sampah', $request->kategori_sampah);
        }

        $katalogSampah = $query->get();

        // Tandai item yang sudah dipilih
        if ($request->has('selected_items') && is_array($request->selected_items)) {
            foreach ($katalogSampah as $item) {
                $item->is_selected = in_array($item->id, $request->selected_items);
            }
        } elseif ($request->has('setoran_id')) {
            // Jika edit setoran, dapatkan item yang sudah dipilih
            $setoranId = $request->setoran_id;

            // Pastikan setoran ini milik user yang login
            $cekSetoran = \App\Models\SetoranSampah::where('id', $setoranId)
                ->where('user_id', $userId)
                ->first();

            if (!$cekSetoran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Setoran tidak ditemukan atau Anda tidak memiliki akses'
                ], 404);
            }

            // Dapatkan ID item yang sudah ada di setoran
            $selectedItemIds = \App\Models\DetailSetoran::where('setoran_sampah_id', $setoranId)
                ->pluck('katalog_sampah_id')
                ->toArray();

            foreach ($katalogSampah as $item) {
                $item->is_selected = in_array($item->id, $selectedItemIds);
            }
        } else {
            foreach ($katalogSampah as $item) {
                $item->is_selected = false;
            }
        }

        // Ambil informasi bank sampah untuk tampilan
        $bankSampah = BankSampah::find($bankSampahId);

        return response()->json([
            'success' => true,
            'data' => [
                'bank_sampah' => [
                    'id' => $bankSampah->id,
                    'nama' => $bankSampah->nama_bank_sampah,
                    'alamat' => $bankSampah->alamat_bank_sampah,
                    'tanggal_setoran' => $bankSampah->tanggal_setoran,
                    'status_operasional' => $bankSampah->status_operasional ? 'Aktif' : 'Tutup'
                ],
                'katalog_sampah' => $katalogSampah
            ]
        ]);
    }
}