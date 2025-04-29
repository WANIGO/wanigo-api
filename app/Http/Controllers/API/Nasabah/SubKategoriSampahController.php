<?php

namespace App\Http\Controllers\API\Nasabah;

use App\Http\Controllers\Controller;
use App\Models\SubKategoriSampah;
use App\Models\KategoriSampah;
use App\Models\BankSampah;
use App\Models\MemberBankSampah;
use App\Models\KatalogSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class SubKategoriSampahController extends Controller
{
    /**
     * Mendapatkan daftar kategori utama sampah (kering/basah)
     *
     * @return \Illuminate\Http\Response
     */
    public function getKategoriUtama()
    {
        $kategori = KategoriSampah::all();

        return response()->json([
            'success' => true,
            'data' => $kategori
        ]);
    }

    /**
     * Mendapatkan daftar sub-kategori sampah berdasarkan bank sampah dan kategori utama.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getSubKategori(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kategori_sampah_id' => 'sometimes|exists:kategori_sampah,id',
            'kode_kategori' => 'sometimes|in:kering,basah',
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

        // Query sub-kategori sampah
        $query = SubKategoriSampah::with('kategoriSampah')
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true);

        // Filter berdasarkan kategori jika ada
        if ($request->has('kategori_sampah_id')) {
            $query->where('kategori_sampah_id', $request->kategori_sampah_id);
        }
        elseif ($request->has('kode_kategori')) {
            $kodeKategori = $request->kode_kategori;
            $query->whereHas('kategoriSampah', function($q) use ($kodeKategori) {
                $q->where('kode_kategori', $kodeKategori);
            });
        }

        // Urutkan sub-kategori
        $subKategoriSampah = $query->ordered()->get();

        // Tambahkan jumlah item dalam setiap sub-kategori
        foreach ($subKategoriSampah as $subKategori) {
            $subKategori->jumlah_item = KatalogSampah::where('sub_kategori_sampah_id', $subKategori->id)
                ->where('status_aktif', true)
                ->count();
        }

        // Data kategori utama (untuk tab)
        $kategoriUtama = KategoriSampah::all();
        foreach ($kategoriUtama as $kategori) {
            $kategori->jumlah_sub_kategori = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
                ->where('kategori_sampah_id', $kategori->id)
                ->where('status_aktif', true)
                ->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bank_sampah' => [
                    'id' => $bankSampah->id,
                    'nama' => $bankSampah->nama_bank_sampah,
                    'alamat' => $bankSampah->alamat_bank_sampah,
                ],
                'kategori_utama' => $kategoriUtama,
                'sub_kategori' => $subKategoriSampah
            ]
        ]);
    }

    /**
     * Mendapatkan katalog sampah berdasarkan sub-kategori.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getKatalogBySubKategori(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sub_kategori_id' => 'required|exists:sub_kategori_sampah,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $subKategoriId = $request->sub_kategori_id;

        // Dapatkan sub-kategori
        $subKategori = SubKategoriSampah::with('kategoriSampah', 'bankSampah')
            ->findOrFail($subKategoriId);

        $bankSampahId = $subKategori->bank_sampah_id;

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

        // Dapatkan katalog sampah untuk sub-kategori ini
        $katalogSampah = KatalogSampah::where('sub_kategori_sampah_id', $subKategoriId)
            ->where('status_aktif', true)
            ->get();

        // Format data untuk tampilan
        foreach ($katalogSampah as $item) {
            $item->harga_format = 'Rp ' . number_format($item->harga_per_kg, 0, ',', '.');
            $item->gambar_url = $item->gambar_item_sampah ? url('storage/' . $item->gambar_item_sampah) : null;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sub_kategori' => [
                    'id' => $subKategori->id,
                    'nama' => $subKategori->nama_sub_kategori,
                    'kode' => $subKategori->kode_sub_kategori,
                    'warna' => $subKategori->warna,
                    'kategori' => [
                        'id' => $subKategori->kategoriSampah->id,
                        'nama' => $subKategori->kategoriSampah->nama_kategori,
                        'kode' => $subKategori->kategoriSampah->kode_kategori,
                    ],
                    'bank_sampah' => [
                        'id' => $subKategori->bankSampah->id,
                        'nama' => $subKategori->bankSampah->nama_bank_sampah,
                    ],
                ],
                'katalog_sampah' => $katalogSampah,
            ]
        ]);
    }

    /**
     * Mendapatkan katalog sampah untuk pilihan setoran berdasarkan sub-kategori.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getKatalogForSetoran(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_sampah_id' => 'required|exists:bank_sampah,id',
            'kode_kategori' => 'required|in:kering,basah',
            'selected_items' => 'sometimes|array',
            'setoran_id' => 'sometimes|exists:setoran_sampah,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $userId = Auth::id();
        $bankSampahId = $request->bank_sampah_id;
        $kodeKategori = $request->kode_kategori;

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

        // Dapatkan ID kategori berdasarkan kode
        $kategori = KategoriSampah::where('kode_kategori', $kodeKategori)->first();
        if (!$kategori) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori sampah tidak valid'
            ], 422);
        }

        // Dapatkan sub-kategori untuk tab
        $subKategoriList = SubKategoriSampah::where('bank_sampah_id', $bankSampahId)
            ->where('kategori_sampah_id', $kategori->id)
            ->where('status_aktif', true)
            ->ordered()
            ->get();

        // Jika tidak ada sub-kategori aktif
        if ($subKategoriList->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'sub_kategori' => [],
                    'katalog_sampah' => [],
                ]
            ]);
        }

        // Filter katalog sampah berdasarkan kategori
        $katalogQuery = KatalogSampah::with('subKategori')
            ->where('bank_sampah_id', $bankSampahId)
            ->where('status_aktif', true);

        // Filter berdasarkan sub-kategori dari kategori ini
        $subKategoriIds = $subKategoriList->pluck('id')->toArray();
        $katalogQuery->whereIn('sub_kategori_sampah_id', $subKategoriIds);

        $katalogSampah = $katalogQuery->get();

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
                ->pluck('item_sampah_id')
                ->toArray();

            foreach ($katalogSampah as $item) {
                $item->is_selected = in_array($item->id, $selectedItemIds);
            }
        } else {
            foreach ($katalogSampah as $item) {
                $item->is_selected = false;
            }
        }

        // Format data untuk tampilan
        foreach ($katalogSampah as $item) {
            $item->harga_format = 'Rp ' . number_format($item->harga_per_kg, 0, ',', '.');
            $item->sub_kategori_nama = $item->subKategori ? $item->subKategori->nama_sub_kategori : '';
            $item->gambar_url = $item->gambar_item_sampah ? url('storage/' . $item->gambar_item_sampah) : null;
        }

        // Tambahkan jumlah item per sub-kategori
        foreach ($subKategoriList as $subKategori) {
            $subKategori->jumlah_item = $katalogSampah->where('sub_kategori_sampah_id', $subKategori->id)->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kategori' => $kategori,
                'sub_kategori' => $subKategoriList,
                'katalog_sampah' => $katalogSampah,
            ]
        ]);
    }
}